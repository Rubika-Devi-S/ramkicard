<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';
sf_require_customer_login('login.php', 'my-profile.php');

$customerId = sf_customer_id();
$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $customerId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$customer) {
    unset($_SESSION['ramki_customer']);
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sf_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your page session expired. Refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'profile');

        if ($action === 'password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            if (!password_verify($currentPassword, (string)$customer['password_hash'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($newPassword) < 8) {
                $error = 'New password must contain at least 8 characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New password confirmation does not match.';
            } else {
                $pdo->prepare("UPDATE customers SET password_hash = :password_hash WHERE id = :id")
                    ->execute([
                        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                        'id' => $customerId,
                    ]);
                $success = 'Password updated successfully.';
            }
        } else {
            $firstName = trim((string)($_POST['first_name'] ?? ''));
            $lastName = trim((string)($_POST['last_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $phone = sf_phone_digits((string)($_POST['phone'] ?? ''));

            if ($firstName === '' || mb_strlen($firstName) > 100) {
                $error = 'Enter a valid first name.';
            } elseif ($lastName !== '' && mb_strlen($lastName) > 100) {
                $error = 'Enter a valid last name.';
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid email address.';
            } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
                $error = 'Enter a valid 10-digit mobile number.';
            } else {
                $duplicate = $pdo->prepare(
                    "SELECT id
                     FROM customers
                     WHERE id <> :id
                       AND (
                           phone = :phone
                           OR (
                               :email_value <> ''
                               AND LOWER(COALESCE(email, '')) = LOWER(:email_match)
                           )
                       )
                     LIMIT 1"
                );
                $duplicate->execute([
                    'id' => $customerId,
                    'phone' => $phone,
                    'email_value' => $email,
                    'email_match' => $email,
                ]);

                if ($duplicate->fetchColumn()) {
                    $error = 'The mobile number or email is already linked to another customer account.';
                } else {
                    $pdo->prepare(
                        "UPDATE customers
                         SET first_name = :first_name,
                             last_name = :last_name,
                             email = :email,
                             phone = :phone
                         WHERE id = :id"
                    )->execute([
                        'first_name' => $firstName,
                        'last_name' => $lastName !== '' ? $lastName : null,
                        'email' => $email !== '' ? $email : null,
                        'phone' => $phone,
                        'id' => $customerId,
                    ]);

                    $_SESSION['ramki_customer'] = [
                        'id' => $customerId,
                        'name' => trim($firstName . ' ' . $lastName),
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'phone' => $phone,
                    ];

                    $success = 'Profile updated successfully.';
                    $stmt->execute(['id' => $customerId]);
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC) ?: $customer;
                }
            }
        }
    }
}

$pageTitle = 'My Profile';
$topStripItems = [];
$storefrontBase = '';
require __DIR__ . '/includes/storefront-header.php';
?>
<main class="store-page commerce-page account-page">
  <div class="container account-layout">
    <?php $accountActive = 'profile'; require __DIR__ . '/includes/customer-account-nav.php'; ?>
    <section class="account-content">
      <div class="account-page-heading"><h1>My Profile</h1><p>Update your customer contact details and password.</p></div>
      <?php if ($error !== ''): ?><div class="purchase-message error"><?= sf_e($error); ?></div><?php endif; ?>
      <?php if ($success !== ''): ?><div class="purchase-message success"><?= sf_e($success); ?></div><?php endif; ?>

      <div class="account-panel glass-card">
        <h2>Contact Details</h2>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>">
          <input type="hidden" name="action" value="profile">
          <div class="profile-form-grid">
            <div><label>First Name</label><input type="text" name="first_name" value="<?= sf_e($customer['first_name']); ?>" maxlength="100" required></div>
            <div><label>Last Name</label><input type="text" name="last_name" value="<?= sf_e($customer['last_name']); ?>" maxlength="100"></div>
            <div><label>Email Address</label><input type="email" name="email" value="<?= sf_e($customer['email']); ?>" maxlength="190"></div>
            <div><label>Mobile Number</label><input type="tel" name="phone" value="<?= sf_e($customer['phone']); ?>" pattern="[0-9]{10}" maxlength="10" required></div>
            <div class="full"><button class="submit-btn" type="submit">Save Profile</button></div>
          </div>
        </form>
      </div>

      <div class="account-panel glass-card">
        <h2>Change Password</h2>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>">
          <input type="hidden" name="action" value="password">
          <div class="profile-form-grid">
            <div class="full"><label>Current Password</label><input type="password" name="current_password" required></div>
            <div><label>New Password</label><input type="password" name="new_password" minlength="8" required></div>
            <div><label>Confirm New Password</label><input type="password" name="confirm_password" minlength="8" required></div>
            <div class="full"><button class="submit-btn" type="submit">Update Password</button></div>
          </div>
        </form>
      </div>
    </section>
  </div>
</main>
<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
