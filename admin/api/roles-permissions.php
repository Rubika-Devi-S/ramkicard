<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';
require_permission($pdo, 'role_permissions', 'can_view');

$action = request_action();

try {
    if ($action === 'roles') {
        $rows = $pdo->query(
            "SELECT id, role_name, role_code
             FROM admin_roles
             WHERE status = 'active'
             ORDER BY role_name"
        )->fetchAll();

        json_response(true, '', $rows);
    }

    if ($action === 'get') {
        $roleId = (int)request_value('role_id');

        $stmt = $pdo->prepare(
            "SELECT
                m.id AS menu_id,
                m.menu_name,
                COALESCE(p.can_view, 0) AS can_view,
                COALESCE(p.can_add, 0) AS can_add,
                COALESCE(p.can_edit, 0) AS can_edit,
                COALESCE(p.can_delete, 0) AS can_delete,
                COALESCE(p.can_approve, 0) AS can_approve,
                COALESCE(p.can_export, 0) AS can_export
             FROM admin_menus m
             LEFT JOIN role_menu_permissions p
                ON p.menu_id = m.id
               AND p.role_id = :role_id
             WHERE m.status = 'active'
               AND m.is_visible = 1
             ORDER BY m.sort_order, m.id"
        );

        $stmt->execute(['role_id' => $roleId]);

        json_response(true, '', $stmt->fetchAll());
    }

    if ($action === 'save') {
        require_permission($pdo, 'role_permissions', 'can_edit');

        $roleId = (int)request_value('role_id');
        $rows = json_decode(
            (string)request_value('permissions_json', '[]'),
            true
        );

        if ($roleId <= 0 || !is_array($rows)) {
            throw new RuntimeException(
                'Invalid permission request.'
            );
        }

        $pdo->beginTransaction();

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
            VALUES
            (
                :role_id,
                :menu_id,
                :can_view,
                :can_add,
                :can_edit,
                :can_delete,
                :can_approve,
                :can_export
            )
            ON DUPLICATE KEY UPDATE
                can_view = VALUES(can_view),
                can_add = VALUES(can_add),
                can_edit = VALUES(can_edit),
                can_delete = VALUES(can_delete),
                can_approve = VALUES(can_approve),
                can_export = VALUES(can_export)"
        );

        foreach ($rows as $row) {
            $stmt->execute([
                'role_id' => $roleId,
                'menu_id' => (int)($row['menu_id'] ?? 0),
                'can_view' => (int)($row['can_view'] ?? 0),
                'can_add' => (int)($row['can_add'] ?? 0),
                'can_edit' => (int)($row['can_edit'] ?? 0),
                'can_delete' => (int)($row['can_delete'] ?? 0),
                'can_approve' => (int)($row['can_approve'] ?? 0),
                'can_export' => (int)($row['can_export'] ?? 0),
            ]);
        }

        $pdo->commit();

        activity_log(
            $pdo,
            'update',
            'Role Permissions',
            'admin_role',
            $roleId,
            'Role permissions updated.'
        );

        json_response(true, 'Permissions saved successfully.');
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response(false, $e->getMessage(), null, 422);
}
