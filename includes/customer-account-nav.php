<?php
declare(strict_types=1);

$accountActive = $accountActive ?? '';
$customer = sf_customer_session();
$name = sf_customer_name();
$initial = strtoupper(substr($name, 0, 1)) ?: 'C';
?>
<aside class="account-sidebar glass-card">
  <div class="account-user-card">
    <span class="account-avatar"><?= sf_e($initial); ?></span>
    <strong><?= sf_e($name); ?></strong>
    <small>
      <?= sf_e(
          ($customer['email'] ?? '') !== ''
              ? $customer['email']
              : ($customer['phone'] ?? '')
      ); ?>
    </small>
  </div>

  <nav class="account-nav" aria-label="Customer account navigation">
    <a
      class="<?= $accountActive === 'dashboard' ? 'active' : ''; ?>"
      href="my-account.php"
    >Dashboard</a>

    <a
      class="<?= $accountActive === 'cart' ? 'active' : ''; ?>"
      href="cart.php"
    >My Cart</a>

    <a
      class="<?= $accountActive === 'favourites' ? 'active' : ''; ?>"
      href="my-favourites.php"
    >Favourites</a>

    <a
      class="<?= $accountActive === 'orders' ? 'active' : ''; ?>"
      href="my-orders.php"
    >Orders</a>

    <a
      class="<?= $accountActive === 'profile' ? 'active' : ''; ?>"
      href="my-profile.php"
    >Profile</a>

    <a
      class="<?= $accountActive === 'addresses' ? 'active' : ''; ?>"
      href="my-addresses.php"
    >Addresses</a>

    <a href="logout.php">Logout</a>
  </nav>
</aside>
