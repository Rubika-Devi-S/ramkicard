<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/catalog-common.php';

catalog_require_login();

$menuKey = 'price_ranges';
$action = catalog_action();

try {
    if ($action === 'list') {
        catalog_require_permission($pdo, $menuKey, 'can_view');

        $rows = $pdo->query(
            "SELECT
                r.id,
                r.range_name,
                r.minimum_price,
                r.maximum_price,
                r.sort_order,
                r.status,
                r.created_at,
                COUNT(p.id) AS product_count
             FROM price_ranges r
             LEFT JOIN products p
                ON p.price_range_id = r.id
               AND p.deleted_at IS NULL
             GROUP BY r.id
             ORDER BY r.sort_order, r.minimum_price, r.id"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['sort_order'] = (int)$row['sort_order'];
            $row['product_count'] = (int)$row['product_count'];
        }
        unset($row);

        catalog_json(true, '', [
            'rows' => $rows,
            'permissions' => catalog_permissions($pdo, $menuKey),
        ]);
    }

    if ($action === 'get') {
        catalog_require_permission($pdo, $menuKey, 'can_view');

        $id = (int)catalog_value('id', 0);
        $stmt = $pdo->prepare(
            "SELECT * FROM price_ranges WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            catalog_json(false, 'Price range not found.', null, 404);
        }

        catalog_json(true, '', $row);
    }

    if ($action === 'save') {
        catalog_require_csrf();

        $id = (int)catalog_value('id', 0);
        catalog_require_permission(
            $pdo,
            $menuKey,
            $id > 0 ? 'can_edit' : 'can_add'
        );

        $name = trim((string)catalog_value('range_name', ''));
        $minimum = (float)catalog_value('minimum_price', 0);
        $maximumRaw = trim((string)catalog_value('maximum_price', ''));
        $maximum = $maximumRaw === '' ? null : (float)$maximumRaw;
        $sortOrder = max(0, (int)catalog_value('sort_order', 0));
        $status = trim((string)catalog_value('status', 'active'));

        if ($name === '' || mb_strlen($name) > 100) {
            throw new RuntimeException('Enter a valid price range name.');
        }

        if ($minimum < 0) {
            throw new RuntimeException('Minimum price cannot be negative.');
        }

        if ($maximum !== null && $maximum < $minimum) {
            throw new RuntimeException('Maximum price must be greater than or equal to minimum price.');
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Invalid price range status.');
        }

        $duplicate = $pdo->prepare(
            "SELECT id
             FROM price_ranges
             WHERE LOWER(range_name) = LOWER(:range_name)
               AND id <> :id
             LIMIT 1"
        );
        $duplicate->execute([
            'range_name' => $name,
            'id' => $id,
        ]);

        if ($duplicate->fetchColumn()) {
            throw new RuntimeException('This price range name already exists.');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare(
                "UPDATE price_ranges
                 SET range_name = :range_name,
                     minimum_price = :minimum_price,
                     maximum_price = :maximum_price,
                     sort_order = :sort_order,
                     status = :status
                 WHERE id = :id"
            );
            $stmt->execute([
                'range_name' => $name,
                'minimum_price' => $minimum,
                'maximum_price' => $maximum,
                'sort_order' => $sortOrder,
                'status' => $status,
                'id' => $id,
            ]);

            if ($stmt->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT id FROM price_ranges WHERE id = :id');
                $exists->execute(['id' => $id]);
                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('Price range not found.');
                }
            }

            $message = 'Price range updated successfully.';
            $logAction = 'update';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO price_ranges
                (
                    range_name,
                    minimum_price,
                    maximum_price,
                    sort_order,
                    status
                )
                VALUES
                (
                    :range_name,
                    :minimum_price,
                    :maximum_price,
                    :sort_order,
                    :status
                )"
            );
            $stmt->execute([
                'range_name' => $name,
                'minimum_price' => $minimum,
                'maximum_price' => $maximum,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);

            $id = (int)$pdo->lastInsertId();
            $message = 'Price range created successfully.';
            $logAction = 'create';
        }

        catalog_log(
            $pdo,
            $logAction,
            'Price Ranges',
            'price_range',
            $id,
            $message
        );

        catalog_json(true, $message, ['id' => $id]);
    }

    if ($action === 'delete') {
        catalog_require_csrf();
        catalog_require_permission($pdo, $menuKey, 'can_delete');

        $id = (int)catalog_value('id', 0);

        $stmt = $pdo->prepare(
            "SELECT range_name FROM price_ranges WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $name = $stmt->fetchColumn();

        if ($name === false) {
            throw new RuntimeException('Price range not found.');
        }

        $used = $pdo->prepare(
            "SELECT COUNT(*)
             FROM products
             WHERE price_range_id = :id
               AND deleted_at IS NULL"
        );
        $used->execute(['id' => $id]);

        if ((int)$used->fetchColumn() > 0) {
            throw new RuntimeException('This price range is assigned to products and cannot be deleted.');
        }

        $delete = $pdo->prepare('DELETE FROM price_ranges WHERE id = :id');
        $delete->execute(['id' => $id]);

        catalog_log(
            $pdo,
            'delete',
            'Price Ranges',
            'price_range',
            $id,
            'Price range deleted: ' . (string)$name
        );

        catalog_json(true, 'Price range deleted successfully.');
    }

    catalog_json(false, 'Invalid action.', null, 422);
} catch (Throwable $exception) {
    error_log('Price ranges API error: ' . $exception->getMessage());
    catalog_json(false, catalog_error_message($exception), null, 422);
}
