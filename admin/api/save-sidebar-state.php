<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';

$collapsed = (int)request_value('collapsed', 0) === 1 ? 1 : 0;

$stmt = $pdo->prepare(
    "INSERT INTO admin_user_preferences
    (admin_user_id, theme_mode, sidebar_collapsed)
    VALUES
    (:admin_user_id, 'light', :sidebar_collapsed)
    ON DUPLICATE KEY UPDATE
        sidebar_collapsed = VALUES(sidebar_collapsed)"
);
$stmt->execute([
    'admin_user_id' => current_admin_id(),
    'sidebar_collapsed' => $collapsed,
]);

json_response(true, 'Sidebar preference saved.');
