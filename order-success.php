<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$orderNumber = trim((string)($_GET['order'] ?? ''));
$sessionOrder = (string)($_SESSION['last_order_number'] ?? '');

if ($orderNumber === '' || !hash_equals($sessionOrder, $orderNumber)) {
    header('Location: products.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT order_number, customer_name, grand_total, payment_status
     FROM orders
     WHERE order_number = :order_number
     LIMIT 1"
);
$stmt->execute(['order_number' => $orderNumber]);
$order = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$order) {
    header('Location: products.php');
    exit;
}

$pageTitle = 'Order ' . $orderNumber;
$topStripItems = [];
require __DIR__ . '/includes/storefront-header.php';
?>

<main class="store-page">
  <div class="container">
    <div class="empty-state glass-card">
      <div style="font-size:60px">🎉</div>
      <h1>Order Received</h1>
      <p>
        Thank you, <?= sf_e($order['customer_name']); ?>.
        Your order number is:
      </p>
      <h2 style="color:var(--maroon);margin:14px 0">
        <?= sf_e($order['order_number']); ?>
      </h2>
      <p>
        Total: <strong><?= sf_e(sf_money(
            $order['grand_total']
        )); ?></strong><br>
        Payment status:
        <strong><?= sf_e(ucwords(str_replace(
            '_',
            ' ',
            $order['payment_status']
        ))); ?></strong>
      </p>
      <p>
        The order is now available in the admin panel under
        <strong>New Orders</strong>.
      </p>
      <a class="primary-btn" href="products.php">
        Continue Shopping
      </a>
    </div>
  </div>
</main>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
