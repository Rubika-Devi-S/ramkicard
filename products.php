<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$categorySlug = trim((string)($_GET['category'] ?? ''));
$rangeId = max(0, (int)($_GET['price_range'] ?? 0));
$search = trim((string)($_GET['q'] ?? ''));

$categories = sf_active_categories($pdo, 100);

$priceRanges = $pdo->query(
    "SELECT id, range_name, minimum_price, maximum_price
     FROM price_ranges
     WHERE status = 'active'
     ORDER BY sort_order, minimum_price"
)->fetchAll(PDO::FETCH_ASSOC);

$sql = "
    SELECT
        p.*,
        c.category_name,
        c.slug AS category_slug
    FROM products p
    INNER JOIN categories c ON c.id = p.category_id
    WHERE p.status = 'active'
      AND p.deleted_at IS NULL
      AND c.status = 'active'
      AND c.deleted_at IS NULL
";

$params = [];

if ($categorySlug !== '') {
    $sql .= " AND c.slug = :category_slug";
    $params['category_slug'] = $categorySlug;
}

if ($rangeId > 0) {
    $sql .= " AND p.price_range_id = :price_range_id";
    $params['price_range_id'] = $rangeId;
}

if ($search !== '') {
    $sql .= "
        AND (
            p.product_name LIKE :search
            OR p.sku LIKE :search
            OR p.short_description LIKE :search
        )
    ";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY p.is_featured DESC, p.updated_at DESC, p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$favouriteProductIds = [];
if (sf_customer_logged_in()) {
    $favouriteStmt = $pdo->prepare(
        "SELECT product_id
         FROM customer_favourites
         WHERE customer_id = :customer_id"
    );
    $favouriteStmt->execute(['customer_id' => sf_customer_id()]);
    $favouriteProductIds = array_fill_keys(
        array_map('intval', $favouriteStmt->fetchAll(PDO::FETCH_COLUMN)),
        true
    );
}

$pageTitle = 'Products | '
    . sf_setting($pdo, 'company_name', 'Ramki Cards');

$topStripItems = [];
require __DIR__ . '/includes/storefront-header.php';
?>

<style>
html.rk-motion-ready [data-rk-reveal] {
    opacity: 0;
    transform: translate3d(0, 24px, 0);
    transition: opacity .62s cubic-bezier(.22, 1, .36, 1),
                transform .62s cubic-bezier(.22, 1, .36, 1);
    transition-delay: var(--rk-delay, 0ms);
}
html.rk-motion-ready [data-rk-reveal="left"] { transform: translate3d(-26px, 0, 0); }
html.rk-motion-ready [data-rk-reveal].rk-visible { opacity: 1; transform: none; }
.product-card { position: relative; }
.rk-favourite-form { position: absolute; top: 12px; right: 12px; z-index: 5; margin: 0; }
.rk-favourite-button {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(126, 20, 47, .2);
    border-radius: 50%;
    background: rgba(255, 255, 255, .94);
    color: #7e142f;
    font: inherit;
    font-size: 1.35rem;
    line-height: 1;
    cursor: pointer;
    box-shadow: 0 8px 22px rgba(79, 20, 31, .13);
    transition: transform .2s ease, color .2s ease, background-color .2s ease;
}
.rk-favourite-button:hover { transform: translateY(-2px) scale(1.04); }
.rk-favourite-button[aria-pressed="true"] { color: #fff; background: #98133a; }
.rk-favourite-button:disabled { cursor: wait; opacity: .65; }
.rk-favourite-button.rk-pop { animation: rkHeartPop .34s ease; }
@keyframes rkHeartPop { 50% { transform: scale(1.22); } }
.rk-toast {
    position: fixed;
    right: 22px;
    bottom: 24px;
    z-index: 10000;
    max-width: min(360px, calc(100vw - 32px));
    padding: 13px 17px;
    border-radius: 12px;
    background: #25191b;
    color: #fff;
    box-shadow: 0 16px 38px rgba(20, 8, 11, .24);
    opacity: 0;
    transform: translate3d(0, 14px, 0);
    transition: opacity .2s ease, transform .2s ease;
}
.rk-toast.show { opacity: 1; transform: none; }
.rk-toast.error { background: #9d1738; }
@media (max-width: 640px) {
    .rk-favourite-form { top: 9px; right: 9px; }
    .rk-favourite-button { width: 38px; height: 38px; }
    .rk-toast { right: 16px; bottom: 18px; }
}
@media (prefers-reduced-motion: reduce) {
    html.rk-motion-ready [data-rk-reveal], .rk-favourite-button, .rk-toast {
        opacity: 1;
        transform: none;
        transition: none;
        animation: none;
    }
}
</style>
<script>
if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.classList.add('rk-motion-ready');
}
</script>

<main class="store-page">
    <div class="container">
        <div class="section-title store-page-title" data-rk-reveal>
            <div class="decor-line"><i></i></div>
            <span>Invitation catalogue</span>
            <h2>Our <em>Products</em></h2>
        </div>

        <div class="catalog-layout">
            <aside class="catalog-filter glass-card" data-rk-reveal="left">
                <h3>Filter Products</h3>

                <form method="GET" action="products.php">
                    <div class="filter-group">
                        <label for="filterSearch">Search</label>
                        <input id="filterSearch" type="search" name="q" value="<?= sf_e($search); ?>"
                            placeholder="Name or SKU">
                    </div>

                    <div class="filter-group">
                        <label for="filterCategory">Category</label>
                        <select id="filterCategory" name="category">
                            <option value="">All Categories</option>

                            <?php foreach ($categories as $category): ?>
                            <option value="<?= sf_e($category['slug']); ?>" <?= $categorySlug === $category['slug']
                      ? 'selected'
                      : ''; ?>>
                                <?= sf_e($category['category_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filterPrice">Price Range</label>
                        <select id="filterPrice" name="price_range">
                            <option value="0">All Prices</option>

                            <?php foreach ($priceRanges as $range): ?>
                            <option value="<?= (int)$range['id']; ?>" <?= $rangeId === (int)$range['id']
                      ? 'selected'
                      : ''; ?>>
                                <?= sf_e($range['range_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-actions">
                        <button class="submit-btn" type="submit">
                            Apply Filters
                        </button>

                        <a class="product-action-btn" href="products.php">
                            Clear Filters
                        </a>
                    </div>
                </form>
            </aside>

            <section>
                <?php if (!$products): ?>
                <div class="empty-state glass-card">
                    <h3>No products found</h3>
                    <p>
                        Change the filters or add active products from the
                        admin panel.
                    </p>
                </div>
                <?php else: ?>
                <div class="products-grid catalog-products">
                    <?php foreach ($products as $productIndex => $product): ?>
                    <?php
              $effectivePrice = sf_effective_price($product);
              $mode = sf_purchase_mode($pdo, $product);
              $quickAdd = sf_quick_add_configuration($pdo, $product);
                  $quickAddAvailable =
                      $quickAdd['available']
                      && sf_product_can_quick_add($product);
                  $isFavourite = isset(
                      $favouriteProductIds[(int)$product['id']]
                  );
              ?>
                    <article class="product-card glass-card" data-rk-reveal
                        style="--rk-delay: <?= (int)(($productIndex % 4) * 55); ?>ms">
                        <form action="toggle-favourite.php" method="POST"
                            class="rk-favourite-form js-favourite-form">
                            <input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>">
                            <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>">
                            <input type="hidden" name="return" value="<?= sf_e(sf_current_return_url('products.php')); ?>">
                            <button class="rk-favourite-button" type="submit"
                                aria-label="<?= $isFavourite ? 'Remove from favourites' : 'Add to favourites'; ?>"
                                aria-pressed="<?= $isFavourite ? 'true' : 'false'; ?>">
                                <span aria-hidden="true">♥</span>
                            </button>
                        </form>
                        <?php if (
                    $effectivePrice < (float)$product['base_price']
                ): ?>
                        <span class="badge">Offer</span>
                        <?php endif; ?>

                        <a href="product.php?slug=<?= rawurlencode(
                      (string)$product['slug']
                  ); ?>">
                            <div class="product-img">
                                <img class="product-photo" src="<?= sf_e(sf_media_path(
                          $product['thumbnail_path'],
                          'banner.png'
                      )); ?>" alt="<?= sf_e($product['product_name']); ?>" loading="lazy">
                            </div>
                        </a>

                        <div class="product-body">
                            <h3><?= sf_e($product['product_name']); ?></h3>

                            <?php if (!empty($product['product_name_tamil'])): ?>
                            <div class="product-name-tamil" lang="ta">
                                <?= sf_e($product['product_name_tamil']); ?>
                            </div>
                            <?php endif; ?>

                            <div class="price">
                                <?= sf_e(sf_money($effectivePrice)); ?>

                                <?php if (
                        $effectivePrice < (float)$product['base_price']
                    ): ?>
                                <span class="price-old">
                                    <?= sf_e(sf_money(
                            $product['base_price']
                        )); ?>
                                </span>
                                <?php endif; ?>
                            </div>

                            <div class="moq-note">
                                MOQ:
                                <?= (int)$product['minimum_order_qty']; ?>
                                · Step:
                                <?= (int)$product['quantity_step']; ?>
                            </div>

                            <div class="product-actions <?= $mode === 'both'
                        ? 'two'
                        : ''; ?>">
                                <?php if (in_array(
                        $mode,
                        ['checkout', 'both'],
                        true
                    )): ?>
                                <?php if ($quickAddAvailable): ?>
                                <form action="add_to_cart.php" method="POST" class="quick-add-form js-add-to-cart-form">
                                    <input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>">
                                    <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>">
                                    <input type="hidden" name="quantity"
                                        value="<?= (int)$product['minimum_order_qty']; ?>">
                                    <input type="hidden" name="color_variant_id"
                                        value="<?= (int)$quickAdd['color_variant_id']; ?>">
                                    <input type="hidden" name="design_variant_id"
                                        value="<?= (int)$quickAdd['design_variant_id']; ?>">
                                    <input type="hidden" name="return_url" value="<?= sf_e(sf_current_return_url(
                                'products.php'
                            )); ?>">

                                    <button type="submit" class="product-action-btn primary" data-add-button>
                                        Add to Cart
                                    </button>
                                </form>
                                <?php elseif (!sf_product_can_quick_add($product)): ?>
                                <button type="button" class="product-action-btn primary" disabled>
                                    Out of Stock
                                </button>
                                <?php else: ?>
                                <a class="product-action-btn primary" href="product.php?slug=<?= rawurlencode(
                              (string)$product['slug']
                          ); ?>#buy">
                                    Choose Options
                                </a>
                                <?php endif; ?>
                                <?php endif; ?>

                                <?php if (in_array(
                        $mode,
                        ['enquiry', 'both'],
                        true
                    )): ?>
                                <a class="product-action-btn" href="product.php?slug=<?= rawurlencode(
                            (string)$product['slug']
                        ); ?>#enquiry">Enquiry</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<div class="rk-toast" id="rkFavouriteToast" role="status" aria-live="polite"></div>

<script>
(() => {
    'use strict';

    const revealItems = document.querySelectorAll('[data-rk-reveal]');
    if (document.documentElement.classList.contains('rk-motion-ready')) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('rk-visible');
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -7% 0px', threshold: .08 });
        revealItems.forEach(item => observer.observe(item));
    } else {
        revealItems.forEach(item => item.classList.add('rk-visible'));
    }

    const toast = document.getElementById('rkFavouriteToast');
    let toastTimer;
    const showToast = (message, error = false) => {
        if (!toast) return;
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.classList.toggle('error', error);
        toast.classList.add('show');
        toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2400);
    };

    document.querySelectorAll('.js-favourite-form').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const button = form.querySelector('.rk-favourite-button');
            if (!button || button.disabled) return;
            button.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const result = await response.json();
                if (response.status === 401 && result.login_url) {
                    window.location.href = result.login_url;
                    return;
                }
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to update favourites.');
                }

                const active = Boolean(result.is_favourite);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
                button.setAttribute('aria-label', active ? 'Remove from favourites' : 'Add to favourites');
                button.classList.remove('rk-pop');
                void button.offsetWidth;
                button.classList.add('rk-pop');
                showToast(result.message);
            } catch (error) {
                showToast(error.message || 'Unable to update favourites.', true);
            } finally {
                button.disabled = false;
            }
        });
    });
})();
</script>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
