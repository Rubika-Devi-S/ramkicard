<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/catalog-common.php';

catalog_require_login();

$menuKey = 'product_categories';
$action = catalog_action();

try {
    if ($action === 'list') {
        catalog_require_permission($pdo, $menuKey, 'can_view');

        $rows = $pdo->query(
            "SELECT
                c.id,
                c.category_name,
                c.slug,
                c.description,
                c.image_path,
                c.sort_order,
                c.status,
                c.created_at,
                COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p
                ON p.category_id = c.id
               AND p.deleted_at IS NULL
             WHERE c.deleted_at IS NULL
             GROUP BY c.id
             ORDER BY c.sort_order, c.category_name, c.id"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['sort_order'] = (int)$row['sort_order'];
            $row['product_count'] = (int)$row['product_count'];
            $row['image_url'] = catalog_admin_media_url($row['image_path'] ?? null);
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
            "SELECT *
             FROM categories
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            catalog_json(false, 'Category not found.', null, 404);
        }

        $row['image_url'] = catalog_admin_media_url($row['image_path'] ?? null);
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

        $name = trim((string)catalog_value('category_name', ''));
        $description = trim((string)catalog_value('description', ''));
        $sortOrder = max(0, (int)catalog_value('sort_order', 0));
        $status = trim((string)catalog_value('status', 'active'));

        if ($name === '' || mb_strlen($name) > 150) {
            throw new RuntimeException('Enter a valid category name.');
        }

        if (mb_strlen($description) > 5000) {
            throw new RuntimeException('Description must not exceed 5000 characters.');
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Invalid category status.');
        }

        $duplicate = $pdo->prepare(
            "SELECT id
             FROM categories
             WHERE LOWER(category_name) = LOWER(:category_name)
               AND deleted_at IS NULL
               AND id <> :id
             LIMIT 1"
        );
        $duplicate->execute([
            'category_name' => $name,
            'id' => $id,
        ]);

        if ($duplicate->fetchColumn()) {
            throw new RuntimeException('This category name already exists.');
        }

        $newImage = isset($_FILES['image'])
            ? catalog_upload_image($_FILES['image'], 'categories')
            : null;

        $oldImage = null;

        try {
            $pdo->beginTransaction();

            if ($id > 0) {
                $select = $pdo->prepare(
                    "SELECT image_path
                     FROM categories
                     WHERE id = :id
                       AND deleted_at IS NULL
                     LIMIT 1
                     FOR UPDATE"
                );
                $select->execute(['id' => $id]);
                $oldImage = $select->fetchColumn();

                if ($oldImage === false) {
                    throw new RuntimeException('Category not found.');
                }

                $slug = catalog_unique_slug($pdo, 'categories', $name, $id);

                $update = $pdo->prepare(
                    "UPDATE categories
                     SET category_name = :category_name,
                         slug = :slug,
                         description = :description,
                         image_path = :image_path,
                         sort_order = :sort_order,
                         status = :status,
                         updated_by = :updated_by
                     WHERE id = :id"
                );

                $update->execute([
                    'category_name' => $name,
                    'slug' => $slug,
                    'description' => $description !== '' ? $description : null,
                    'image_path' => $newImage ?? $oldImage,
                    'sort_order' => $sortOrder,
                    'status' => $status,
                    'updated_by' => catalog_admin_id(),
                    'id' => $id,
                ]);

                $message = 'Category updated successfully.';
                $logAction = 'update';
            } else {
                $slug = catalog_unique_slug($pdo, 'categories', $name);

                $insert = $pdo->prepare(
                    "INSERT INTO categories
                    (
                        category_name,
                        slug,
                        description,
                        image_path,
                        sort_order,
                        status,
                        created_by,
                        updated_by
                    )
                    VALUES
                    (
                        :category_name,
                        :slug,
                        :description,
                        :image_path,
                        :sort_order,
                        :status,
                        :created_by,
                        :updated_by
                    )"
                );

                $insert->execute([
                    'category_name' => $name,
                    'slug' => $slug,
                    'description' => $description !== '' ? $description : null,
                    'image_path' => $newImage,
                    'sort_order' => $sortOrder,
                    'status' => $status,
                    'created_by' => catalog_admin_id(),
                    'updated_by' => catalog_admin_id(),
                ]);

                $id = (int)$pdo->lastInsertId();
                $message = 'Category created successfully.';
                $logAction = 'create';
            }

            catalog_log(
                $pdo,
                $logAction,
                'Product Categories',
                'category',
                $id,
                $message
            );

            $pdo->commit();

            if ($newImage !== null && $oldImage) {
                catalog_delete_file((string)$oldImage);
            }

            catalog_json(true, $message, ['id' => $id]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($newImage !== null) {
                catalog_delete_file($newImage);
            }

            throw $exception;
        }
    }

    if ($action === 'delete') {
        catalog_require_csrf();
        catalog_require_permission($pdo, $menuKey, 'can_delete');

        $id = (int)catalog_value('id', 0);

        if ($id <= 0) {
            throw new RuntimeException('Invalid category.');
        }

        $pdo->beginTransaction();

        $select = $pdo->prepare(
            "SELECT category_name, image_path
             FROM categories
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1
             FOR UPDATE"
        );
        $select->execute(['id' => $id]);
        $category = $select->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            throw new RuntimeException('Category not found.');
        }

        $productCount = $pdo->prepare(
            "SELECT COUNT(*)
             FROM products
             WHERE category_id = :id
               AND deleted_at IS NULL"
        );
        $productCount->execute(['id' => $id]);

        if ((int)$productCount->fetchColumn() > 0) {
            throw new RuntimeException('Move or delete the products in this category first.');
        }

        $childCount = $pdo->prepare(
            "SELECT COUNT(*)
             FROM categories
             WHERE parent_id = :id
               AND deleted_at IS NULL"
        );
        $childCount->execute(['id' => $id]);

        if ((int)$childCount->fetchColumn() > 0) {
            throw new RuntimeException('This category contains subcategories and cannot be deleted.');
        }

        $delete = $pdo->prepare(
            "UPDATE categories
             SET status = 'inactive',
                 image_path = NULL,
                 deleted_at = NOW(),
                 updated_by = :updated_by
             WHERE id = :id"
        );
        $delete->execute([
            'updated_by' => catalog_admin_id(),
            'id' => $id,
        ]);

        catalog_log(
            $pdo,
            'delete',
            'Product Categories',
            'category',
            $id,
            'Category deleted: ' . (string)$category['category_name']
        );

        $pdo->commit();
        catalog_delete_file($category['image_path'] ?? null);

        catalog_json(true, 'Category deleted successfully.');
    }

    catalog_json(false, 'Invalid action.', null, 422);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Categories API error: ' . $exception->getMessage());
    catalog_json(false, catalog_error_message($exception), null, 422);
}
