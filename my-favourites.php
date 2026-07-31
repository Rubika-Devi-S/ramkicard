<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';
sf_require_customer_login('login.php', 'my-favourites.php');

$stmt = $pdo->prepare(
    "SELECT p.*, c.category_name
     FROM customer_favourites f
     INNER JOIN products p ON p.id = f.product_id
     INNER JOIN categories c ON c.id = p.category_id
     WHERE f.customer_id = :customer_id
       AND p.status = 'active'
       AND p.deleted_at IS NULL
       AND c.status = 'active'
       AND c.deleted_at IS NULL
     ORDER BY f.created_at DESC"
);
$stmt->execute(['customer_id' => sf_customer_id()]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Favourites';
$topStripItems = [];
$storefrontBase = '';
require __DIR__ . '/includes/storefront-header.php';
?>
<main class="store-page commerce-page account-page">
  <div class="container account-layout">
    <?php $accountActive = 'favourites'; require __DIR__ . '/includes/customer-account-nav.php'; ?>
    <section class="account-content">
      <div class="account-page-heading"><h1>My Favourites</h1><p>Products saved under your customer account.</p></div>
      <?php if (!$products): ?>
        <div class="empty-state glass-card"><h3>No favourites yet</h3><p>Use the heart button on a product to save it here.</p><a class="primary-btn" href="products.php">Browse Products</a></div>
      <?php else: ?>
        <div class="favourite-grid">
          <?php foreach ($products as $product): ?>
            <?php $price = sf_effective_price($product); ?>
            <article class="product-card glass-card">
              <form action="toggle-favourite.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>">
                <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>">
                <input type="hidden" name="return" value="my-favourites.php">
                <button class="favourite-button active" type="submit" aria-label="Remove from favourites">♥</button>
              </form>
              <a href="product.php?slug=<?= rawurlencode($product['slug']); ?>"><div class="product-img"><img class="product-photo" src="<?= sf_e(sf_account_media_url($product['thumbnail_path'])); ?>" alt="<?= sf_e($product['product_name']); ?>"></div></a>
              <div class="product-body">
                  <h3><?= sf_e($product['product_name']); ?></h3>
                  <?php if (!empty($product['product_name_tamil'])): ?>
                    <div class="product-name-tamil" lang="ta">
                      <?= sf_e($product['product_name_tamil']); ?>
                    </div>
                  <?php endif; ?>
                  <div class="price"><?= sf_e(sf_money($price)); ?></div><div class="moq-note">Minimum order: <?= (int)$product['minimum_order_qty']; ?></div><a class="product-action-btn primary" href="product.php?slug=<?= rawurlencode($product['slug']); ?>">View Product</a></div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
