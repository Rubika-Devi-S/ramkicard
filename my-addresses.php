<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';
sf_require_customer_login('login.php', 'my-addresses.php');

$customerId = sf_customer_id();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sf_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your page session expired. Refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'save');
        $addressId = max(0, (int)($_POST['address_id'] ?? 0));

        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM customer_addresses WHERE id = :id AND customer_id = :customer_id")
                ->execute(['id' => $addressId, 'customer_id' => $customerId]);
            $success = 'Address removed.';
        } elseif ($action === 'default') {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = :customer_id")
                    ->execute(['customer_id' => $customerId]);
                $pdo->prepare("UPDATE customer_addresses SET is_default = 1 WHERE id = :id AND customer_id = :customer_id")
                    ->execute(['id' => $addressId, 'customer_id' => $customerId]);
                $pdo->commit();
                $success = 'Default address updated.';
            } catch (Throwable $exception) {
                $pdo->rollBack();
                $error = 'Unable to update the default address.';
            }
        } else {
            $contactName = trim((string)($_POST['contact_name'] ?? ''));
            $phone = sf_phone_digits((string)($_POST['phone'] ?? ''));
            $line1 = trim((string)($_POST['address_line_1'] ?? ''));
            $line2 = trim((string)($_POST['address_line_2'] ?? ''));
            $landmark = trim((string)($_POST['landmark'] ?? ''));
            $city = trim((string)($_POST['city'] ?? ''));
            $district = trim((string)($_POST['district'] ?? ''));
            $state = trim((string)($_POST['state'] ?? 'Tamil Nadu'));
            $postalCode = trim((string)($_POST['postal_code'] ?? ''));
            $country = trim((string)($_POST['country'] ?? 'India'));
            $type = in_array($_POST['address_type'] ?? '', ['billing', 'shipping', 'other'], true)
                ? (string)$_POST['address_type']
                : 'shipping';

            if ($contactName === '' || !preg_match('/^[0-9]{10}$/', $phone) || $line1 === '' || $city === '' || $state === '' || !preg_match('/^[0-9]{6}$/', $postalCode)) {
                $error = 'Complete all required address fields correctly.';
            } else {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_addresses WHERE customer_id = :customer_id");
                $countStmt->execute(['customer_id' => $customerId]);
                $isFirst = (int)$countStmt->fetchColumn() === 0;

                $pdo->prepare(
                    "INSERT INTO customer_addresses
                    (
                        customer_id,
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
                        country,
                        is_default
                    )
                    VALUES
                    (
                        :customer_id,
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
                        :country,
                        :is_default
                    )"
                )->execute([
                    'customer_id' => $customerId,
                    'address_type' => $type,
                    'contact_name' => $contactName,
                    'phone' => $phone,
                    'address_line_1' => $line1,
                    'address_line_2' => $line2 !== '' ? $line2 : null,
                    'landmark' => $landmark !== '' ? $landmark : null,
                    'city' => $city,
                    'district' => $district !== '' ? $district : null,
                    'state' => $state,
                    'postal_code' => $postalCode,
                    'country' => $country,
                    'is_default' => $isFirst ? 1 : 0,
                ]);
                $success = 'Address saved successfully.';
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM customer_addresses WHERE customer_id = :customer_id ORDER BY is_default DESC, updated_at DESC, id DESC");
$stmt->execute(['customer_id' => $customerId]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Addresses';
$topStripItems = [];
$storefrontBase = '';
require __DIR__ . '/includes/storefront-header.php';
?>
<main class="store-page commerce-page account-page">
  <div class="container account-layout">
    <?php $accountActive = 'addresses'; require __DIR__ . '/includes/customer-account-nav.php'; ?>
    <section class="account-content">
      <div class="account-page-heading"><h1>My Addresses</h1><p>Save delivery and billing addresses for future orders.</p></div>
      <?php if ($error !== ''): ?><div class="purchase-message error"><?= sf_e($error); ?></div><?php endif; ?>
      <?php if ($success !== ''): ?><div class="purchase-message success"><?= sf_e($success); ?></div><?php endif; ?>

      <div class="account-panel glass-card">
        <h2>Saved Addresses</h2>
        <?php if (!$addresses): ?><div class="empty-state"><p>No saved addresses.</p></div><?php else: ?>
          <div class="address-list">
            <?php foreach ($addresses as $address): ?>
              <div class="address-card">
                <?php if ((int)$address['is_default'] === 1): ?><span class="default-label">DEFAULT</span><?php endif; ?>
                <strong><?= sf_e($address['contact_name']); ?></strong>
                <p><?= sf_e($address['phone']); ?><br><?= sf_e($address['address_line_1']); ?><?= $address['address_line_2'] ? ', ' . sf_e($address['address_line_2']) : ''; ?><br><?= sf_e($address['city']); ?>, <?= sf_e($address['state']); ?> - <?= sf_e($address['postal_code']); ?><br><?= sf_e($address['country']); ?></p>
                <div class="address-actions">
                  <?php if ((int)$address['is_default'] !== 1): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>"><input type="hidden" name="action" value="default"><input type="hidden" name="address_id" value="<?= (int)$address['id']; ?>"><button class="product-action-btn" type="submit">Make Default</button></form><?php endif; ?>
                  <form method="POST" onsubmit="return confirm('Remove this address?')"><input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="address_id" value="<?= (int)$address['id']; ?>"><button class="product-action-btn" type="submit">Delete</button></form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="account-panel glass-card">
        <h2>Add Address</h2>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>">
          <input type="hidden" name="action" value="save">
          <div class="address-form-grid">
            <div><label>Address Type</label><select name="address_type"><option value="shipping">Shipping</option><option value="billing">Billing</option><option value="other">Other</option></select></div>
            <div><label>Contact Name</label><input type="text" name="contact_name" maxlength="150" required></div>
            <div><label>Mobile Number</label><input type="tel" name="phone" pattern="[0-9]{10}" maxlength="10" required></div>
            <div class="full"><label>Address Line 1</label><input type="text" name="address_line_1" maxlength="255" required></div>
            <div class="full"><label>Address Line 2</label><input type="text" name="address_line_2" maxlength="255"></div>
            <div class="full"><label>Landmark</label><input type="text" name="landmark" maxlength="255"></div>
            <div><label>City</label><input type="text" name="city" maxlength="100" required></div>
            <div><label>District</label><input type="text" name="district" maxlength="100"></div>
            <div><label>State</label><input type="text" name="state" value="Tamil Nadu" maxlength="100" required></div>
            <div><label>Postal Code</label><input type="text" name="postal_code" pattern="[0-9]{6}" maxlength="6" required></div>
            <div class="full"><label>Country</label><input type="text" name="country" value="India" maxlength="100" required></div>
            <div class="full"><button class="submit-btn" type="submit">Save Address</button></div>
          </div>
        </form>
      </div>
    </section>
  </div>
</main>
<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
