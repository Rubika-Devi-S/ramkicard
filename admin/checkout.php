<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$cartId = sf_active_cart_id($pdo, false);

if ($cartId <= 0) {
    header('Location: cart.php');
    exit;
}

function checkout_items(PDO $pdo, int $cartId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            ci.*,
            p.*,
            p.id AS product_id_value,
            cv.color_name,
            cv.price_adjustment AS color_adjustment,
            dv.design_name,
            dv.price_adjustment AS design_adjustment
         FROM cart_items ci
         INNER JOIN products p ON p.id = ci.product_id
         LEFT JOIN product_color_variants cv
            ON cv.id = ci.color_variant_id
         LEFT JOIN product_design_variants dv
            ON dv.id = ci.design_variant_id
         WHERE ci.cart_id = :cart_id
         ORDER BY ci.id
         FOR UPDATE"
    );

    $stmt->execute(['cart_id' => $cartId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$displayItems = [];
$stmt = $pdo->prepare(
    "SELECT
        ci.*,
        p.product_name,
        p.product_name_tamil,
        p.thumbnail_path,
        p.minimum_order_qty,
        p.quantity_step,
        cv.color_name,
        dv.design_name
     FROM cart_items ci
     INNER JOIN products p ON p.id = ci.product_id
     LEFT JOIN product_color_variants cv
        ON cv.id = ci.color_variant_id
     LEFT JOIN product_design_variants dv
        ON dv.id = ci.design_variant_id
     WHERE ci.cart_id = :cart_id
     ORDER BY ci.id"
);
$stmt->execute(['cart_id' => $cartId]);
$displayItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$displayItems) {
    header('Location: cart.php');
    exit;
}

$displaySubtotal = 0.0;

foreach ($displayItems as $item) {
    $displaySubtotal +=
        (float)$item['unit_price_snapshot']
        * (int)$item['quantity'];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sf_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your page session expired. Refresh and try again.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = sf_phone_digits((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $address1 = trim((string)($_POST['address_line_1'] ?? ''));
        $address2 = trim((string)($_POST['address_line_2'] ?? ''));
        $landmark = trim((string)($_POST['landmark'] ?? ''));
        $city = trim((string)($_POST['city'] ?? ''));
        $district = trim((string)($_POST['district'] ?? ''));
        $state = trim((string)($_POST['state'] ?? 'Tamil Nadu'));
        $postalCode = trim((string)($_POST['postal_code'] ?? ''));
        $country = trim((string)($_POST['country'] ?? 'India'));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($name === '' || mb_strlen($name) > 150) {
            $error = 'Enter a valid customer name.';
        } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
            $error = 'Enter a valid 10-digit mobile number.';
        } elseif (
            $email !== ''
            && !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $error = 'Enter a valid email address.';
        } elseif (
            $address1 === ''
            || $city === ''
            || $state === ''
            || $postalCode === ''
            || $country === ''
        ) {
            $error = 'Complete the required delivery address fields.';
        } elseif (!preg_match('/^[0-9]{6}$/', $postalCode)) {
            $error = 'Enter a valid 6-digit postal code.';
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();

                $items = checkout_items($pdo, $cartId);

                if (!$items) {
                    throw new RuntimeException('Your cart is empty.');
                }

                $subtotal = 0.0;
                $validatedItems = [];

                foreach ($items as $item) {
                    if (
                        $item['status'] !== 'active'
                        || $item['deleted_at'] !== null
                    ) {
                        throw new RuntimeException(
                            $item['product_name']
                            . ' is no longer available.'
                        );
                    }

                    $mode = sf_purchase_mode($pdo, $item);

                    if (!in_array($mode, ['checkout', 'both'], true)) {
                        throw new RuntimeException(
                            $item['product_name']
                            . ' is not available for checkout.'
                        );
                    }

                    $quantity = (int)$item['quantity'];

                    if (!sf_valid_quantity($item, $quantity)) {
                        throw new RuntimeException(
                            'Invalid quantity for '
                            . $item['product_name']
                            . '.'
                        );
                    }

                    if (
                        (int)$item['manage_stock'] === 1
                        && $quantity > (int)$item['stock_quantity']
                    ) {
                        throw new RuntimeException(
                            'Insufficient stock for '
                            . $item['product_name']
                            . '.'
                        );
                    }

                    $baseEffective = sf_effective_price($item);
                    $variantAdjustment =
                        (float)($item['color_adjustment'] ?? 0)
                        + (float)($item['design_adjustment'] ?? 0);

                    $finalUnitPrice =
                        max(0, $baseEffective + $variantAdjustment);

                    $lineTotal = $finalUnitPrice * $quantity;
                    $subtotal += $lineTotal;

                    $item['base_effective_price'] = $baseEffective;
                    $item['variant_adjustment_total'] =
                        $variantAdjustment;
                    $item['final_unit_price_value'] = $finalUnitPrice;
                    $item['line_total_value'] = $lineTotal;

                    $validatedItems[] = $item;
                }

                $shippingEnabled =
                    sf_setting($pdo, 'shipping_enabled', '1') === '1';

                $shippingAmount = $shippingEnabled
                    ? max(
                        0,
                        (float)sf_setting(
                            $pdo,
                            'flat_shipping_amount',
                            '0'
                        )
                    )
                    : 0.0;

                $taxEnabled =
                    sf_setting($pdo, 'tax_enabled', '0') === '1';

                $taxPercentage = $taxEnabled
                    ? max(
                        0,
                        (float)sf_setting(
                            $pdo,
                            'tax_percentage',
                            '0'
                        )
                    )
                    : 0.0;

                $taxAmount = round(
                    $subtotal * $taxPercentage / 100,
                    2
                );

                $grandTotal =
                    $subtotal + $shippingAmount + $taxAmount;

                $customerId = sf_find_or_create_customer(
                    $pdo,
                    $name,
                    $phone,
                    $email
                );

                $orderNumber = sf_next_number(
                    $pdo,
                    'order',
                    'RKC'
                );

                $orderStmt = $pdo->prepare(
                    "INSERT INTO orders
                    (
                        order_number,
                        customer_id,
                        enquiry_id,
                        order_source,
                        status,
                        payment_status,
                        customer_name,
                        customer_email,
                        customer_phone,
                        currency,
                        subtotal,
                        discount_amount,
                        shipping_amount,
                        tax_amount,
                        grand_total,
                        customer_notes
                    )
                    VALUES
                    (
                        :order_number,
                        :customer_id,
                        NULL,
                        'website_checkout',
                        'new',
                        'pending',
                        :customer_name,
                        :customer_email,
                        :customer_phone,
                        :currency,
                        :subtotal,
                        0,
                        :shipping_amount,
                        :tax_amount,
                        :grand_total,
                        :customer_notes
                    )"
                );

                $orderStmt->execute([
                    'order_number' => $orderNumber,
                    'customer_id' => $customerId,
                    'customer_name' => $name,
                    'customer_email' => $email !== '' ? $email : null,
                    'customer_phone' => $phone,
                    'currency' => sf_setting($pdo, 'currency', 'INR'),
                    'subtotal' => $subtotal,
                    'shipping_amount' => $shippingAmount,
                    'tax_amount' => $taxAmount,
                    'grand_total' => $grandTotal,
                    'customer_notes' => $notes !== '' ? $notes : null,
                ]);

                $orderId = (int)$pdo->lastInsertId();

                $itemStmt = $pdo->prepare(
                    "INSERT INTO order_items
                    (
                        order_id,
                        product_id,
                        color_variant_id,
                        design_variant_id,
                        product_name_snapshot,
                        sku_snapshot,
                        thumbnail_snapshot,
                        selected_color_name,
                        selected_design_name,
                        minimum_qty_snapshot,
                        quantity_step_snapshot,
                        quantity,
                        base_unit_price,
                        variant_adjustment,
                        discount_amount,
                        final_unit_price,
                        line_total,
                        customer_item_notes
                    )
                    VALUES
                    (
                        :order_id,
                        :product_id,
                        :color_variant_id,
                        :design_variant_id,
                        :product_name_snapshot,
                        :sku_snapshot,
                        :thumbnail_snapshot,
                        :selected_color_name,
                        :selected_design_name,
                        :minimum_qty_snapshot,
                        :quantity_step_snapshot,
                        :quantity,
                        :base_unit_price,
                        :variant_adjustment,
                        0,
                        :final_unit_price,
                        :line_total,
                        :customer_item_notes
                    )"
                );

                $stockStmt = $pdo->prepare(
                    "UPDATE products
                     SET stock_quantity = stock_quantity - :quantity
                     WHERE id = :product_id
                       AND manage_stock = 1
                       AND stock_quantity >= :quantity_check"
                );

                foreach ($validatedItems as $item) {
                    $itemStmt->execute([
                        'order_id' => $orderId,
                        'product_id' => (int)$item['product_id_value'],
                        'color_variant_id' =>
                            $item['color_variant_id'] ?: null,
                        'design_variant_id' =>
                            $item['design_variant_id'] ?: null,
                        'product_name_snapshot' => $item['product_name'],
                        'sku_snapshot' => $item['sku'] ?: null,
                        'thumbnail_snapshot' => $item['thumbnail_path'],
                        'selected_color_name' =>
                            $item['color_name'] ?: null,
                        'selected_design_name' =>
                            $item['design_name'] ?: null,
                        'minimum_qty_snapshot' =>
                            (int)$item['minimum_order_qty'],
                        'quantity_step_snapshot' =>
                            (int)$item['quantity_step'],
                        'quantity' => (int)$item['quantity'],
                        'base_unit_price' =>
                            $item['base_effective_price'],
                        'variant_adjustment' =>
                            $item['variant_adjustment_total'],
                        'final_unit_price' =>
                            $item['final_unit_price_value'],
                        'line_total' => $item['line_total_value'],
                        'customer_item_notes' =>
                            $item['customer_item_notes'] ?: null,
                    ]);

                    if ((int)$item['manage_stock'] === 1) {
                        $stockStmt->execute([
                            'quantity' => (int)$item['quantity'],
                            'product_id' => (int)$item['product_id_value'],
                            'quantity_check' => (int)$item['quantity'],
                        ]);

                        if ($stockStmt->rowCount() !== 1) {
                            throw new RuntimeException(
                                'Stock changed while placing the order.'
                            );
                        }
                    }
                }

                $addressStmt = $pdo->prepare(
                    "INSERT INTO order_addresses
                    (
                        order_id,
                        address_type,
                        contact_name,
                        phone,
                        address_line_1,
                        address_line_2,
                        landmark,
                        city,
                        district,
                        state,
                        postal_code,
                        country
                    )
                    VALUES
                    (
                        :order_id,
                        :address_type,
                        :contact_name,
                        :phone,
                        :address_line_1,
                        :address_line_2,
                        :landmark,
                        :city,
                        :district,
                        :state,
                        :postal_code,
                        :country
                    )"
                );

                foreach (['shipping', 'billing'] as $addressType) {
                    $addressStmt->execute([
                        'order_id' => $orderId,
                        'address_type' => $addressType,
                        'contact_name' => $name,
                        'phone' => $phone,
                        'address_line_1' => $address1,
                        'address_line_2' =>
                            $address2 !== '' ? $address2 : null,
                        'landmark' =>
                            $landmark !== '' ? $landmark : null,
                        'city' => $city,
                        'district' =>
                            $district !== '' ? $district : null,
                        'state' => $state,
                        'postal_code' => $postalCode,
                        'country' => $country,
                    ]);
                }

                $paymentStmt = $pdo->prepare(
                    "INSERT INTO payments
                    (
                        order_id,
                        payment_gateway,
                        payment_method,
                        amount,
                        currency,
                        status
                    )
                    VALUES
                    (
                        :order_id,
                        :payment_gateway,
                        'pending_selection',
                        :amount,
                        :currency,
                        'pending'
                    )"
                );

                $paymentStmt->execute([
                    'order_id' => $orderId,
                    'payment_gateway' =>
                        sf_setting($pdo, 'gateway_name', 'manual'),
                    'amount' => $grandTotal,
                    'currency' => sf_setting($pdo, 'currency', 'INR'),
                ]);

                $cartStmt = $pdo->prepare(
                    "UPDATE carts
                     SET customer_id = :customer_id,
                         status = 'converted'
                     WHERE id = :cart_id
                       AND status = 'active'"
                );

                $cartStmt->execute([
                    'customer_id' => $customerId,
                    'cart_id' => $cartId,
                ]);

                $pdo->commit();

                $_SESSION['last_order_number'] = $orderNumber;
                unset($_SESSION['sf_cart_token']);

                $companyName = sf_setting(
                    $pdo,
                    'company_name',
                    'Ramki Cards'
                );

                if ($email !== '') {
                    sf_send_mail(
                        $pdo,
                        $email,
                        'Order received ' . $orderNumber,
                        implode("\n", [
                            'Dear ' . $name . ',',
                            '',
                            'Your order has been received.',
                            'Order number: ' . $orderNumber,
                            'Grand total: ' . sf_money($grandTotal),
                            'Payment status: Pending',
                            '',
                            'Our team will contact you with payment and delivery details.',
                            '',
                            'Regards,',
                            $companyName,
                        ])
                    );
                }

                header(
                    'Location: order-success.php?order='
                    . rawurlencode($orderNumber)
                );
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Checkout failed: ' . $e->getMessage());

                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to place the order. Please try again.';
            }
        }
    }
}

$pageTitle = 'Checkout | '
    . sf_setting($pdo, 'company_name', 'Ramki Cards');

$topStripItems = [];
require __DIR__ . '/includes/storefront-header.php';
?>

<main class="store-page">
  <div class="container">
    <div class="section-title store-page-title">
      <div class="decor-line"><i></i></div>
      <span>Delivery and order details</span>
      <h2>Secure <em>Checkout</em></h2>
    </div>

    <?php if ($error !== ''): ?>
      <div class="purchase-message error">
        <?= sf_e($error); ?>
      </div>
    <?php endif; ?>

    <div class="checkout-grid">
      <form
        method="POST"
        action="checkout.php"
        class="store-panel glass-card"
      >
        <input
          type="hidden"
          name="csrf_token"
          value="<?= sf_e(sf_csrf_token()); ?>"
        >

        <h3>Customer Details</h3>

        <div class="form-grid" style="margin-top:16px">
          <div class="form-group">
            <input
              type="text"
              name="name"
              value="<?= sf_e($_POST['name'] ?? ''); ?>"
              placeholder="Full Name"
              maxlength="150"
              required
            >
          </div>

          <div class="form-group">
            <input
              type="tel"
              name="phone"
              value="<?= sf_e($_POST['phone'] ?? ''); ?>"
              placeholder="10-digit Mobile Number"
              pattern="[0-9]{10}"
              maxlength="10"
              required
            >
          </div>

          <div class="form-group full">
            <input
              type="email"
              name="email"
              value="<?= sf_e($_POST['email'] ?? ''); ?>"
              placeholder="Email Address (Optional)"
              maxlength="190"
            >
          </div>

          <div class="form-group full">
            <input
              type="text"
              name="address_line_1"
              value="<?= sf_e($_POST['address_line_1'] ?? ''); ?>"
              placeholder="Address Line 1"
              maxlength="255"
              required
            >
          </div>

          <div class="form-group full">
            <input
              type="text"
              name="address_line_2"
              value="<?= sf_e($_POST['address_line_2'] ?? ''); ?>"
              placeholder="Address Line 2 (Optional)"
              maxlength="255"
            >
          </div>

          <div class="form-group full">
            <input
              type="text"
              name="landmark"
              value="<?= sf_e($_POST['landmark'] ?? ''); ?>"
              placeholder="Landmark (Optional)"
              maxlength="255"
            >
          </div>

          <div class="form-group">
            <input
              type="text"
              name="city"
              value="<?= sf_e($_POST['city'] ?? ''); ?>"
              placeholder="City"
              maxlength="100"
              required
            >
          </div>

          <div class="form-group">
            <input
              type="text"
              name="district"
              value="<?= sf_e($_POST['district'] ?? ''); ?>"
              placeholder="District"
              maxlength="100"
            >
          </div>

          <div class="form-group">
            <input
              type="text"
              name="state"
              value="<?= sf_e($_POST['state'] ?? 'Tamil Nadu'); ?>"
              placeholder="State"
              maxlength="100"
              required
            >
          </div>

          <div class="form-group">
            <input
              type="text"
              name="postal_code"
              value="<?= sf_e($_POST['postal_code'] ?? ''); ?>"
              placeholder="6-digit Postal Code"
              pattern="[0-9]{6}"
              maxlength="6"
              required
            >
          </div>

          <div class="form-group full">
            <input
              type="text"
              name="country"
              value="<?= sf_e($_POST['country'] ?? 'India'); ?>"
              placeholder="Country"
              maxlength="100"
              required
            >
          </div>

          <div class="form-group full">
            <textarea
              name="notes"
              placeholder="Order and customization notes"
              maxlength="1500"
            ><?= sf_e($_POST['notes'] ?? ''); ?></textarea>
          </div>
        </div>

        <div class="notice">
          This version creates the order with payment status
          <strong>Pending</strong>. Connect the approved payment gateway
          after adding its credentials and callback verification.
        </div>

        <button class="submit-btn" type="submit">
          Place Order
        </button>
      </form>

      <aside class="store-panel glass-card checkout-summary">
        <h3>Order Summary</h3>

        <?php foreach ($displayItems as $item): ?>
          <?php
          $lineTotal =
              (float)$item['unit_price_snapshot']
              * (int)$item['quantity'];
          ?>
          <div class="summary-row" style="margin-top:14px">
            <span>
              <span class="checkout-product-name">
                <?= sf_e($item['product_name']); ?>

                <?php if (!empty(
                    $item['product_name_tamil']
                )): ?>
                  <small
                    class="product-name-tamil checkout-name-tamil"
                    lang="ta"
                  >
                    <?= sf_e(
                        $item['product_name_tamil']
                    ); ?>
                  </small>
                <?php endif; ?>
              </span>

              × <?= (int)$item['quantity']; ?>
            </span>
            <strong><?= sf_e(sf_money($lineTotal)); ?></strong>
          </div>
        <?php endforeach; ?>

        <div class="summary-row total">
          <span>Current Subtotal</span>
          <span><?= sf_e(sf_money($displaySubtotal)); ?></span>
        </div>
      </aside>
    </div>
  </div>
</main>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
