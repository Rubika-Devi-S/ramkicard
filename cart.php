<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

sf_require_customer_login('login.php', 'cart.php');

$cart = sf_cart_snapshot($pdo);
$items = $cart['items'];
$error = trim((string)($_GET['error'] ?? ''));

$pageTitle = 'Shopping Cart | '
    . sf_setting($pdo, 'company_name', 'Ramki Cards');

$topStripItems = [];
$storefrontBase = '';

require __DIR__ . '/includes/storefront-header.php';
?>

<main class="store-page commerce-page">
  <div class="container">
    <div class="commerce-page-heading">
      <div>
        <span class="commerce-eyebrow">Your selected products</span>
        <h1>Shopping Cart</h1>
        <p>
          Review quantities and options before proceeding to checkout.
        </p>
      </div>

      <a href="products.php" class="commerce-outline-button">
        Continue Shopping
      </a>
    </div>

    <?php if ($error !== ''): ?>
      <div class="purchase-message error">
        <?= sf_e($error); ?>
      </div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="commerce-empty-state glass-card">
        <span>🛍️</span>
        <h2>Your cart is empty</h2>
        <p>Add products from the catalogue to continue.</p>
        <a class="primary-btn" href="products.php">
          Browse Products
        </a>
      </div>
    <?php else: ?>
      <div class="cart-page-layout">
        <form
          action="cart_action.php"
          method="POST"
          class="cart-page-items"
        >
          <input
            type="hidden"
            name="csrf_token"
            value="<?= sf_e(sf_csrf_token()); ?>"
          >
          <input type="hidden" name="action" value="update">

          <?php foreach ($items as $item): ?>
            <article class="cart-page-item glass-card">
              <a
                class="cart-page-image"
                href="product.php?slug=<?= rawurlencode(
                    (string)$item['slug']
                ); ?>"
              >
                <img
                  src="<?= sf_e($item['image']); ?>"
                  alt="<?= sf_e($item['product_name']); ?>"
                >
              </a>

              <div class="cart-page-copy">
                <a
                  href="product.php?slug=<?= rawurlencode(
                      (string)$item['slug']
                  ); ?>"
                >
                  <h2><?= sf_e($item['product_name']); ?></h2>

                  <?php if ($item['product_name_tamil'] !== ''): ?>
                    <p class="product-name-tamil" lang="ta">
                      <?= sf_e($item['product_name_tamil']); ?>
                    </p>
                  <?php endif; ?>
                </a>

                <?php if (
                    $item['color_name'] !== ''
                    || $item['design_name'] !== ''
                ): ?>
                  <p class="cart-page-options">
                    <?= sf_e(implode(' · ', array_filter([
                        $item['color_name'],
                        $item['design_name'],
                    ]))); ?>
                  </p>
                <?php endif; ?>

                <p class="cart-page-unit-price">
                  <?= sf_e($item['unit_price_formatted']); ?> each
                </p>

                <div class="cart-page-controls">
                  <label>
                    Quantity
                    <input
                      type="number"
                      name="quantities[<?= (int)$item['id']; ?>]"
                      min="<?= (int)$item['minimum_order_qty']; ?>"
                      step="<?= (int)$item['quantity_step']; ?>"
                      value="<?= (int)$item['quantity']; ?>"
                      required
                    >
                  </label>

                  <small>
                    Minimum <?= (int)$item['minimum_order_qty']; ?>
                    · Step <?= (int)$item['quantity_step']; ?>
                  </small>
                </div>
              </div>

              <div class="cart-page-price">
                <strong>
                  <?= sf_e($item['line_total_formatted']); ?>
                </strong>

                <button
                  type="submit"
                  class="cart-page-remove"
                  name="remove_item_id"
                  value="<?= (int)$item['id']; ?>"
                  aria-label="Remove <?= sf_e(
                      $item['product_name']
                  ); ?>"
                >
                  Remove
                </button>
              </div>
            </article>
          <?php endforeach; ?>

          <button class="commerce-outline-button" type="submit">
            Update Cart
          </button>
        </form>

        <aside class="cart-page-summary glass-card">
          <span class="commerce-eyebrow">Order summary</span>
          <h2>Cart Total</h2>

          <div class="summary-row">
            <span>Products</span>
            <strong><?= (int)$cart['item_count']; ?></strong>
          </div>

          <div class="summary-row">
            <span>Subtotal</span>
            <strong>
              <?= sf_e($cart['subtotal_formatted']); ?>
            </strong>
          </div>

          <div class="summary-note">
            Shipping and tax are calculated at checkout.
          </div>

          <div class="summary-row total">
            <span>Current Total</span>
            <span><?= sf_e($cart['subtotal_formatted']); ?></span>
          </div>

          <a href="checkout.php" class="cart-checkout-button">
            Proceed to Checkout
          </a>
        </aside>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
