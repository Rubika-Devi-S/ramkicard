<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';
sf_require_customer_login('login.php', 'my-orders.php');

$status = trim((string)($_GET['status'] ?? ''));
$allowedStatuses = ['new', 'processed', 'delivered', 'cancelled'];

$sql = "SELECT order_number, status, payment_status, grand_total, created_at
        FROM orders
        WHERE customer_id = :customer_id";
$params = ['customer_id' => sf_customer_id()];

if (in_array($status, $allowedStatuses, true)) {
    $sql .= " AND status = :status";
    $params['status'] = $status;
}

$sql .= " ORDER BY created_at DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Orders';
$topStripItems = [];
$storefrontBase = '';
require __DIR__ . '/includes/storefront-header.php';
?>
<main class="store-page commerce-page account-page">
  <div class="container account-layout">
    <?php $accountActive = 'orders'; require __DIR__ . '/includes/customer-account-nav.php'; ?>
    <section class="account-content">
      <div class="account-page-heading"><h1>My Orders</h1><p>Only orders belonging to your logged-in customer account are shown.</p></div>

      <div class="account-panel glass-card">
        <form method="GET" class="order-filter-form">
          <div>
            <label for="orderStatus">Order Status</label>
            <select id="orderStatus" name="status">
              <option value="">All Orders</option>
              <?php foreach ($allowedStatuses as $option): ?>
                <option value="<?= sf_e($option); ?>" <?= $status === $option ? 'selected' : ''; ?>><?= sf_e(ucfirst($option)); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="product-action-btn primary" type="submit">Filter</button>
          <a class="product-action-btn" href="my-orders.php">Clear</a>
        </form>

        <?php if (!$orders): ?>
          <div class="empty-state"><h3>No orders found</h3><p>Orders placed using this customer account will appear here.</p></div>
        <?php else: ?>
          <div class="order-list">
            <?php foreach ($orders as $order): ?>
              <div class="order-card">
                <div><strong><?= sf_e($order['order_number']); ?></strong><small><?= sf_e(date('d M Y, h:i A', strtotime($order['created_at']))); ?></small></div>
                <span class="status-pill"><?= sf_e(str_replace('_', ' ', $order['status'])); ?></span>
                <div><small>Payment</small><strong><?= sf_e(ucwords(str_replace('_', ' ', $order['payment_status']))); ?></strong></div>
                <div><strong><?= sf_e(sf_money($order['grand_total'])); ?></strong><a class="product-action-btn" href="order-details.php?order=<?= rawurlencode($order['order_number']); ?>">View Details</a></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>
<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
