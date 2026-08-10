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
<style>
html.rk-motion-ready .favourite-grid .product-card {
  opacity: 0;
  transform: translate3d(0, 22px, 0);
  transition: opacity .58s cubic-bezier(.22, 1, .36, 1),
              transform .58s cubic-bezier(.22, 1, .36, 1);
  transition-delay: var(--rk-delay, 0ms);
}
html.rk-motion-ready .favourite-grid .product-card.rk-visible { opacity: 1; transform: none; }
.favourite-grid .product-card { position: relative; }
.favourite-grid .product-card.rk-removing {
  opacity: 0 !important;
  transform: scale(.96) !important;
  pointer-events: none;
}
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
  .rk-toast { right: 16px; bottom: 18px; }
}
@media (prefers-reduced-motion: reduce) {
  html.rk-motion-ready .favourite-grid .product-card,
  .favourite-grid .product-card.rk-removing,
  .rk-toast { opacity: 1; transform: none; transition: none; }
}
</style>
<script>
if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  document.documentElement.classList.add('rk-motion-ready');
}
</script>
<main class="store-page commerce-page account-page">
  <div class="container account-layout">
    <?php $accountActive = 'favourites'; require __DIR__ . '/includes/customer-account-nav.php'; ?>
    <section class="account-content">
      <div class="account-page-heading"><h1>My Favourites</h1><p>Products saved under your customer account.</p></div>
      <?php if (!$products): ?>
        <div class="empty-state glass-card"><h3>No favourites yet</h3><p>Use the heart button on a product to save it here.</p><a class="primary-btn" href="products.php">Browse Products</a></div>
      <?php else: ?>
        <div class="favourite-grid" id="favouriteGrid">
          <?php foreach ($products as $productIndex => $product): ?>
            <?php $price = sf_effective_price($product); ?>
            <article class="product-card glass-card" data-favourite-card
              style="--rk-delay: <?= (int)(($productIndex % 4) * 55); ?>ms">
              <form action="toggle-favourite.php" method="POST" class="js-favourite-form">
                <input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>">
                <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>">
                <input type="hidden" name="return" value="my-favourites.php">
                <button class="favourite-button active" type="submit"
                  aria-label="Remove from favourites" aria-pressed="true">♥</button>
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
<div class="rk-toast" id="rkFavouriteToast" role="status" aria-live="polite"></div>
<script>
(() => {
  'use strict';

  const cards = document.querySelectorAll('[data-favourite-card]');
  if (document.documentElement.classList.contains('rk-motion-ready')) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('rk-visible');
        observer.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -7% 0px', threshold: .08 });
    cards.forEach(card => observer.observe(card));
  } else {
    cards.forEach(card => card.classList.add('rk-visible'));
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
      const button = form.querySelector('button[type="submit"]');
      const card = form.closest('[data-favourite-card]');
      if (!button || !card || button.disabled) return;
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
          throw new Error(result.message || 'Unable to remove favourite.');
        }

        showToast(result.message);
        card.classList.add('rk-removing');
        window.setTimeout(() => {
          card.remove();
          const grid = document.getElementById('favouriteGrid');
          if (grid && !grid.querySelector('[data-favourite-card]')) {
            grid.outerHTML = '<div class="empty-state glass-card"><h3>No favourites yet</h3><p>Use the heart button on a product to save it here.</p><a class="primary-btn" href="products.php">Browse Products</a></div>';
          }
        }, 260);
      } catch (error) {
        showToast(error.message || 'Unable to remove favourite.', true);
        button.disabled = false;
      }
    });
  });
})();
</script>
<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
