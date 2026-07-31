<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
}

$mode = trim((string)request_value('mode', ''));

if (!in_array($mode, ['light', 'dark'], true)) {
    json_response(false, 'Invalid theme mode.', null, 422);
}

$adminId = current_admin_id();

if (!$adminId) {
    json_response(false, 'Login session expired.', null, 401);
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO admin_user_preferences
        (admin_user_id, theme_mode, sidebar_collapsed)
        VALUES
        (:admin_user_id, :theme_mode, 0)
        ON DUPLICATE KEY UPDATE
            theme_mode = VALUES(theme_mode)"
    );

    $stmt->execute([
        'admin_user_id' => $adminId,
        'theme_mode' => $mode,
    ]);

    $_SESSION['ramki_admin']['theme_mode'] = $mode;

    json_response(true, 'Theme preference saved.', [
        'mode' => $mode,
    ]);
} catch (Throwable $e) {
    error_log('Save admin theme preference failed: ' . $e->getMessage());

    json_response(
        false,
        'Unable to save the theme preference.',
        null,
        500
    );
}
