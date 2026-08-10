<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$slug = trim((string)($_GET['slug'] ?? ''));

$customerLoggedIn = sf_customer_logged_in();
$adminLoggedIn = sf_admin_logged_in();

/*
 * Customer accounts use a customer-owned cart.
 * Administrators may also open the purchase form for storefront testing.
 */
$purchaseLoggedIn = $customerLoggedIn || $adminLoggedIn;

$stmt = $pdo->prepare(
    "SELECT p.*, c.category_name, c.slug AS category_slug
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     WHERE p.slug = :slug
       AND p.status = 'active'
       AND p.deleted_at IS NULL
       AND c.status = 'active'
       AND c.deleted_at IS NULL
     LIMIT 1"
);

$stmt->execute(['slug' => $slug]);
$product = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product Not Found';
    $topStripItems = [];
    require __DIR__ . '/includes/storefront-header.php';
    ?>

<main class="store-page">
    <div class="container">
        <div class="empty-state glass-card">
            <h1>Product not found</h1>
            <p>The requested product is inactive or unavailable.</p>
            <a class="primary-btn" href="products.php">
                View Products
            </a>
        </div>
    </div>
</main>
<?php
    require __DIR__ . '/includes/storefront-footer.php';
    exit;
}

$productId = (int)$product['id'];

$stmt = $pdo->prepare(
    "SELECT image_path, alt_text
     FROM product_images
     WHERE product_id = :product_id
       AND status = 'active'
     ORDER BY sort_order, id"
);
$stmt->execute(['product_id' => $productId]);
$productImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    "SELECT *
     FROM product_color_variants
     WHERE product_id = :product_id
       AND status = 'active'
     ORDER BY sort_order, color_name"
);
$stmt->execute(['product_id' => $productId]);
$colors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    "SELECT *
     FROM product_design_variants
     WHERE product_id = :product_id
       AND status = 'active'
     ORDER BY sort_order, design_name"
);
$stmt->execute(['product_id' => $productId]);
$designs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mode = sf_purchase_mode($pdo, $product);
$effectivePrice = sf_effective_price($product);
$basePrice = max(0, (float)$product['base_price']);
$savingsAmount = max(0, $basePrice - $effectivePrice);
$discountPercent = $basePrice > 0 && $savingsAmount > 0
    ? (int)round(($savingsAmount / $basePrice) * 100)
    : 0;
$minimumOrderQty = max(1, (int)$product['minimum_order_qty']);
$quantityStep = max(1, (int)$product['quantity_step']);
$stockManaged = (int)($product['manage_stock'] ?? 0) === 1;
$stockQuantity = max(0, (int)($product['stock_quantity'] ?? 0));
$inStock = !$stockManaged || $stockQuantity >= $minimumOrderQty;
$hasCheckout = in_array($mode, ['checkout', 'both'], true);
$hasEnquiry = in_array($mode, ['enquiry', 'both'], true);
$productReturnUrl = 'product.php?slug=' . rawurlencode(
    (string)$product['slug']
) . '#buy';
$productLoginUrl = sf_login_required_url($productReturnUrl);

$mainImage = sf_media_path(
    $product['thumbnail_path'],
    'banner.png'
);

$gallery = array_merge(
    [[
        'image_path' => $mainImage,
        'alt_text' => $product['product_name'],
    ]],
    $productImages
);

$pageTitle = ($product['meta_title'] ?: $product['product_name'])
    . ' | '
    . sf_setting($pdo, 'company_name', 'Ramki Cards');

$topStripItems = [];
require __DIR__ . '/includes/storefront-header.php';

$cartStatus = trim((string)($_GET['cart'] ?? ''));

$enquiryToast = $_SESSION['product_enquiry_toast'] ?? [];
unset($_SESSION['product_enquiry_toast']);

$enquiryNumber = trim((string)(
    $enquiryToast['number']
    ?? $_GET['enquiry']
    ?? ''
));

$enquiryToastMessage = trim((string)(
    $enquiryToast['message']
    ?? ''
));

$error = trim((string)($_GET['error'] ?? ''));
?>

<style>
.product-enquiry-toast {
    position: fixed;
    top: 94px;
    right: 22px;
    z-index: 10030;
    display: grid;
    grid-template-columns: 48px minmax(0, 1fr) 34px;
    gap: 13px;
    align-items: center;
    width: min(440px, calc(100vw - 32px));
    padding: 16px;
    overflow: hidden;
    color: #253228;
    background: #fff;
    border: 1px solid rgba(21, 128, 61, 0.2);
    border-radius: 16px;
    box-shadow:
        0 24px 60px rgba(38, 20, 26, 0.22),
        0 6px 18px rgba(38, 20, 26, 0.08);
    opacity: 0;
    pointer-events: none;
    transform: translateX(calc(100% + 45px));
    transition:
        opacity 0.3s ease,
        transform 0.35s cubic-bezier(.22, 1, .36, 1);
}

.product-enquiry-toast.show {
    opacity: 1;
    pointer-events: auto;
    transform: translateX(0);
}

.product-enquiry-toast.error {
    border-color: rgba(190, 38, 63, 0.22);
}

.product-enquiry-toast-icon {
    display: grid;
    place-items: center;
    width: 48px;
    height: 48px;
    color: #fff;
    font-size: 23px;
    font-weight: 800;
    background: linear-gradient(145deg, #20a957, #13773b);
    border-radius: 50%;
    box-shadow: 0 9px 22px rgba(19, 119, 59, 0.24);
}

.product-enquiry-toast.error .product-enquiry-toast-icon {
    background: linear-gradient(145deg, #d44258, #9d1830);
    box-shadow: 0 9px 22px rgba(157, 24, 48, 0.22);
}

.product-enquiry-toast-copy {
    min-width: 0;
}

.product-enquiry-toast-copy strong {
    display: block;
    margin-bottom: 3px;
    color: #176a38;
    font-family: "Playfair Display", Georgia, serif;
    font-size: 17px;
    line-height: 1.3;
}

.product-enquiry-toast.error .product-enquiry-toast-copy strong {
    color: #a21832;
}

.product-enquiry-toast-copy p {
    margin: 0;
    color: #6d6466;
    font-size: 12px;
    line-height: 1.55;
}

.product-enquiry-toast-copy b {
    color: #8f1231;
    font-weight: 800;
}

.product-enquiry-toast-close {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    padding: 0;
    color: #887d80;
    font-size: 24px;
    line-height: 1;
    background: #f8f3f4;
    border: 0;
    border-radius: 50%;
    cursor: pointer;
}

.product-enquiry-toast-close:hover {
    color: #fff;
    background: #8f1231;
}

.product-enquiry-toast-progress {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    height: 4px;
    background: rgba(21, 128, 61, 0.12);
}

.product-enquiry-toast-progress::after {
    content: "";
    display: block;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, #20a957, #d8a641);
    transform-origin: left center;
    animation: productEnquiryToastProgress 5.5s linear forwards;
}

.product-enquiry-toast.error .product-enquiry-toast-progress::after {
    background: linear-gradient(90deg, #d44258, #8f1231);
}

@keyframes productEnquiryToastProgress {
    from {
        transform: scaleX(1);
    }

    to {
        transform: scaleX(0);
    }
}

@media (max-width: 575.98px) {
    .product-enquiry-toast {
        top: auto;
        right: 16px;
        bottom: 18px;
        left: 16px;
        width: auto;
        grid-template-columns: 42px minmax(0, 1fr) 32px;
        padding: 14px;
        transform: translateY(calc(100% + 40px));
    }

    .product-enquiry-toast.show {
        transform: translateY(0);
    }

    .product-enquiry-toast-icon {
        width: 42px;
        height: 42px;
        font-size: 20px;
    }
}


/* ================================================================
   Product details page: compact main image and thumbnail gallery
   ================================================================ */

.product-detail-grid {
    grid-template-columns: minmax(360px, 560px) minmax(0, 1fr);
    gap: 40px;
}

.product-media-panel {
    width: 100%;
    max-width: 560px;
    justify-self: center;
    padding: 18px;
}

.product-media-panel .product-main-image {
    display: block;
    width: 100%;
    max-width: 500px;
    max-height: 500px;
    margin: 0 auto;
    object-fit: contain;
    border-radius: 14px;
    background: #fffaf2;
}

.product-media-panel .product-gallery {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    max-width: 500px;
    margin: 14px auto 0;
}

.product-media-panel .product-gallery-thumb {
    flex: 0 0 72px;
    width: 72px;
    height: 72px;
    aspect-ratio: auto;
    padding: 3px;
    object-fit: cover;
    border: 1px solid rgba(201, 150, 62, 0.34);
    border-radius: 10px;
    background: #fff;
    opacity: 0.78;
    cursor: pointer;
    transition:
        opacity 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.2s ease;
}

.product-media-panel .product-gallery-thumb:hover,
.product-media-panel .product-gallery-thumb.active {
    opacity: 1;
    border-color: #8b1231;
    box-shadow: 0 0 0 2px rgba(139, 18, 49, 0.12);
    transform: translateY(-2px);
}

@media (max-width: 1000px) {
    .product-detail-grid {
        grid-template-columns: 1fr;
        gap: 28px;
    }

    .product-media-panel {
        max-width: 560px;
    }

    .product-media-panel .product-main-image {
        max-width: 480px;
        max-height: 480px;
    }
}

@media (max-width: 700px) {
    .product-media-panel {
        max-width: 100%;
        padding: 12px;
    }

    .product-media-panel .product-main-image {
        max-width: 100%;
        max-height: 380px;
        border-radius: 11px;
    }

    .product-media-panel .product-gallery {
        flex-wrap: nowrap;
        justify-content: flex-start;
        gap: 8px;
        max-width: 100%;
        margin-top: 10px;
        padding: 2px 1px 6px;
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .product-media-panel .product-gallery-thumb {
        flex-basis: 62px;
        width: 62px;
        height: 62px;
        border-radius: 9px;
    }
}

@media (max-width: 420px) {
    .product-media-panel .product-main-image {
        max-height: 320px;
    }

    .product-media-panel .product-gallery-thumb {
        flex-basis: 54px;
        width: 54px;
        height: 54px;
    }
}


/* ================================================================
   E-commerce colour and design thumbnail selectors
   ================================================================ */

.variant-picker {
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.variant-picker legend {
    display: flex;
    align-items: baseline;
    gap: 9px;
    width: 100%;
    margin: 0 0 9px;
    padding: 0;
    color: #321f24;
    font-size: 15px;
    font-weight: 600;
}

.variant-picker legend small {
    color: #88777b;
    font-size: 11px;
    font-weight: 400;
}

.variant-options {
    display: flex;
    align-items: stretch;
    gap: 11px;
    width: 100%;
    padding: 3px 2px 8px;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-snap-type: x proximity;
    scrollbar-width: thin;
    scrollbar-color: #cfb7bd transparent;
}

.variant-options::-webkit-scrollbar {
    height: 5px;
}

.variant-options::-webkit-scrollbar-thumb {
    background: #cfb7bd;
    border-radius: 999px;
}

.variant-option {
    position: relative;
    display: block;
    flex: 0 0 96px;
    min-width: 0;
    margin: 0;
    cursor: pointer;
    scroll-snap-align: start;
}

.variant-option>input {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: 0;
    overflow: hidden;
    opacity: 0;
    pointer-events: none;
}

.variant-option-card {
    position: relative;
    display: grid;
    gap: 7px;
    align-content: start;
    height: 100%;
    min-height: 124px;
    padding: 7px;
    color: #433137;
    text-align: center;
    background: #fffdf9;
    border: 1px solid #eadcdf;
    border-radius: 13px;
    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        transform .2s ease,
        background-color .2s ease;
}

.variant-option:hover .variant-option-card {
    border-color: #c6923e;
    transform: translateY(-2px);
}

.variant-option>input:checked+.variant-option-card {
    background: #fff8f8;
    border-color: #941332;
    box-shadow:
        0 0 0 2px rgba(148, 19, 50, .11),
        0 10px 24px rgba(78, 15, 33, .10);
    transform: translateY(-2px);
}

.variant-option>input:focus-visible+.variant-option-card {
    outline: 3px solid rgba(201, 150, 62, .34);
    outline-offset: 2px;
}

.variant-option-check {
    position: absolute;
    top: 4px;
    right: 4px;
    z-index: 2;
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    background: #921332;
    border: 2px solid #fff;
    border-radius: 50%;
    box-shadow: 0 4px 12px rgba(80, 10, 29, .22);
    opacity: 0;
    transform: scale(.72);
    transition:
        opacity .18s ease,
        transform .18s ease;
}

.variant-option>input:checked+.variant-option-card .variant-option-check {
    opacity: 1;
    transform: scale(1);
}

.variant-option-thumb {
    display: block;
    width: 80px;
    height: 76px;
    margin: 0 auto;
    overflow: hidden;
    background: #fff7e9;
    border: 1px solid #eadfd5;
    border-radius: 9px;
}

.variant-option-thumb img,
.variant-color-swatch {
    display: block;
    width: 100%;
    height: 100%;
}

.variant-option-thumb img {
    object-fit: cover;
}

.variant-color-swatch {
    position: relative;
    background:
        linear-gradient(145deg,
            rgba(255, 255, 255, .42),
            rgba(0, 0, 0, .05)),
        var(--variant-color);
}

.variant-color-swatch::after {
    content: "";
    position: absolute;
    inset: 9px;
    border: 1px solid rgba(255, 255, 255, .58);
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .10);
}

.variant-option-name {
    display: -webkit-box;
    min-width: 0;
    overflow: hidden;
    color: #4c343b;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.35;
    overflow-wrap: anywhere;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.variant-option-price {
    display: block;
    color: #8d1834;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.2;
}

@media (max-width: 700px) {
    .variant-picker legend {
        display: block;
        margin-bottom: 7px;
        font-size: 14px;
    }

    .variant-picker legend small {
        display: block;
        margin-top: 2px;
        font-size: 10px;
    }

    .variant-options {
        gap: 8px;
        margin-right: -3px;
        margin-left: -3px;
        padding-right: 3px;
        padding-left: 3px;
    }

    .variant-option {
        flex-basis: 84px;
    }

    .variant-option-card {
        min-height: 110px;
        padding: 6px;
        border-radius: 11px;
    }

    .variant-option-thumb {
        width: 70px;
        height: 66px;
        border-radius: 8px;
    }

    .variant-option-name {
        font-size: 10px;
    }
}

/* ================================================================
   Marketplace product layout - Flipkart-inspired information flow
   Ramki Cards branding and all existing storefront behaviour remain.
   ================================================================ */

.product-marketplace-page {
    min-height: 70vh;
    padding: 16px 0 64px;
    background: #f1f3f6;
}

.product-marketplace-page .product-marketplace-shell {
    width: min(1320px, 96%);
}

.product-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 10px;
    color: #777;
    font-size: 12px;
}

.product-breadcrumb a {
    color: #5f5f5f;
    transition: color .18s ease;
}

.product-breadcrumb a:hover {
    color: #8b1231;
}

.product-breadcrumb span {
    color: #aaa;
}

.product-marketplace-page .product-detail-grid {
    display: grid;
    grid-template-columns: minmax(430px, 46%) minmax(0, 1fr);
    gap: 12px;
    align-items: start;
}

.product-marketplace-page .product-marketplace-media,
.product-marketplace-page .marketplace-product-info {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 3px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
}

.product-marketplace-page .product-marketplace-media {
    position: sticky;
    top: 92px;
    width: 100%;
    max-width: none;
    padding: 16px;
}

.marketplace-gallery-layout {
    display: grid;
    grid-template-columns: 76px minmax(0, 1fr);
    gap: 12px;
    min-height: 500px;
}

.marketplace-gallery-layout.no-thumbnails {
    grid-template-columns: 1fr;
}

.marketplace-image-stage {
    display: grid;
    place-items: center;
    min-width: 0;
    min-height: 500px;
    padding: 14px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #eef0f2;
}

.product-marketplace-page .product-media-panel .product-main-image {
    width: 100%;
    max-width: 500px;
    height: 470px;
    max-height: none;
    margin: 0;
    object-fit: contain;
    background: #fff;
    border: 0;
    border-radius: 0;
}

.product-marketplace-page .product-media-panel .product-gallery {
    display: flex;
    flex-direction: column;
    flex-wrap: nowrap;
    justify-content: flex-start;
    gap: 8px;
    width: 76px;
    max-width: none;
    max-height: 500px;
    margin: 0;
    padding: 0 4px 0 0;
    overflow-x: hidden;
    overflow-y: auto;
    scrollbar-width: thin;
}

.product-marketplace-page .product-media-panel .product-gallery-thumb {
    flex: 0 0 68px;
    width: 68px;
    height: 68px;
    padding: 3px;
    object-fit: contain;
    background: #fff;
    border: 2px solid #e5e7eb;
    border-radius: 2px;
    opacity: 1;
    transform: none;
}

.product-marketplace-page .product-media-panel .product-gallery-thumb:hover,
.product-marketplace-page .product-media-panel .product-gallery-thumb.active {
    border-color: #8b1231;
    box-shadow: none;
    transform: none;
}

.marketplace-action-bar {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
}

.marketplace-action-bar.single {
    grid-template-columns: 1fr;
}

.marketplace-action {
    min-height: 56px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 12px 16px;
    color: #fff;
    border: 0;
    border-radius: 2px;
    font-family: "Poppins", sans-serif;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: .15px;
    cursor: pointer;
    transition: filter .18s ease, transform .18s ease;
}

.marketplace-action:hover {
    color: #fff;
    filter: brightness(.96);
    transform: translateY(-1px);
}

.marketplace-action.cart-action {
    background: #ff9f00;
}

.marketplace-action.login-action {
    background: #8b1231;
}

.marketplace-media-note {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-top: 14px;
    padding-top: 13px;
    color: #666;
    border-top: 1px solid #eee;
    font-size: 10px;
    text-align: center;
}

.marketplace-media-note span {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.product-marketplace-page .marketplace-product-info {
    min-width: 0;
    padding: 24px 30px 32px;
}

.marketplace-category-link {
    display: inline-block;
    margin-bottom: 7px;
    color: #878787;
    font-size: 12px;
    font-weight: 500;
}

.product-marketplace-page .marketplace-product-info h1 {
    margin: 0 0 5px;
    color: #212121;
    font-family: "Poppins", sans-serif;
    font-size: clamp(20px, 2.2vw, 26px);
    font-weight: 500;
    line-height: 1.42;
}

.marketplace-tamil-title {
    margin: 0 0 8px;
    color: #6b5960;
    font-family: "Noto Sans Tamil", "Noto Sans", sans-serif;
    font-size: 16px;
    font-weight: 600;
}

.marketplace-short-copy {
    margin: 3px 0 10px;
    color: #666;
    font-size: 13px;
    line-height: 1.6;
}

.marketplace-meta-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin: 8px 0 13px;
}

.marketplace-assurance,
.marketplace-stock {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 700;
}

.marketplace-assurance {
    color: #fff;
    background: #8b1231;
}

.marketplace-stock.in-stock {
    color: #137333;
    background: #e9f7ee;
}

.marketplace-stock.out-of-stock {
    color: #b3263d;
    background: #fdecef;
}

.marketplace-sku {
    color: #878787;
    font-size: 11px;
}

.marketplace-price-area {
    padding: 12px 0 17px;
    border-bottom: 1px solid #eee;
}

.marketplace-special-price {
    display: block;
    margin-bottom: 3px;
    color: #388e3c;
    font-size: 12px;
    font-weight: 700;
}

.marketplace-price-row {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 9px;
}

.product-marketplace-page .marketplace-price-row .detail-price {
    margin: 0;
    color: #212121;
    font-size: 30px;
    font-weight: 700;
}

.product-marketplace-page .marketplace-price-row .price-old {
    margin: 0;
    color: #878787;
    font-size: 15px;
}

.marketplace-discount {
    color: #388e3c;
    font-size: 14px;
    font-weight: 700;
}

.marketplace-offers {
    padding: 18px 0;
    border-bottom: 1px solid #eee;
}

.marketplace-section-title {
    margin: 0 0 12px;
    color: #212121;
    font-size: 15px;
    font-weight: 700;
}

.marketplace-offer-list {
    display: grid;
    gap: 9px;
}

.marketplace-offer-item {
    display: grid;
    grid-template-columns: 18px minmax(0, 1fr);
    gap: 8px;
    align-items: start;
    color: #333;
    font-size: 12px;
    line-height: 1.55;
}

.marketplace-offer-icon {
    color: #388e3c;
    font-size: 14px;
    font-weight: 800;
}

.marketplace-specs {
    display: grid;
    gap: 0;
    padding: 10px 0 4px;
    border-bottom: 1px solid #eee;
}

.marketplace-spec-row {
    display: grid;
    grid-template-columns: 135px minmax(0, 1fr);
    gap: 16px;
    padding: 9px 0;
    font-size: 12px;
    line-height: 1.55;
}

.marketplace-spec-row>span:first-child {
    color: #878787;
    font-weight: 500;
}

.marketplace-spec-row>span:last-child {
    color: #333;
    font-weight: 500;
}

.marketplace-product-options {
    padding: 18px 0 4px;
    border-bottom: 1px solid #eee;
}

.marketplace-product-options-head {
    margin-bottom: 10px;
}

.marketplace-product-options-head .marketplace-section-title {
    margin: 0;
}

.marketplace-product-options .variant-picker {
    margin: 0 0 12px;
}

.marketplace-product-options .variant-picker:last-child {
    margin-bottom: 12px;
}

.marketplace-product-options .variant-option-card {
    border-radius: 3px;
    box-shadow: none;
}

.marketplace-product-options .variant-option-thumb {
    border-radius: 2px;
}

.marketplace-description-block {
    padding: 18px 0 2px;
}

.product-marketplace-page .marketplace-description-block .product-description {
    color: #4d4d4d;
    font-size: 13px;
    line-height: 1.8;
}

.product-marketplace-page .marketplace-purchase-box {
    margin-top: 20px;
    padding: 22px 0 4px;
    background: #fff;
    border: 0;
    border-top: 1px solid #e5e7eb;
}

.product-marketplace-page .marketplace-purchase-box>h3 {
    margin-bottom: 16px;
    color: #212121;
    font-size: 17px;
}

.product-marketplace-page .marketplace-purchase-box .purchase-grid {
    gap: 15px;
}

.product-marketplace-page .marketplace-purchase-box input,
.product-marketplace-page .marketplace-purchase-box textarea {
    background: #fff;
    border-color: #d9d9d9;
    border-radius: 3px;
}

.product-marketplace-page .marketplace-purchase-box input:focus,
.product-marketplace-page .marketplace-purchase-box textarea:focus {
    border-color: #8b1231;
    box-shadow: 0 0 0 3px rgba(139, 18, 49, .08);
}

.product-marketplace-page .marketplace-purchase-box .variant-option-card {
    border-radius: 3px;
    box-shadow: none;
}

.product-marketplace-page .marketplace-purchase-box .variant-option-thumb {
    border-radius: 2px;
}

.product-marketplace-page .marketplace-cart-form>.submit-btn[data-add-button] {
    min-height: 48px;
    margin-top: 4px;
    background: #ff9f00;
    border-radius: 2px;
}

.product-marketplace-page .marketplace-enquiry-box>.submit-btn {
    min-height: 48px;
    margin-top: 4px;
    background: #fb641b;
    border-radius: 2px;
}

@media (max-width: 1050px) {
    .product-marketplace-page .product-detail-grid {
        grid-template-columns: 1fr;
    }

    .product-marketplace-page .product-marketplace-media {
        position: static;
    }

    .marketplace-gallery-layout {
        min-height: 430px;
    }

    .marketplace-image-stage {
        min-height: 430px;
    }

    .product-marketplace-page .product-media-panel .product-main-image {
        height: 410px;
    }
}

@media (max-width: 700px) {
    .product-marketplace-page {
        padding: 8px 0 34px;
    }

    .product-marketplace-page .product-marketplace-shell {
        width: min(100%, 100%);
    }

    .product-breadcrumb {
        padding: 0 12px;
        overflow: hidden;
        white-space: nowrap;
    }

    .product-marketplace-page .product-detail-grid {
        gap: 8px;
    }

    .product-marketplace-page .product-marketplace-media,
    .product-marketplace-page .marketplace-product-info {
        border-right: 0;
        border-left: 0;
        border-radius: 0;
    }

    .product-marketplace-page .product-marketplace-media {
        padding: 12px;
    }

    .marketplace-gallery-layout,
    .marketplace-gallery-layout.no-thumbnails {
        display: flex;
        flex-direction: column-reverse;
        gap: 8px;
        min-height: 0;
    }

    .marketplace-image-stage {
        min-height: 330px;
        padding: 8px;
    }

    .product-marketplace-page .product-media-panel .product-main-image {
        height: 330px;
        max-height: 330px;
    }

    .product-marketplace-page .product-media-panel .product-gallery {
        width: 100%;
        max-width: 100%;
        flex-direction: row;
        gap: 7px;
        margin: 0;
        padding: 2px 0 4px;
        overflow-x: auto;
        overflow-y: hidden;
    }

    .product-marketplace-page .product-media-panel .product-gallery-thumb {
        flex: 0 0 58px;
        width: 58px;
        height: 58px;
    }

    .marketplace-action {
        min-height: 52px;
        padding: 10px 8px;
        font-size: 12px;
    }

    .marketplace-media-note {
        grid-template-columns: 1fr;
        gap: 7px;
        text-align: left;
    }

    .marketplace-media-note span {
        justify-content: flex-start;
    }

    .product-marketplace-page .marketplace-product-info {
        padding: 20px 16px 28px;
    }

    .product-marketplace-page .marketplace-product-info h1 {
        font-size: 19px;
    }

    .product-marketplace-page .marketplace-price-row .detail-price {
        font-size: 27px;
    }

    .marketplace-spec-row {
        grid-template-columns: 105px minmax(0, 1fr);
        gap: 10px;
    }

    .product-marketplace-page .marketplace-purchase-box .purchase-grid {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 420px) {
    .marketplace-image-stage {
        min-height: 285px;
    }

    .product-marketplace-page .product-media-panel .product-main-image {
        height: 285px;
        max-height: 285px;
    }

    .marketplace-action-bar {
        gap: 7px;
    }

    .marketplace-action {
        font-size: 11px;
    }
}

/* ================================================================
   Ramki Cards premium product presentation
   Reference structure adapted to the maroon, antique-gold and ivory theme.
   ================================================================ */
.product-marketplace-page {
    padding: 22px 0 72px;
    background:
        radial-gradient(circle at 92% 4%, rgba(213, 166, 77, .13), transparent 28%),
        linear-gradient(180deg, #fffdf9 0%, #fbf5eb 100%);
}

.product-marketplace-page .product-marketplace-shell {
    width: min(1500px, calc(100% - 48px));
}

.product-breadcrumb {
    gap: 7px;
    margin-bottom: 16px;
    color: #8b7a70;
    font-size: 13px;
}

.product-breadcrumb a {
    color: #6f172b;
    font-weight: 600;
}

.product-breadcrumb a:hover { color: #c38b2f; }

.product-marketplace-page .product-detail-grid {
    grid-template-columns: minmax(520px, 1.08fr) minmax(430px, .92fr);
    gap: 28px;
}

.product-marketplace-page .product-marketplace-media,
.product-marketplace-page .marketplace-product-info {
    overflow: hidden;
    background: rgba(255, 255, 255, .94);
    border: 1px solid rgba(184, 132, 45, .25);
    border-radius: 22px;
    box-shadow: 0 22px 55px rgba(83, 39, 32, .09);
    animation: ramkiProductRise .62s cubic-bezier(.22, 1, .36, 1) both;
}

.product-marketplace-page .marketplace-product-info {
    animation-delay: .08s;
}

@keyframes ramkiProductRise {
    from { opacity: 0; transform: translate3d(0, 18px, 0); }
    to { opacity: 1; transform: none; }
}

.product-marketplace-page .product-marketplace-media {
    top: 105px;
    padding: 18px;
}

.marketplace-gallery-layout {
    grid-template-columns: 82px minmax(0, 1fr);
    gap: 16px;
    min-height: 610px;
}

.marketplace-image-stage {
    min-height: 610px;
    padding: 22px;
    background:
        radial-gradient(circle at 50% 42%, #fff 0%, #fffdf8 62%, #faf1e3 100%);
    border: 1px solid rgba(192, 139, 50, .18);
    border-radius: 16px;
}

.product-marketplace-page .product-media-panel .product-main-image {
    max-width: 620px;
    height: 570px;
    transition: opacity .18s ease, transform .28s ease;
}

.product-marketplace-page .product-media-panel .product-main-image.is-changing {
    opacity: .3;
    transform: scale(.985);
}

.product-marketplace-page .product-media-panel .product-gallery {
    width: 82px;
    max-height: 610px;
    gap: 10px;
    padding-right: 5px;
}

.product-marketplace-page .product-media-panel .product-gallery-thumb {
    flex-basis: 74px;
    width: 74px;
    height: 74px;
    padding: 4px;
    border: 1px solid rgba(132, 93, 34, .25);
    border-radius: 11px;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

.product-marketplace-page .product-media-panel .product-gallery-thumb:hover {
    border-color: #c69035;
    transform: translateY(-2px);
}

.product-marketplace-page .product-media-panel .product-gallery-thumb.active {
    border-color: #8f1231;
    box-shadow: 0 0 0 3px rgba(143, 18, 49, .10);
}

.marketplace-action-bar { margin-top: 16px; }

.marketplace-action {
    min-height: 56px;
    border-radius: 12px;
    letter-spacing: .35px;
    box-shadow: 0 12px 26px rgba(105, 24, 42, .15);
}

.marketplace-action.cart-action {
    color: #5e1325;
    background: linear-gradient(135deg, #e2b557, #c89030);
}

.marketplace-action.login-action {
    background: linear-gradient(135deg, #a5163b, #721126);
}

.marketplace-media-note {
    color: #746461;
    border-color: rgba(171, 122, 44, .18);
    font-size: 11px;
}

.product-marketplace-page .marketplace-product-info {
    padding: 34px 38px 38px;
}

.marketplace-product-kicker {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 11px;
}

.marketplace-category-link,
.marketplace-sku {
    margin: 0;
    color: #a16d22;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.15px;
    text-transform: uppercase;
}

.marketplace-kicker-divider { color: #d2ad70; }

.product-marketplace-page .marketplace-product-info h1 {
    margin-bottom: 8px;
    color: #681126;
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(28px, 3vw, 42px);
    font-weight: 700;
    line-height: 1.14;
}

.marketplace-tamil-title { color: #8a3146; }

.marketplace-short-copy {
    max-width: 740px;
    margin-top: 12px;
    color: #6f5e5a;
    font-size: 14px;
    line-height: 1.75;
}

.marketplace-meta-row { margin: 14px 0 8px; }

.marketplace-assurance,
.marketplace-stock {
    padding: 6px 10px;
    border-radius: 999px;
}

.marketplace-assurance {
    color: #661226;
    background: #f1d494;
}

.marketplace-price-area {
    padding: 18px 0;
    border-color: rgba(134, 87, 33, .16);
}

.marketplace-special-price { color: #8f1231; }

.product-marketplace-page .marketplace-price-row .detail-price {
    color: #8f1231;
    font-family: "Playfair Display", Georgia, serif;
    font-size: 34px;
}

.marketplace-discount { color: #ad7621; }

.marketplace-offers {
    margin: 14px 0 0;
    padding: 15px 17px;
    background: #fff9ef;
    border: 1px solid rgba(190, 135, 42, .18);
    border-radius: 14px;
}

.marketplace-section-title {
    color: #4b2029;
    font-size: 16px;
}

.marketplace-offer-icon { color: #b17c27; }

.marketplace-product-options {
    padding: 22px 0 12px;
    border-color: rgba(134, 87, 33, .16);
}

.marketplace-product-options-head { margin-bottom: 15px; }

.variant-picker legend {
    margin-bottom: 12px;
    color: #4d2630;
    font-size: 15px;
    font-weight: 700;
}

.variant-options {
    gap: 15px;
    padding: 4px 4px 12px;
    scrollbar-color: #d0a75d transparent;
}

/* Round colour choices inspired by the reference, in Ramki styling. */
.colour-variant-picker .variant-option {
    flex: 0 0 82px;
}

.colour-variant-picker .variant-option-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    min-height: 0;
    padding: 2px;
    background: transparent;
    border: 0;
    border-radius: 0;
}

.colour-variant-picker .variant-option:hover .variant-option-card,
.colour-variant-picker .variant-option>input:checked+.variant-option-card {
    background: transparent;
    border: 0;
    box-shadow: none;
    transform: translateY(-2px);
}

.colour-variant-picker .variant-option-thumb {
    width: 70px;
    height: 70px;
    padding: 4px;
    background: #fff;
    border: 2px solid #e3d4bd;
    border-radius: 50%;
    box-shadow: 0 5px 14px rgba(74, 35, 28, .08);
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

.colour-variant-picker .variant-option-thumb img,
.colour-variant-picker .variant-color-swatch {
    border-radius: 50%;
}

.colour-variant-picker .variant-option:hover .variant-option-thumb {
    border-color: #c38b2f;
    transform: scale(1.035);
}

.colour-variant-picker .variant-option>input:checked+.variant-option-card .variant-option-thumb {
    border-color: #8f1231;
    box-shadow:
        0 0 0 3px #f1d494,
        0 8px 20px rgba(93, 18, 38, .16);
}

.colour-variant-picker .variant-option-check {
    top: -1px;
    right: 4px;
    background: #8f1231;
    border-color: #f4d897;
}

.colour-variant-picker .variant-option-name {
    display: block;
    max-width: 82px;
    color: #50333a;
    font-size: 10px;
    line-height: 1.25;
    white-space: normal;
}

.colour-variant-picker .variant-option-price {
    color: #9a6720;
    font-size: 9px;
}

.design-variant-picker { margin-top: 11px !important; }

.design-variant-picker .variant-option { flex-basis: 100px; }

.design-variant-picker .variant-option-card {
    min-height: 122px;
    background: #fffdf8;
    border-color: #e8d9c3;
    border-radius: 12px;
}

.design-variant-picker .variant-option-thumb {
    width: 82px;
    height: 74px;
    border-radius: 8px;
}

.design-variant-picker .variant-option>input:checked+.variant-option-card {
    background: #fff8f3;
    border-color: #8f1231;
    box-shadow: 0 0 0 2px rgba(143, 18, 49, .10);
}

.marketplace-product-tabs {
    margin-top: 22px;
    border: 1px solid rgba(156, 105, 39, .18);
    border-radius: 14px;
    overflow: hidden;
}

.marketplace-tab-list {
    display: flex;
    overflow-x: auto;
    background: #f8efe1;
    scrollbar-width: thin;
}

.marketplace-tab {
    flex: 0 0 auto;
    min-height: 50px;
    padding: 12px 17px;
    color: #6c5352;
    font: inherit;
    font-size: 12px;
    font-weight: 700;
    background: transparent;
    border: 0;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    white-space: nowrap;
}

.marketplace-tab:hover { color: #8f1231; }

.marketplace-tab.active {
    color: #7d112c;
    background: #fffdf9;
    border-bottom-color: #c69235;
}

.marketplace-tab-panel {
    min-height: 116px;
    padding: 20px;
    color: #5f4b4b;
    font-size: 13px;
    line-height: 1.75;
}

.marketplace-tab-panel p { margin: 0 0 8px; }
.marketplace-tab-panel p:last-child { margin-bottom: 0; }

.marketplace-tab-specs {
    display: grid;
    grid-template-columns: minmax(120px, .45fr) minmax(0, 1fr);
    gap: 9px 18px;
}

.marketplace-tab-specs span { color: #907d75; }
.marketplace-tab-specs strong { color: #4f3037; }

.product-marketplace-page .marketplace-purchase-box {
    margin-top: 24px;
    padding: 22px;
    background: #fffaf2;
    border: 1px solid rgba(179, 124, 40, .2);
    border-radius: 14px;
}

.product-marketplace-page .marketplace-purchase-box input,
.product-marketplace-page .marketplace-purchase-box textarea {
    border-color: #dfcfb9;
    border-radius: 9px;
}

.product-marketplace-page .marketplace-cart-form>.submit-btn[data-add-button],
.product-marketplace-page .marketplace-enquiry-box>.submit-btn {
    min-height: 50px;
    color: #fff;
    background: linear-gradient(135deg, #a4163b, #741126);
    border-radius: 10px;
    box-shadow: 0 11px 25px rgba(111, 16, 39, .18);
}

@media (max-width: 1180px) {
    .product-marketplace-page .product-detail-grid {
        grid-template-columns: minmax(440px, 1fr) minmax(390px, .95fr);
        gap: 20px;
    }

    .marketplace-gallery-layout,
    .marketplace-image-stage { min-height: 520px; }

    .product-marketplace-page .product-media-panel .product-main-image { height: 480px; }
}

@media (max-width: 960px) {
    .product-marketplace-page .product-marketplace-shell {
        width: min(100% - 28px, 760px);
    }

    .product-marketplace-page .product-detail-grid { grid-template-columns: 1fr; }
    .product-marketplace-page .product-marketplace-media { position: static; }
}

@media (max-width: 700px) {
    .product-marketplace-page { padding-top: 10px; }

    .product-marketplace-page .product-marketplace-shell {
        width: 100%;
    }

    .product-marketplace-page .product-marketplace-media,
    .product-marketplace-page .marketplace-product-info {
        border-radius: 0;
    }

    .product-marketplace-page .marketplace-product-info { padding: 24px 17px 30px; }

    .marketplace-gallery-layout,
    .marketplace-gallery-layout.no-thumbnails {
        flex-direction: column;
    }

    .product-marketplace-page .product-media-panel .product-gallery {
        order: 2;
    }

    .marketplace-image-stage {
        order: 1;
        min-height: 350px;
        border-radius: 12px;
    }

    .product-marketplace-page .product-media-panel .product-main-image {
        height: 330px;
        max-height: 330px;
    }

    .product-marketplace-page .marketplace-product-info h1 { font-size: 28px; }

    .marketplace-offers { margin-right: -2px; margin-left: -2px; }

    .marketplace-tab-panel { padding: 17px; }

    .product-marketplace-page .marketplace-purchase-box {
        margin-right: -4px;
        margin-left: -4px;
        padding: 18px 14px;
    }
}

@media (max-width: 420px) {
    .marketplace-image-stage { min-height: 305px; }

    .product-marketplace-page .product-media-panel .product-main-image {
        height: 285px;
        max-height: 285px;
    }

    .colour-variant-picker .variant-option { flex-basis: 74px; }

    .colour-variant-picker .variant-option-thumb {
        width: 62px;
        height: 62px;
    }

    .marketplace-tab { padding-right: 14px; padding-left: 14px; }

    .marketplace-tab-specs {
        grid-template-columns: 1fr;
        gap: 2px;
    }

    .marketplace-tab-specs strong { margin-bottom: 8px; }
}

@media (prefers-reduced-motion: reduce) {
    .product-enquiry-toast {
        transition: none;
    }

    .product-enquiry-toast-progress::after {
        animation: none;
    }

    .product-marketplace-page .product-marketplace-media,
    .product-marketplace-page .marketplace-product-info,
    .product-marketplace-page .product-media-panel .product-main-image {
        animation: none;
        transition: none;
    }
}
</style>

<div class="product-enquiry-toast" id="productEnquiryToast" role="status" aria-live="polite" aria-atomic="true">
    <span class="product-enquiry-toast-icon" id="productEnquiryToastIcon" aria-hidden="true">✓</span>

    <div class="product-enquiry-toast-copy">
        <strong id="productEnquiryToastTitle">
            Enquiry submitted successfully
        </strong>
        <p id="productEnquiryToastText"></p>
    </div>

    <button type="button" class="product-enquiry-toast-close" id="productEnquiryToastClose"
        aria-label="Close notification">×</button>

    <span class="product-enquiry-toast-progress" aria-hidden="true"></span>
</div>

<main class="store-page product-marketplace-page">
    <div class="container product-marketplace-shell">
        <nav class="product-breadcrumb" aria-label="Breadcrumb">
            <a href="index.php">Home</a>
            <span aria-hidden="true">›</span>
            <a href="products.php">Products</a>
            <span aria-hidden="true">›</span>
            <a href="products.php?category=<?= rawurlencode((string)$product['category_slug']); ?>">
                <?= sf_e($product['category_name']); ?>
            </a>
        </nav>

        <div class="product-detail-grid">
            <section class="product-media-panel product-marketplace-media">
                <div class="marketplace-gallery-layout<?= count($gallery) > 1 ? '' : ' no-thumbnails'; ?>">
                    <?php if (count($gallery) > 1): ?>
                    <div class="product-gallery" aria-label="Product images">
                        <?php foreach ($gallery as $imageIndex => $image): ?>
                        <img src="<?= sf_e(sf_media_path(
                        $image['image_path'] ?? '',
                        $mainImage
                    )); ?>" alt="<?= sf_e(
                        $image['alt_text']
                        ?: $product['product_name']
                    ); ?>" class="product-gallery-thumb<?= $imageIndex === 0 ? ' active' : ''; ?>" loading="lazy"
                            data-gallery-image>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="marketplace-image-stage">
                        <img src="<?= sf_e($mainImage); ?>" alt="<?= sf_e($product['product_name']); ?>"
                            class="product-main-image" id="mainProductImage" data-default-src="<?= sf_e($mainImage); ?>"
                            data-default-alt="<?= sf_e($product['product_name']); ?>">
                    </div>
                </div>

                <?php if ($hasCheckout): ?>
                <div class="marketplace-action-bar single">
                    <?php if ($purchaseLoggedIn): ?>
                    <a href="#buy" class="marketplace-action cart-action">
                        <span aria-hidden="true">🛒</span>
                        ADD TO CART
                    </a>
                    <?php else: ?>
                    <a href="<?= sf_e($productLoginUrl); ?>" class="marketplace-action login-action">
                        <span aria-hidden="true">🔐</span>
                        LOGIN TO BUY
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="marketplace-media-note" aria-label="Product service highlights">
                    <span>✓ Customisable</span>
                    <span>🔒 Secure order</span>
                    <span>☎ Order support</span>
                </div>
            </section>

            <section class="product-detail-copy marketplace-product-info">
                <div class="marketplace-product-kicker">
                    <a class="marketplace-category-link"
                        href="products.php?category=<?= rawurlencode((string)$product['category_slug']); ?>">
                        <?= sf_e($product['category_name']); ?>
                    </a>
                    <?php if (!empty($product['sku'])): ?>
                    <span class="marketplace-kicker-divider" aria-hidden="true">•</span>
                    <span class="marketplace-sku">SKU: <?= sf_e($product['sku']); ?></span>
                    <?php endif; ?>
                </div>

                <h1><?= sf_e($product['product_name']); ?></h1>

                <?php if (!empty($product['product_name_tamil'])): ?>
                <div class="marketplace-tamil-title" lang="ta">
                    <?= sf_e($product['product_name_tamil']); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($product['short_description'])): ?>
                <p class="marketplace-short-copy">
                    <?= sf_e($product['short_description']); ?>
                </p>
                <?php endif; ?>

                <div class="marketplace-meta-row">
                    <span class="marketplace-assurance">✓ Ramki Cards</span>
                    <span class="marketplace-stock <?= $inStock ? 'in-stock' : 'out-of-stock'; ?>">
                        <?= $inStock ? 'In Stock' : 'Currently Unavailable'; ?>
                    </span>
                </div>

                <div class="marketplace-price-area">
                    <?php if ($discountPercent > 0): ?>
                    <span class="marketplace-special-price">Special price</span>
                    <?php endif; ?>

                    <div class="marketplace-price-row">
                        <div class="detail-price" id="marketplaceCurrentPrice"
                            data-base-price="<?= sf_e((string)$effectivePrice); ?>">
                            <?= sf_e(sf_money($effectivePrice)); ?>
                        </div>

                        <?php if ($savingsAmount > 0): ?>
                        <span class="price-old">
                            <?= sf_e(sf_money($basePrice)); ?>
                        </span>
                        <span class="marketplace-discount">
                            <?= $discountPercent; ?>% off
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="marketplace-offers">
                    <h2 class="marketplace-section-title">
                        <?= $savingsAmount > 0 ? 'Available offers & services' : 'Product services'; ?>
                    </h2>
                    <div class="marketplace-offer-list">
                        <?php if ($savingsAmount > 0): ?>
                        <div class="marketplace-offer-item">
                            <span class="marketplace-offer-icon">🏷</span>
                            <span>
                                <strong>Special price:</strong>
                                Save <?= sf_e(sf_money($savingsAmount)); ?> on this product.
                            </span>
                        </div>
                        <?php endif; ?>

                        <div class="marketplace-offer-item">
                            <span class="marketplace-offer-icon">✓</span>
                            <span>
                                <strong>Bulk order:</strong>
                                Minimum order quantity is <?= $minimumOrderQty; ?> pieces.
                            </span>
                        </div>

                    </div>
                </div>

                <?php if ($colors || $designs): ?>
                <section class="marketplace-product-options" aria-labelledby="productOptionsTitle">
                    <div class="marketplace-product-options-head">
                        <h2 class="marketplace-section-title" id="productOptionsTitle">Choose Your Style</h2>
                    </div>

                    <?php if ($colors): ?>
                    <fieldset class="variant-picker colour-variant-picker">
                        <legend>Colour variants</legend>

                        <div class="variant-options" role="group" aria-label="Available product colours">
                            <?php foreach ($colors as $color): ?>
                            <?php
                  $colorImagePath = trim(
                      (string)($color['image_path'] ?? '')
                  );

                  $colorPreviewImage = $colorImagePath !== ''
                      ? sf_media_path($colorImagePath, $mainImage)
                      : $mainImage;

                  $colorCode = trim(
                      (string)($color['color_code'] ?? '')
                  );

                  if (!preg_match('/^#?[0-9a-f]{3,8}$/i', $colorCode)) {
                      $colorCode = '#eadfd1';
                  } elseif (!str_starts_with($colorCode, '#')) {
                      $colorCode = '#' . $colorCode;
                  }
                  ?>

                            <label class="variant-option">
                                <input type="checkbox" value="<?= (int)$color['id']; ?>" data-product-variant-choice
                                    data-variant-kind="colour" data-variant-field="color_variant_id"
                                    data-variant-preview="<?= sf_e($colorPreviewImage); ?>"
                                    data-variant-price="<?= sf_e((string)(float)$color['price_adjustment']); ?>"
                                    data-variant-label="<?= sf_e($color['color_name']); ?>">

                                <span class="variant-option-card">
                                    <span class="variant-option-check">✓</span>

                                    <span class="variant-option-thumb">
                                        <?php if ($colorImagePath !== ''): ?>
                                        <img src="<?= sf_e($colorPreviewImage); ?>"
                                            alt="<?= sf_e($color['color_name']); ?>" loading="lazy">
                                        <?php else: ?>
                                        <span class="variant-color-swatch"
                                            style="--variant-color: <?= sf_e($colorCode); ?>" aria-hidden="true"></span>
                                        <?php endif; ?>
                                    </span>

                                    <span class="variant-option-name">
                                        <?= sf_e($color['color_name']); ?>
                                    </span>

                                    <?php if ((float)$color['price_adjustment'] !== 0.0): ?>
                                    <small class="variant-option-price">
                                        <?= (float)$color['price_adjustment'] > 0 ? '+' : ''; ?><?= sf_e(
                                            sf_money($color['price_adjustment'])
                                        ); ?>
                                    </small>
                                    <?php endif; ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <?php endif; ?>

                    <?php if ($designs): ?>
                    <fieldset class="variant-picker design-variant-picker">
                        <legend>Design variants</legend>

                        <div class="variant-options" role="group" aria-label="Available product designs">
                            <?php foreach ($designs as $design): ?>
                            <?php
                  $designImagePath = trim(
                      (string)($design['image_path'] ?? '')
                  );

                  $designPreviewImage = $designImagePath !== ''
                      ? sf_media_path($designImagePath, $mainImage)
                      : $mainImage;
                  ?>

                            <label class="variant-option">
                                <input type="checkbox" value="<?= (int)$design['id']; ?>" data-product-variant-choice
                                    data-variant-kind="design" data-variant-field="design_variant_id"
                                    data-variant-preview="<?= sf_e($designPreviewImage); ?>"
                                    data-variant-price="<?= sf_e((string)(float)$design['price_adjustment']); ?>"
                                    data-variant-label="<?= sf_e($design['design_name']); ?>">

                                <span class="variant-option-card">
                                    <span class="variant-option-check">✓</span>

                                    <span class="variant-option-thumb">
                                        <img src="<?= sf_e($designPreviewImage); ?>"
                                            alt="<?= sf_e($design['design_name']); ?>" loading="lazy">
                                    </span>

                                    <span class="variant-option-name">
                                        <?= sf_e($design['design_name']); ?>
                                    </span>

                                    <?php if ((float)$design['price_adjustment'] !== 0.0): ?>
                                    <small class="variant-option-price">
                                        <?= (float)$design['price_adjustment'] > 0 ? '+' : ''; ?><?= sf_e(
                                            sf_money($design['price_adjustment'])
                                        ); ?>
                                    </small>
                                    <?php endif; ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <section class="marketplace-product-tabs" aria-label="Product information">
                    <div class="marketplace-tab-list" role="tablist" aria-label="Product information tabs">
                        <button class="marketplace-tab active" type="button" role="tab" aria-selected="true"
                            aria-controls="productTabDescription" id="productTabButtonDescription"
                            data-product-tab="description">Description</button>
                        <button class="marketplace-tab" type="button" role="tab" aria-selected="false"
                            aria-controls="productTabPrinting" id="productTabButtonPrinting"
                            data-product-tab="printing">Printing</button>
                        <button class="marketplace-tab" type="button" role="tab" aria-selected="false"
                            aria-controls="productTabShipping" id="productTabButtonShipping"
                            data-product-tab="shipping">Shipping</button>
                        <button class="marketplace-tab" type="button" role="tab" aria-selected="false"
                            aria-controls="productTabInformation" id="productTabButtonInformation"
                            data-product-tab="information">Additional Information</button>
                    </div>

                    <div class="marketplace-tab-panel active" id="productTabDescription" role="tabpanel"
                        aria-labelledby="productTabButtonDescription" data-product-tab-panel="description">
                        <div class="product-description">
                            <?= nl2br(sf_e(
                      $product['description']
                      ?: $product['short_description']
                      ?: 'Contact us for complete product details and customization options.'
                  )); ?>
                        </div>
                    </div>

                    <div class="marketplace-tab-panel" id="productTabPrinting" role="tabpanel"
                        aria-labelledby="productTabButtonPrinting" data-product-tab-panel="printing" hidden>
                        <p>Names, wording, language, colours and print finishing can be confirmed with our team before production.</p>
                        <?php if ($colors): ?>
                        <p><strong>Available colours:</strong>
                            <?= sf_e(implode(', ', array_column($colors, 'color_name'))); ?></p>
                        <?php endif; ?>
                        <?php if ($designs): ?>
                        <p><strong>Available designs:</strong>
                            <?= sf_e(implode(', ', array_column($designs, 'design_name'))); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="marketplace-tab-panel" id="productTabShipping" role="tabpanel"
                        aria-labelledby="productTabButtonShipping" data-product-tab-panel="shipping" hidden>
                        <p>Delivery availability and the expected dispatch date will be confirmed after quantity, customization and destination are reviewed.</p>
                    </div>

                    <div class="marketplace-tab-panel" id="productTabInformation" role="tabpanel"
                        aria-labelledby="productTabButtonInformation" data-product-tab-panel="information" hidden>
                        <div class="marketplace-tab-specs">
                            <span>Minimum order</span><strong><?= $minimumOrderQty; ?> pieces</strong>
                            <span>Quantity step</span><strong><?= $quantityStep; ?> pieces</strong>
                            <span>Availability</span><strong><?= $inStock ? 'Available for ordering' : 'Currently unavailable'; ?></strong>
                            <?php if (!empty($product['sku'])): ?>
                            <span>Product code</span><strong><?= sf_e($product['sku']); ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <?php if ($cartStatus === 'added'): ?>
                <div class="purchase-message success">
                    Product added to your cart.
                    <a href="cart.php">View cart</a>
                </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                <div class="purchase-message error">
                    <?= sf_e($error); ?>
                </div>
                <?php endif; ?>

                <?php if (in_array($mode, ['checkout', 'both'], true)): ?>
                <?php if ($purchaseLoggedIn): ?>
                <form action="add_to_cart.php" method="POST"
                    class="purchase-box marketplace-purchase-box marketplace-cart-form js-add-to-cart-form" id="buy">
                    <h3>Quantity & Customization</h3>

                    <?php if ($adminLoggedIn && !$customerLoggedIn): ?>
                    <div class="purchase-message success">
                        Administrator test mode is active. This cart belongs to the
                        current admin browser session.
                    </div>
                    <?php endif; ?>

                    <input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>">

                    <input type="hidden" name="product_id" value="<?= $productId; ?>">

                    <input type="hidden" name="return_url" value="<?= sf_e($productReturnUrl); ?>">

                    <?php if ($colors): ?>
                    <input type="hidden" name="color_variant_id" value="" data-selected-variant-kind="colour">
                    <?php endif; ?>

                    <?php if ($designs): ?>
                    <input type="hidden" name="design_variant_id" value="" data-selected-variant-kind="design">
                    <?php endif; ?>

                    <div class="purchase-grid">
                        <div>
                            <label for="buyQuantity">Quantity</label>
                            <input id="buyQuantity" type="number" name="quantity"
                                min="<?= (int)$product['minimum_order_qty']; ?>"
                                step="<?= (int)$product['quantity_step']; ?>"
                                value="<?= (int)$product['minimum_order_qty']; ?>" required>
                        </div>

                        <div class="full">
                            <label for="buyNotes">Customization notes</label>
                            <textarea id="buyNotes" name="notes" maxlength="1000"
                                placeholder="Names, language, colour, printing or delivery notes"></textarea>
                        </div>
                    </div>

                    <button class="submit-btn" type="submit" data-add-button>
                        Add to Cart
                    </button>
                </form>
                <?php else: ?>
                <div class="purchase-box marketplace-purchase-box" id="buy">
                    <h3>Buy / Add to Cart</h3>

                    <div class="login-required-card">
                        <span class="login-required-icon" aria-hidden="true">🔐</span>

                        <div>
                            <h3>Login to add this product</h3>
                            <p>
                                Product details and enquiries are available without login.
                                Sign in only when you are ready to add this product to
                                your cart.
                            </p>
                        </div>

                        <a class="primary-btn login-required-button" href="<?= sf_e($productLoginUrl); ?>">
                            Login & Continue →
                        </a>

                        <small>
                            After login, you will return to this product.
                        </small>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if (in_array($mode, ['enquiry', 'both'], true)): ?>
                <form action="submit_product_enquiry.php" method="POST"
                    class="purchase-box marketplace-purchase-box marketplace-enquiry-box js-product-enquiry-form"
                    id="enquiry">
                    <h3>Product Enquiry</h3>

                    <input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>">

                    <input type="hidden" name="product_id" value="<?= $productId; ?>">

                    <?php if ($colors): ?>
                    <input type="hidden" name="color_variant_id" value="" data-selected-variant-kind="colour">
                    <?php endif; ?>

                    <?php if ($designs): ?>
                    <input type="hidden" name="design_variant_id" value="" data-selected-variant-kind="design">
                    <?php endif; ?>

                    <div class="purchase-grid">
                        <div>
                            <label for="enquiryName">Name</label>
                            <input id="enquiryName" type="text" name="name" maxlength="150" required>
                        </div>

                        <div>
                            <label for="enquiryPhone">Mobile</label>
                            <input id="enquiryPhone" type="tel" name="mobile" pattern="[0-9]{10}" maxlength="10"
                                required>
                        </div>

                        <div class="full">
                            <label for="enquiryEmail">Email (Optional)</label>
                            <input id="enquiryEmail" type="email" name="email" maxlength="190">
                        </div>

                        <div>
                            <label for="enquiryQuantity">Required Quantity</label>
                            <input id="enquiryQuantity" type="number" name="quantity"
                                min="<?= (int)$product['minimum_order_qty']; ?>"
                                step="<?= (int)$product['quantity_step']; ?>"
                                value="<?= (int)$product['minimum_order_qty']; ?>" required>
                        </div>

                        <div class="full">
                            <label for="enquiryNotes">Requirements</label>
                            <textarea id="enquiryNotes" name="notes" maxlength="1500"
                                placeholder="Enter customization, event or delivery requirements"></textarea>
                        </div>
                    </div>

                    <button class="submit-btn" type="submit" data-enquiry-submit>
                        ENQUIRE NOW
                    </button>
                </form>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<script>
let productPreviewTimer = 0;

function setProductPreview(source, alternativeText) {
    const main = document.getElementById('mainProductImage');

    if (!main || !source) {
        return;
    }

    window.clearTimeout(productPreviewTimer);
    main.classList.add('is-changing');

    productPreviewTimer = window.setTimeout(() => {
        main.src = source;
        main.alt = alternativeText || main.dataset.defaultAlt || 'Product';

        window.requestAnimationFrame(() => {
            main.classList.remove('is-changing');
        });
    }, 90);
}

document.querySelectorAll('[data-gallery-image]').forEach(image => {
    image.addEventListener('click', () => {
        document.querySelectorAll('[data-gallery-image]').forEach(item => {
            item.classList.remove('active');
        });

        image.classList.add('active');
        setProductPreview(image.src, image.alt);
    });
});

const productVariantChoices = Array.from(
    document.querySelectorAll('[data-product-variant-choice]')
);

function syncSelectedVariant(kind) {
    const selected = productVariantChoices.find(choice =>
        choice.dataset.variantKind === kind && choice.checked
    );

    document.querySelectorAll('[data-selected-variant-kind]').forEach(hidden => {
        if (hidden.dataset.selectedVariantKind === kind) {
            hidden.value = selected ? selected.value : '';
        }
    });
}

function showVariantPreview(preferredChoice = null) {
    const main = document.getElementById('mainProductImage');

    if (!main) {
        return;
    }

    const selected = preferredChoice?.checked ?
        preferredChoice :
        productVariantChoices.find(choice => choice.checked);

    document.querySelectorAll('[data-gallery-image]').forEach(item => {
        item.classList.remove('active');
    });

    if (selected && selected.dataset.variantPreview) {
        const variantAlt = selected.dataset.variantLabel ?
            `${main.dataset.defaultAlt || 'Product'} - ${selected.dataset.variantLabel}` :
            (main.dataset.defaultAlt || 'Product');

        setProductPreview(selected.dataset.variantPreview, variantAlt);
        return;
    }

    setProductPreview(
        main.dataset.defaultSrc || main.src,
        main.dataset.defaultAlt || 'Product'
    );

    const firstGalleryImage = document.querySelector('[data-gallery-image]');
    firstGalleryImage?.classList.add('active');
}

function updateVariantPrice() {
    const price = document.getElementById('marketplaceCurrentPrice');

    if (!price) {
        return;
    }

    const basePrice = Number.parseFloat(price.dataset.basePrice || '0');
    const adjustment = productVariantChoices.reduce((total, choice) => {
        if (!choice.checked) {
            return total;
        }

        return total + Number.parseFloat(choice.dataset.variantPrice || '0');
    }, 0);

    price.textContent = new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Math.max(0, basePrice + adjustment));
}

productVariantChoices.forEach(choice => {
    choice.addEventListener('change', () => {
        const kind = choice.dataset.variantKind || '';

        if (choice.checked) {
            productVariantChoices.forEach(otherChoice => {
                if (
                    otherChoice !== choice &&
                    otherChoice.dataset.variantKind === kind
                ) {
                    otherChoice.checked = false;
                }
            });
        }

        syncSelectedVariant(kind);
        showVariantPreview(choice);
        updateVariantPrice();
    });
});

updateVariantPrice();

(() => {
    'use strict';

    const tabs = Array.from(document.querySelectorAll('[data-product-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-product-tab-panel]'));

    const activateTab = tab => {
        const target = tab.dataset.productTab;

        tabs.forEach(item => {
            const active = item === tab;
            item.classList.toggle('active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
            item.tabIndex = active ? 0 : -1;
        });

        panels.forEach(panel => {
            const active = panel.dataset.productTabPanel === target;
            panel.classList.toggle('active', active);
            panel.hidden = !active;
        });
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activateTab(tab));
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }

            event.preventDefault();
            let nextIndex = index;

            if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') nextIndex = 0;
            if (event.key === 'End') nextIndex = tabs.length - 1;

            activateTab(tabs[nextIndex]);
            tabs[nextIndex].focus();
        });
    });
})();

(() => {
    'use strict';

    const form = document.querySelector('.js-product-enquiry-form');
    const button = form?.querySelector('[data-enquiry-submit]');
    const toast = document.getElementById('productEnquiryToast');
    const toastIcon = document.getElementById(
        'productEnquiryToastIcon'
    );
    const toastTitle = document.getElementById(
        'productEnquiryToastTitle'
    );
    const toastText = document.getElementById(
        'productEnquiryToastText'
    );
    const toastClose = document.getElementById(
        'productEnquiryToastClose'
    );

    let toastTimer = 0;

    const closeToast = () => {
        if (!toast) {
            return;
        }

        window.clearTimeout(toastTimer);
        toast.classList.remove('show');
    };

    const showToast = (
        type,
        title,
        message
    ) => {
        if (!toast || !toastTitle || !toastText || !toastIcon) {
            return;
        }

        window.clearTimeout(toastTimer);

        toast.classList.toggle('error', type === 'error');
        toastIcon.textContent = type === 'error' ? '!' : '✓';
        toastTitle.textContent = title;
        toastText.textContent = message;

        toast.classList.remove('show');

        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                toast.classList.add('show');
            });
        });

        toastTimer = window.setTimeout(closeToast, 5500);
    };

    const parseJson = async response => {
        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch {
            throw new Error(
                text.trim() ||
                'The enquiry service returned an invalid response.'
            );
        }
    };

    form?.addEventListener('submit', async event => {
        event.preventDefault();

        if (!form.reportValidity()) {
            return;
        }

        const originalText =
            button?.textContent || 'ENQUIRE NOW';

        if (button) {
            button.disabled = true;
            button.textContent = 'Submitting...';
        }

        const controller = new AbortController();
        const requestTimeout = window.setTimeout(() => {
            controller.abort();
        }, 20000);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                signal: controller.signal
            });

            const result = await parseJson(response);

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || 'Unable to submit the enquiry.'
                );
            }

            const enquiryNumber =
                result.data?.enquiry_number || '';

            showToast(
                'success',
                'Enquiry submitted successfully',
                result.data?.message ||
                (
                    enquiryNumber ?
                    `Your enquiry ${enquiryNumber} has been received. Our team will contact you shortly.` :
                    'Your enquiry has been received. Our team will contact you shortly.'
                )
            );

            form.reset();

            Array.from(new Set(
                productVariantChoices.map(choice => choice.dataset.variantKind)
            )).forEach(kind => syncSelectedVariant(kind || ''));

            updateVariantPrice();

            const quantity = form.querySelector(
                'input[name="quantity"]'
            );

            if (quantity && quantity.defaultValue) {
                quantity.value = quantity.defaultValue;
            }
        } catch (error) {
            const message = error?.name === 'AbortError' ?
                'The request took too long. The button has been reset. Please check the enquiry list before submitting again.' :
                error.message || 'Please try again.';

            showToast(
                'error',
                'Unable to confirm enquiry',
                message
            );
        } finally {
            window.clearTimeout(requestTimeout);

            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    });

    toastClose?.addEventListener('click', closeToast);

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeToast();
        }
    });

    <?php if ($enquiryNumber !== ''): ?>
    showToast(
        'success',
        'Enquiry submitted successfully',
        <?= json_encode(
        $enquiryToastMessage !== ''
            ? $enquiryToastMessage
            : 'Your enquiry '
                . $enquiryNumber
                . ' has been received. Our team will contact you shortly.',
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    ); ?>
    );
    <?php endif; ?>

    <?php if ($error !== ''): ?>
    showToast(
        'error',
        'Unable to submit enquiry',
        <?= json_encode(
        $error,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    ); ?>
    );
    <?php endif; ?>

    const currentUrl = new URL(window.location.href);

    let shouldCleanUrl = false;

    if (currentUrl.searchParams.has('enquiry')) {
        currentUrl.searchParams.delete('enquiry');
        shouldCleanUrl = true;
    }

    if (currentUrl.searchParams.has('error')) {
        currentUrl.searchParams.delete('error');
        shouldCleanUrl = true;
    }

    if (shouldCleanUrl) {
        window.history.replaceState({},
            document.title,
            currentUrl.pathname +
            currentUrl.search +
            currentUrl.hash
        );
    }
})();
</script>

<?php require __DIR__ . '/includes/storefront-footer.php'; 
