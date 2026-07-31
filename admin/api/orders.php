<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';

$action = request_action();

function orders_api_statuses(): array
{
    return ['new', 'processed', 'delivered', 'cancelled'];
}

try {
    if ($action === 'list') {
        require_permission($pdo, 'orders', 'can_view');

        $status = trim((string)request_value('status', ''));
        $params = [];
        $where = '';

        if ($status !== '') {
            if (!in_array($status, orders_api_statuses(), true)) {
                throw new RuntimeException(
                    'Invalid order status filter.'
                );
            }

            $where = 'WHERE o.status = :status';
            $params['status'] = $status;
        }

        $stmt = $pdo->prepare(
            "SELECT
                o.id,
                o.order_number,
                o.customer_name,
                o.customer_phone,
                o.grand_total,
                o.payment_status,
                o.status,
                (
                    SELECT COUNT(*)
                    FROM order_items oi
                    WHERE oi.order_id = o.id
                ) AS item_count,
                DATE_FORMAT(
                    o.created_at,
                    '%d-%m-%Y %h:%i %p'
                ) AS created_at
             FROM orders o
             {$where}
             ORDER BY o.id DESC"
        );

        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = [
            'all' => 0,
            'new' => 0,
            'processed' => 0,
            'delivered' => 0,
            'cancelled' => 0,
        ];

        $counts['all'] = (int)$pdo
            ->query("SELECT COUNT(*) FROM orders")
            ->fetchColumn();

        $countStmt = $pdo->query(
            "SELECT status, COUNT(*) AS total
             FROM orders
             GROUP BY status"
        );

        foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rowStatus = (string)$row['status'];

            if (array_key_exists($rowStatus, $counts)) {
                $counts[$rowStatus] = (int)$row['total'];
            }
        }

        json_response(
            true,
            'Orders loaded successfully.',
            [
                'rows' => $rows,
                'counts' => $counts,
            ]
        );
    }

    if ($action === 'get') {
        require_permission($pdo, 'orders', 'can_view');

        $id = max(0, (int)request_value('id'));

        $stmt = $pdo->prepare(
            "SELECT *
             FROM orders
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        $stmt = $pdo->prepare(
            "SELECT
                id,
                product_id,
                product_name_snapshot,
                sku_snapshot,
                thumbnail_snapshot,
                selected_color_name,
                selected_design_name,
                quantity,
                final_unit_price,
                line_total,
                customer_item_notes
             FROM order_items
             WHERE order_id = :id
             ORDER BY id"
        );

        $stmt->execute(['id' => $id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $addressStmt = $pdo->prepare(
            "SELECT *
             FROM order_addresses
             WHERE order_id = :id
             ORDER BY
                CASE
                    WHEN address_type = 'shipping' THEN 0
                    ELSE 1
                END,
                id
             LIMIT 1"
        );

        $addressStmt->execute(['id' => $id]);
        $address =
            $addressStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $paymentStmt = $pdo->prepare(
            "SELECT *
             FROM payments
             WHERE order_id = :id
             ORDER BY id DESC
             LIMIT 1"
        );

        $paymentStmt->execute(['id' => $id]);
        $payment =
            $paymentStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        json_response(
            true,
            'Order loaded successfully.',
            [
                'order' => $order,
                'items' => $items,
                'address' => $address,
                'payment' => $payment,
            ]
        );
    }

    if ($action === 'update_status') {
        require_permission($pdo, 'orders', 'can_edit');

        $id = max(0, (int)request_value('id'));
        $newStatus = trim(
            (string)request_value('status', '')
        );

        if (!in_array(
            $newStatus,
            orders_api_statuses(),
            true
        )) {
            throw new RuntimeException('Invalid order status.');
        }

        $stmt = $pdo->prepare(
            "SELECT status
             FROM orders
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $oldStatus = $stmt->fetchColumn();

        if ($oldStatus === false) {
            throw new RuntimeException('Order not found.');
        }

        $notes = trim(
            (string)request_value('notes', '')
        );

        if (mb_strlen($notes) > 1500) {
            throw new RuntimeException(
                'Admin notes must not exceed 1500 characters.'
            );
        }

        $sql =
            "UPDATE orders
             SET status = :status,
                 admin_notes = :notes";

        if ($newStatus === 'processed') {
            $sql .= ', processed_at = COALESCE(processed_at, NOW())';
        }

        if ($newStatus === 'delivered') {
            $sql .= ', delivered_at = COALESCE(delivered_at, NOW())';
        }

        if ($newStatus === 'cancelled') {
            $sql .=
                ', cancelled_at = COALESCE(cancelled_at, NOW()),
                   cancellation_reason = :cancellation_reason';
        }

        $sql .= ' WHERE id = :id';

        $params = [
            'status' => $newStatus,
            'notes' => $notes !== '' ? $notes : null,
            'id' => $id,
        ];

        if ($newStatus === 'cancelled') {
            $params['cancellation_reason'] =
                $notes !== ''
                    ? $notes
                    : 'Cancelled by administrator';
        }

        $pdo->beginTransaction();

        $pdo->prepare($sql)->execute($params);

        $historyStmt = $pdo->prepare(
            "INSERT INTO order_status_history
            (
                order_id,
                changed_by_admin_id,
                previous_status,
                new_status,
                notes
            )
            VALUES
            (
                :order_id,
                :admin_id,
                :previous_status,
                :new_status,
                :notes
            )"
        );

        $historyStmt->execute([
            'order_id' => $id,
            'admin_id' => current_admin_id(),
            'previous_status' => (string)$oldStatus,
            'new_status' => $newStatus,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        $pdo->commit();

        activity_log(
            $pdo,
            'update_status',
            'Orders',
            'order',
            $id,
            'Order status changed from '
                . $oldStatus
                . ' to '
                . $newStatus
                . '.'
        );

        json_response(
            true,
            'Order status updated successfully.',
            [
                'id' => $id,
                'status' => $newStatus,
            ]
        );
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Orders API failed: '
        . $exception->getMessage()
    );

    json_response(
        false,
        $exception->getMessage(),
        null,
        422
    );
}
