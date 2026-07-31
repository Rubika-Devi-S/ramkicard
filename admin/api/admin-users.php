<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';
require_permission($pdo, 'admin_users', 'can_view');

$action = request_action();

try {
    if ($action === 'list') {
        $users = $pdo->query(
            "SELECT
                au.id,
                au.name,
                au.email,
                au.phone,
                au.status,
                DATE_FORMAT(au.last_login_at, '%d-%m-%Y %h:%i %p') AS last_login_at,
                ar.role_name
             FROM admin_users au
             INNER JOIN admin_roles ar ON ar.id = au.role_id
             ORDER BY au.id DESC"
        )->fetchAll();

        $roles = $pdo->query(
            "SELECT id, role_name
             FROM admin_roles
             WHERE status = 'active'
             ORDER BY role_name"
        )->fetchAll();

        json_response(true, '', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    if ($action === 'get') {
        $stmt = $pdo->prepare(
            "SELECT id, role_id, name, email, phone, status
             FROM admin_users
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => (int)request_value('id')]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('Admin user not found.');
        }

        json_response(true, '', $row);
    }

    if ($action === 'save') {
        $id = (int)request_value('id', 0);

        require_permission(
            $pdo,
            'role_permissions',
            $id ? 'can_edit' : 'can_add'
        );

        $name = trim((string)request_value('name'));
        $email = trim((string)request_value('email'));
        $phone = trim((string)request_value('phone', '')) ?: null;
        $roleId = (int)request_value('role_id');
        $password = (string)request_value('password', '');

        $status = in_array(
            request_value('status'),
            ['active', 'inactive', 'blocked'],
            true
        )
            ? request_value('status')
            : 'active';

        if (
            $name === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $roleId <= 0
        ) {
            throw new RuntimeException(
                'Name, valid email and role are required.'
            );
        }

        if (!$id && strlen($password) < 8) {
            throw new RuntimeException(
                'New admin password must contain at least 8 characters.'
            );
        }

        if ($id > 0) {
            $sql = "UPDATE admin_users
                    SET role_id = :role_id,
                        name = :name,
                        email = :email,
                        phone = :phone,
                        status = :status";

            $params = [
                'role_id' => $roleId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'status' => $status,
                'id' => $id,
            ];

            if ($password !== '') {
                if (strlen($password) < 8) {
                    throw new RuntimeException(
                        'Password must contain at least 8 characters.'
                    );
                }

                $sql .= ', password_hash = :password_hash,
                          password_changed_at = NOW()';

                $params['password_hash'] =
                    password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= ' WHERE id = :id';

            $pdo->prepare($sql)->execute($params);

            activity_log(
                $pdo,
                'update',
                'Admin Users',
                'admin_user',
                $id,
                'Admin user updated.'
            );

            json_response(true, 'Admin user updated successfully.');
        }

        $pdo->prepare(
            "INSERT INTO admin_users
            (
                role_id,
                name,
                email,
                phone,
                password_hash,
                status
            )
            VALUES
            (
                :role_id,
                :name,
                :email,
                :phone,
                :password_hash,
                :status
            )"
        )->execute([
            'role_id' => $roleId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password_hash' =>
                password_hash($password, PASSWORD_DEFAULT),
            'status' => $status,
        ]);

        $newId = (int)$pdo->lastInsertId();

        activity_log(
            $pdo,
            'create',
            'Admin Users',
            'admin_user',
            $newId,
            'Admin user created.'
        );

        json_response(true, 'Admin user created successfully.');
    }

    throw new RuntimeException('Invalid action.');
} catch (PDOException $e) {
    if ((int)$e->errorInfo[1] === 1062) {
        json_response(
            false,
            'This email address is already registered.',
            null,
            422
        );
    }

    json_response(false, 'Database operation failed.', null, 500);
} catch (Throwable $e) {
    json_response(false, $e->getMessage(), null, 422);
}
