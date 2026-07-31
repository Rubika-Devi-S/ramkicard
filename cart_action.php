<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

sf_require_customer_login(
    'login.php',
    'cart.php'
);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

if (!sf_verify_csrf($_POST['csrf_token'] ?? null)) {
    header(
        'Location: cart.php?error='
        . rawurlencode('Page session expired.')
    );
    exit;
}

$cartId = sf_active_cart_id($pdo, false);

if ($cartId <= 0) {
    header('Location: cart.php');
    exit;
}

$removeId = max(0, (int)($_POST['remove_item_id'] ?? 0));

try {
    $pdo->beginTransaction();

    if ($removeId > 0) {
        $stmt = $pdo->prepare(
            "DELETE FROM cart_items
             WHERE id = :id
               AND cart_id = :cart_id"
        );

        $stmt->execute([
            'id' => $removeId,
            'cart_id' => $cartId,
        ]);

        $pdo->commit();
        header('Location: cart.php');
        exit;
    }

    $quantities = $_POST['quantities'] ?? [];

    if (!is_array($quantities)) {
        throw new RuntimeException('Invalid cart quantities.');
    }

    $itemStmt = $pdo->prepare(
        "SELECT
            ci.id,
            ci.product_id,
            p.minimum_order_qty,
            p.quantity_step,
            p.manage_stock,
            p.stock_quantity,
            p.status,
            p.deleted_at
         FROM cart_items ci
         INNER JOIN products p ON p.id = ci.product_id
         WHERE ci.id = :id
           AND ci.cart_id = :cart_id
         LIMIT 1
         FOR UPDATE"
    );

    $updateStmt = $pdo->prepare(
        "UPDATE cart_items
         SET quantity = :quantity
         WHERE id = :id
           AND cart_id = :cart_id"
    );

    foreach ($quantities as $itemId => $quantityValue) {
        $itemId = (int)$itemId;
        $quantity = (int)$quantityValue;

        $itemStmt->execute([
            'id' => $itemId,
            'cart_id' => $cartId,
        ]);

        $item = $itemStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if (!$item) {
            continue;
        }

        if (
            $item['status'] !== 'active'
            || $item['deleted_at'] !== null
        ) {
            throw new RuntimeException(
                'One of the products is no longer available.'
            );
        }

        if (!sf_valid_quantity($item, $quantity)) {
            throw new RuntimeException(
                'A quantity does not follow its MOQ and step rules.'
            );
        }

        if (
            (int)$item['manage_stock'] === 1
            && $quantity > (int)$item['stock_quantity']
        ) {
            throw new RuntimeException(
                'A requested quantity is not currently in stock.'
            );
        }

        $updateStmt->execute([
            'quantity' => $quantity,
            'id' => $itemId,
            'cart_id' => $cartId,
        ]);
    }

    $pdo->commit();

    header('Location: cart.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Cart update failed: ' . $e->getMessage());

    header(
        'Location: cart.php?error='
        . rawurlencode(
            $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to update your cart.'
        )
    );
    exit;
}
