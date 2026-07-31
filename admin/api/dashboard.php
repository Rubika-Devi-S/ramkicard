<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';

require_permission($pdo, 'dashboard', 'can_view');

if (request_action() !== 'summary') {
    json_response(false, 'Invalid action.', null, 422);
}

function dashboard_api_table_exists(
    PDO $pdo,
    string $tableName
): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name"
    );

    $stmt->execute([
        'table_name' => $tableName,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function dashboard_api_column_exists(
    PDO $pdo,
    string $tableName,
    string $columnName
): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name"
    );

    $stmt->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

$counts = [
    'new_enquiries' => 0,
    'new_orders' => 0,
    'active_products' => 0,
    'customers' => 0,
];

$recentEnquiries = [];
$recentOrders = [];

try {
    if (dashboard_api_table_exists($pdo, 'enquiries')) {
        $counts['new_enquiries'] = (int)$pdo
            ->query(
                "SELECT COUNT(*)
                 FROM enquiries
                 WHERE status = 'new'"
            )
            ->fetchColumn();

        $typeParts = [];

        if (
            dashboard_api_column_exists(
                $pdo,
                'enquiries',
                'event_type'
            )
        ) {
            $typeParts[] = "NULLIF(e.event_type, '')";
        }

        if (
            dashboard_api_column_exists(
                $pdo,
                'enquiries',
                'subject'
            )
        ) {
            $typeParts[] = "NULLIF(e.subject, '')";
        }

        $hasItems =
            dashboard_api_table_exists($pdo, 'enquiry_items');

        if ($hasItems) {
            $typeParts[] = "NULLIF(ei.product_name, '')";
        }

        if (
            dashboard_api_column_exists(
                $pdo,
                'enquiries',
                'source'
            )
        ) {
            $typeParts[] = "NULLIF(e.source, '')";
        }

        $typeParts[] = "'Website Enquiry'";

        $typeExpression =
            'COALESCE('
            . implode(', ', $typeParts)
            . ')';

        $itemJoin = $hasItems
            ? "LEFT JOIN (
                   SELECT
                       enquiry_id,
                       MAX(product_name_snapshot) AS product_name
                   FROM enquiry_items
                   GROUP BY enquiry_id
               ) ei
                   ON ei.enquiry_id = e.id"
            : '';

        $recentEnquiries = $pdo
            ->query(
                "SELECT
                    e.enquiry_number,
                    e.customer_name,
                    e.customer_phone,
                    {$typeExpression} AS event_type,
                    e.status,
                    DATE_FORMAT(
                        e.created_at,
                        '%d-%m-%Y %h:%i %p'
                    ) AS created_at
                 FROM enquiries e
                 {$itemJoin}
                 ORDER BY e.id DESC
                 LIMIT 6"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    if (dashboard_api_table_exists($pdo, 'orders')) {
        $counts['new_orders'] = (int)$pdo
            ->query(
                "SELECT COUNT(*)
                 FROM orders
                 WHERE status = 'new'"
            )
            ->fetchColumn();

        $recentOrders = $pdo
            ->query(
                "SELECT
                    order_number,
                    customer_name,
                    grand_total,
                    status,
                    payment_status,
                    DATE_FORMAT(
                        created_at,
                        '%d-%m-%Y %h:%i %p'
                    ) AS created_at
                 FROM orders
                 ORDER BY id DESC
                 LIMIT 6"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    if (dashboard_api_table_exists($pdo, 'products')) {
        $conditions = ["status = 'active'"];

        if (
            dashboard_api_column_exists(
                $pdo,
                'products',
                'deleted_at'
            )
        ) {
            $conditions[] = 'deleted_at IS NULL';
        }

        $counts['active_products'] = (int)$pdo
            ->query(
                "SELECT COUNT(*)
                 FROM products
                 WHERE "
                 . implode(' AND ', $conditions)
            )
            ->fetchColumn();
    }

    if (dashboard_api_table_exists($pdo, 'customers')) {
        $counts['customers'] = (int)$pdo
            ->query("SELECT COUNT(*) FROM customers")
            ->fetchColumn();
    }

    json_response(
        true,
        'Dashboard loaded successfully.',
        [
            'counts' => $counts,
            'recent_enquiries' => $recentEnquiries,
            'recent_orders' => $recentOrders,
        ]
    );
} catch (Throwable $exception) {
    error_log(
        'Dashboard API failed: '
        . $exception->getMessage()
    );

    json_response(
        false,
        'Unable to load dashboard data.',
        [
            'counts' => $counts,
            'recent_enquiries' => $recentEnquiries,
            'recent_orders' => $recentOrders,
        ],
        500
    );
}
