<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';
require_permission($pdo, 'activity_logs', 'can_view');

if (request_action() !== 'list') {
    json_response(false, 'Invalid action.', null, 422);
}

$rows = $pdo->query(
    "SELECT
        l.id,
        l.action,
        l.module_name,
        l.description,
        l.ip_address,
        DATE_FORMAT(l.created_at, '%d-%m-%Y %h:%i %p') AS created_at,
        u.name AS admin_name
     FROM activity_logs l
     LEFT JOIN admin_users u ON u.id = l.admin_user_id
     ORDER BY l.id DESC
     LIMIT 1000"
)->fetchAll();

json_response(true, '', $rows);
