<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';
sf_require_customer_login('login.php', 'my-account.php');

$customerId = sf_customer_id();
$cartCount = sf_cart_count($pdo);

$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_orders,
        SUM(status = 'new') AS new_orders,
        SUM(status = 'processed') AS processed_orders,
        SUM(status = 'delivered') AS delivered_orders,
        SUM(status = 'cancelled') AS cancelled_orders
     FROM orders
     WHERE customer_id = :customer_id"
);
$stmt->execute(['customer_id' => $customerId]);
$orderStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM customer_favourites
     WHERE customer_id = :customer_id"
);
$stmt->execute(['customer_id' => $customerId]);
$favouriteCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT order_number, status, payment_status, grand_total, created_at
     FROM orders
     WHERE customer_id = :customer_id
     ORDER BY created_at DESC, id DESC
     LIMIT 5"
);
$stmt->execute(['customer_id' => $customerId]);
$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Account';
$topStripItems = [];
$storefrontBase = '';
require __DIR__ . '/includes/storefront-header.php';
?>
<main class="store-page commerce-page account-page">
  <div class="container account-layout">
    <?php $accountActive = 'dashboard'; require __DIR__ . '/includes/customer-account-nav.php'; ?>

    <section class="account-content">
      <div class="account-page-heading">
        <h1>Welcome, <?= sf_e(sf_customer_name()); ?></h1>
        <p>Review your cart, favourites and recent orders.</p>
      </div>

      <div class="account-stat-grid">
        <div class="account-stat"><span>CART ITEMS</span><strong><?= $cartCount; ?></strong></div>
        <div class="account-stat"><span>FAVOURITES</span><strong><?= $favouriteCount; ?></strong></div>
        <div class="account-stat"><span>TOTAL ORDERS</span><strong><?= (int)($orderStats['total_orders'] ?? 0); ?></strong></div>
        <div class="account-stat"><span>DELIVERED</span><strong><?= (int)($orderStats['delivered_orders'] ?? 0); ?></strong></div>
      </div>

      <div class="account-panel glass-card">
        <h2>Order Status</h2>
        <div class="account-stat-grid" style="margin-bottom:0">
          <div class="account-stat"><span>NEW</span><strong><?= (int)($orderStats['new_orders'] ?? 0); ?></strong></div>
          <div class="account-stat"><span>PROCESSED</span><strong><?= (int)($orderStats['processed_orders'] ?? 0); ?></strong></div>
          <div class="account-stat"><span>DELIVERED</span><strong><?= (int)($orderStats['delivered_orders'] ?? 0); ?></strong></div>
          <div class="account-stat"><span>CANCELLED</span><strong><?= (int)($orderStats['cancelled_orders'] ?? 0); ?></strong></div>
        </div>
      </div>

      <div class="account-panel glass-card">
        <h2>Recent Orders</h2>
        <?php if (!$recentOrders): ?>
          <div class="empty-state"><p>No orders have been placed from this account.</p><a class="primary-btn" href="products.php">Browse Products</a></div>
        <?php else: ?>
          <div class="order-list">
            <?php foreach ($recentOrders as $order): ?>
              <div class="order-card">
                <div><strong><?= sf_e($order['order_number']); ?></strong><small><?= sf_e(date('d M Y, h:i A', strtotime($order['created_at']))); ?></small></div>
                <span class="status-pill"><?= sf_e(str_replace('_', ' ', $order['status'])); ?></span>
                <strong><?= sf_e(sf_money($order['grand_total'])); ?></strong>
                <a class="product-action-btn" href="order-details.php?order=<?= rawurlencode($order['order_number']); ?>">View</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>
<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
