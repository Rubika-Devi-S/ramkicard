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
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">Loading products...</td>
                </tr>
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
