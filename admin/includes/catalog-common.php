<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Ramki Cards catalogue module shared security and utility functions
|--------------------------------------------------------------------------
*/

function catalog_json(
    bool $success,
    string $message = '',
    mixed $data = null,
    int $statusCode = 200
): never {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode(
        [
            'success' => $success,
            'status' => $success,
            'message' => $message,
            'data' => $data,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function catalog_action(): string
{
    return strtolower(trim((string)(
        $_POST['action']
        ?? $_GET['action']
        ?? ''
    )));
}

function catalog_value(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function catalog_admin_id(): int
{
    return (int)($_SESSION['ramki_admin']['id'] ?? 0);
}

function catalog_role_id(): int
{
    return (int)($_SESSION['ramki_admin']['role_id'] ?? 0);
}

function catalog_role_code(): string
{
    return strtolower(trim((string)(
        $_SESSION['ramki_admin']['role_code'] ?? ''
    )));
}

function catalog_require_login(): void
{
    if (catalog_admin_id() <= 0) {
        catalog_json(
            false,
            'Your login session has expired.',
            ['redirect' => '../login.php'],
            401
        );
    }
}

/**
 * @return array{can_view:bool,can_add:bool,can_edit:bool,can_delete:bool,can_approve:bool,can_export:bool}
 */
function catalog_permissions(PDO $pdo, string $menuKey): array
{
    $empty = [
        'can_view' => false,
        'can_add' => false,
        'can_edit' => false,
        'can_delete' => false,
        'can_approve' => false,
        'can_export' => false,
    ];

    if (catalog_admin_id() <= 0) {
        return $empty;
    }

    if (catalog_role_code() === 'super_admin') {
        return array_fill_keys(array_keys($empty), true);
    }

    $roleId = catalog_role_id();

    if ($roleId <= 0) {
        return $empty;
    }

    $stmt = $pdo->prepare(
        "SELECT
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
         WHERE m.menu_key = :menu_key
           AND m.status = 'active'
         LIMIT 1"
    );

    $stmt->execute([
        'role_id' => $roleId,
        'menu_key' => $menuKey,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    foreach ($empty as $column => $unused) {
        $empty[$column] = (bool)($row[$column] ?? false);
    }

    return $empty;
}

/**
 * Product add permission may be assigned on Product List or Add Product.
 *
 * @return array{can_view:bool,can_add:bool,can_edit:bool,can_delete:bool,can_approve:bool,can_export:bool}
 */
function catalog_product_permissions(PDO $pdo): array
{
    $list = catalog_permissions($pdo, 'product_list');
    $add = catalog_permissions($pdo, 'product_add');

    $list['can_add'] =
        $list['can_add']
        || $add['can_add']
        || $add['can_view'];

    return $list;
}

function catalog_require_permission(
    PDO $pdo,
    string $menuKey,
    string $permission
): void {
    $allowedColumns = [
        'can_view',
        'can_add',
        'can_edit',
        'can_delete',
        'can_approve',
        'can_export',
    ];

    if (!in_array($permission, $allowedColumns, true)) {
        catalog_json(false, 'Invalid permission check.', null, 500);
    }

    $permissions = $menuKey === 'product_list'
        ? catalog_product_permissions($pdo)
        : catalog_permissions($pdo, $menuKey);

    if (!$permissions[$permission]) {
        catalog_json(
            false,
            'You do not have permission to perform this action.',
            ['permission' => $permission],
            403
        );
    }
}

function catalog_csrf_token(): string
{
    if (function_exists('csrf_token')) {
        return (string)csrf_token();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function catalog_require_csrf(): void
{
    $token = trim((string)(
        $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? ''
    ));

    if (
        $token === ''
        || !hash_equals(catalog_csrf_token(), $token)
    ) {
        catalog_json(
            false,
            'The page session expired. Refresh and try again.',
            null,
            419
        );
    }
}

function catalog_slugify(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return 'item';
    }

    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'item';
}

function catalog_unique_slug(
    PDO $pdo,
    string $table,
    string $source,
    int $excludeId = 0
): string {
    if (!in_array($table, ['categories', 'products'], true)) {
        throw new InvalidArgumentException('Invalid slug table.');
    }

    $base = catalog_slugify($source);
    $candidate = $base;
    $counter = 2;

    while (true) {
        $sql = "SELECT id
                FROM `{$table}`
                WHERE slug = :slug";

        /*
         * Slug columns are globally unique even for soft-deleted rows.
         * Include every row so a new record receives a safe -2/-3 suffix.
         */

        if ($excludeId > 0) {
            $sql .= " AND id <> :id";
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $params = ['slug' => $candidate];

        if ($excludeId > 0) {
            $params['id'] = $excludeId;
        }

        $stmt->execute($params);

        if (!$stmt->fetchColumn()) {
            return $candidate;
        }

        $candidate = $base . '-' . $counter;
        $counter++;
    }
}

function catalog_upload_image(
    array $file,
    string $folder,
    int $maxBytes = 5242880
): ?string {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please select the file again.');
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Invalid uploaded image.');
    }

    if ($size <= 0 || $size > $maxBytes) {
        throw new RuntimeException('Each image must not exceed 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmpName);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    }

    $folder = trim($folder, '/');
    $relativeFolder = 'assets/uploads/' . $folder;
    $projectRoot = dirname(__DIR__, 2);
    $absoluteFolder = $projectRoot . '/' . $relativeFolder;

    if (
        !is_dir($absoluteFolder)
        && !mkdir($absoluteFolder, 0775, true)
        && !is_dir($absoluteFolder)
    ) {
        throw new RuntimeException('Unable to create the image upload folder.');
    }

    if (!is_writable($absoluteFolder)) {
        throw new RuntimeException('The image upload folder is not writable.');
    }

    $filename = date('YmdHis')
        . '-'
        . bin2hex(random_bytes(8))
        . '.'
        . $allowed[$mime];

    $absolutePath = $absoluteFolder . '/' . $filename;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        throw new RuntimeException('Unable to store the uploaded image.');
    }

    return $relativeFolder . '/' . $filename;
}

/** @return array<int,array<string,mixed>> */
function catalog_normalize_multiple_files(string $field): array
{
    if (
        !isset($_FILES[$field])
        || !is_array($_FILES[$field]['name'] ?? null)
    ) {
        return [];
    }

    $files = [];
    $count = count($_FILES[$field]['name']);

    for ($index = 0; $index < $count; $index++) {
        $files[] = [
            'name' => $_FILES[$field]['name'][$index] ?? '',
            'type' => $_FILES[$field]['type'][$index] ?? '',
            'tmp_name' => $_FILES[$field]['tmp_name'][$index] ?? '',
            'error' => $_FILES[$field]['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES[$field]['size'][$index] ?? 0,
        ];
    }

    return $files;
}

function catalog_delete_file(?string $relativePath): void
{
    $path = trim((string)$relativePath);

    if (
        $path === ''
        || str_contains($path, '..')
        || !str_starts_with($path, 'assets/uploads/')
    ) {
        return;
    }

    $absolute = dirname(__DIR__, 2) . '/' . ltrim($path, '/');

    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

function catalog_admin_media_url(?string $path): string
{
    $path = trim((string)$path);

    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if (str_contains($path, '..')) {
        return '';
    }

    return '../' . ltrim($path, '/');
}

function catalog_log(
    PDO $pdo,
    string $action,
    string $module,
    string $entityType,
    int $entityId,
    string $description
): void {
    if (function_exists('activity_log')) {
        activity_log(
            $pdo,
            $action,
            $module,
            $entityType,
            $entityId,
            $description
        );
    }
}

function catalog_error_message(Throwable $exception): string
{
    if ($exception instanceof RuntimeException) {
        return $exception->getMessage();
    }

    if (
        $exception instanceof PDOException
        && (string)$exception->getCode() === '23000'
    ) {
        return 'The value already exists or the record is being used by another module.';
    }

    return 'Unable to complete the requested action.';
}
