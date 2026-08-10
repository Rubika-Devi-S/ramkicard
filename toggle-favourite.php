<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$return = sf_safe_return_url(
    (string)($_POST['return'] ?? $_GET['return'] ?? 'products.php'),
    'products.php'
);

$accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
$requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
$wantsJson = str_contains($accept, 'application/json')
    || $requestedWith === 'xmlhttprequest';

$jsonResponse = static function (array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

if (!sf_customer_logged_in()) {
    $loginUrl = sf_login_required_url($return);

    if ($wantsJson) {
        $jsonResponse([
            'success' => false,
            'requires_login' => true,
            'login_url' => $loginUrl,
            'message' => 'Please log in to save favourites.',
        ], 401);
    }

    header('Location: ' . $loginUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($wantsJson) {
        $jsonResponse([
            'success' => false,
            'message' => 'Invalid request method.',
        ], 405);
    }

    header('Location: ' . $return);
    exit;
}

if (!sf_verify_csrf($_POST['csrf_token'] ?? null)) {
    if ($wantsJson) {
        $jsonResponse([
            'success' => false,
            'message' => 'Your session expired. Refresh the page and try again.',
        ], 419);
    }

    header('Location: ' . $return);
    exit;
}

$productId = max(0, (int)($_POST['product_id'] ?? 0));
$product = sf_get_product($pdo, $productId);

if (!$product) {
    if ($wantsJson) {
        $jsonResponse([
            'success' => false,
            'message' => 'This product is no longer available.',
        ], 404);
    }

    header('Location: ' . $return);
    exit;
}

$customerId = sf_customer_id();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id
         FROM customer_favourites
         WHERE customer_id = :customer_id
           AND product_id = :product_id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([
        'customer_id' => $customerId,
        'product_id' => $productId,
    ]);
    $favouriteId = (int)$stmt->fetchColumn();

    if ($favouriteId > 0) {
        $pdo->prepare(
            "DELETE FROM customer_favourites
             WHERE id = :id
               AND customer_id = :customer_id"
        )->execute([
            'id' => $favouriteId,
            'customer_id' => $customerId,
        ]);
        $isFavourite = false;
    } else {
        $pdo->prepare(
            "INSERT INTO customer_favourites
                (customer_id, product_id, created_at)
             VALUES
                (:customer_id, :product_id, NOW())"
        )->execute([
            'customer_id' => $customerId,
            'product_id' => $productId,
        ]);
        $isFavourite = true;
    }

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM customer_favourites
         WHERE customer_id = :customer_id"
    );
    $countStmt->execute(['customer_id' => $customerId]);
    $favouriteCount = (int)$countStmt->fetchColumn();

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($wantsJson) {
        $jsonResponse([
            'success' => false,
            'message' => 'Unable to update favourites right now. Please try again.',
        ], 500);
    }

    header('Location: ' . $return);
    exit;
}

if ($wantsJson) {
    $jsonResponse([
        'success' => true,
        'is_favourite' => $isFavourite,
        'favourite_count' => $favouriteCount,
        'product_id' => $productId,
        'message' => $isFavourite
            ? 'Added to favourites.'
            : 'Removed from favourites.',
    ]);
}

header('Location: ' . $return);
exit;
