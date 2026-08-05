<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/catalog-common.php';

catalog_require_login();

$action = trim((string)(
    $_POST['action']
    ?? $_GET['action']
    ?? ''
));

/**
 * Use the same product permission resolver as the visible Products page.
 * This supports installations where the menu key is either product_list
 * or products.
 */
function product_api_require_permission(PDO $pdo, string $ability): void
{
    $permissions = catalog_product_permissions($pdo);

    if (empty($permissions[$ability])) {
        catalog_json(false, 'Permission denied.', null, 403);
    }
}

/** @return array<int,string> */
function product_post_array(string $key): array
{
    $value = $_POST[$key] ?? [];

    if (!is_array($value)) {
        return [];
    }

    return array_values(array_map(
        static fn (mixed $item): string => trim((string)$item),
        $value
    ));
}

/** @return array<int,int> */
function product_post_int_array(string $key): array
{
    return array_map(
        static fn (string $item): int => (int)$item,
        product_post_array($key)
    );
}

/**
 * @param array<int,string> $newPaths
 * @param array<int,string> $deleteAfterCommit
 */
function sync_color_variants(
    PDO $pdo,
    int $productId,
    array &$newPaths,
    array &$deleteAfterCommit
): int {
    $ids = product_post_int_array('color_id');
    $names = product_post_array('color_name');
    $codes = product_post_array('color_code');
    $adjustments = product_post_array('color_price_adjustment');
    $sortOrders = product_post_array('color_sort_order');
    $statuses = product_post_array('color_status');
    $existingImages = product_post_array('color_existing_image');

    if (count($names) > 50) {
        throw new RuntimeException('A product can contain a maximum of 50 colour variants.');
    }

    $keptIds = [];
    $usedNames = [];
    $savedCount = 0;

    foreach ($names as $index => $name) {
        if ($name === '') {
            continue;
        }

        if (mb_strlen($name) > 100) {
            throw new RuntimeException('Colour name must not exceed 100 characters.');
        }

        $nameKey = mb_strtolower($name);
        if (isset($usedNames[$nameKey])) {
            throw new RuntimeException('Duplicate colour variant: ' . $name);
        }
        $usedNames[$nameKey] = true;

        $id = (int)($ids[$index] ?? 0);
        $code = trim((string)($codes[$index] ?? ''));
        $adjustment = (float)($adjustments[$index] ?? 0);
        $sortOrder = max(0, (int)($sortOrders[$index] ?? 0));
        $status = (string)($statuses[$index] ?? 'active');
        $oldImage = trim((string)($existingImages[$index] ?? ''));

        if ($code !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $code)) {
            throw new RuntimeException('Colour code must use the format #RRGGBB.');
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $fileKey = 'color_image_' . $index;
        $newImage = isset($_FILES[$fileKey])
            ? catalog_upload_image($_FILES[$fileKey], 'products/variants/colors')
            : null;

        if ($newImage !== null) {
            $newPaths[] = $newImage;
        }

        if ($id > 0) {
            $check = $pdo->prepare(
                "SELECT image_path
                 FROM product_color_variants
                 WHERE id = :id
                   AND product_id = :product_id
                 LIMIT 1"
            );
            $check->execute([
                'id' => $id,
                'product_id' => $productId,
            ]);
            $databaseImage = $check->fetchColumn();

            if ($databaseImage === false) {
                throw new RuntimeException('A colour variant no longer exists. Refresh and try again.');
            }

            $oldImage = (string)$databaseImage;

            $stmt = $pdo->prepare(
                "UPDATE product_color_variants
                 SET color_name = :color_name,
                     color_code = :color_code,
                     image_path = :image_path,
                     price_adjustment = :price_adjustment,
                     sort_order = :sort_order,
                     status = :status
                 WHERE id = :id
                   AND product_id = :product_id"
            );
            $stmt->execute([
                'color_name' => $name,
                'color_code' => $code !== '' ? $code : null,
                'image_path' => $newImage ?? ($oldImage !== '' ? $oldImage : null),
                'price_adjustment' => $adjustment,
                'sort_order' => $sortOrder,
                'status' => $status,
                'id' => $id,
                'product_id' => $productId,
            ]);

            if ($newImage !== null && $oldImage !== '') {
                $deleteAfterCommit[] = $oldImage;
            }

            $keptIds[] = $id;
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO product_color_variants
                (
                    product_id,
                    color_name,
                    color_code,
                    image_path,
                    price_adjustment,
                    sort_order,
                    status
                )
                VALUES
                (
                    :product_id,
                    :color_name,
                    :color_code,
                    :image_path,
                    :price_adjustment,
                    :sort_order,
                    :status
                )"
            );
            $stmt->execute([
                'product_id' => $productId,
                'color_name' => $name,
                'color_code' => $code !== '' ? $code : null,
                'image_path' => $newImage,
                'price_adjustment' => $adjustment,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);

            $keptIds[] = (int)$pdo->lastInsertId();
        }

        $savedCount++;
    }

    $existing = $pdo->prepare(
        "SELECT id, image_path
         FROM product_color_variants
         WHERE product_id = :product_id"
    );
    $existing->execute(['product_id' => $productId]);

    foreach ($existing->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rowId = (int)$row['id'];
        if (!in_array($rowId, $keptIds, true)) {
            $delete = $pdo->prepare(
                "DELETE FROM product_color_variants
                 WHERE id = :id
                   AND product_id = :product_id"
            );
            $delete->execute([
                'id' => $rowId,
                'product_id' => $productId,
            ]);

            if (!empty($row['image_path'])) {
                $deleteAfterCommit[] = (string)$row['image_path'];
            }
        }
    }

    return $savedCount;
}

/**
 * @param array<int,string> $newPaths
 * @param array<int,string> $deleteAfterCommit
 */
function sync_design_variants(
    PDO $pdo,
    int $productId,
    array &$newPaths,
    array &$deleteAfterCommit
): int {
    $ids = product_post_int_array('design_id');
    $names = product_post_array('design_name');
    $codes = product_post_array('design_code');
    $descriptions = product_post_array('design_description');
    $adjustments = product_post_array('design_price_adjustment');
    $sortOrders = product_post_array('design_sort_order');
    $statuses = product_post_array('design_status');
    $existingImages = product_post_array('design_existing_image');

    if (count($names) > 50) {
        throw new RuntimeException('A product can contain a maximum of 50 design variants.');
    }

    $keptIds = [];
    $usedNames = [];
    $usedCodes = [];
    $savedCount = 0;

    foreach ($names as $index => $name) {
        if ($name === '') {
            continue;
        }

        if (mb_strlen($name) > 150) {
            throw new RuntimeException('Design name must not exceed 150 characters.');
        }

        $nameKey = mb_strtolower($name);
        if (isset($usedNames[$nameKey])) {
            throw new RuntimeException('Duplicate design variant: ' . $name);
        }
        $usedNames[$nameKey] = true;

        $id = (int)($ids[$index] ?? 0);
        $code = trim((string)($codes[$index] ?? ''));
        $description = trim((string)($descriptions[$index] ?? ''));
        $adjustment = (float)($adjustments[$index] ?? 0);
        $sortOrder = max(0, (int)($sortOrders[$index] ?? 0));
        $status = (string)($statuses[$index] ?? 'active');
        $oldImage = trim((string)($existingImages[$index] ?? ''));

        if ($code !== '') {
            $codeKey = mb_strtolower($code);
            if (isset($usedCodes[$codeKey])) {
                throw new RuntimeException('Duplicate design code: ' . $code);
            }
            $usedCodes[$codeKey] = true;
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $fileKey = 'design_image_' . $index;
        $newImage = isset($_FILES[$fileKey])
            ? catalog_upload_image($_FILES[$fileKey], 'products/variants/designs')
            : null;

        if ($newImage !== null) {
            $newPaths[] = $newImage;
        }

        if ($id > 0) {
            $check = $pdo->prepare(
                "SELECT image_path
                 FROM product_design_variants
                 WHERE id = :id
                   AND product_id = :product_id
                 LIMIT 1"
            );
            $check->execute([
                'id' => $id,
                'product_id' => $productId,
            ]);
            $databaseImage = $check->fetchColumn();

            if ($databaseImage === false) {
                throw new RuntimeException('A design variant no longer exists. Refresh and try again.');
            }

            $oldImage = (string)$databaseImage;

            $stmt = $pdo->prepare(
                "UPDATE product_design_variants
                 SET design_name = :design_name,
                     design_code = :design_code,
                     description = :description,
                     image_path = :image_path,
                     price_adjustment = :price_adjustment,
                     sort_order = :sort_order,
                     status = :status
                 WHERE id = :id
                   AND product_id = :product_id"
            );
            $stmt->execute([
                'design_name' => $name,
                'design_code' => $code !== '' ? $code : null,
                'description' => $description !== '' ? $description : null,
                'image_path' => $newImage ?? ($oldImage !== '' ? $oldImage : null),
                'price_adjustment' => $adjustment,
                'sort_order' => $sortOrder,
                'status' => $status,
                'id' => $id,
                'product_id' => $productId,
            ]);

            if ($newImage !== null && $oldImage !== '') {
                $deleteAfterCommit[] = $oldImage;
            }

            $keptIds[] = $id;
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO product_design_variants
                (
                    product_id,
                    design_name,
                    design_code,
                    description,
                    image_path,
                    price_adjustment,
                    sort_order,
                    status
                )
                VALUES
                (
                    :product_id,
                    :design_name,
                    :design_code,
                    :description,
                    :image_path,
                    :price_adjustment,
                    :sort_order,
                    :status
                )"
            );
            $stmt->execute([
                'product_id' => $productId,
                'design_name' => $name,
                'design_code' => $code !== '' ? $code : null,
                'description' => $description !== '' ? $description : null,
                'image_path' => $newImage,
                'price_adjustment' => $adjustment,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);

            $keptIds[] = (int)$pdo->lastInsertId();
        }

        $savedCount++;
    }

    $existing = $pdo->prepare(
        "SELECT id, image_path
         FROM product_design_variants
         WHERE product_id = :product_id"
    );
    $existing->execute(['product_id' => $productId]);

    foreach ($existing->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rowId = (int)$row['id'];
        if (!in_array($rowId, $keptIds, true)) {
            $delete = $pdo->prepare(
                "DELETE FROM product_design_variants
                 WHERE id = :id
                   AND product_id = :product_id"
            );
            $delete->execute([
                'id' => $rowId,
                'product_id' => $productId,
            ]);

            if (!empty($row['image_path'])) {
                $deleteAfterCommit[] = (string)$row['image_path'];
            }
        }
    }

    return $savedCount;
}

try {
    if ($action === 'options') {
        product_api_require_permission($pdo, 'can_view');

        $categories = $pdo->query(
            "SELECT id, category_name, status
             FROM categories
             WHERE deleted_at IS NULL
             ORDER BY sort_order, category_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $ranges = $pdo->query(
            "SELECT id, range_name, status
             FROM price_ranges
             ORDER BY sort_order, minimum_price"
        )->fetchAll(PDO::FETCH_ASSOC);

        catalog_json(true, '', [
            'categories' => $categories,
            'price_ranges' => $ranges,
            'permissions' => catalog_product_permissions($pdo),
        ]);
    }

    if ($action === 'list') {
        product_api_require_permission($pdo, 'can_view');

        $categoryId = max(0, (int)catalog_value('category_id', 0));
        $status = trim((string)catalog_value('status', ''));

        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if ($categoryId > 0) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if ($status !== '') {
            if (!in_array($status, ['active', 'draft', 'inactive'], true)) {
                throw new RuntimeException('Invalid product status filter.');
            }
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }

        /*
         * Avoid GROUP BY across multiple one-to-many joins. That query can
         * fail under ONLY_FULL_GROUP_BY and can multiply rows before counting.
         */
        $sql =
            "SELECT
                p.id,
                p.product_name,
                p.product_name_tamil,
                p.slug,
                p.sku,
                p.thumbnail_path,
                p.base_price,
                p.offer_price,
                p.minimum_order_qty,
                p.quantity_step,
                p.purchase_action,
                p.is_featured,
                p.status,
                p.updated_at,
                COALESCE(c.category_name, 'Unassigned') AS category_name,
                (
                    SELECT COUNT(*)
                    FROM product_color_variants cv
                    WHERE cv.product_id = p.id
                ) AS color_count,
                (
                    SELECT COUNT(*)
                    FROM product_design_variants dv
                    WHERE dv.product_id = p.id
                ) AS design_count,
                (
                    SELECT COUNT(*)
                    FROM product_images pi
                    WHERE pi.product_id = p.id
                ) AS image_count
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT 1000";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['minimum_order_qty'] = (int)$row['minimum_order_qty'];
            $row['quantity_step'] = (int)$row['quantity_step'];
            $row['is_featured'] = (bool)$row['is_featured'];
            $row['color_count'] = (int)$row['color_count'];
            $row['design_count'] = (int)$row['design_count'];
            $row['image_count'] = (int)$row['image_count'];
            $row['thumbnail_url'] = catalog_admin_media_url($row['thumbnail_path'] ?? null);
        }
        unset($row);

        catalog_json(true, '', [
            'rows' => $rows,
            'permissions' => catalog_product_permissions($pdo),
        ]);
    }

    if ($action === 'get') {
        product_api_require_permission($pdo, 'can_view');

        $id = (int)catalog_value('id', 0);

        $stmt = $pdo->prepare(
            "SELECT *
             FROM products
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            catalog_json(false, 'Product not found.', null, 404);
        }

        $imagesStmt = $pdo->prepare(
            "SELECT id, image_path, alt_text, sort_order, status
             FROM product_images
             WHERE product_id = :product_id
             ORDER BY sort_order, id"
        );
        $imagesStmt->execute(['product_id' => $id]);
        $images = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($images as &$image) {
            $image['id'] = (int)$image['id'];
            $image['image_url'] = catalog_admin_media_url($image['image_path'] ?? null);
        }
        unset($image);

        $colorsStmt = $pdo->prepare(
            "SELECT *
             FROM product_color_variants
             WHERE product_id = :product_id
             ORDER BY sort_order, id"
        );
        $colorsStmt->execute(['product_id' => $id]);
        $colors = $colorsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($colors as &$color) {
            $color['id'] = (int)$color['id'];
            $color['image_url'] = catalog_admin_media_url($color['image_path'] ?? null);
        }
        unset($color);

        $designsStmt = $pdo->prepare(
            "SELECT *
             FROM product_design_variants
             WHERE product_id = :product_id
             ORDER BY sort_order, id"
        );
        $designsStmt->execute(['product_id' => $id]);
        $designs = $designsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($designs as &$design) {
            $design['id'] = (int)$design['id'];
            $design['image_url'] = catalog_admin_media_url($design['image_path'] ?? null);
        }
        unset($design);

        $product['thumbnail_url'] = catalog_admin_media_url($product['thumbnail_path'] ?? null);

        catalog_json(true, '', [
            'product' => $product,
            'images' => $images,
            'colors' => $colors,
            'designs' => $designs,
            'permissions' => catalog_product_permissions($pdo),
        ]);
    }

    if ($action === 'save') {
        catalog_require_csrf();

        $id = (int)catalog_value('id', 0);
        product_api_require_permission(
            $pdo,
            $id > 0 ? 'can_edit' : 'can_add'
        );

        $categoryId = (int)catalog_value('category_id', 0);
        $priceRangeRaw = trim((string)catalog_value('price_range_id', ''));
        $priceRangeId = $priceRangeRaw === '' ? null : (int)$priceRangeRaw;
        $name = trim((string)catalog_value('product_name', ''));
        $tamilName = trim(
            (string)catalog_value(
                'product_name_tamil',
                ''
            )
        );
        $sku = trim((string)catalog_value('sku', ''));
        $shortDescription = trim((string)catalog_value('short_description', ''));
        $description = trim((string)catalog_value('description', ''));
        $basePrice = (float)catalog_value('base_price', 0);
        $offerRaw = trim((string)catalog_value('offer_price', ''));
        $offerPrice = $offerRaw === '' ? null : (float)$offerRaw;
        $minimumQty = (int)catalog_value('minimum_order_qty', 1);
        $quantityStep = (int)catalog_value('quantity_step', 1);
        $purchaseAction = trim((string)catalog_value('purchase_action', 'inherit'));
        $status = trim((string)catalog_value('status', 'draft'));
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

        if ($categoryId <= 0) {
            throw new RuntimeException('Select a category.');
        }

        if ($name === '' || mb_strlen($name) > 200) {
            throw new RuntimeException(
                'Enter a valid English product name.'
            );
        }

        if (mb_strlen($tamilName) > 200) {
            throw new RuntimeException(
                'Tamil product name must not exceed 200 characters.'
            );
        }

        if (mb_strlen($sku) > 100) {
            throw new RuntimeException('SKU must not exceed 100 characters.');
        }

        if ($basePrice < 0) {
            throw new RuntimeException('Base price cannot be negative.');
        }

        if ($offerPrice !== null && ($offerPrice < 0 || $offerPrice > $basePrice)) {
            throw new RuntimeException('Offer price must be between zero and the base price.');
        }

        if ($minimumQty <= 0 || $quantityStep <= 0) {
            throw new RuntimeException('Minimum quantity and quantity step must be greater than zero.');
        }

        if (!in_array($purchaseAction, ['inherit', 'checkout', 'enquiry', 'both'], true)) {
            throw new RuntimeException('Invalid purchase action.');
        }

        if (!in_array($status, ['active', 'draft', 'inactive'], true)) {
            throw new RuntimeException('Invalid product status.');
        }

        $categoryStmt = $pdo->prepare(
            "SELECT status
             FROM categories
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $categoryStmt->execute(['id' => $categoryId]);
        $categoryStatus = $categoryStmt->fetchColumn();

        if ($categoryStatus === false) {
            throw new RuntimeException('The selected category does not exist.');
        }

        if ($status === 'active' && $categoryStatus !== 'active') {
            throw new RuntimeException('Activate the selected category before activating this product.');
        }

        if ($priceRangeId !== null) {
            $rangeStmt = $pdo->prepare(
                'SELECT id FROM price_ranges WHERE id = :id LIMIT 1'
            );
            $rangeStmt->execute(['id' => $priceRangeId]);
            if (!$rangeStmt->fetchColumn()) {
                throw new RuntimeException('The selected price range does not exist.');
            }
        }

        if ($sku !== '') {
            $skuStmt = $pdo->prepare(
                "SELECT id
                 FROM products
                 WHERE LOWER(sku) = LOWER(:sku)
                   AND id <> :id
                 LIMIT 1"
            );
            $skuStmt->execute([
                'sku' => $sku,
                'id' => $id,
            ]);
            if ($skuStmt->fetchColumn()) {
                throw new RuntimeException('This SKU is already assigned to another product.');
            }
        }

        $newPaths = [];
        $deleteAfterCommit = [];
        $oldThumbnail = '';

        try {
            $newThumbnail = isset($_FILES['thumbnail'])
                ? catalog_upload_image($_FILES['thumbnail'], 'products/thumbnails')
                : null;

            if ($newThumbnail !== null) {
                $newPaths[] = $newThumbnail;
            }

            $secondaryFiles = catalog_normalize_multiple_files('secondary_images');

            if (count($secondaryFiles) > 10) {
                throw new RuntimeException('Upload a maximum of 10 secondary images at a time.');
            }

            $newSecondaryPaths = [];
            foreach ($secondaryFiles as $file) {
                $path = catalog_upload_image($file, 'products/gallery');
                if ($path !== null) {
                    $newSecondaryPaths[] = $path;
                    $newPaths[] = $path;
                }
            }

            $pdo->beginTransaction();

            if ($id > 0) {
                $select = $pdo->prepare(
                    "SELECT thumbnail_path
                     FROM products
                     WHERE id = :id
                       AND deleted_at IS NULL
                     LIMIT 1
                     FOR UPDATE"
                );
                $select->execute(['id' => $id]);
                $oldThumbnailValue = $select->fetchColumn();

                if ($oldThumbnailValue === false) {
                    throw new RuntimeException('Product not found.');
                }

                $oldThumbnail = (string)$oldThumbnailValue;
                $thumbnailPath = $newThumbnail ?? $oldThumbnail;

                if ($thumbnailPath === '') {
                    throw new RuntimeException('Product thumbnail is required.');
                }

                $slug = catalog_unique_slug($pdo, 'products', $name, $id);

                $update = $pdo->prepare(
                    "UPDATE products
                     SET category_id = :category_id,
                         price_range_id = :price_range_id,
                         product_name = :product_name,
                         product_name_tamil = :product_name_tamil,
                         slug = :slug,
                         sku = :sku,
                         short_description = :short_description,
                         description = :description,
                         thumbnail_path = :thumbnail_path,
                         base_price = :base_price,
                         offer_price = :offer_price,
                         minimum_order_qty = :minimum_order_qty,
                         quantity_step = :quantity_step,
                         purchase_action = :purchase_action,
                         is_featured = :is_featured,
                         status = :status,
                         updated_by = :updated_by
                     WHERE id = :id"
                );

                $update->execute([
                    'category_id' => $categoryId,
                    'price_range_id' => $priceRangeId,
                    'product_name' => $name,
                    'product_name_tamil' =>
                        $tamilName !== ''
                            ? $tamilName
                            : null,
                    'slug' => $slug,
                    'sku' => $sku !== '' ? $sku : null,
                    'short_description' => $shortDescription !== '' ? $shortDescription : null,
                    'description' => $description !== '' ? $description : null,
                    'thumbnail_path' => $thumbnailPath,
                    'base_price' => $basePrice,
                    'offer_price' => $offerPrice,
                    'minimum_order_qty' => $minimumQty,
                    'quantity_step' => $quantityStep,
                    'purchase_action' => $purchaseAction,
                    'is_featured' => $isFeatured,
                    'status' => $status,
                    'updated_by' => catalog_admin_id(),
                    'id' => $id,
                ]);

                $message = 'Product updated successfully.';
                $logAction = 'update';
            } else {
                if ($newThumbnail === null) {
                    throw new RuntimeException('Product thumbnail is required for a new product.');
                }

                $slug = catalog_unique_slug($pdo, 'products', $name);

                $insert = $pdo->prepare(
                    "INSERT INTO products
                    (
                        category_id,
                        price_range_id,
                        product_name,
                        product_name_tamil,
                        slug,
                        sku,
                        short_description,
                        description,
                        thumbnail_path,
                        base_price,
                        offer_price,
                        minimum_order_qty,
                        quantity_step,
                        purchase_action,
                        is_featured,
                        status,
                        created_by,
                        updated_by
                    )
                    VALUES
                    (
                        :category_id,
                        :price_range_id,
                        :product_name,
                        :product_name_tamil,
                        :slug,
                        :sku,
                        :short_description,
                        :description,
                        :thumbnail_path,
                        :base_price,
                        :offer_price,
                        :minimum_order_qty,
                        :quantity_step,
                        :purchase_action,
                        :is_featured,
                        :status,
                        :created_by,
                        :updated_by
                    )"
                );

                $insert->execute([
                    'category_id' => $categoryId,
                    'price_range_id' => $priceRangeId,
                    'product_name' => $name,
                    'product_name_tamil' =>
                        $tamilName !== ''
                            ? $tamilName
                            : null,
                    'slug' => $slug,
                    'sku' => $sku !== '' ? $sku : null,
                    'short_description' => $shortDescription !== '' ? $shortDescription : null,
                    'description' => $description !== '' ? $description : null,
                    'thumbnail_path' => $newThumbnail,
                    'base_price' => $basePrice,
                    'offer_price' => $offerPrice,
                    'minimum_order_qty' => $minimumQty,
                    'quantity_step' => $quantityStep,
                    'purchase_action' => $purchaseAction,
                    'is_featured' => $isFeatured,
                    'status' => $status,
                    'created_by' => catalog_admin_id(),
                    'updated_by' => catalog_admin_id(),
                ]);

                $id = (int)$pdo->lastInsertId();
                $message = 'Product created successfully.';
                $logAction = 'create';
            }

            $removeImageIds = product_post_int_array('remove_image_ids');
            if ($removeImageIds) {
                $selectImage = $pdo->prepare(
                    "SELECT image_path
                     FROM product_images
                     WHERE id = :id
                       AND product_id = :product_id
                     LIMIT 1"
                );
                $deleteImage = $pdo->prepare(
                    "DELETE FROM product_images
                     WHERE id = :id
                       AND product_id = :product_id"
                );

                foreach ($removeImageIds as $imageId) {
                    if ($imageId <= 0) {
                        continue;
                    }

                    $selectImage->execute([
                        'id' => $imageId,
                        'product_id' => $id,
                    ]);
                    $path = $selectImage->fetchColumn();

                    if ($path !== false) {
                        $deleteImage->execute([
                            'id' => $imageId,
                            'product_id' => $id,
                        ]);
                        $deleteAfterCommit[] = (string)$path;
                    }
                }
            }

            if ($newSecondaryPaths) {
                $nextSortStmt = $pdo->prepare(
                    "SELECT COALESCE(MAX(sort_order), 0)
                     FROM product_images
                     WHERE product_id = :product_id"
                );
                $nextSortStmt->execute(['product_id' => $id]);
                $nextSort = (int)$nextSortStmt->fetchColumn();

                $imageInsert = $pdo->prepare(
                    "INSERT INTO product_images
                    (
                        product_id,
                        image_path,
                        alt_text,
                        sort_order,
                        status
                    )
                    VALUES
                    (
                        :product_id,
                        :image_path,
                        :alt_text,
                        :sort_order,
                        'active'
                    )"
                );

                foreach ($newSecondaryPaths as $path) {
                    $nextSort++;
                    $imageInsert->execute([
                        'product_id' => $id,
                        'image_path' => $path,
                        'alt_text' => $name,
                        'sort_order' => $nextSort,
                    ]);
                }
            }

            $colorCount = sync_color_variants(
                $pdo,
                $id,
                $newPaths,
                $deleteAfterCommit
            );

            $designCount = sync_design_variants(
                $pdo,
                $id,
                $newPaths,
                $deleteAfterCommit
            );

            $flags = $pdo->prepare(
                "UPDATE products
                 SET has_color_variants = :has_colors,
                     has_design_variants = :has_designs
                 WHERE id = :id"
            );
            $flags->execute([
                'has_colors' => $colorCount > 0 ? 1 : 0,
                'has_designs' => $designCount > 0 ? 1 : 0,
                'id' => $id,
            ]);

            catalog_log(
                $pdo,
                $logAction,
                'Products',
                'product',
                $id,
                $message
            );

            $pdo->commit();

            if ($newThumbnail !== null && $oldThumbnail !== '') {
                $deleteAfterCommit[] = $oldThumbnail;
            }

            foreach (array_unique($deleteAfterCommit) as $path) {
                catalog_delete_file($path);
            }

            catalog_json(true, $message, ['id' => $id]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach (array_unique($newPaths) as $path) {
                catalog_delete_file($path);
            }

            throw $exception;
        }
    }

    if ($action === 'delete') {
        catalog_require_csrf();
        product_api_require_permission($pdo, 'can_delete');

        $id = (int)catalog_value('id', 0);

        $pdo->beginTransaction();

        $select = $pdo->prepare(
            "SELECT product_name
             FROM products
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1
             FOR UPDATE"
        );
        $select->execute(['id' => $id]);
        $name = $select->fetchColumn();

        if ($name === false) {
            throw new RuntimeException('Product not found.');
        }

        $delete = $pdo->prepare(
            "UPDATE products
             SET status = 'inactive',
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
            'Products',
            'product',
            $id,
            'Product deleted: ' . (string)$name
        );

        $pdo->commit();
        catalog_json(true, 'Product deleted successfully.');
    }

    catalog_json(false, 'Invalid action.', null, 422);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Products API error: ' . $exception->getMessage());
    catalog_json(false, catalog_error_message($exception), null, 422);
}
