<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/catalog-common.php';

$productPermissions = catalog_product_permissions($pdo);

if (!$productPermissions['can_add']) {
    http_response_code(403);
    exit('Permission denied.');
}

$categories = $pdo->query(
    "SELECT id, category_name, status
     FROM categories
     WHERE deleted_at IS NULL
     ORDER BY sort_order, category_name"
)->fetchAll(PDO::FETCH_ASSOC);

$priceRanges = $pdo->query(
    "SELECT id, range_name, status
     FROM price_ranges
     ORDER BY sort_order, minimum_price"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Add Product';
$pageScript = 'product-add.js';

require __DIR__ . '/includes/header.php';
?>

<style>
.product-add-page {
    --product-add-surface:
        var(--ui-card-bg, var(--bs-body-bg, #ffffff));
    --product-add-soft:
        var(--ui-card-header-bg, #fff8f3);
    --product-add-text:
        var(--ui-text-main, var(--bs-body-color, #2d2421));
    --product-add-muted:
        var(--ui-text-muted, #786d68);
    --product-add-border:
        var(--ui-border-soft, rgba(139, 18, 49, 0.14));
    --product-add-primary:
        var(--ui-brand-1, #8b1231);
    --product-add-primary-dark:
        var(--ui-brand-2, #5d071d);
    --product-add-gold:
        var(--ui-accent, #c9963e);
    color: var(--product-add-text);
}

.product-add-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
}

.product-add-back,
.product-add-cancel {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    padding: 9px 15px;
    color: var(--product-add-primary);
    font-size: 0.74rem;
    font-weight: 800;
    background: var(--product-add-surface);
    border: 1px solid var(--product-add-border);
    border-radius: 11px;
    text-decoration: none;
}

.product-add-banner {
    position: relative;
    margin-bottom: 21px;
    padding: 26px 28px;
    overflow: hidden;
    color: #fff;
    background:
        radial-gradient(circle at 87% 16%,
            rgba(255, 255, 255, 0.18),
            transparent 25%),
        linear-gradient(135deg,
            var(--product-add-primary-dark),
            var(--product-add-primary));
    border-radius: 22px;
    box-shadow: 0 18px 46px rgba(82, 14, 34, 0.2);
}

.product-add-banner::after {
    content: '';
    position: absolute;
    right: -42px;
    bottom: -85px;
    width: 210px;
    height: 210px;
    border: 35px solid rgba(255, 255, 255, 0.07);
    border-radius: 50%;
}

.product-add-banner small {
    display: block;
    margin-bottom: 5px;
    color: #f2d17a;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 1.4px;
    text-transform: uppercase;
}

.product-add-banner h1 {
    position: relative;
    z-index: 2;
    margin: 0 0 6px;
    color: #fff;
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(1.75rem, 3vw, 2.4rem);
    font-weight: 800;
}

.product-add-banner p {
    position: relative;
    z-index: 2;
    max-width: 760px;
    margin: 0;
    color: rgba(255, 255, 255, 0.76);
    font-size: 0.78rem;
    line-height: 1.65;
}

.product-add-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 310px;
    gap: 20px;
    align-items: start;
}

.product-add-main {
    display: grid;
    gap: 18px;
    min-width: 0;
}

.product-add-card {
    overflow: hidden;
    background: var(--product-add-surface);
    border: 1px solid var(--product-add-border);
    border-radius: 19px;
    box-shadow: 0 14px 38px rgba(65, 27, 38, 0.075);
}

.product-add-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 17px 19px;
    background: var(--product-add-soft);
    border-bottom: 1px solid var(--product-add-border);
}

.product-add-card-header h2 {
    margin: 0 0 3px;
    color: var(--product-add-text);
    font-family: "Playfair Display", Georgia, serif;
    font-size: 1.08rem;
    font-weight: 800;
}

.product-add-card-header p {
    margin: 0;
    color: var(--product-add-muted);
    font-size: 0.66rem;
}

.product-add-card-body {
    padding: 20px;
}

.product-add-form-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 15px;
}

.product-add-field {
    grid-column: span 6;
    min-width: 0;
}

.product-add-field.third {
    grid-column: span 4;
}

.product-add-field.quarter {
    grid-column: span 3;
}

.product-add-field.full {
    grid-column: 1 / -1;
}

.product-add-field label {
    display: block;
    margin-bottom: 7px;
    color: var(--product-add-text);
    font-size: 0.7rem;
    font-weight: 800;
}

.product-add-required {
    color: #c02b42;
}

.product-add-field .form-control,
.product-add-field .form-select {
    min-height: 46px;
    color: var(--product-add-text);
    background: var(--product-add-surface);
    border: 1px solid var(--product-add-border);
    border-radius: 11px;
    box-shadow: none;
}

.product-add-field textarea.form-control {
    min-height: 95px;
    resize: vertical;
}

.product-add-field .form-control:focus,
.product-add-field .form-select:focus {
    border-color: color-mix(in srgb,
            var(--product-add-primary) 58%,
            transparent);
    box-shadow: 0 0 0 4px color-mix(in srgb,
            var(--product-add-primary) 9%,
            transparent);
}

.product-add-help {
    display: block;
    margin-top: 6px;
    color: var(--product-add-muted);
    font-size: 0.61rem;
    line-height: 1.55;
}

.product-add-check {
    display: flex;
    align-items: center;
    min-height: 46px;
    padding: 11px 13px;
    background: var(--product-add-soft);
    border: 1px solid var(--product-add-border);
    border-radius: 11px;
}

.product-add-check label {
    margin: 0 0 0 8px;
}

.product-add-upload-preview {
    display: none;
    margin-top: 12px;
    padding: 10px;
    background: var(--product-add-soft);
    border: 1px solid var(--product-add-border);
    border-radius: 12px;
}

.product-add-upload-preview.show {
    display: block;
}

.product-add-upload-preview img {
    width: 130px;
    height: 100px;
    object-fit: cover;
    border-radius: 9px;
}

.product-add-gallery-preview {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 9px;
    margin-top: 12px;
}

.product-add-gallery-preview:empty {
    display: none;
}

.product-add-gallery-preview img {
    width: 100%;
    height: 85px;
    object-fit: cover;
    border: 1px solid var(--product-add-border);
    border-radius: 9px;
}

.product-add-variant-list {
    display: grid;
    gap: 11px;
}

.product-add-variant-empty {
    padding: 25px 15px;
    color: var(--product-add-muted);
    font-size: 0.7rem;
    text-align: center;
    background: var(--product-add-soft);
    border: 1px dashed var(--product-add-border);
    border-radius: 13px;
}

.product-add-variant-row {
    padding: 14px;
    background: var(--product-add-soft);
    border: 1px solid var(--product-add-border);
    border-radius: 14px;
}

.product-add-variant-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 10px;
}

.product-add-variant-field {
    grid-column: span 3;
    min-width: 0;
}

.product-add-variant-field.wide {
    grid-column: span 5;
}

.product-add-variant-field.full {
    grid-column: 1 / -1;
}

.product-add-variant-field label {
    display: block;
    margin-bottom: 5px;
    color: var(--product-add-muted);
    font-size: 0.59rem;
    font-weight: 800;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.product-add-variant-field .form-control,
.product-add-variant-field .form-select {
    min-height: 40px;
    color: var(--product-add-text);
    background: var(--product-add-surface);
    border: 1px solid var(--product-add-border);
    border-radius: 9px;
    font-size: 0.7rem;
}

.product-add-variant-remove {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    margin-left: auto;
    color: #b4233d;
    background: var(--product-add-surface);
    border: 1px solid rgba(180, 35, 61, 0.22);
    border-radius: 9px;
    cursor: pointer;
}

.product-add-variant-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 38px;
    padding: 8px 12px;
    color: var(--product-add-primary);
    font-size: 0.66rem;
    font-weight: 800;
    background: var(--product-add-surface);
    border: 1px solid var(--product-add-border);
    border-radius: 9px;
    cursor: pointer;
}

.product-add-sidebar {
    position: sticky;
    top: 105px;
    display: grid;
    gap: 16px;
}

.product-add-summary {
    padding: 19px;
}

.product-add-summary h2 {
    margin: 0 0 14px;
    color: var(--product-add-text);
    font-family: "Playfair Display", Georgia, serif;
    font-size: 1.05rem;
    font-weight: 800;
}

.product-add-summary-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    color: var(--product-add-muted);
    font-size: 0.68rem;
    border-bottom: 1px solid var(--product-add-border);
}

.product-add-summary-item:last-child {
    border-bottom: 0;
}

.product-add-summary-item strong {
    color: var(--product-add-text);
    text-align: right;
}

.product-add-actions {
    padding: 17px;
}

.product-add-save {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    min-height: 48px;
    color: #fff;
    font-size: 0.76rem;
    font-weight: 800;
    background: linear-gradient(135deg,
            var(--product-add-primary),
            var(--product-add-primary-dark));
    border: 0;
    border-radius: 12px;
    box-shadow: 0 13px 28px rgba(91, 12, 33, 0.22);
    cursor: pointer;
}

.product-add-save:disabled {
    cursor: not-allowed;
    opacity: 0.62;
}

.product-add-cancel {
    width: 100%;
    margin-top: 9px;
}

.product-add-message {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 11000;
    display: grid;
    gap: 9px;
    width: min(390px, calc(100vw - 32px));
    pointer-events: none;
}

.product-add-toast {
    padding: 14px 16px;
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    background: #197345;
    border-radius: 12px;
    box-shadow: 0 18px 45px rgba(0, 0, 0, 0.22);
    opacity: 0;
    transform: translateY(16px);
    animation: productAddToastIn 0.24s ease forwards;
}

.product-add-toast.error {
    background: #b3263e;
}

@keyframes productAddToastIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 1099.98px) {
    .product-add-layout {
        grid-template-columns: 1fr;
    }

    .product-add-sidebar {
        position: static;
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 767.98px) {

    .product-add-field,
    .product-add-field.third,
    .product-add-field.quarter {
        grid-column: span 12;
    }

    .product-add-variant-field,
    .product-add-variant-field.wide {
        grid-column: span 6;
    }

    .product-add-gallery-preview {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 575.98px) {
    .product-add-topbar {
        align-items: stretch;
        flex-direction: column;
    }

    .product-add-banner {
        padding: 22px 19px;
        border-radius: 18px;
    }

    .product-add-card-body {
        padding: 15px;
    }

    .product-add-sidebar {
        grid-template-columns: 1fr;
    }

    .product-add-variant-field,
    .product-add-variant-field.wide {
        grid-column: span 12;
    }

    .product-add-gallery-preview {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .product-add-message {
        right: 16px;
        bottom: 16px;
    }
}
</style>

<div class="product-add-page" id="productAddModule" data-products-url="products.php"
    data-product-view-url="product-view.php">
    <div class="product-add-topbar">
        <a href="products.php" class="product-add-back">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Products
        </a>
    </div>

    <section class="product-add-banner">
        <small>Product catalogue</small>
        <h1>Add New Product</h1>
        <p>
            Create the product, upload images and add optional colour or
            design variants from this dedicated page.
        </p>
    </section>

    <form id="productAddForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="id" value="">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

        <div class="product-add-layout">
            <main class="product-add-main">
                <section class="product-add-card">
                    <header class="product-add-card-header">
                        <div>
                            <h2>Basic Information</h2>
                            <p>
                                Enter the category, names and description.
                            </p>
                        </div>
                    </header>

                    <div class="product-add-card-body">
                        <div class="product-add-form-grid">
                            <div class="product-add-field">
                                <label for="addProductCategory">
                                    Category
                                    <span class="product-add-required">*</span>
                                </label>

                                <select class="form-select" id="addProductCategory" name="category_id" required>
                                    <option value="">Select Category</option>

                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int)$category['id']; ?>">
                                        <?= e($category['category_name']); ?>
                                        <?= $category['status'] !== 'active'
                                                ? ' (Inactive)'
                                                : ''; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="product-add-field">
                                <label for="addProductPriceRange">
                                    Price Range
                                </label>

                                <select class="form-select" id="addProductPriceRange" name="price_range_id">
                                    <option value="">No Price Range</option>

                                    <?php foreach ($priceRanges as $range): ?>
                                    <option value="<?= (int)$range['id']; ?>">
                                        <?= e($range['range_name']); ?>
                                        <?= $range['status'] !== 'active'
                                                ? ' (Inactive)'
                                                : ''; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="product-add-field">
                                <label for="addProductName">
                                    Product Name — English
                                    <span class="product-add-required">*</span>
                                </label>

                                <input class="form-control" id="addProductName" name="product_name" maxlength="200"
                                    autocomplete="off" required>
                            </div>

                            <div class="product-add-field">
                                <label for="addProductTamilName">
                                    Product Name — Tamil
                                </label>

                                <input class="form-control" id="addProductTamilName" name="product_name_tamil"
                                    maxlength="200" lang="ta" autocomplete="off"
                                    placeholder="உதாரணம்: பிரீமியம் திருமண அழைப்பிதழ்">
                            </div>

                            <div class="product-add-field full">
                                <label for="addProductShortDescription">
                                    Short Description
                                </label>

                                <textarea class="form-control" id="addProductShortDescription" name="short_description"
                                    rows="2" maxlength="1000"></textarea>
                            </div>

                            <div class="product-add-field full">
                                <label for="addProductDescription">
                                    Full Description
                                </label>

                                <textarea class="form-control" id="addProductDescription" name="description"
                                    rows="5"></textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="product-add-card">
                    <header class="product-add-card-header">
                        <div>
                            <h2>Price & Order Settings</h2>
                            <p>
                                Configure price, quantity and purchase mode.
                            </p>
                        </div>
                    </header>

                    <div class="product-add-card-body">
                        <div class="product-add-form-grid">
                            <div class="product-add-field quarter">
                                <label for="addProductBasePrice">
                                    Base Price
                                    <span class="product-add-required">*</span>
                                </label>

                                <input type="number" class="form-control" id="addProductBasePrice" name="base_price"
                                    min="0" step="0.01" value="0.00" required>
                            </div>

                            <div class="product-add-field quarter">
                                <label for="addProductOfferPrice">
                                    Offer Price
                                </label>

                                <input type="number" class="form-control" id="addProductOfferPrice" name="offer_price"
                                    min="0" step="0.01">
                            </div>

                            <div class="product-add-field quarter">
                                <label for="addProductMinimumQty">
                                    Minimum Quantity
                                    <span class="product-add-required">*</span>
                                </label>

                                <input type="number" class="form-control" id="addProductMinimumQty"
                                    name="minimum_order_qty" min="1" value="1" required>
                            </div>

                            <div class="product-add-field quarter">
                                <label for="addProductQuantityStep">
                                    Quantity Step
                                    <span class="product-add-required">*</span>
                                </label>

                                <input type="number" class="form-control" id="addProductQuantityStep"
                                    name="quantity_step" min="1" value="1" required>
                            </div>

                            <div class="product-add-field third">
                                <label for="addProductPurchaseAction">
                                    Purchase Action
                                </label>

                                <select class="form-select" id="addProductPurchaseAction" name="purchase_action">
                                    <option value="inherit">
                                        Use Global Setting
                                    </option>
                                    <option value="checkout">Checkout</option>
                                    <option value="enquiry">Enquiry Now</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>

                            <div class="product-add-field third">
                                <label for="addProductStatus">Status</label>

                                <select class="form-select" id="addProductStatus" name="status">
                                    <option value="draft" selected>Draft</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div class="product-add-field third">
                                <label>Featured Product</label>

                                <div class="product-add-check">
                                    <input class="form-check-input" type="checkbox" id="addProductFeatured"
                                        name="is_featured" value="1">
                                    <label class="form-check-label" for="addProductFeatured">
                                        Show in featured products
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="product-add-card">
                    <header class="product-add-card-header">
                        <div>
                            <h2>Product Images</h2>
                            <p>
                                Upload the required thumbnail and optional
                                gallery images.
                            </p>
                        </div>
                    </header>

                    <div class="product-add-card-body">
                        <div class="product-add-form-grid">
                            <div class="product-add-field">
                                <label for="addProductThumbnail">
                                    Thumbnail
                                    <span class="product-add-required">*</span>
                                </label>

                                <input type="file" class="form-control" id="addProductThumbnail" name="thumbnail"
                                    accept=".jpg,.jpeg,.png,.webp" required>

                                <span class="product-add-help">
                                    JPG, PNG or WEBP. Maximum 5 MB.
                                </span>

                                <div class="product-add-upload-preview" id="thumbnailPreviewWrap">
                                    <img id="thumbnailPreview" alt="Thumbnail preview">
                                </div>
                            </div>

                            <div class="product-add-field">
                                <label for="addProductGallery">
                                    Secondary Images
                                </label>

                                <input type="file" class="form-control" id="addProductGallery" name="secondary_images[]"
                                    accept=".jpg,.jpeg,.png,.webp" multiple>

                                <span class="product-add-help">
                                    Upload up to 10 images at a time.
                                </span>

                                <div class="product-add-gallery-preview" id="galleryPreview"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="product-add-card">
                    <header class="product-add-card-header">
                        <div>
                            <h2>Colour Variants</h2>
                            <p>
                                Add colours only when the product requires
                                selectable colour options.
                            </p>
                        </div>

                        <button type="button" class="product-add-variant-button" id="addProductColor">
                            <i class="fa-solid fa-plus"></i>
                            Add Colour
                        </button>
                    </header>

                    <div class="product-add-card-body">
                        <div class="product-add-variant-list" id="productColorRows">
                            <div class="product-add-variant-empty" data-empty-state>
                                No colour variants added.
                            </div>
                        </div>
                    </div>
                </section>

                <section class="product-add-card">
                    <header class="product-add-card-header">
                        <div>
                            <h2>Design Variants</h2>
                            <p>
                                Add design choices, codes, price changes and
                                optional images.
                            </p>
                        </div>

                        <button type="button" class="product-add-variant-button" id="addProductDesign">
                            <i class="fa-solid fa-plus"></i>
                            Add Design
                        </button>
                    </header>

                    <div class="product-add-card-body">
                        <div class="product-add-variant-list" id="productDesignRows">
                            <div class="product-add-variant-empty" data-empty-state>
                                No design variants added.
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <aside class="product-add-sidebar">
                <section class="product-add-card product-add-summary">
                    <h2>Product Summary</h2>

                    <div class="product-add-summary-item">
                        <span>Name</span>
                        <strong id="summaryProductName">
                            Not entered
                        </strong>
                    </div>

                    <div class="product-add-summary-item">
                        <span>Price</span>
                        <strong id="summaryProductPrice">
                            ₹0.00
                        </strong>
                    </div>

                    <div class="product-add-summary-item">
                        <span>MOQ</span>
                        <strong id="summaryProductMoq">1</strong>
                    </div>

                    <div class="product-add-summary-item">
                        <span>Colours</span>
                        <strong id="summaryProductColors">0</strong>
                    </div>

                    <div class="product-add-summary-item">
                        <span>Designs</span>
                        <strong id="summaryProductDesigns">0</strong>
                    </div>

                    <div class="product-add-summary-item">
                        <span>Status</span>
                        <strong id="summaryProductStatus">Draft</strong>
                    </div>
                </section>

                <section class="product-add-card product-add-actions">
                    <button type="submit" class="product-add-save" id="saveNewProductButton">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save Product
                    </button>

                    <a href="products.php" class="product-add-cancel">
                        Cancel
                    </a>
                </section>
            </aside>
        </div>
    </form>

    <div class="product-add-message" id="productAddMessage" aria-live="polite" aria-atomic="true"></div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>