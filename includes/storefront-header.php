<?php
declare(strict_types=1);

$companyName = sf_setting(
    $pdo,
    'company_name',
    'Ramki Cards'
);

$phoneNumber = sf_setting(
    $pdo,
    'phone_number',
    '96299 54411'
);

$whatsappNumber = sf_setting(
    $pdo,
    'whatsapp_number',
    $phoneNumber
);

$logoPath = sf_media_path(
    sf_setting(
        $pdo,
        'logo_path',
        'logo.png'
    ),
    'logo.png'
);

$customerLoggedIn = sf_customer_logged_in();
$adminLoggedIn = sf_admin_logged_in();

$cartCount = $customerLoggedIn
    ? sf_cart_count($pdo)
    : 0;

$pageTitle = $pageTitle ?? $companyName;

$currentReturn = sf_current_return_url(
    'index.php'
);

$loginUrl = sf_login_required_url(
    $currentReturn
);

$productsUrl = 'products.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
  >
  <meta
    name="csrf-token"
    content="<?= sf_e(sf_csrf_token()); ?>"
  >
  <title><?= sf_e($pageTitle); ?></title>
  <link
    href="https://fonts.googleapis.com/css2?family=Noto+Sans+Tamil:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap"
    rel="stylesheet"
  >
  <link
    href="assets/css/storefront.css?v=20260730-8"
    rel="stylesheet"
  >
  <link
    href="assets/css/storefront-auth-gate.css?v=20260730-2"
    rel="stylesheet"
  >
  <link
    href="assets/css/storefront-commerce-ui.css?v=20260730-1"
    rel="stylesheet"
  >
</head>
<body>
<?php if (!empty($topStripItems) && is_array($topStripItems)): ?>
<div class="top-strip">
  <div class="top-strip-track">
    <?php for ($copy = 0; $copy < 2; $copy++): ?>
      <div
        class="top-strip-content"
        <?= $copy === 1 ? 'aria-hidden="true"' : ''; ?>
      >
        <?php foreach ($topStripItems as $item): ?>
          <span>
            <?= sf_e($item['icon'] ?? '✨'); ?>
            <?= sf_e($item['text'] ?? ''); ?>
          </span>
        <?php endforeach; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<header>
  <div class="container navbar">
    <a
      href="index.php"
      class="brand"
      aria-label="<?= sf_e($companyName); ?>"
    >
      <div class="brand-logo">
        <img
          src="<?= sf_e($logoPath); ?>"
          alt="<?= sf_e($companyName); ?> Logo"
        >
      </div>
    </a>

    <nav id="storeNavigation">
      <ul>
        <li><a href="index.php">Home</a></li>
        <li>
          <a href="<?= sf_e($productsUrl); ?>">
            Products
          </a>
        </li>
        <li><a href="index.php#services">Services</a></li>
        <li><a href="index.php#custom">Custom Design</a></li>
        <li><a href="index.php#why">About</a></li>
        <li><a href="index.php#contact">Contact</a></li>
      </ul>
    </nav>

    <div class="store-nav-actions">
      <?php if ($customerLoggedIn): ?>
        <a
          href="cart.php"
          class="quote-btn cart-link"
          aria-label="Open shopping cart"
          data-cart-open
        >
          <span aria-hidden="true">🛒</span>
          <span>Cart</span>

          <?php if ($cartCount > 0): ?>
            <span class="cart-count" data-cart-count>
              <?= $cartCount; ?>
            </span>
          <?php endif; ?>
        </a>

        <div
          class="store-account-menu"
          data-account-menu
        >
          <button
            type="button"
            class="store-account-trigger"
            data-account-trigger
            aria-haspopup="true"
            aria-expanded="false"
          >
            <span class="store-account-avatar">
              <?= sf_e(
                  strtoupper(
                      substr(
                          sf_customer_name(),
                          0,
                          1
                      )
                  )
              ); ?>
            </span>

            <span class="store-account-copy">
              <small>My account</small>
              <strong>
                <?= sf_e(sf_customer_name()); ?>
              </strong>
            </span>

            <span aria-hidden="true">⌄</span>
          </button>

          <div class="store-account-dropdown">
            <a href="my-account.php">
              Dashboard
            </a>
            <a href="my-orders.php">
              My Orders
            </a>
            <a href="my-favourites.php">
              Favourites
            </a>
            <a href="my-profile.php">
              Profile
            </a>
            <a href="logout.php" class="logout-link">
              Logout
            </a>
          </div>
        </div>

      <?php elseif ($adminLoggedIn): ?>
        <a
          href="admin/dashboard.php"
          class="quote-btn storefront-admin-button"
        >
          <span aria-hidden="true">⚙</span>
          Admin Panel
        </a>

      <?php else: ?>
        <a
          href="<?= sf_e($loginUrl); ?>"
          class="quote-btn storefront-login-button"
          aria-label="Login to continue"
        >
          <span
            class="storefront-login-icon"
            aria-hidden="true"
          >👤</span>

          <span class="storefront-login-copy">
            <small>Welcome</small>
            <strong>Login</strong>
          </span>
        </a>
      <?php endif; ?>

      <button
        type="button"
        class="mobile-menu"
        id="mobileMenuButton"
        aria-label="Open menu"
        aria-controls="storeNavigation"
        aria-expanded="false"
      >☰</button>
    </div>
  </div>
</header>
