<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$canViewProduct =
    can_menu($pdo, 'product_list')
    || can_menu($pdo, 'products');

if (!$canViewProduct) {
    http_response_code(403);
    exit('Permission denied.');
}

function product_view_media_url(?string $path): string
{
    $path = trim((string)$path);

    if ($path === '') {
        return '../assets/images/banner.png';
    }

    if (
        preg_match('#^(?:https?:)?//#i', $path)
        || str_starts_with($path, 'data:')
        || str_starts_with($path, '/')
    ) {
        return $path;
    }

    return '../' . ltrim($path, './');
}

$productId = max(0, (int)($_GET['id'] ?? 0));

$productStmt = $pdo->prepare(
    "SELECT
        p.*,
        c.category_name,
        pr.range_name
     FROM products p
     INNER JOIN categories c
        ON c.id = p.category_id
     LEFT JOIN price_ranges pr
        ON pr.id = p.price_range_id
     WHERE p.id = :id
       AND p.deleted_at IS NULL
     LIMIT 1"
);
$productStmt->execute(['id' => $productId]);
$product = $productStmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}

$imageStmt = $pdo->prepare(
    "SELECT *
     FROM product_images
     WHERE product_id = :id
     ORDER BY sort_order, id"
);
$imageStmt->execute(['id' => $productId]);
$images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

$colorStmt = $pdo->prepare(
    "SELECT *
     FROM product_color_variants
     WHERE product_id = :id
     ORDER BY sort_order, id"
);
$colorStmt->execute(['id' => $productId]);
$colors = $colorStmt->fetchAll(PDO::FETCH_ASSOC);

$designStmt = $pdo->prepare(
    "SELECT *
     FROM product_design_variants
     WHERE product_id = :id
     ORDER BY sort_order, id"
);
$designStmt->execute(['id' => $productId]);
$designs = $designStmt->fetchAll(PDO::FETCH_ASSOC);

$mainImage = product_view_media_url(
    $product['thumbnail_path'] ?? ''
);

$effectivePrice = (
    $product['offer_price'] !== null
    && $product['offer_price'] !== ''
)
    ? (float)$product['offer_price']
    : (float)$product['base_price'];

$pageTitle = 'Product - ' . $product['product_name'];

require __DIR__ . '/includes/header.php';
?>

<style>
.admin-detail-page {
    --detail-surface:
        var(--ui-card-bg, var(--bs-body-bg, #ffffff));
    --detail-soft:
        var(--ui-card-header-bg, #fff8f3);
    --detail-text:
        var(--ui-text-main, var(--bs-body-color, #2d2421));
    --detail-muted:
        var(--ui-text-muted, #786d68);
    --detail-border:
        var(--ui-border-soft, rgba(139, 18, 49, 0.13));
    --detail-primary:
        var(--ui-brand-1, #8b1231);
    --detail-primary-dark:
        var(--ui-brand-2, #5d071d);
    --detail-gold:
        var(--ui-accent, #c9963e);
    color: var(--detail-text);
}

.detail-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 20px;
}

.detail-back-link,
.detail-action-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 41px;
    padding: 9px 14px;
    color: var(--detail-primary);
    font-size: 0.72rem;
    font-weight: 800;
    background: var(--detail-surface);
    border: 1px solid var(--detail-border);
    border-radius: 11px;
    text-decoration: none;
}

.detail-action-link.primary {
    color: #fff;
    background: linear-gradient(
        135deg,
        var(--detail-primary),
        var(--detail-primary-dark)
    );
    border-color: transparent;
}

.detail-page-banner {
    position: relative;
    margin-bottom: 21px;
    padding: 25px 27px;
    overflow: hidden;
    color: #fff;
    background:
        radial-gradient(
            circle at 88% 15%,
            rgba(255, 255, 255, 0.17),
            transparent 24%
        ),
        linear-gradient(
            135deg,
            var(--detail-primary-dark),
            var(--detail-primary)
        );
    border-radius: 21px;
    box-shadow: 0 18px 45px rgba(82, 14, 34, 0.2);
}

.detail-page-banner small {
    display: block;
    margin-bottom: 5px;
    color: #f0cd73;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 1.3px;
    text-transform: uppercase;
}

.detail-page-banner h1 {
    margin: 0 0 5px;
    color: #fff;
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(1.65rem, 3vw, 2.3rem);
    font-weight: 800;
}

.detail-page-banner p {
    margin: 0;
    color: rgba(255, 255, 255, 0.76);
    font-size: 0.8rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(290px, 0.8fr);
    gap: 19px;
    align-items: start;
}

.detail-card {
    overflow: hidden;
    background: var(--detail-surface);
    border: 1px solid var(--detail-border);
    border-radius: 18px;
    box-shadow: 0 13px 36px rgba(65, 27, 38, 0.075);
}

.detail-card + .detail-card {
    margin-top: 18px;
}

.detail-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 17px 19px;
    background: var(--detail-soft);
    border-bottom: 1px solid var(--detail-border);
}

.detail-card-header h2 {
    margin: 0;
    color: var(--detail-text);
    font-family: "Playfair Display", Georgia, serif;
    font-size: 1.08rem;
    font-weight: 800;
}

.detail-card-body {
    padding: 19px;
}

.detail-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.detail-info-item {
    min-width: 0;
    padding: 13px;
    background: var(--detail-soft);
    border: 1px solid var(--detail-border);
    border-radius: 12px;
}

.detail-info-item.full {
    grid-column: 1 / -1;
}

.detail-info-item span {
    display: block;
    margin-bottom: 5px;
    color: var(--detail-muted);
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}

.detail-info-item strong,
.detail-info-item p {
    margin: 0;
    color: var(--detail-text);
    font-size: 0.77rem;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.detail-product-list {
    display: grid;
    gap: 12px;
}

.detail-product-row {
    display: grid;
    grid-template-columns: 76px minmax(0, 1fr) auto;
    gap: 14px;
    align-items: center;
    padding: 13px;
    background: var(--detail-soft);
    border: 1px solid var(--detail-border);
    border-radius: 14px;
}

.detail-product-image {
    width: 76px;
    height: 76px;
    overflow: hidden;
    background: var(--detail-surface);
    border: 1px solid var(--detail-border);
    border-radius: 11px;
}

.detail-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.detail-product-copy {
    min-width: 0;
}

.detail-product-copy h3 {
    margin: 0 0 4px;
    color: var(--detail-primary);
    font-size: 0.82rem;
    font-weight: 800;
}

.detail-product-copy p {
    margin: 2px 0;
    color: var(--detail-muted);
    font-size: 0.67rem;
}

.detail-product-total {
    color: var(--detail-text);
    font-size: 0.82rem;
    font-weight: 800;
    text-align: right;
}

.detail-summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 10px 0;
    color: var(--detail-muted);
    font-size: 0.74rem;
    border-bottom: 1px solid var(--detail-border);
}

.detail-summary-row:last-child {
    border-bottom: 0;
}

.detail-summary-row.total {
    margin-top: 5px;
    color: var(--detail-primary);
    font-size: 0.94rem;
    font-weight: 800;
}

.detail-status-form label {
    display: block;
    margin-bottom: 6px;
    color: var(--detail-muted);
    font-size: 0.68rem;
    font-weight: 800;
}

.detail-status-form select,
.detail-status-form textarea {
    width: 100%;
    color: var(--detail-text);
    background: var(--detail-surface);
    border: 1px solid var(--detail-border);
    border-radius: 11px;
}

.detail-status-form textarea {
    min-height: 105px;
    resize: vertical;
}

.detail-submit-btn {
    width: 100%;
    min-height: 45px;
    margin-top: 13px;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
    background: linear-gradient(
        135deg,
        var(--detail-primary),
        var(--detail-primary-dark)
    );
    border: 0;
    border-radius: 11px;
}

.detail-history {
    display: grid;
    gap: 12px;
}

.detail-history-item {
    position: relative;
    padding-left: 21px;
}

.detail-history-item::before {
    content: '';
    position: absolute;
    top: 5px;
    left: 1px;
    width: 10px;
    height: 10px;
    background: var(--detail-gold);
    border-radius: 50%;
}

.detail-history-item::after {
    content: '';
    position: absolute;
    top: 16px;
    bottom: -14px;
    left: 5px;
    width: 1px;
    background: var(--detail-border);
}

.detail-history-item:last-child::after {
    display: none;
}

.detail-history-item strong {
    display: block;
    color: var(--detail-text);
    font-size: 0.72rem;
}

.detail-history-item span,
.detail-history-item p {
    display: block;
    margin: 3px 0 0;
    color: var(--detail-muted);
    font-size: 0.65rem;
}

@media (max-width: 991.98px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 575.98px) {
    .detail-topbar {
        align-items: stretch;
        flex-direction: column;
    }

    .detail-page-banner {
        padding: 21px 19px;
        border-radius: 17px;
    }

    .detail-info-grid {
        grid-template-columns: 1fr;
    }

    .detail-info-item.full {
        grid-column: auto;
    }

    .detail-product-row {
        grid-template-columns: 64px minmax(0, 1fr);
    }

    .detail-product-image {
        width: 64px;
        height: 64px;
    }

    .detail-product-total {
        grid-column: 1 / -1;
        text-align: left;
    }
}
</style>


<style>
.product-view-layout {
    display: grid;
    grid-template-columns: minmax(300px, 0.85fr) minmax(0, 1.35fr);
    gap: 20px;
    align-items: start;
}

.product-view-main-image {
    width: 100%;
    max-height: 490px;
    object-fit: cover;
    background: var(--detail-soft);
    border-radius: 14px;
}

.product-view-gallery {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin-top: 10px;
}

.product-view-gallery button {
    height: 72px;
    padding: 0;
    overflow: hidden;
    background: var(--detail-soft);
    border: 1px solid var(--detail-border);
    border-radius: 9px;
}

.product-view-gallery img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-title-tamil {
    margin-top: 5px;
    color: var(--detail-muted);
    font-size: 0.88rem;
    font-weight: 700;
}

.product-price-row {
    display: flex;
    align-items: baseline;
    gap: 11px;
    margin: 15px 0;
}

.product-current-price {
    color: var(--detail-primary);
    font-family: "Playfair Display", Georgia, serif;
    font-size: 1.75rem;
    font-weight: 800;
}

.product-old-price {
    color: var(--detail-muted);
    font-size: 0.83rem;
    text-decoration: line-through;
}

.product-description {
    color: var(--detail-muted);
    font-size: 0.76rem;
    line-height: 1.75;
}

.product-variant-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.product-variant-card {
    display: grid;
    grid-template-columns: 56px minmax(0, 1fr);
    gap: 11px;
    padding: 11px;
    background: var(--detail-soft);
    border: 1px solid var(--detail-border);
    border-radius: 12px;
}

.product-variant-image {
    width: 56px;
    height: 56px;
    overflow: hidden;
    background: var(--detail-surface);
    border-radius: 9px;
}

.product-variant-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-colour-swatch {
    width: 56px;
    height: 56px;
    border: 5px solid var(--detail-surface);
    border-radius: 9px;
    box-shadow: 0 0 0 1px var(--detail-border);
}

.product-variant-card strong {
    display: block;
    color: var(--detail-text);
    font-size: 0.75rem;
}

.product-variant-card span,
.product-variant-card p {
    display: block;
    margin: 3px 0 0;
    color: var(--detail-muted);
    font-size: 0.64rem;
}

@media (max-width: 991.98px) {
    .product-view-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 575.98px) {
    .product-variant-grid {
        grid-template-columns: 1fr;
    }

    .product-view-gallery {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
</style>

<div class="admin-detail-page">
    <div class="detail-topbar">
        <a href="products.php" class="detail-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Products
        </a>

        <div class="d-flex gap-2 flex-wrap">
            <a
                href="products.php?edit=<?= $productId; ?>"
                class="detail-action-link"
            >
                <i class="fa-solid fa-pen"></i>
                Edit Product
            </a>

            <a
                href="../product.php?slug=<?= rawurlencode(
                    (string)$product['slug']
                ); ?>"
                class="detail-action-link primary"
                target="_blank"
                rel="noopener"
            >
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                Public Product Page
            </a>
        </div>
    </div>

    <section class="detail-page-banner">
        <small>Separate product details page</small>
        <h1><?= e($product['product_name']); ?></h1>
        <p>
            <?= e($product['category_name']); ?>
            · SKU <?= e($product['sku'] ?: 'Not assigned'); ?>
        </p>
    </section>

    <div class="product-view-layout">
        <div>
            <section class="detail-card">
                <div class="detail-card-body">
                    <img
                        src="<?= e($mainImage); ?>"
                        alt="<?= e($product['product_name']); ?>"
                        class="product-view-main-image"
                        id="adminMainProductImage"
                    >

                    <?php if ($images): ?>
                        <div class="product-view-gallery">
                            <button
                                type="button"
                                data-admin-product-image="<?= e($mainImage); ?>"
                            >
                                <img
                                    src="<?= e($mainImage); ?>"
                                    alt=""
                                >
                            </button>

                            <?php foreach ($images as $image): ?>
                                <?php
                                $imageUrl = product_view_media_url(
                                    $image['image_path'] ?? ''
                                );
                                ?>
                                <button
                                    type="button"
                                    data-admin-product-image="<?= e(
                                        $imageUrl
                                    ); ?>"
                                >
                                    <img
                                        src="<?= e($imageUrl); ?>"
                                        alt="<?= e(
                                            $image['alt_text']
                                            ?: $product['product_name']
                                        ); ?>"
                                    >
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($colors): ?>
                <section class="detail-card">
                    <div class="detail-card-header">
                        <h2>Colour Variants</h2>
                    </div>

                    <div class="detail-card-body">
                        <div class="product-variant-grid">
                            <?php foreach ($colors as $color): ?>
                                <article class="product-variant-card">
                                    <?php if (!empty(
                                        $color['image_path']
                                    )): ?>
                                        <div class="product-variant-image">
                                            <img
                                                src="<?= e(
                                                    product_view_media_url(
                                                        $color['image_path']
                                                    )
                                                ); ?>"
                                                alt=""
                                            >
                                        </div>
                                    <?php else: ?>
                                        <div
                                            class="product-colour-swatch"
                                            style="background: <?= e(
                                                $color['color_code']
                                                ?: '#ddd'
                                            ); ?>"
                                        ></div>
                                    <?php endif; ?>

                                    <div>
                                        <strong>
                                            <?= e($color['color_name']); ?>
                                        </strong>
                                        <span>
                                            <?= e(
                                                $color['color_code']
                                                ?: 'No colour code'
                                            ); ?>
                                        </span>
                                        <p>
                                            Adjustment:
                                            ₹<?= e(number_format(
                                                (float)$color[
                                                    'price_adjustment'
                                                ],
                                                2
                                            )); ?>
                                        </p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <div>
            <section class="detail-card">
                <div class="detail-card-header">
                    <h2>Product Information</h2>
                </div>

                <div class="detail-card-body">
                    <h2 class="h4 mb-1">
                        <?= e($product['product_name']); ?>
                    </h2>

                    <?php if (!empty(
                        $product['product_name_tamil']
                    )): ?>
                        <div
                            class="product-title-tamil"
                            lang="ta"
                        >
                            <?= e($product['product_name_tamil']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="product-price-row">
                        <span class="product-current-price">
                            ₹<?= e(number_format(
                                $effectivePrice,
                                2
                            )); ?>
                        </span>

                        <?php if (
                            $effectivePrice
                            < (float)$product['base_price']
                        ): ?>
                            <span class="product-old-price">
                                ₹<?= e(number_format(
                                    (float)$product['base_price'],
                                    2
                                )); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="detail-info-grid mb-3">
                        <div class="detail-info-item">
                            <span>Status</span>
                            <strong>
                                <?= e(ucwords($product['status'])); ?>
                            </strong>
                        </div>

                        <div class="detail-info-item">
                            <span>Purchase Action</span>
                            <strong>
                                <?= e(ucwords(str_replace(
                                    '_',
                                    ' ',
                                    (string)$product['purchase_action']
                                ))); ?>
                            </strong>
                        </div>

                        <div class="detail-info-item">
                            <span>Minimum Quantity</span>
                            <strong>
                                <?= (int)$product['minimum_order_qty']; ?>
                            </strong>
                        </div>

                        <div class="detail-info-item">
                            <span>Quantity Step</span>
                            <strong>
                                <?= (int)$product['quantity_step']; ?>
                            </strong>
                        </div>

                        <div class="detail-info-item">
                            <span>Price Range</span>
                            <strong>
                                <?= e($product['range_name'] ?: '—'); ?>
                            </strong>
                        </div>

                        <div class="detail-info-item">
                            <span>Featured</span>
                            <strong>
                                <?= (int)$product['is_featured'] === 1
                                    ? 'Yes'
                                    : 'No'; ?>
                            </strong>
                        </div>
                    </div>

                    <div class="product-description">
                        <?= nl2br(e(
                            $product['description']
                            ?: $product['short_description']
                            ?: 'No product description available.'
                        )); ?>
                    </div>
                </div>
            </section>

            <?php if ($designs): ?>
                <section class="detail-card">
                    <div class="detail-card-header">
                        <h2>Design Variants</h2>
                    </div>

                    <div class="detail-card-body">
                        <div class="product-variant-grid">
                            <?php foreach ($designs as $design): ?>
                                <article class="product-variant-card">
                                    <div class="product-variant-image">
                                        <img
                                            src="<?= e(
                                                product_view_media_url(
                                                    $design['image_path']
                                                    ?? ''
                                                )
                                            ); ?>"
                                            alt=""
                                        >
                                    </div>

                                    <div>
                                        <strong>
                                            <?= e($design['design_name']); ?>
                                        </strong>
                                        <span>
                                            <?= e(
                                                $design['design_code']
                                                ?: 'No design code'
                                            ); ?>
                                        </span>

                                        <?php if (!empty(
                                            $design['description']
                                        )): ?>
                                            <p>
                                                <?= e(
                                                    $design['description']
                                                ); ?>
                                            </p>
                                        <?php endif; ?>

                                        <p>
                                            Adjustment:
                                            ₹<?= e(number_format(
                                                (float)$design[
                                                    'price_adjustment'
                                                ],
                                                2
                                            )); ?>
                                        </p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document
    .querySelectorAll('[data-admin-product-image]')
    .forEach(button => {
        button.addEventListener('click', () => {
            const mainImage = document.getElementById(
                'adminMainProductImage'
            );

            if (mainImage) {
                mainImage.src =
                    button.dataset.adminProductImage || mainImage.src;
            }
        });
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
