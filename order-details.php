<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';
sf_require_customer_login('login.php', 'my-orders.php');

$orderNumber = trim((string)($_GET['order'] ?? ''));

$stmt = $pdo->prepare(
    "SELECT *
     FROM orders
     WHERE order_number = :order_number
       AND customer_id = :customer_id
     LIMIT 1"
);
$stmt->execute([
    'order_number' => $orderNumber,
    'customer_id' => sf_customer_id(),
]);
$order = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$order) {
    http_response_code(404);
}

$items = [];
$addresses = [];
$payments = [];
$history = [];

if ($order) {
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id");
    $stmt->execute(['order_id' => (int)$order['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM order_addresses WHERE order_id = :order_id ORDER BY address_type DESC");
    $stmt->execute(['order_id' => (int)$order['id']]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = :order_id ORDER BY id DESC");
    $stmt->execute(['order_id' => (int)$order['id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = :order_id ORDER BY created_at DESC, id DESC");
    $stmt->execute(['order_id' => (int)$order['id']]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = $order ? 'Order ' . $orderNumber : 'Order Not Found';
$topStripItems = [];
$storefrontBase = '';
require __DIR__ . '/includes/storefront-header.php';
?>
<main class="store-page">
  <div class="container account-layout">
    <?php $accountActive = 'orders'; require __DIR__ . '/includes/customer-account-nav.php'; ?>
    <section class="account-content">
      <?php if (!$order): ?>
        <div class="empty-state glass-card"><h1>Order not found</h1><p>This order does not belong to your account or does not exist.</p><a class="primary-btn" href="my-orders.php">Back to Orders</a></div>
      <?php else: ?>
        <div class="account-page-heading"><h1><?= sf_e($order['order_number']); ?></h1><p>Placed on <?= sf_e(date('d M Y, h:i A', strtotime($order['created_at']))); ?></p></div>

        <div class="account-stat-grid">
          <div class="account-stat"><span>ORDER STATUS</span><strong style="font-size:18px"><?= sf_e(ucfirst($order['status'])); ?></strong></div>
          <div class="account-stat"><span>PAYMENT STATUS</span><strong style="font-size:18px"><?= sf_e(ucwords(str_replace('_', ' ', $order['payment_status']))); ?></strong></div>
          <div class="account-stat"><span>ITEMS</span><strong><?= count($items); ?></strong></div>
          <div class="account-stat"><span>GRAND TOTAL</span><strong style="font-size:18px"><?= sf_e(sf_money($order['grand_total'])); ?></strong></div>
        </div>

        <div class="account-panel glass-card">
          <h2>Products</h2>
          <div class="cart-table-wrap">
            <table class="cart-table">
              <thead><tr><th>Product</th><th>Options</th><th>Quantity</th><th>Unit Price</th><th>Total</th></tr></thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td><div class="cart-product"><img src="<?= sf_e(sf_account_media_url($item['thumbnail_snapshot'])); ?>" alt="<?= sf_e($item['product_name_snapshot']); ?>"><div><strong><?= sf_e($item['product_name_snapshot']); ?></strong><small><?= sf_e($item['sku_snapshot'] ?: ''); ?></small></div></div></td>
                    <td><?= sf_e($item['selected_color_name'] ?: '-'); ?><?php if ($item['selected_design_name']): ?><br><?= sf_e($item['selected_design_name']); ?><?php endif; ?></td>
                    <td><?= (int)$item['quantity']; ?></td>
                    <td><?= sf_e(sf_money($item['final_unit_price'])); ?></td>
                    <td><?= sf_e(sf_money($item['line_total'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="account-panel glass-card">
          <h2>Amount Summary</h2>
          <div class="cart-summary" style="margin:0 0 0 auto;box-shadow:none">
            <div class="summary-row"><span>Subtotal</span><strong><?= sf_e(sf_money($order['subtotal'])); ?></strong></div>
            <div class="summary-row"><span>Discount</span><strong><?= sf_e(sf_money($order['discount_amount'])); ?></strong></div>
            <div class="summary-row"><span>Shipping</span><strong><?= sf_e(sf_money($order['shipping_amount'])); ?></strong></div>
            <div class="summary-row"><span>Tax</span><strong><?= sf_e(sf_money($order['tax_amount'])); ?></strong></div>
            <div class="summary-row total"><span>Grand Total</span><strong><?= sf_e(sf_money($order['grand_total'])); ?></strong></div>
          </div>
        </div>

        <?php if ($addresses): ?>
          <div class="account-panel glass-card"><h2>Delivery Address</h2><div class="address-list">
            <?php foreach ($addresses as $address): ?><div class="address-card"><strong><?= sf_e(ucfirst($address['address_type'])); ?></strong><p><?= sf_e($address['contact_name']); ?><br><?= sf_e($address['phone']); ?><br><?= sf_e($address['address_line_1']); ?><?= $address['address_line_2'] ? ', ' . sf_e($address['address_line_2']) : ''; ?><br><?= sf_e($address['city']); ?>, <?= sf_e($address['state']); ?> - <?= sf_e($address['postal_code']); ?><br><?= sf_e($address['country']); ?></p></div><?php endforeach; ?>
          </div></div>
        <?php endif; ?>

        <?php if ($history): ?>
          <div class="account-panel glass-card"><h2>Status History</h2><div class="order-list">
            <?php foreach ($history as $row): ?><div class="order-card"><div><strong><?= sf_e(ucfirst(str_replace('_', ' ', $row['new_status']))); ?></strong><small><?= sf_e(date('d M Y, h:i A', strtotime($row['created_at']))); ?></small></div><div><?= sf_e($row['notes'] ?: 'Status updated'); ?></div></div><?php endforeach; ?>
          </div></div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
