<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/catalog-common.php';

$productPermissions = catalog_product_permissions($pdo);

if (!$productPermissions['can_view']) {
    http_response_code(403);
    exit('Permission denied.');
}

/*
 * Keep old sidebar/menu links working.
 * products.php?action=add now opens the dedicated Add Product page.
 */
if (
    ($_GET['action'] ?? '') === 'add'
    && $productPermissions['can_add']
) {
    header('Location: product-add.php');
    exit;
}

$initialCategories = [];
$initialProducts = [];
$initialProductLoadError = '';

try {
    $initialCategories = $pdo->query(
        "SELECT id, category_name, status
         FROM categories
         WHERE deleted_at IS NULL
         ORDER BY sort_order, category_name"
    )->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Render an initial product list on the server. The JavaScript refreshes
     * this list through the API, but products remain visible even when the
     * browser blocks JavaScript or an API response is temporarily invalid.
     * Correlated subqueries avoid ONLY_FULL_GROUP_BY failures.
     */
    $initialProductStmt = $pdo->query(
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
         WHERE p.deleted_at IS NULL
         ORDER BY p.updated_at DESC, p.id DESC
         LIMIT 1000"
    );

    $initialProducts = $initialProductStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('Initial product list error: ' . $exception->getMessage());
    $initialProductLoadError = 'Unable to load products. Use Refresh to retry.';
}

$pageTitle = 'Products';
$pageScript = 'products.js';

require __DIR__ . '/includes/header.php';
?>

<div
    class="ramki-card p-3"
    id="productModule"
    data-can-add="<?= $productPermissions['can_add'] ? '1' : '0'; ?>"
    data-can-edit="<?= $productPermissions['can_edit'] ? '1' : '0'; ?>"
    data-can-delete="<?= $productPermissions['can_delete'] ? '1' : '0'; ?>"
    data-api-url="api/products.php"
    data-view-url="product-view.php"
>
    <div id="productMessage"></div>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Products</h2>
            <p class="text-muted small mb-0">
                Add invitation cards with price, MOQ, offer, images and optional variants.
            </p>
        </div>

        <?php if ($productPermissions['can_add']): ?>
            <a
                class="btn btn-ramki"
                id="addProductBtn"
                href="product-add.php"
            >
                <i class="fa-solid fa-plus me-2"></i>Add Product
            </a>
        <?php endif; ?>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <select class="form-select" id="filterCategory">
                <option value="">All Categories</option>
                <?php foreach ($initialCategories as $category): ?>
                    <option value="<?= (int)$category['id']; ?>">
                        <?= htmlspecialchars(
                            (string)$category['category_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <select class="form-select" id="filterStatus">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="draft">Draft</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <div class="col-md-4">
            <button class="btn btn-outline-secondary w-100" id="refreshProducts" type="button">
                <i class="fa-solid fa-rotate me-2"></i>Refresh
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle w-100" id="productsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>MOQ</th>
                    <th>Variants</th>
                    <th>Action Mode</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($initialProductLoadError !== ''): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-danger">
                            <?= htmlspecialchars(
                                $initialProductLoadError,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </td>
                    </tr>
                <?php elseif (!$initialProducts): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            No products found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($initialProducts as $index => $row): ?>
                        <?php
                        $effectivePrice = (
                            $row['offer_price'] !== null
                            && $row['offer_price'] !== ''
                        )
                            ? (float)$row['offer_price']
                            : (float)$row['base_price'];

                        $thumbnailUrl = catalog_admin_media_url(
                            $row['thumbnail_path'] ?? null
                        );
                        ?>
                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td>
                                <div class="d-flex gap-2 align-items-center">
                                    <?php if ($thumbnailUrl !== ''): ?>
                                        <img
                                            src="<?= htmlspecialchars(
                                                $thumbnailUrl,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                            alt=""
                                            style="width:62px;height:52px;object-fit:cover;border-radius:8px;"
                                        >
                                    <?php endif; ?>

                                    <div>
                                        <strong>
                                            <?= htmlspecialchars(
                                                (string)$row['product_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </strong>

                                        <?php if (!empty($row['product_name_tamil'])): ?>
                                            <div class="small fw-semibold" lang="ta">
                                                <?= htmlspecialchars(
                                                    (string)$row['product_name_tamil'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="small text-muted">
                                            <?= htmlspecialchars(
                                                (string)(
                                                    $row['sku']
                                                    ?: $row['slug']
                                                    ?: ''
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                            <?= !empty($row['is_featured'])
                                                ? ' · Featured'
                                                : ''; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    (string)$row['category_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>
                            <td>
                                <strong>
                                    ₹<?= number_format($effectivePrice, 2); ?>
                                </strong>
                                <?php if (
                                    $row['offer_price'] !== null
                                    && $row['offer_price'] !== ''
                                ): ?>
                                    <div class="small text-muted text-decoration-line-through">
                                        ₹<?= number_format(
                                            (float)$row['base_price'],
                                            2
                                        ); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= (int)$row['minimum_order_qty']; ?>
                                <div class="small text-muted">
                                    Step <?= (int)$row['quantity_step']; ?>
                                </div>
                            </td>
                            <td>
                                <?= (int)$row['color_count']; ?> colours<br>
                                <?= (int)$row['design_count']; ?> designs<br>
                                <span class="small text-muted">
                                    <?= (int)$row['image_count']; ?> gallery
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    (string)$row['purchase_action'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = match ($row['status']) {
                                    'active' => 'bg-success',
                                    'draft' => 'bg-warning text-dark',
                                    default => 'bg-secondary',
                                };
                                ?>
                                <span class="badge <?= $statusClass; ?>">
                                    <?= htmlspecialchars(
                                        (string)$row['status'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a
                                        class="btn btn-sm btn-outline-secondary"
                                        href="product-view.php?id=<?= (int)$row['id']; ?>"
                                        title="View"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </a>

                                    <?php if ($productPermissions['can_edit']): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary js-edit-product"
                                            data-id="<?= (int)$row['id']; ?>"
                                            title="Edit"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($productPermissions['can_delete']): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger js-delete-product"
                                            data-id="<?= (int)$row['id']; ?>"
                                            data-name="<?= htmlspecialchars(
                                                (string)$row['product_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                            title="Delete"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form class="modal-content" id="productForm" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title">Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="id">
                <input type="hidden" name="action" value="save">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Category *</label>
                        <select class="form-select" name="category_id" id="productCategory" required></select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Price Range</label>
                        <select class="form-select" name="price_range_id" id="productPriceRange"></select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Product Name — English *
                        </label>
                        <input
                            class="form-control"
                            name="product_name"
                            maxlength="200"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Product Name — Tamil
                        </label>
                        <input
                            class="form-control"
                            name="product_name_tamil"
                            maxlength="200"
                            lang="ta"
                            placeholder="உதாரணம்: பிரீமியம் திருமண அழைப்பிதழ்"
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">SKU</label>
                        <input
                            class="form-control"
                            name="sku"
                            maxlength="100"
                        >
                    </div>

                    <div class="col-12">
                        <label class="form-label">Short Description</label>
                        <textarea class="form-control" rows="2" name="short_description"></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Full Description</label>
                        <textarea class="form-control" rows="4" name="description"></textarea>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Base Price *</label>
                        <input type="number" min="0" step="0.01" class="form-control" name="base_price" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Offer Price</label>
                        <input type="number" min="0" step="0.01" class="form-control" name="offer_price">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Minimum Quantity *</label>
                        <input type="number" min="1" class="form-control" name="minimum_order_qty" value="1" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Quantity Step *</label>
                        <input type="number" min="1" class="form-control" name="quantity_step" value="1" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Purchase Action</label>
                        <select class="form-select" name="purchase_action">
                            <option value="inherit">Use Global Setting</option>
                            <option value="checkout">Checkout</option>
                            <option value="enquiry">Enquiry Now</option>
                            <option value="both">Both</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="isFeatured">
                            <label class="form-check-label" for="isFeatured">Featured Product</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Thumbnail
                            <small class="text-muted">(required for a new product)</small>
                        </label>
                        <input type="file" class="form-control" name="thumbnail" accept=".jpg,.jpeg,.png,.webp">
                        <div class="form-text">JPG, PNG or WEBP. Maximum 5 MB.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Secondary Images</label>
                        <input type="file" class="form-control" name="secondary_images[]" multiple accept=".jpg,.jpeg,.png,.webp">
                    </div>

                    <div class="col-12 d-none" id="currentThumbnailWrap">
                        <label class="form-label">Current Thumbnail</label>
                        <div>
                            <img id="currentThumbnail" alt="Current thumbnail" style="width:120px;height:90px;object-fit:cover;border-radius:10px;">
                        </div>
                    </div>

                    <div class="col-12 d-none" id="existingImagesWrap">
                        <label class="form-label">Existing Secondary Images</label>
                        <div class="row g-2" id="existingImages"></div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="mb-0">Optional Colour Variants</h6>
                                <small class="text-muted">Removing a row deletes that variant when the product is saved.</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="addColorRow">Add Colour</button>
                        </div>
                        <div id="colorRows"></div>
                    </div>

                    <div class="col-lg-6">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="mb-0">Optional Design Variants</h6>
                                <small class="text-muted">Removing a row deletes that variant when the product is saved.</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="addDesignRow">Add Design</button>
                        </div>
                        <div id="designRows"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-ramki" type="submit" id="saveProductBtn">Update Product</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
