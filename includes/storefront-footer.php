<?php
declare(strict_types=1);

$storefrontBase = isset($storefrontBase)
    ? (string)$storefrontBase
    : '';

$drawerCustomerLoggedIn = sf_customer_logged_in();

$footerProductsUrl =
    $storefrontBase . 'products.php';

$footer = sf_section($pdo, 'footer');
$companyName = sf_setting($pdo, 'company_name', 'Ramki Cards');
$phoneNumber = sf_setting($pdo, 'phone_number', '96299 54411');
$secondaryPhoneNumber = sf_setting($pdo, 'secondary_phone_number');
$whatsappNumber = sf_setting($pdo, 'whatsapp_number', $phoneNumber);
$emailAddress = sf_setting($pdo, 'email_address', 'info@ramkicards.com');
$address = sf_setting($pdo, 'address', 'Chennai, Tamil Nadu, India');
$facebook = sf_setting($pdo, 'facebook_url');
$instagram = sf_setting($pdo, 'instagram_url');
$youtube = sf_setting($pdo, 'youtube_url');
$logoPath = sf_media_path(
    sf_setting($pdo, 'logo_path', 'logo.png'),
    'logo.png'
);

if (!preg_match('#^https?://#i', $logoPath)) {
    $logoPath = $storefrontBase . ltrim($logoPath, '/');
}
?>
<footer>
  <div class="container">
    <div class="footer-grid">
      <div class="footer-col">
        <div class="footer-brand">
          <div class="brand-logo footer-brand-logo">
            <img
              src="<?= sf_e($logoPath); ?>"
              alt="<?= sf_e($companyName); ?> Logo"
            >
          </div>
          <div>
            <h2><?= sf_e($companyName); ?></h2>
            <p><?= sf_e($footer['section_subtitle'] ?? 'Celebrate your beginning'); ?></p>
          </div>
        </div>

        <p><?= nl2br(sf_e(
            $footer['section_content']
            ?? 'Creating beautiful memories with premium invitations, custom printing and luxury designs.'
        )); ?></p>

        <div class="socials">
          <?php if ($facebook !== ''): ?>
            <a href="<?= sf_e($facebook); ?>" target="_blank" rel="noopener">f</a>
          <?php endif; ?>
          <?php if ($instagram !== ''): ?>
            <a href="<?= sf_e($instagram); ?>" target="_blank" rel="noopener">◎</a>
          <?php endif; ?>
          <?php if ($youtube !== ''): ?>
            <a href="<?= sf_e($youtube); ?>" target="_blank" rel="noopener">▶</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="footer-col">
        <h3>Quick Links</h3>
        <ul class="footer-links">
          <li><a href="<?= sf_e($storefrontBase); ?>index.php">Home</a></li>
          <li>
            <a href="<?= sf_e($footerProductsUrl); ?>">
              Products
            </a>
          </li>
          <li><a href="<?= sf_e($storefrontBase); ?>services.php">Services</a></li>
          <li><a href="<?= sf_e($storefrontBase); ?>services.php#gallery">Gallery</a></li>
          <li><a href="<?= sf_e($storefrontBase); ?>about.php">About Us</a></li>
          <li><a href="<?= sf_e($storefrontBase); ?>index.php#contact">Contact Us</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h3>Contact</h3>
        <ul class="footer-links">
          <li>
            <a href="tel:<?= sf_e(sf_phone_digits($phoneNumber)); ?>">
              <?= sf_e($phoneNumber); ?>
            </a>
          </li>
          <?php if (
              $secondaryPhoneNumber !== ''
              && sf_phone_digits($secondaryPhoneNumber)
                  !== sf_phone_digits($phoneNumber)
          ): ?>
          <li>
            <a href="tel:<?= sf_e(sf_phone_digits($secondaryPhoneNumber)); ?>">
              <?= sf_e($secondaryPhoneNumber); ?>
            </a>
          </li>
          <?php endif; ?>
          <li>
            <a href="mailto:<?= sf_e($emailAddress); ?>">
              <?= sf_e($emailAddress); ?>
            </a>
          </li>
          <li><?= nl2br(sf_e($address)); ?></li>
        </ul>
      </div>

      <div class="footer-col">
        <h3>Quick Enquiry</h3>
        <p>Chat directly with our team on WhatsApp.</p>
        <p>
          <?php
          $footerWhatsAppUrl = sf_whatsapp_url(
              $whatsappNumber,
              'Hello Ramki Cards, I would like to know more about your products.'
          );

          ?>

          <a
            class="wa-btn"
            href="<?= sf_e($footerWhatsAppUrl); ?>"
            target="_blank"
            rel="noopener"
          >
            Chat on WhatsApp
          </a>
        </p>
      </div>
    </div>

    <div class="copyright">
      <p>© <?= date('Y'); ?> <?= sf_e($companyName); ?>. All Rights Reserved.</p>
      <p>
        <a href="<?= sf_e($storefrontBase); ?>page.php?slug=privacy-policy">Privacy Policy</a>
        <a href="<?= sf_e($storefrontBase); ?>page.php?slug=terms">Terms & Conditions</a>
      </p>
    </div>
  </div>
</footer>

<?php
$floatingWhatsAppUrl = sf_whatsapp_url(
    $whatsappNumber,
    'Hello Ramki Cards, I would like to enquire about invitation cards.'
);

?>

<a
  href="<?= sf_e($floatingWhatsAppUrl); ?>"
  class="whatsapp-float"
  target="_blank"
  rel="noopener"
  aria-label="Chat on WhatsApp"
>
  <span class="tooltip">Chat with us</span>
  <svg viewBox="0 0 24 24" width="36" height="36" fill="white">
    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.981.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.895 6.993c-.003 5.45-4.437 9.884-9.887 9.884"/>
  </svg>
</a>


<div
  class="cart-drawer-overlay"
  id="cartDrawerOverlay"
  hidden
></div>

<aside
  class="cart-drawer"
  id="cartDrawer"
  aria-hidden="true"
  aria-labelledby="cartDrawerTitle"
  data-summary-url="<?= sf_e($storefrontBase); ?>cart-drawer-api.php"
  data-login-url="<?= sf_e(sf_login_required_url(
      sf_current_return_url('index.php')
  )); ?>"
>
  <div class="cart-drawer-header">
    <div>
      <small>Your cart</small>
      <h2 id="cartDrawerTitle">
        Shopping Cart
        <span
          class="cart-drawer-count"
          data-cart-drawer-count
        >0</span>
      </h2>
    </div>

    <button
      type="button"
      class="cart-drawer-close"
      data-cart-close
      aria-label="Close shopping cart"
    >×</button>
  </div>

  <div
    class="cart-drawer-body"
    id="cartDrawerBody"
    aria-live="polite"
  >
    <div class="cart-drawer-loading">
      <span class="cart-loader"></span>
      Loading your cart...
    </div>
  </div>

  <div class="cart-drawer-footer" id="cartDrawerFooter">
    <div class="cart-drawer-subtotal">
      <span>Subtotal</span>
      <strong data-cart-subtotal>₹0.00</strong>
    </div>

    <a
      href="<?= sf_e($storefrontBase); ?>checkout.php"
      class="cart-checkout-button"
    >
      Proceed to Checkout
    </a>

    <a
      href="<?= sf_e($storefrontBase); ?>cart.php"
      class="cart-full-link"
    >
      View Full Cart
    </a>
  </div>
</aside>

<div
  class="cart-toast"
  id="cartToast"
  role="status"
  aria-live="polite"
></div>


<script>
(() => {
  'use strict';

  const menuButton = document.getElementById('mobileMenuButton');
  const navigation = document.getElementById('storeNavigation');

  function closeMobileMenu() {
    navigation?.classList.remove('show');
    menuButton?.setAttribute('aria-expanded', 'false');
  }

  menuButton?.addEventListener('click', () => {
    const isOpen = navigation?.classList.toggle('show') ?? false;
    menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  navigation?.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', closeMobileMenu);
  });

  const accountMenu = document.querySelector('[data-account-menu]');
  const accountTrigger = document.querySelector('[data-account-trigger]');

  accountTrigger?.addEventListener('click', event => {
    event.stopPropagation();
    const isOpen = accountMenu?.classList.toggle('open') ?? false;
    accountTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  document.addEventListener('click', event => {
    if (accountMenu && !accountMenu.contains(event.target)) {
      accountMenu.classList.remove('open');
      accountTrigger?.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      closeMobileMenu();
      accountMenu?.classList.remove('open');
      accountTrigger?.setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>

<script
  src="<?= sf_e($storefrontBase); ?>assets/js/storefront-cart.js?v=20260730-1"
  defer
></script>
</body>
</html>
