<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

function cart_fail(
    array $product,
    string $message,
    int $status = 422,
    array $data = []
): never {
    $productUrl = 'product.php?slug='
        . rawurlencode((string)($product['slug'] ?? ''))
        . '#buy';

    if (sf_wants_json()) {
        sf_json(
            false,
            $message,
            array_merge(
                ['product_url' => $productUrl],
                $data
            ),
            $status
        );
    }

    header(
        'Location: product.php?slug='
        . rawurlencode((string)($product['slug'] ?? ''))
        . '&error='
        . rawurlencode($message)
        . '#buy'
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

if (!sf_verify_csrf($_POST['csrf_token'] ?? null)) {
    if (sf_wants_json()) {
        sf_json(
            false,
            'Your page session expired. Refresh and try again.',
            [],
            419
        );
    }

    header('Location: products.php');
    exit;
}

$productId = max(0, (int)($_POST['product_id'] ?? 0));
$product = sf_get_product($pdo, $productId);

if (!$product) {
    if (sf_wants_json()) {
        sf_json(false, 'Product not found.', [], 404);
    }

    header('Location: products.php');
    exit;
}

$returnUrl = sf_safe_return_url(
    (string)(
        $_POST['return_url']
        ?? 'product.php?slug='
            . rawurlencode((string)$product['slug'])
            . '#buy'
    ),
    'products.php'
);

if (!sf_customer_logged_in()) {
    $loginUrl = sf_login_required_url($returnUrl);

    if (sf_wants_json()) {
        sf_json(
            false,
            'Please login to add products to your cart.',
            ['login_url' => $loginUrl],
            401
        );
    }

    header('Location: ' . $loginUrl);
    exit;
}

$mode = sf_purchase_mode($pdo, $product);

if (!in_array($mode, ['checkout', 'both'], true)) {
    cart_fail(
        $product,
        'Online ordering is not available for this product.'
    );
}

$quantity = max(0, (int)($_POST['quantity'] ?? 0));
$colorId = max(0, (int)($_POST['color_variant_id'] ?? 0));
$designId = max(0, (int)($_POST['design_variant_id'] ?? 0));
$notes = trim((string)($_POST['notes'] ?? ''));

if (!sf_valid_quantity($product, $quantity)) {
    cart_fail(
        $product,
        'Quantity must follow the minimum order and quantity-step rules.'
    );
}

if ((int)$product['manage_stock'] === 1) {
    $stock = (int)($product['stock_quantity'] ?? 0);

    if ($quantity > $stock) {
        cart_fail(
            $product,
            'Requested quantity is currently unavailable.'
        );
    }
}

$color = sf_variant(
    $pdo,
    'product_color_variants',
    $colorId,
    $productId
);

$design = sf_variant(
    $pdo,
    'product_design_variants',
    $designId,
    $productId
);

if ((int)$product['has_color_variants'] === 1 && !$color) {
    cart_fail(
        $product,
        'Choose a colour from the product page.'
    );
}

if ((int)$product['has_design_variants'] === 1 && !$design) {
    cart_fail(
        $product,
        'Choose a design from the product page.'
    );
}

$unitPrice = sf_effective_price($product)
    + (float)($color['price_adjustment'] ?? 0)
    + (float)($design['price_adjustment'] ?? 0);

try {
    $pdo->beginTransaction();

    $cartId = sf_active_cart_id($pdo, true);

    if ($cartId <= 0) {
        throw new RuntimeException(
            'Unable to create a customer cart.'
        );
    }

    $find = $pdo->prepare(
        "SELECT id, quantity
         FROM cart_items
         WHERE cart_id = :cart_id
           AND product_id = :product_id
           AND (
               color_variant_id = :color_variant_id
               OR (
                   color_variant_id IS NULL
                   AND :color_variant_id_null IS NULL
               )
           )
           AND (
               design_variant_id = :design_variant_id
               OR (
                   design_variant_id IS NULL
                   AND :design_variant_id_null IS NULL
               )
           )
         LIMIT 1
         FOR UPDATE"
    );

    $colorValue = $color ? (int)$color['id'] : null;
    $designValue = $design ? (int)$design['id'] : null;

    $find->execute([
        'cart_id' => $cartId,
        'product_id' => $productId,
        'color_variant_id' => $colorValue,
        'color_variant_id_null' => $colorValue,
        'design_variant_id' => $designValue,
        'design_variant_id_null' => $designValue,
    ]);

    $existing = $find->fetch(PDO::FETCH_ASSOC) ?: [];
    $finalQuantity = $quantity;

    if ($existing) {
        $finalQuantity += (int)$existing['quantity'];

        if (!sf_valid_quantity($product, $finalQuantity)) {
            throw new RuntimeException(
                'The combined quantity is invalid.'
            );
        }

        if (
            (int)$product['manage_stock'] === 1
            && $finalQuantity > (int)$product['stock_quantity']
        ) {
            throw new RuntimeException(
                'Requested quantity is unavailable.'
            );
        }

        $update = $pdo->prepare(
            "UPDATE cart_items
             SET quantity = :quantity,
                 unit_price_snapshot = :unit_price_snapshot,
                 customer_item_notes = :customer_item_notes
             WHERE id = :id"
        );

        $update->execute([
            'quantity' => $finalQuantity,
            'unit_price_snapshot' => $unitPrice,
            'customer_item_notes' =>
                $notes !== '' ? $notes : null,
            'id' => (int)$existing['id'],
        ]);
    } else {
        $insert = $pdo->prepare(
            "INSERT INTO cart_items
            (
                cart_id,
                product_id,
                color_variant_id,
                design_variant_id,
                quantity,
                unit_price_snapshot,
                customer_item_notes
            )
            VALUES
            (
                :cart_id,
                :product_id,
                :color_variant_id,
                :design_variant_id,
                :quantity,
                :unit_price_snapshot,
                :customer_item_notes
            )"
        );

        $insert->execute([
            'cart_id' => $cartId,
            'product_id' => $productId,
            'color_variant_id' => $colorValue,
            'design_variant_id' => $designValue,
            'quantity' => $quantity,
            'unit_price_snapshot' => $unitPrice,
            'customer_item_notes' =>
                $notes !== '' ? $notes : null,
        ]);
    }

    $pdo->commit();

    if (sf_wants_json()) {
        sf_json(
            true,
            $product['product_name'] . ' added to cart.',
            [
                'cart' => sf_cart_snapshot($pdo),
                'return_url' => $returnUrl,
            ]
        );
    }

    $separator = str_contains($returnUrl, '?') ? '&' : '?';

    header(
        'Location: '
        . $returnUrl
        . $separator
        . 'cart=added'
    );
    exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Add to cart failed: '
        . $exception->getMessage()
    );

    cart_fail(
        $product,
        $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Unable to add this product to the cart.'
    );
}
