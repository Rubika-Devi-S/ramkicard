<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

if (!sf_customer_logged_in()) {
    sf_json(
        false,
        'Please login to access your cart.',
        [
            'login_url' => sf_login_required_url(
                sf_current_return_url('index.php')
            ),
        ],
        401
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    sf_json(
        true,
        'Cart loaded.',
        sf_cart_snapshot($pdo)
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sf_json(false, 'Method not allowed.', [], 405);
}

if (!sf_verify_csrf($_POST['csrf_token'] ?? null)) {
    sf_json(
        false,
        'Your page session expired. Refresh and try again.',
        [],
        419
    );
}

$action = (string)($_POST['action'] ?? '');
$itemId = max(0, (int)($_POST['item_id'] ?? 0));
$cartId = sf_active_cart_id($pdo, false);

if ($cartId <= 0) {
    sf_json(
        true,
        'Your cart is empty.',
        sf_cart_snapshot($pdo)
    );
}

try {
    $pdo->beginTransaction();

    if ($action === 'remove') {
        $stmt = $pdo->prepare(
            "DELETE FROM cart_items
             WHERE id = :id
               AND cart_id = :cart_id"
        );

        $stmt->execute([
            'id' => $itemId,
            'cart_id' => $cartId,
        ]);

        $pdo->commit();

        sf_json(
            true,
            'Product removed from cart.',
            sf_cart_snapshot($pdo)
        );
    }

    if ($action !== 'quantity') {
        throw new RuntimeException('Invalid cart action.');
    }

    $quantity = max(0, (int)($_POST['quantity'] ?? 0));

    $stmt = $pdo->prepare(
        "SELECT
            ci.id,
            p.product_name,
            p.minimum_order_qty,
            p.quantity_step,
            p.manage_stock,
            p.stock_quantity,
            p.status,
            p.deleted_at
         FROM cart_items ci
         INNER JOIN products p
            ON p.id = ci.product_id
         WHERE ci.id = :id
           AND ci.cart_id = :cart_id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([
        'id' => $itemId,
        'cart_id' => $cartId,
    ]);

    $item = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$item) {
        throw new RuntimeException('Cart item not found.');
    }

    if (
        $item['status'] !== 'active'
        || $item['deleted_at'] !== null
    ) {
        throw new RuntimeException(
            'This product is no longer available.'
        );
    }

    if (!sf_valid_quantity($item, $quantity)) {
        throw new RuntimeException(
            'Quantity must follow the minimum order and step rules.'
        );
    }

    if (
        (int)$item['manage_stock'] === 1
        && $quantity > (int)$item['stock_quantity']
    ) {
        throw new RuntimeException(
            'Requested quantity is not currently available.'
        );
    }

    $update = $pdo->prepare(
        "UPDATE cart_items
         SET quantity = :quantity
         WHERE id = :id
           AND cart_id = :cart_id"
    );

    $update->execute([
        'quantity' => $quantity,
        'id' => $itemId,
        'cart_id' => $cartId,
    ]);

    $pdo->commit();

    sf_json(
        true,
        'Cart quantity updated.',
        sf_cart_snapshot($pdo)
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Cart drawer action failed: '
        . $exception->getMessage()
    );

    sf_json(
        false,
        $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Unable to update your cart.',
        [],
        422
    );
}
