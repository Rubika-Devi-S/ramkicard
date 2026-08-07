<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';

$action = request_action();

function enquiry_columns(PDO $pdo): string
{
    $columns = [
        'id',
        'enquiry_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'source',
        'status',
        'subject',
        'message',
        'admin_notes',
        'created_at',
    ];

    foreach (
        [
            'event_type',
            'other_event',
            'event_date',
            'event_location',
            'ip_address',
            'user_agent',
        ]
        as $column
    ) {
        $columns[] = table_has_column($pdo, 'enquiries', $column)
            ? $column
            : "NULL AS {$column}";
    }

    return implode(', ', $columns);
}

function enquiry_items_map(PDO $pdo, array $enquiryIds): array
{
    $enquiryIds = array_values(array_unique(array_filter(array_map(
        static fn ($id): int => (int)$id,
        $enquiryIds
    ))));

    if (!$enquiryIds) {
        return [];
    }

    $placeholders = implode(
        ', ',
        array_fill(0, count($enquiryIds), '?')
    );

    $stmt = $pdo->prepare(
        "SELECT
            id,
            enquiry_id,
            product_id,
            product_name_snapshot,
            sku_snapshot,
            thumbnail_snapshot,
            selected_color_name,
            selected_design_name,
            requested_quantity,
            unit_price_snapshot,
            line_total_estimate,
            customer_item_notes
         FROM enquiry_items
         WHERE enquiry_id IN ({$placeholders})
         ORDER BY enquiry_id, id"
    );

    $stmt->execute($enquiryIds);

    $map = [];

    foreach ($stmt->fetchAll() as $item) {
        $map[(int)$item['enquiry_id']][] = $item;
    }

    return $map;
}

try {
    if ($action === 'list') {
        require_permission($pdo, 'enquiries', 'can_view');

        $where = [];
        $params = [];

        if (in_array(
            request_value('status'),
            [
                'new',
                'contacted',
                'quotation_sent',
                'converted',
                'closed',
                'rejected',
            ],
            true
        )) {
            $where[] = 'status = :status';
            $params['status'] = request_value('status');
        }

        $sql = "SELECT " . enquiry_columns($pdo) . "
                FROM enquiries" .
                ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
                " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        $itemsByEnquiry = enquiry_items_map(
            $pdo,
            array_column($rows, 'id')
        );

        foreach ($rows as &$row) {
            $row['items'] = $itemsByEnquiry[(int)$row['id']] ?? [];

            if (!empty($row['event_date'])) {
                $row['event_date'] = date(
                    'd-m-Y',
                    strtotime($row['event_date'])
                );
            }

            $row['created_at'] = date(
                'd-m-Y h:i A',
                strtotime($row['created_at'])
            );
        }

        json_response(true, '', $rows);
    }

    if ($action === 'get') {
        require_permission($pdo, 'enquiries', 'can_view');

        $stmt = $pdo->prepare(
            "SELECT " . enquiry_columns($pdo) . "
             FROM enquiries
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute([
            'id' => (int)request_value('id')
        ]);

        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('Enquiry not found.');
        }

        $itemsByEnquiry = enquiry_items_map($pdo, [(int)$row['id']]);
        $row['items'] = $itemsByEnquiry[(int)$row['id']] ?? [];

        $row['created_at'] = date(
            'd-m-Y h:i A',
            strtotime($row['created_at'])
        );

        if (!empty($row['event_date'])) {
            $row['event_date'] = date(
                'd-m-Y',
                strtotime($row['event_date'])
            );
        }

        json_response(true, '', $row);
    }

    if ($action === 'update') {
        require_permission($pdo, 'enquiries', 'can_edit');

        $id = (int)request_value('id');
        $status = request_value('status');

        if (!in_array(
            $status,
            [
                'new',
                'contacted',
                'quotation_sent',
                'converted',
                'closed',
                'rejected',
            ],
            true
        )) {
            throw new RuntimeException('Invalid enquiry status.');
        }

        $notes = trim(
            (string)request_value('admin_notes', '')
        );

        $pdo->prepare(
            "UPDATE enquiries
             SET status = :status,
                 admin_notes = :admin_notes,
                 last_contacted_at =
                    CASE
                        WHEN :status IN ('contacted', 'quotation_sent')
                        THEN NOW()
                        ELSE last_contacted_at
                    END
             WHERE id = :id"
        )->execute([
            'status' => $status,
            'admin_notes' => $notes ?: null,
            'id' => $id,
        ]);

        activity_log(
            $pdo,
            'update_status',
            'Enquiries',
            'enquiry',
            $id,
            "Enquiry status changed to {$status}."
        );

        json_response(true, 'Enquiry updated successfully.');
    }

    if ($action === 'delete') {
        require_permission($pdo, 'enquiries', 'can_delete');

        $id = (int)request_value('id');

        if ($id <= 0) {
            throw new RuntimeException('Invalid enquiry.');
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "SELECT enquiry_number
                 FROM enquiries
                 WHERE id = :id
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);

            $enquiryNumber = trim((string)$stmt->fetchColumn());

            if ($enquiryNumber === '') {
                throw new RuntimeException('Enquiry not found.');
            }

            activity_log(
                $pdo,
                'delete',
                'Enquiries',
                'enquiry',
                $id,
                "Deleted enquiry {$enquiryNumber}."
            );

            $stmt = $pdo->prepare(
                "DELETE FROM enquiries
                 WHERE id = :id"
            );
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Unable to delete the enquiry.');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        json_response(true, 'Enquiry deleted successfully.');
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    json_response(false, $e->getMessage(), null, 422);
}