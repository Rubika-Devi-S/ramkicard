<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$cartId = sf_active_cart_id($pdo, false);
$items = [];

if ($cartId > 0) {
    $stmt = $pdo->prepare(
        "SELECT
            ci.*,
            p.product_name,
            p.product_name_tamil,
            p.slug,
            p.thumbnail_path,
            p.minimum_order_qty,
            p.quantity_step,
            p.status AS product_status,
            p.deleted_at,
            cv.color_name,
            dv.design_name
         FROM cart_items ci
         INNER JOIN products p ON p.id = ci.product_id
         LEFT JOIN product_color_variants cv
            ON cv.id = ci.color_variant_id
         LEFT JOIN product_design_variants dv
            ON dv.id = ci.design_variant_id
         WHERE ci.cart_id = :cart_id
         ORDER BY ci.id DESC"
    );

    $stmt->execute(['cart_id' => $cartId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$subtotal = 0.0;

foreach ($items as $item) {
    $subtotal +=
        (float)$item['unit_price_snapshot']
        * (int)$item['quantity'];
}

$pageTitle = 'Shopping Cart | '
    . sf_setting($pdo, 'company_name', 'Ramki Cards');

$topStripItems = [];
require __DIR__ . '/includes/storefront-header.php';

$error = trim((string)($_GET['error'] ?? ''));
?>

<main class="store-page">
  <div class="container">
    <div class="section-title store-page-title">
      <div class="decor-line"><i></i></div>
      <span>Your selected products</span>
      <h2>Shopping <em>Cart</em></h2>
    </div>

    <?php if ($error !== ''): ?>
      <div class="purchase-message error">
        <?= sf_e($error); ?>
      </div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="empty-state glass-card">
        <h3>Your cart is empty</h3>
        <p>Add products from the catalogue to continue.</p>
        <a class="primary-btn" href="products.php">
          Browse Products
        </a>
      </div>
    <?php else: ?>
      <div class="store-panel glass-card cart-table-wrap">
        <form action="cart_action.php" method="POST">
          <input
            type="hidden"
            name="csrf_token"
            value="<?= sf_e(sf_csrf_token()); ?>"
          >
          <input type="hidden" name="action" value="update">

          <table class="cart-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Unit Price</th>
                <th>Options</th>
                <th>Quantity</th>
                <th>Total</th>
                <th></th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($items as $item): ?>
                <?php
                $lineTotal =
                    (float)$item['unit_price_snapshot']
                    * (int)$item['quantity'];
                ?>
                <tr>
                  <td>
                    <div class="cart-product">
                      <img
                        src="<?= sf_e(sf_media_path(
                            $item['thumbnail_path'],
                            'banner.png'
                        )); ?>"
                        alt="<?= sf_e($item['product_name']); ?>"
                      >
                      <div>
                        <strong>
                          <?= sf_e($item['product_name']); ?>
                        </strong>

                        <?php if (!empty(
                            $item['product_name_tamil']
                        )): ?>
                          <div
                            class="product-name-tamil cart-name-tamil"
                            lang="ta"
                          >
                            <?= sf_e(
                                $item['product_name_tamil']
                            ); ?>
                          </div>
                        <?php endif; ?>

                        <div class="moq-note">
                          MOQ:
                          <?= (int)$item['minimum_order_qty']; ?>
                          · Step:
                          <?= (int)$item['quantity_step']; ?>
                        </div>
                      </div>
                    </div>
                  </td>

                  <td>
                    <?= sf_e(sf_money(
                        $item['unit_price_snapshot']
                    )); ?>
                  </td>

                  <td>
                    <?= sf_e($item['color_name'] ?: '-'); ?>
                    <?php if (!empty($item['design_name'])): ?>
                      <br><?= sf_e($item['design_name']); ?>
                    <?php endif; ?>
                  </td>

                  <td>
                    <input
                      type="number"
                      name="quantities[<?= (int)$item['id']; ?>]"
                      min="<?= (int)$item['minimum_order_qty']; ?>"
                      step="<?= (int)$item['quantity_step']; ?>"
                      value="<?= (int)$item['quantity']; ?>"
                      required
                      style="max-width:110px"
                    >
                  </td>

                  <td>
                    <?= sf_e(sf_money($lineTotal)); ?>
                  </td>

                  <td>
                    <button
                      type="submit"
                      class="product-action-btn"
                      formaction="cart_action.php"
                      name="remove_item_id"
                      value="<?= (int)$item['id']; ?>"
                      formmethod="POST"
                    >Remove</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div style="margin-top:18px">
            <button class="product-action-btn" type="submit">
              Update Cart
            </button>
          </div>
        </form>
      </div>

      <div class="cart-summary glass-card">
        <div class="summary-row">
          <span>Subtotal</span>
          <strong><?= sf_e(sf_money($subtotal)); ?></strong>
        </div>

        <div class="summary-row">
          <span>Shipping and tax</span>
          <span>Calculated at checkout</span>
        </div>

        <div class="summary-row total">
          <span>Current Total</span>
          <span><?= sf_e(sf_money($subtotal)); ?></span>
        </div>

        <a href="checkout.php" class="submit-btn">
          Proceed to Checkout
        </a>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
