<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(
        false,
        'Invalid request method.',
        null,
        405
    );
}

csrf_validate();

/*
|--------------------------------------------------------------------------
| Receive username or email
|--------------------------------------------------------------------------
*/

$login = trim((string)($_POST['login'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($login === '' || $password === '') {
    json_response(
        false,
        'Enter your username or email and password.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| Find active administrator
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        au.id,
        au.role_id,
        au.name,
        au.username,
        au.email,
        au.password_hash,
        au.status,
        ar.role_name,
        ar.role_code
     FROM admin_users au
     INNER JOIN admin_roles ar
        ON ar.id = au.role_id
     WHERE
        (
            LOWER(au.username) = LOWER(:username)
            OR LOWER(au.email) = LOWER(:email)
        )
        AND au.status = 'active'
        AND ar.status = 'active'
     LIMIT 1"
);

$stmt->execute([
    'username' => $login,
    'email'    => $login,
]);

$admin = $stmt->fetch();

if (
    !$admin ||
    !password_verify($password, $admin['password_hash'])
) {
    json_response(
        false,
        'Invalid username or password.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| Create login session
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);

$_SESSION['ramki_admin'] = [
    'id'        => (int)$admin['id'],
    'role_id'   => (int)$admin['role_id'],
    'role_name' => $admin['role_name'],
    'role_code' => $admin['role_code'],
    'name'      => $admin['name'],
    'username'  => $admin['username'],
    'email'     => $admin['email'],
];

/*
|--------------------------------------------------------------------------
| Update last login
|--------------------------------------------------------------------------
*/

$update = $pdo->prepare(
    "UPDATE admin_users
     SET
        last_login_at = NOW(),
        last_login_ip = :last_login_ip
     WHERE id = :id"
);

$update->execute([
    'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'id'            => (int)$admin['id'],
]);

if (function_exists('activity_log')) {
    activity_log(
        $pdo,
        'login',
        'Authentication',
        'admin_user',
        (int)$admin['id'],
        'Administrator logged in successfully.'
    );
}

json_response(
    true,
    'Login successful.',
    [
        'redirect' => 'dashboard.php'
    ]
);