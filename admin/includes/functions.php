<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function admin_url(string $path = ''): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/admin/index.php';
    $adminPos = strpos($script, '/admin/');
    $base = $adminPos !== false ? substr($script, 0, $adminPos + 6) : '/admin';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function json_response(
    bool $success,
    string $message = '',
    mixed $data = null,
    int $status = 200,
    array $extra = []
): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    $payload = array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra);

    if ($data !== null) {
        $payload['data'] = $data;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_value(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function request_action(): string
{
    return trim((string)request_value('action', ''));
}

function normalize_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function current_admin(): ?array
{
    return $_SESSION['ramki_admin'] ?? null;
}

function current_admin_id(): ?int
{
    return isset($_SESSION['ramki_admin']['id'])
        ? (int)$_SESSION['ramki_admin']['id']
        : null;
}

function is_super_admin(PDO $pdo): bool
{
    $admin = current_admin();
    if (!$admin) {
        return false;
    }

    if (($admin['role_code'] ?? '') === 'super_admin') {
        return true;
    }

    $stmt = $pdo->prepare(
        "SELECT ar.role_code
         FROM admin_users au
         INNER JOIN admin_roles ar ON ar.id = au.role_id
         WHERE au.id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $admin['id']]);

    return $stmt->fetchColumn() === 'super_admin';
}

function can_menu(PDO $pdo, string $menuKey, string $permission = 'can_view'): bool
{
    if (is_super_admin($pdo)) {
        return true;
    }

    $allowed = ['can_view', 'can_add', 'can_edit', 'can_delete', 'can_approve', 'can_export'];
    if (!in_array($permission, $allowed, true)) {
        return false;
    }

    $admin = current_admin();
    if (!$admin) {
        return false;
    }

    $sql = "SELECT rmp.`{$permission}`
            FROM role_menu_permissions rmp
            INNER JOIN admin_menus am ON am.id = rmp.menu_id
            WHERE rmp.role_id = :role_id
              AND am.menu_key = :menu_key
              AND am.status = 'active'
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'role_id' => $admin['role_id'],
        'menu_key' => $menuKey,
    ]);

    return (int)$stmt->fetchColumn() === 1;
}

function require_permission(PDO $pdo, string $menuKey, string $permission = 'can_view'): void
{
    if (!can_menu($pdo, $menuKey, $permission)) {
        json_response(false, 'Permission denied.', null, 403);
    }
}

function activity_log(
    PDO $pdo,
    string $action,
    string $module,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $description = null,
    ?array $oldValues = null,
    ?array $newValues = null
): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs
            (admin_user_id, action, module_name, entity_type, entity_id,
             description, old_values, new_values, route_name, request_method,
             ip_address, user_agent)
            VALUES
            (:admin_user_id, :action, :module_name, :entity_type, :entity_id,
             :description, :old_values, :new_values, :route_name, :request_method,
             :ip_address, :user_agent)"
        );

        $stmt->execute([
            'admin_user_id' => current_admin_id(),
            'action' => $action,
            'module_name' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            'route_name' => $_SERVER['REQUEST_URI'] ?? null,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}

function upload_image(array $file, string $relativeDirectory, int $maxBytes = 5242880): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please select an image.');
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }

    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('Image must be 5 MB or smaller.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG and WebP images are allowed.');
    }

    $root = dirname(__DIR__, 2);
    $directory = $root . '/' . trim($relativeDirectory, '/');

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Unable to save image.');
    }

    return trim($relativeDirectory, '/') . '/' . $filename;
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = "{$table}.{$column}";

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name"
    );
    $stmt->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return $cache[$key] = ((int)$stmt->fetchColumn() > 0);
}
