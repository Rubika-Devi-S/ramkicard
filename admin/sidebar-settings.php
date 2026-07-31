<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'sidebar_settings', 'can_view')) {
    http_response_code(403);
    exit('Permission denied.');
}

/*
|--------------------------------------------------------------------------
| Permission map
|--------------------------------------------------------------------------
*/

$sidebarPermissions = [
    'can_view' => can_menu(
        $pdo,
        'sidebar_settings',
        'can_view'
    ),
    'can_add' => can_menu(
        $pdo,
        'sidebar_settings',
        'can_add'
    ),
    'can_edit' => can_menu(
        $pdo,
        'sidebar_settings',
        'can_edit'
    ),
    'can_delete' => can_menu(
        $pdo,
        'sidebar_settings',
        'can_delete'
    ),
];

if (is_super_admin($pdo)) {
    $sidebarPermissions = [
        'can_view' => true,
        'can_add' => true,
        'can_edit' => true,
        'can_delete' => true,
    ];
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function sidebar_settings_redirect(
    string $type,
    string $message
): never {
    $_SESSION['sidebar_settings_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    header(
        'Location: '
        . admin_url('sidebar-settings.php')
    );
    exit;
}

function sidebar_settings_creates_cycle(
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

        $cursor =
            (int)($stmt->fetchColumn() ?: 0);
    }

    return false;
}

function sidebar_settings_normalize_route(
    string $route
): string {
    $route = trim($route);

    if ($route === '') {
        return '#';
    }

    $legacyRoutes = [
        'admin.categories.index'
            => 'categories.php',

        'admin.price-ranges.index'
            => 'price-ranges.php',

        'admin.products.index'
            => 'products.php',

        'admin.products.create'
            => 'products.php?action=add',
    ];

    return $legacyRoutes[$route] ?? $route;
}

function sidebar_settings_grant_super_admin(
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

/*
|--------------------------------------------------------------------------
| Process all actions in this same PHP file
|--------------------------------------------------------------------------
| This intentionally avoids the external sidebar-settings.js and
| api/sidebar-menu.php dependency for menu listing and CRUD.
|--------------------------------------------------------------------------
*/

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    === 'POST'
) {
    try {
        csrf_validate();

        $action = trim(
            (string)($_POST['action'] ?? '')
        );

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);

            $requiredPermission =
                $id > 0
                    ? 'can_edit'
                    : 'can_add';

            if (
                !$sidebarPermissions[
                    $requiredPermission
                ]
            ) {
                throw new RuntimeException(
                    'You do not have permission to save this menu.'
                );
            }

            $parentId =
                max(
                    0,
                    (int)(
                        $_POST['parent_id']
                        ?? 0
                    )
                );

            $menuName = trim(
                (string)(
                    $_POST['menu_name']
                    ?? ''
                )
            );

            $menuKey = strtolower(
                trim(
                    (string)(
                        $_POST['menu_key']
                        ?? ''
                    )
                )
            );

            $routeName =
                sidebar_settings_normalize_route(
                    (string)(
                        $_POST['route_name']
                        ?? '#'
                    )
                );

            $iconClass = trim(
                (string)(
                    $_POST['icon_class']
                    ?? 'fa-solid fa-circle'
                )
            );

            $sortOrder =
                max(
                    0,
                    (int)(
                        $_POST['sort_order']
                        ?? 0
                    )
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
                !admin_menu_route_is_safe(
                    $routeName
                )
            ) {
                throw new RuntimeException(
                    'Use a safe relative PHP path or # for a parent menu.'
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

                if (
                    !$parentStmt->fetchColumn()
                ) {
                    throw new RuntimeException(
                        'The selected parent menu does not exist.'
                    );
                }
            }

            if (
                sidebar_settings_creates_cycle(
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

            if (
                $duplicateStmt->fetchColumn()
            ) {
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
                $existsStmt = $pdo->prepare(
                    "SELECT id
                     FROM admin_menus
                     WHERE id = :id
                     LIMIT 1
                     FOR UPDATE"
                );

                $existsStmt->execute([
                    'id' => $id,
                ]);

                if (
                    !$existsStmt->fetchColumn()
                ) {
                    throw new RuntimeException(
                        'Sidebar menu not found.'
                    );
                }

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

                $id =
                    (int)$pdo->lastInsertId();

                sidebar_settings_grant_super_admin(
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

            sidebar_settings_redirect(
                'success',
                $message
            );
        }

        if ($action === 'toggle') {
            if (
                !$sidebarPermissions[
                    'can_edit'
                ]
            ) {
                throw new RuntimeException(
                    'You do not have permission to update this menu.'
                );
            }

            $id =
                (int)($_POST['id'] ?? 0);

            $field = trim(
                (string)(
                    $_POST['field']
                    ?? ''
                )
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
                        IF(
                            is_visible = 1,
                            0,
                            1
                        )
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
                'Sidebar menu status changed.'
            );

            sidebar_settings_redirect(
                'success',
                'Sidebar menu updated successfully.'
            );
        }

        if ($action === 'delete') {
            if (
                !$sidebarPermissions[
                    'can_delete'
                ]
            ) {
                throw new RuntimeException(
                    'You do not have permission to delete this menu.'
                );
            }

            $id =
                (int)($_POST['id'] ?? 0);

            $menuStmt = $pdo->prepare(
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

            $menuStmt->execute([
                'id' => $id,
            ]);

            $menu =
                $menuStmt->fetch(
                    PDO::FETCH_ASSOC
                );

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

            if (
                (int)$menu['child_count'] > 0
            ) {
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

            sidebar_settings_redirect(
                'success',
                'Sidebar menu deleted successfully.'
            );
        }

        throw new RuntimeException(
            'Invalid sidebar action.'
        );
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        sidebar_settings_redirect(
            'danger',
            $exception->getMessage()
        );
    }
}

/*
|--------------------------------------------------------------------------
| Page filters
|--------------------------------------------------------------------------
*/

$search = trim(
    (string)($_GET['search'] ?? '')
);

$typeFilter = trim(
    (string)($_GET['type'] ?? '')
);

$statusFilter = trim(
    (string)($_GET['status'] ?? '')
);

$where = [];
$params = [];

if ($search !== '') {
    $where[] =
        "(
            m.menu_name LIKE :search
            OR m.menu_key LIKE :search
            OR m.route_name LIKE :search
            OR p.menu_name LIKE :search
        )";

    $params['search'] =
        '%' . $search . '%';
}

if ($typeFilter === 'main') {
    $where[] = 'm.parent_id IS NULL';
} elseif ($typeFilter === 'child') {
    $where[] = 'm.parent_id IS NOT NULL';
}

if ($statusFilter === 'active') {
    $where[] = "m.status = 'active'";
} elseif ($statusFilter === 'inactive') {
    $where[] = "m.status = 'inactive'";
} elseif ($statusFilter === 'hidden') {
    $where[] = 'm.is_visible = 0';
}

$listSql =
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
            FROM admin_menus child
            WHERE child.parent_id = m.id
        ) AS child_count
     FROM admin_menus m
     LEFT JOIN admin_menus p
        ON p.id = m.parent_id";

if ($where) {
    $listSql .=
        ' WHERE '
        . implode(' AND ', $where);
}

$listSql .=
    " ORDER BY
        CASE
            WHEN m.parent_id IS NULL
            THEN m.sort_order
            ELSE 999999
        END,
        COALESCE(m.parent_id, m.id),
        m.parent_id IS NOT NULL,
        m.sort_order,
        m.id";

$listStmt =
    $pdo->prepare($listSql);

$listStmt->execute($params);

$sidebarMenus =
    $listStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

/*
|--------------------------------------------------------------------------
| Main menu options
|--------------------------------------------------------------------------
*/

$parentMenus =
    $pdo->query(
        "SELECT
            id,
            menu_name
         FROM admin_menus
         WHERE parent_id IS NULL
         ORDER BY sort_order, menu_name, id"
    )->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Add/edit form state
|--------------------------------------------------------------------------
*/

$formMode = trim(
    (string)($_GET['mode'] ?? '')
);

$editId =
    (int)($_GET['edit'] ?? 0);

$editingMenu = null;

if ($editId > 0) {
    if (
        !$sidebarPermissions['can_edit']
    ) {
        http_response_code(403);
        exit('Permission denied.');
    }

    $editStmt = $pdo->prepare(
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

    $editStmt->execute([
        'id' => $editId,
    ]);

    $editingMenu =
        $editStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$editingMenu) {
        sidebar_settings_redirect(
            'danger',
            'Sidebar menu not found.'
        );
    }

    $formMode = 'edit';
}

$showForm =
    $formMode === 'add'
    || $formMode === 'edit';

if (
    $formMode === 'add'
    && !$sidebarPermissions['can_add']
) {
    http_response_code(403);
    exit('Permission denied.');
}

$flash =
    $_SESSION['sidebar_settings_flash']
    ?? null;

unset(
    $_SESSION['sidebar_settings_flash']
);

$pageTitle = 'Sidebar Settings';

/*
 * Do not load sidebar-settings.js.
 * The page is server-rendered and all actions post back here.
 */
$pageScript = null;

require __DIR__ . '/includes/header.php';
?>

<div class="sidebar-settings-head">
    <div>
        <span class="badge rounded-pill badge-soft-warning">
            DYNAMIC MENUS
        </span>

        <h2 class="h4 mt-2 mb-1">
            Sidebar Menu Control
        </h2>

        <p class="text-muted mb-0">
            The menu list is loaded directly from
            <code>admin_menus</code>.
            No AJAX loading is required.
        </p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a
            class="btn btn-outline-secondary"
            href="<?= e(admin_url('sidebar-settings.php')); ?>"
        >
            <i class="fa-solid fa-rotate me-2"></i>
            Refresh
        </a>

        <?php if ($sidebarPermissions['can_add']): ?>
            <a
                class="btn btn-ramki"
                href="<?= e(
                    admin_url(
                        'sidebar-settings.php?mode=add'
                    )
                ); ?>"
            >
                <i class="fa-solid fa-plus me-2"></i>
                Add Menu
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (is_array($flash)): ?>
    <div
        class="alert alert-<?= e(
            (string)($flash['type'] ?? 'info')
        ); ?> alert-dismissible fade show"
        role="alert"
    >
        <?= e(
            (string)($flash['message'] ?? '')
        ); ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close"
        ></button>
    </div>
<?php endif; ?>

<?php if ($showForm): ?>
    <div class="ramki-card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="h5 mb-1">
                    <?= $editingMenu
                        ? 'Edit Sidebar Menu'
                        : 'Add Sidebar Menu'; ?>
                </h3>

                <p class="small text-muted mb-0">
                    Use a relative PHP file path.
                </p>
            </div>

            <a
                class="btn btn-sm btn-light"
                href="<?= e(
                    admin_url(
                        'sidebar-settings.php'
                    )
                ); ?>"
            >
                Close
            </a>
        </div>

        <form method="post">
            <input
                type="hidden"
                name="_token"
                value="<?= e(csrf_token()); ?>"
            >

            <input
                type="hidden"
                name="action"
                value="save"
            >

            <input
                type="hidden"
                name="id"
                value="<?= (int)(
                    $editingMenu['id']
                    ?? 0
                ); ?>"
            >

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">
                        Parent Menu
                    </label>

                    <select
                        class="form-select"
                        name="parent_id"
                    >
                        <option value="0">
                            Main Menu
                        </option>

                        <?php foreach ($parentMenus as $parent): ?>
                            <?php
                            if (
                                $editingMenu
                                && (int)$parent['id']
                                    === (int)$editingMenu['id']
                            ) {
                                continue;
                            }
                            ?>

                            <option
                                value="<?= (int)$parent['id']; ?>"
                                <?= (
                                    (int)(
                                        $editingMenu['parent_id']
                                        ?? 0
                                    )
                                    === (int)$parent['id']
                                ) ? 'selected' : ''; ?>
                            >
                                <?= e(
                                    (string)$parent['menu_name']
                                ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Menu Name *
                    </label>

                    <input
                        class="form-control"
                        name="menu_name"
                        maxlength="150"
                        value="<?= e(
                            (string)(
                                $editingMenu['menu_name']
                                ?? ''
                            )
                        ); ?>"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Menu Key *
                    </label>

                    <input
                        class="form-control"
                        name="menu_key"
                        maxlength="150"
                        pattern="[a-z0-9_]+"
                        value="<?= e(
                            (string)(
                                $editingMenu['menu_key']
                                ?? ''
                            )
                        ); ?>"
                        required
                    >

                    <div class="form-text">
                        Lowercase letters, numbers and underscores only.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Route / PHP File
                    </label>

                    <input
                        class="form-control"
                        name="route_name"
                        maxlength="190"
                        value="<?= e(
                            (string)(
                                $editingMenu['route_name']
                                ?? '#'
                            )
                        ); ?>"
                    >

                    <div class="form-text">
                        Examples:
                        <code>categories.php</code>,
                        <code>products.php?action=add</code>,
                        or <code>#</code>.
                    </div>
                </div>

                <div class="col-md-8">
                    <label class="form-label">
                        Font Awesome Icon
                    </label>

                    <input
                        class="form-control"
                        name="icon_class"
                        maxlength="150"
                        value="<?= e(
                            (string)(
                                $editingMenu['icon_class']
                                ?? 'fa-solid fa-circle'
                            )
                        ); ?>"
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        Sort Order
                    </label>

                    <input
                        type="number"
                        min="0"
                        max="999999"
                        class="form-control"
                        name="sort_order"
                        value="<?= (int)(
                            $editingMenu['sort_order']
                            ?? 0
                        ); ?>"
                    >
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch mt-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="is_visible"
                            value="1"
                            id="sidebarIsVisible"
                            <?= (
                                !$editingMenu
                                || (int)$editingMenu[
                                    'is_visible'
                                ] === 1
                            ) ? 'checked' : ''; ?>
                        >

                        <label
                            class="form-check-label"
                            for="sidebarIsVisible"
                        >
                            Show in sidebar
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch mt-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="is_active"
                            value="1"
                            id="sidebarIsActive"
                            <?= (
                                !$editingMenu
                                || (
                                    $editingMenu['status']
                                    ?? 'active'
                                ) === 'active'
                            ) ? 'checked' : ''; ?>
                        >

                        <label
                            class="form-check-label"
                            for="sidebarIsActive"
                        >
                            Active
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button
                    class="btn btn-ramki"
                    type="submit"
                >
                    <?= $editingMenu
                        ? 'Update Menu'
                        : 'Save Menu'; ?>
                </button>

                <a
                    class="btn btn-light"
                    href="<?= e(
                        admin_url(
                            'sidebar-settings.php'
                        )
                    ); ?>"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="ramki-card p-3">
    <form
        method="get"
        class="row g-2 mb-3"
    >
        <div class="col-md-5">
            <input
                type="search"
                class="form-control"
                name="search"
                value="<?= e($search); ?>"
                placeholder="Search menu name, key or route"
            >
        </div>

        <div class="col-md-3">
            <select
                class="form-select"
                name="type"
            >
                <option value="">
                    All Menus
                </option>

                <option
                    value="main"
                    <?= $typeFilter === 'main'
                        ? 'selected'
                        : ''; ?>
                >
                    Main Menus
                </option>

                <option
                    value="child"
                    <?= $typeFilter === 'child'
                        ? 'selected'
                        : ''; ?>
                >
                    Child Menus
                </option>
            </select>
        </div>

        <div class="col-md-2">
            <select
                class="form-select"
                name="status"
            >
                <option value="">
                    All Statuses
                </option>

                <option
                    value="active"
                    <?= $statusFilter === 'active'
                        ? 'selected'
                        : ''; ?>
                >
                    Active
                </option>

                <option
                    value="inactive"
                    <?= $statusFilter === 'inactive'
                        ? 'selected'
                        : ''; ?>
                >
                    Inactive
                </option>

                <option
                    value="hidden"
                    <?= $statusFilter === 'hidden'
                        ? 'selected'
                        : ''; ?>
                >
                    Hidden
                </option>
            </select>
        </div>

        <div class="col-md-2 d-grid">
            <button
                class="btn btn-outline-secondary"
                type="submit"
            >
                Filter
            </button>
        </div>
    </form>

    <div class="table-responsive">
        <table
            class="table table-hover align-middle w-100"
        >
            <thead>
                <tr>
                    <th>#</th>
                    <th>Menu</th>
                    <th>Parent</th>
                    <th>Route / PHP File</th>
                    <th>Sort</th>
                    <th>Visible</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$sidebarMenus): ?>
                    <tr>
                        <td
                            colspan="8"
                            class="text-center py-5 text-muted"
                        >
                            No sidebar menus found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach (
                        $sidebarMenus
                        as $index => $menu
                    ): ?>
                        <tr>
                            <td>
                                <?= $index + 1; ?>
                            </td>

                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span
                                        class="stat-icon"
                                        style="width:38px;height:38px;font-size:15px"
                                    >
                                        <i class="<?= e(
                                            admin_menu_icon_class(
                                                (string)(
                                                    $menu['icon_class']
                                                    ?? ''
                                                )
                                            )
                                        ); ?>"></i>
                                    </span>

                                    <div>
                                        <strong>
                                            <?= e(
                                                (string)$menu[
                                                    'menu_name'
                                                ]
                                            ); ?>
                                        </strong>

                                        <small class="d-block text-muted">
                                            <?= e(
                                                (string)$menu[
                                                    'menu_key'
                                                ]
                                            ); ?>
                                        </small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?= e(
                                    (string)(
                                        $menu['parent_name']
                                        ?? 'Main Menu'
                                    )
                                ); ?>
                            </td>

                            <td>
                                <code>
                                    <?= e(
                                        (string)(
                                            $menu['route_name']
                                            ?? '#'
                                        )
                                    ); ?>
                                </code>
                            </td>

                            <td>
                                <?= (int)$menu[
                                    'sort_order'
                                ]; ?>
                            </td>

                            <td>
                                <?php if (
                                    $sidebarPermissions[
                                        'can_edit'
                                    ]
                                ): ?>
                                    <form
                                        method="post"
                                        class="d-inline"
                                    >
                                        <input
                                            type="hidden"
                                            name="_token"
                                            value="<?= e(
                                                csrf_token()
                                            ); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="toggle"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$menu[
                                                'id'
                                            ]; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="field"
                                            value="is_visible"
                                        >

                                        <button
                                            class="btn btn-sm <?= (
                                                (int)$menu[
                                                    'is_visible'
                                                ] === 1
                                            )
                                                ? 'btn-success'
                                                : 'btn-outline-secondary'; ?>"
                                            type="submit"
                                        >
                                            <?= (
                                                (int)$menu[
                                                    'is_visible'
                                                ] === 1
                                            )
                                                ? 'Shown'
                                                : 'Hidden'; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge <?= (
                                        (int)$menu[
                                            'is_visible'
                                        ] === 1
                                    )
                                        ? 'bg-success'
                                        : 'bg-secondary'; ?>">
                                        <?= (
                                            (int)$menu[
                                                'is_visible'
                                            ] === 1
                                        )
                                            ? 'Shown'
                                            : 'Hidden'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (
                                    $sidebarPermissions[
                                        'can_edit'
                                    ]
                                ): ?>
                                    <form
                                        method="post"
                                        class="d-inline"
                                    >
                                        <input
                                            type="hidden"
                                            name="_token"
                                            value="<?= e(
                                                csrf_token()
                                            ); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="toggle"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$menu[
                                                'id'
                                            ]; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="field"
                                            value="status"
                                        >

                                        <button
                                            class="btn btn-sm <?= (
                                                $menu['status']
                                                === 'active'
                                            )
                                                ? 'btn-primary'
                                                : 'btn-outline-secondary'; ?>"
                                            type="submit"
                                        >
                                            <?= e(
                                                ucfirst(
                                                    (string)$menu[
                                                        'status'
                                                    ]
                                                )
                                            ); ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge <?= (
                                        $menu['status']
                                        === 'active'
                                    )
                                        ? 'bg-primary'
                                        : 'bg-secondary'; ?>">
                                        <?= e(
                                            ucfirst(
                                                (string)$menu[
                                                    'status'
                                                ]
                                            )
                                        ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-nowrap">
                                <div class="d-flex gap-1">
                                    <?php if (
                                        $sidebarPermissions[
                                            'can_edit'
                                        ]
                                    ): ?>
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="<?= e(
                                                admin_url(
                                                    'sidebar-settings.php?edit='
                                                    . (int)$menu['id']
                                                )
                                            ); ?>"
                                            title="Edit"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (
                                        $sidebarPermissions[
                                            'can_delete'
                                        ]
                                    ): ?>
                                        <form
                                            method="post"
                                            class="d-inline"
                                            onsubmit="return confirm('Delete this sidebar menu?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="_token"
                                                value="<?= e(
                                                    csrf_token()
                                                ); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$menu[
                                                    'id'
                                                ]; ?>"
                                            >

                                            <button
                                                class="btn btn-sm btn-outline-danger"
                                                type="submit"
                                                title="Delete"
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (
                                        !$sidebarPermissions[
                                            'can_edit'
                                        ]
                                        && !$sidebarPermissions[
                                            'can_delete'
                                        ]
                                    ): ?>
                                        <span class="text-muted">
                                            View only
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
