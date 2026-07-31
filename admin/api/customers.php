<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';
require_permission($pdo, 'customers', 'can_view');

if (request_action() !== 'list') {
    json_response(false, 'Invalid action.', null, 422);
}

$rows = $pdo->query(
    "SELECT
        c.id,
        c.first_name,
        c.last_name,
        c.email,
        c.phone,
        c.status,
        DATE_FORMAT(c.created_at, '%d-%m-%Y') AS created_at,
        (
            SELECT COUNT(*)
            FROM orders o
            WHERE o.customer_id = c.id
        ) AS order_count,
        (
            SELECT COUNT(*)
            FROM enquiries e
            WHERE e.customer_id = c.id
        ) AS enquiry_count
     FROM customers c
     ORDER BY c.id DESC"
)->fetchAll();

json_response(true, '', $rows);
