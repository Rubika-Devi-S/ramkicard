<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';

$action = request_action();

function sidebar_permissions(PDO $pdo): array
{
    $permissions = [
        'can_view' => false,
        'can_add' => false,
        'can_edit' => false,
        'can_delete' => false,
    ];

    if (function_exists('is_super_admin') && is_super_admin($pdo)) {
        return [
            'can_view' => true,
            'can_add' => true,
            'can_edit' => true,
            'can_delete' => true,
        ];
    }

    $roleId = (int)(
        $_SESSION['ramki_admin']['role_id']
        ?? 0
    );

    if ($roleId <= 0) {
        return $permissions;
    }

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(p.can_view, 0) AS can_view,
            COALESCE(p.can_add, 0) AS can_add,
            COALESCE(p.can_edit, 0) AS can_edit,
            COALESCE(p.can_delete, 0) AS can_delete
         FROM admin_menus m
         LEFT JOIN role_menu_permissions p
            ON p.menu_id = m.id
           AND p.role_id = :role_id
         WHERE m.menu_key = 'sidebar_settings'
         LIMIT 1"
    );

    $stmt->execute([
        'role_id' => $roleId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    foreach (array_keys($permissions) as $key) {
        $permissions[$key] = (bool)($row[$key] ?? false);
    }

    return $permissions;
}

function sidebar_parent_creates_cycle(
    PDO $pdo,
    int $menuId,
    int $parentId
): bool {
    if ($menuId <= 0 || $parentId <= 0) {
        return false;
    }

    if ($menuId === $parentId) {
        return true;
    }

    $cursor = $parentId;
    $guard = 0;

    while ($cursor > 0 && $guard++ < 100) {
        if ($cursor === $menuId) {
            return true;
        }

        $stmt = $pdo->prepare(
            "SELECT parent_id
             FROM admin_menus
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute([
            'id' => $cursor,
        ]);

        $cursor = (int)($stmt->fetchColumn() ?: 0);
    }

    return false;
}

function sidebar_normalize_route(string $route): string
{
    $route = trim($route);

    if ($route === '') {
        return '#';
    }

    $knownLegacyRoutes = [
        'admin.categories.index' => 'categories.php',
        'admin.price-ranges.index' => 'price-ranges.php',
        'admin.products.index' => 'products.php',
        'admin.products.create' => 'products.php?action=add',
    ];

    return $knownLegacyRoutes[$route] ?? $route;
}

function sidebar_grant_super_admin(
    PDO $pdo,
    int $menuId
): void {
    $stmt = $pdo->prepare(
        "INSERT INTO role_menu_permissions
        (
            role_id,
            menu_id,
            can_view,
            can_add,
            can_edit,
            can_delete,
            can_approve,
            can_export
        )
        SELECT
            id,
            :menu_id,
            1, 1, 1, 1, 1, 1
        FROM admin_roles
        WHERE role_code = 'super_admin'
        ON DUPLICATE KEY UPDATE
            can_view = 1,
            can_add = 1,
            can_edit = 1,
            can_delete = 1,
            can_approve = 1,
            can_export = 1"
    );

    $stmt->execute([
        'menu_id' => $menuId,
    ]);
}

try {
    if ($action === 'list') {
        require_permission(
            $pdo,
            'sidebar_settings',
            'can_view'
        );

        $rows = $pdo->query(
            "SELECT
                m.id,
                m.parent_id,
                m.menu_name,
                m.menu_key,
                m.route_name,
                m.icon_class,
                m.sort_order,
                m.is_visible,
                m.status,
                p.menu_name AS parent_name,
                (
                    SELECT COUNT(*)
                    FROM admin_menus c
                    WHERE c.parent_id = m.id
                ) AS child_count
             FROM admin_menus m
             LEFT JOIN admin_menus p
                ON p.id = m.parent_id
             ORDER BY
                CASE
                    WHEN m.parent_id IS NULL
                    THEN m.sort_order
                    ELSE 999999
                END,
                COALESCE(m.parent_id, m.id),
                m.parent_id IS NOT NULL,
                m.sort_order,
                m.id"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['parent_id'] =
                $row['parent_id'] !== null
                    ? (int)$row['parent_id']
                    : 0;
            $row['sort_order'] = (int)$row['sort_order'];
            $row['is_visible'] = (int)$row['is_visible'];
            $row['child_count'] = (int)$row['child_count'];
        }
        unset($row);

        json_response(true, '', [
            'rows' => $rows,
            'permissions' => sidebar_permissions($pdo),
        ]);
    }

    if ($action === 'get') {
        require_permission(
            $pdo,
            'sidebar_settings',
            'can_view'
        );

        $id = (int)request_value('id', 0);

        $stmt = $pdo->prepare(
            "SELECT
                id,
                parent_id,
                menu_name,
                menu_key,
                route_name,
                icon_class,
                sort_order,
                is_visible,
                status
             FROM admin_menus
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute([
            'id' => $id,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException(
                'Sidebar menu not found.'
            );
        }

        json_response(true, '', $row);
    }

    if ($action === 'save') {
        $id = (int)request_value('id', 0);

        require_permission(
            $pdo,
            'sidebar_settings',
            $id > 0 ? 'can_edit' : 'can_add'
        );

        $parentId = (int)request_value('parent_id', 0);
        $menuName = trim(
            (string)request_value('menu_name', '')
        );

        $menuKey = strtolower(trim(
            (string)request_value('menu_key', '')
        ));

        $routeName = sidebar_normalize_route(
            (string)request_value('route_name', '#')
        );

        $iconClass = trim(
            (string)request_value(
                'icon_class',
                'fa-solid fa-circle'
            )
        );

        $sortOrder = max(
            0,
            (int)request_value('sort_order', 0)
        );

        $isVisible =
            isset($_POST['is_visible'])
                ? 1
                : 0;

        $status =
            isset($_POST['is_active'])
                ? 'active'
                : 'inactive';

        if (
            $menuName === ''
            || mb_strlen($menuName) > 150
        ) {
            throw new RuntimeException(
                'Enter a valid menu name.'
            );
        }

        if (
            !preg_match(
                '/^[a-z0-9_]+$/',
                $menuKey
            )
        ) {
            throw new RuntimeException(
                'Menu key may contain lowercase letters, numbers and underscores only.'
            );
        }

        if (
            mb_strlen($menuKey) > 150
        ) {
            throw new RuntimeException(
                'Menu key is too long.'
            );
        }

        if (
            !admin_menu_route_is_safe($routeName)
        ) {
            throw new RuntimeException(
                'Enter a safe relative PHP route or use # for a parent menu.'
            );
        }

        if (
            !preg_match(
                '/^[a-zA-Z0-9 _-]+$/',
                $iconClass
            )
        ) {
            throw new RuntimeException(
                'Invalid Font Awesome icon class.'
            );
        }

        if ($parentId > 0) {
            $parentStmt = $pdo->prepare(
                "SELECT id
                 FROM admin_menus
                 WHERE id = :id
                 LIMIT 1"
            );

            $parentStmt->execute([
                'id' => $parentId,
            ]);

            if (!$parentStmt->fetchColumn()) {
                throw new RuntimeException(
                    'Selected parent menu does not exist.'
                );
            }
        }

        if (
            sidebar_parent_creates_cycle(
                $pdo,
                $id,
                $parentId
            )
        ) {
            throw new RuntimeException(
                'A menu cannot be placed below itself or one of its child menus.'
            );
        }

        $duplicateStmt = $pdo->prepare(
            "SELECT id
             FROM admin_menus
             WHERE menu_key = :menu_key
               AND id <> :id
             LIMIT 1"
        );

        $duplicateStmt->execute([
            'menu_key' => $menuKey,
            'id' => $id,
        ]);

        if ($duplicateStmt->fetchColumn()) {
            throw new RuntimeException(
                'The menu key already exists.'
            );
        }

        $parentValue =
            $parentId > 0
                ? $parentId
                : null;

        $pdo->beginTransaction();

        if ($id > 0) {
            $stmt = $pdo->prepare(
                "UPDATE admin_menus
                 SET parent_id = :parent_id,
                     menu_name = :menu_name,
                     menu_key = :menu_key,
                     route_name = :route_name,
                     icon_class = :icon_class,
                     sort_order = :sort_order,
                     is_visible = :is_visible,
                     status = :status
                 WHERE id = :id"
            );

            $stmt->execute([
                'parent_id' => $parentValue,
                'menu_name' => $menuName,
                'menu_key' => $menuKey,
                'route_name' => $routeName,
                'icon_class' => $iconClass,
                'sort_order' => $sortOrder,
                'is_visible' => $isVisible,
                'status' => $status,
                'id' => $id,
            ]);

            activity_log(
                $pdo,
                'update',
                'Sidebar Settings',
                'admin_menu',
                $id,
                'Sidebar menu updated.'
            );

            $message =
                'Sidebar menu updated successfully.';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO admin_menus
                (
                    parent_id,
                    menu_name,
                    menu_key,
                    route_name,
                    icon_class,
                    sort_order,
                    is_visible,
                    status
                )
                VALUES
                (
                    :parent_id,
                    :menu_name,
                    :menu_key,
                    :route_name,
                    :icon_class,
                    :sort_order,
                    :is_visible,
                    :status
                )"
            );

            $stmt->execute([
                'parent_id' => $parentValue,
                'menu_name' => $menuName,
                'menu_key' => $menuKey,
                'route_name' => $routeName,
                'icon_class' => $iconClass,
                'sort_order' => $sortOrder,
                'is_visible' => $isVisible,
                'status' => $status,
            ]);

            $id = (int)$pdo->lastInsertId();

            sidebar_grant_super_admin(
                $pdo,
                $id
            );

            activity_log(
                $pdo,
                'create',
                'Sidebar Settings',
                'admin_menu',
                $id,
                'Sidebar menu created.'
            );

            $message =
                'Sidebar menu created successfully.';
        }

        $pdo->commit();

        json_response(true, $message, [
            'id' => $id,
            'route_name' => $routeName,
        ]);
    }

    if ($action === 'toggle') {
        require_permission(
            $pdo,
            'sidebar_settings',
            'can_edit'
        );

        $id = (int)request_value('id', 0);
        $field = trim(
            (string)request_value('field', '')
        );

        if ($id <= 0) {
            throw new RuntimeException(
                'Invalid sidebar menu.'
            );
        }

        if ($field === 'is_visible') {
            $stmt = $pdo->prepare(
                "UPDATE admin_menus
                 SET is_visible =
                    IF(is_visible = 1, 0, 1)
                 WHERE id = :id"
            );
        } elseif ($field === 'status') {
            $stmt = $pdo->prepare(
                "UPDATE admin_menus
                 SET status =
                    IF(
                        status = 'active',
                        'inactive',
                        'active'
                    )
                 WHERE id = :id"
            );
        } else {
            throw new RuntimeException(
                'Invalid sidebar field.'
            );
        }

        $stmt->execute([
            'id' => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException(
                'Sidebar menu not found.'
            );
        }

        activity_log(
            $pdo,
            'toggle',
            'Sidebar Settings',
            'admin_menu',
            $id,
            'Sidebar menu visibility or status changed.'
        );

        json_response(
            true,
            'Sidebar menu updated successfully.'
        );
    }

    if ($action === 'delete') {
        require_permission(
            $pdo,
            'sidebar_settings',
            'can_delete'
        );

        $id = (int)request_value('id', 0);

        $stmt = $pdo->prepare(
            "SELECT
                menu_key,
                menu_name,
                (
                    SELECT COUNT(*)
                    FROM admin_menus child
                    WHERE child.parent_id =
                        admin_menus.id
                ) AS child_count
             FROM admin_menus
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute([
            'id' => $id,
        ]);

        $menu = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$menu) {
            throw new RuntimeException(
                'Sidebar menu not found.'
            );
        }

        if (
            in_array(
                (string)$menu['menu_key'],
                [
                    'dashboard',
                    'sidebar_settings',
                    'theme_settings',
                    'role_permissions',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'This core menu cannot be deleted. Hide it instead.'
            );
        }

        if ((int)$menu['child_count'] > 0) {
            throw new RuntimeException(
                'Delete or move the child menus before deleting this parent menu.'
            );
        }

        $pdo->beginTransaction();

        $pdo->prepare(
            "DELETE FROM role_menu_permissions
             WHERE menu_id = :menu_id"
        )->execute([
            'menu_id' => $id,
        ]);

        $pdo->prepare(
            "DELETE FROM admin_menus
             WHERE id = :id"
        )->execute([
            'id' => $id,
        ]);

        activity_log(
            $pdo,
            'delete',
            'Sidebar Settings',
            'admin_menu',
            $id,
            'Sidebar menu deleted: '
            . (string)$menu['menu_name']
        );

        $pdo->commit();

        json_response(
            true,
            'Sidebar menu deleted successfully.'
        );
    }

    throw new RuntimeException(
        'Invalid sidebar action.'
    );
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (
        (int)($exception->errorInfo[1] ?? 0)
        === 1062
    ) {
        json_response(
            false,
            'The menu key already exists.',
            null,
            422
        );
    }

    error_log(
        'Sidebar menu API failed: '
        . $exception->getMessage()
    );

    json_response(
        false,
        'Unable to process the sidebar request.',
        null,
        500
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response(
        false,
        $exception->getMessage(),
        null,
        422
    );
}
