<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$return = sf_safe_return_url(
    (string)($_POST['return'] ?? $_GET['return'] ?? 'products.php'),
    'products.php'
);

if (!sf_customer_logged_in()) {
    header('Location: login.php?return=' . rawurlencode($return));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !sf_verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . $return);
    exit;
}

$productId = max(0, (int)($_POST['product_id'] ?? 0));
$product = sf_get_product($pdo, $productId);

if (!$product) {
    header('Location: ' . $return);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM customer_favourites WHERE customer_id = :customer_id AND product_id = :product_id LIMIT 1");
$stmt->execute(['customer_id' => sf_customer_id(), 'product_id' => $productId]);
$favouriteId = (int)$stmt->fetchColumn();

if ($favouriteId > 0) {
    $pdo->prepare("DELETE FROM customer_favourites WHERE id = :id AND customer_id = :customer_id")
        ->execute(['id' => $favouriteId, 'customer_id' => sf_customer_id()]);
} else {
    $pdo->prepare("INSERT INTO customer_favourites (customer_id, product_id, created_at) VALUES (:customer_id, :product_id, NOW())")
        ->execute(['customer_id' => sf_customer_id(), 'product_id' => $productId]);
}

header('Location: ' . $return);
exit;
