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

$pageTitle = 'Products | '
    . sf_setting($pdo, 'company_name', 'Ramki Cards');

$topStripItems = [];
require __DIR__ . '/includes/storefront-header.php';
?>

<main class="store-page">
  <div class="container">
    <div class="section-title store-page-title">
      <div class="decor-line"><i></i></div>
      <span>Invitation catalogue</span>
      <h2>Our <em>Products</em></h2>
    </div>

    <div class="catalog-layout">
      <aside class="catalog-filter glass-card">
        <h3>Filter Products</h3>

        <form method="GET" action="products.php">
          <div class="filter-group">
            <label for="filterSearch">Search</label>
            <input
              id="filterSearch"
              type="search"
              name="q"
              value="<?= sf_e($search); ?>"
              placeholder="Name or SKU"
            >
          </div>

          <div class="filter-group">
            <label for="filterCategory">Category</label>
            <select id="filterCategory" name="category">
              <option value="">All Categories</option>

              <?php foreach ($categories as $category): ?>
                <option
                  value="<?= sf_e($category['slug']); ?>"
                  <?= $categorySlug === $category['slug']
                      ? 'selected'
                      : ''; ?>
                >
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
                <option
                  value="<?= (int)$range['id']; ?>"
                  <?= $rangeId === (int)$range['id']
                      ? 'selected'
                      : ''; ?>
                >
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
            <?php foreach ($products as $product): ?>
              <?php
              $effectivePrice = sf_effective_price($product);
              $mode = sf_purchase_mode($pdo, $product);
              $quickAdd = sf_quick_add_configuration($pdo, $product);
              $quickAddAvailable =
                  $quickAdd['available']
                  && sf_product_can_quick_add($product);
              ?>
              <article class="product-card glass-card">
                <?php if (
                    $effectivePrice < (float)$product['base_price']
                ): ?>
                  <span class="badge">Offer</span>
                <?php endif; ?>

                <a
                  href="product.php?slug=<?= rawurlencode(
                      (string)$product['slug']
                  ); ?>"
                >
                  <div class="product-img">
                    <img
                      class="product-photo"
                      src="<?= sf_e(sf_media_path(
                          $product['thumbnail_path'],
                          'banner.png'
                      )); ?>"
                      alt="<?= sf_e($product['product_name']); ?>"
                      loading="lazy"
                    >
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

                  <div
                    class="product-actions <?= $mode === 'both'
                        ? 'two'
                        : ''; ?>"
                  >
                    <?php if (in_array(
                        $mode,
                        ['checkout', 'both'],
                        true
                    )): ?>
                      <?php if ($quickAddAvailable): ?>
                        <form
                          action="add_to_cart.php"
                          method="POST"
                          class="quick-add-form js-add-to-cart-form"
                        >
                          <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= sf_e(sf_csrf_token()); ?>"
                          >
                          <input
                            type="hidden"
                            name="product_id"
                            value="<?= (int)$product['id']; ?>"
                          >
                          <input
                            type="hidden"
                            name="quantity"
                            value="<?= (int)$product['minimum_order_qty']; ?>"
                          >
                          <input
                            type="hidden"
                            name="color_variant_id"
                            value="<?= (int)$quickAdd['color_variant_id']; ?>"
                          >
                          <input
                            type="hidden"
                            name="design_variant_id"
                            value="<?= (int)$quickAdd['design_variant_id']; ?>"
                          >
                          <input
                            type="hidden"
                            name="return_url"
                            value="<?= sf_e(sf_current_return_url(
                                'products.php'
                            )); ?>"
                          >

                          <button
                            type="submit"
                            class="product-action-btn primary"
                            data-add-button
                          >
                            Add to Cart
                          </button>
                        </form>
                      <?php elseif (!sf_product_can_quick_add($product)): ?>
                        <button
                          type="button"
                          class="product-action-btn primary"
                          disabled
                        >
                          Out of Stock
                        </button>
                      <?php else: ?>
                        <a
                          class="product-action-btn primary"
                          href="product.php?slug=<?= rawurlencode(
                              (string)$product['slug']
                          ); ?>#buy"
                        >
                          Choose Options
                        </a>
                      <?php endif; ?>
                    <?php endif; ?>

                    <?php if (in_array(
                        $mode,
                        ['enquiry', 'both'],
                        true
                    )): ?>
                      <a
                        class="product-action-btn"
                        href="product.php?slug=<?= rawurlencode(
                            (string)$product['slug']
                        ); ?>#enquiry"
                      >Enquiry</a>
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

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
