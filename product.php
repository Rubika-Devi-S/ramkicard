<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$customerLoggedIn = sf_customer_logged_in();

$stmt = $pdo->prepare(
    "SELECT p.*, c.category_name, c.slug AS category_slug
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     WHERE p.slug = :slug
       AND p.status = 'active'
       AND p.deleted_at IS NULL
       AND c.status = 'active'
       AND c.deleted_at IS NULL
     LIMIT 1"
);

$stmt->execute(['slug' => $slug]);
$product = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product Not Found';
    $topStripItems = [];
    require __DIR__ . '/includes/storefront-header.php';
    ?>
    
<style>
.product-enquiry-toast {
  position: fixed;
  top: 94px;
  right: 22px;
  z-index: 10030;
  display: grid;
  grid-template-columns: 48px minmax(0, 1fr) 34px;
  gap: 13px;
  align-items: center;
  width: min(440px, calc(100vw - 32px));
  padding: 16px;
  overflow: hidden;
  color: #253228;
  background: #fff;
  border: 1px solid rgba(21, 128, 61, 0.2);
  border-radius: 16px;
  box-shadow:
    0 24px 60px rgba(38, 20, 26, 0.22),
    0 6px 18px rgba(38, 20, 26, 0.08);
  opacity: 0;
  pointer-events: none;
  transform: translateX(calc(100% + 45px));
  transition:
    opacity 0.3s ease,
    transform 0.35s cubic-bezier(.22, 1, .36, 1);
}

.product-enquiry-toast.show {
  opacity: 1;
  pointer-events: auto;
  transform: translateX(0);
}

.product-enquiry-toast.error {
  border-color: rgba(190, 38, 63, 0.22);
}

.product-enquiry-toast-icon {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  color: #fff;
  font-size: 23px;
  font-weight: 800;
  background: linear-gradient(145deg, #20a957, #13773b);
  border-radius: 50%;
  box-shadow: 0 9px 22px rgba(19, 119, 59, 0.24);
}

.product-enquiry-toast.error .product-enquiry-toast-icon {
  background: linear-gradient(145deg, #d44258, #9d1830);
  box-shadow: 0 9px 22px rgba(157, 24, 48, 0.22);
}

.product-enquiry-toast-copy {
  min-width: 0;
}

.product-enquiry-toast-copy strong {
  display: block;
  margin-bottom: 3px;
  color: #176a38;
  font-family: "Playfair Display", Georgia, serif;
  font-size: 17px;
  line-height: 1.3;
}

.product-enquiry-toast.error .product-enquiry-toast-copy strong {
  color: #a21832;
}

.product-enquiry-toast-copy p {
  margin: 0;
  color: #6d6466;
  font-size: 12px;
  line-height: 1.55;
}

.product-enquiry-toast-copy b {
  color: #8f1231;
  font-weight: 800;
}

.product-enquiry-toast-close {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  padding: 0;
  color: #887d80;
  font-size: 24px;
  line-height: 1;
  background: #f8f3f4;
  border: 0;
  border-radius: 50%;
  cursor: pointer;
}

.product-enquiry-toast-close:hover {
  color: #fff;
  background: #8f1231;
}

.product-enquiry-toast-progress {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  height: 4px;
  background: rgba(21, 128, 61, 0.12);
}

.product-enquiry-toast-progress::after {
  content: "";
  display: block;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, #20a957, #d8a641);
  transform-origin: left center;
  animation: productEnquiryToastProgress 5.5s linear forwards;
}

.product-enquiry-toast.error .product-enquiry-toast-progress::after {
  background: linear-gradient(90deg, #d44258, #8f1231);
}

@keyframes productEnquiryToastProgress {
  from { transform: scaleX(1); }
  to { transform: scaleX(0); }
}

@media (max-width: 575.98px) {
  .product-enquiry-toast {
    top: auto;
    right: 16px;
    bottom: 18px;
    left: 16px;
    width: auto;
    grid-template-columns: 42px minmax(0, 1fr) 32px;
    padding: 14px;
    transform: translateY(calc(100% + 40px));
  }

  .product-enquiry-toast.show {
    transform: translateY(0);
  }

  .product-enquiry-toast-icon {
    width: 42px;
    height: 42px;
    font-size: 20px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .product-enquiry-toast {
    transition: none;
  }

  .product-enquiry-toast-progress::after {
    animation: none;
  }
}
</style>

<div
  class="product-enquiry-toast"
  id="productEnquiryToast"
  role="status"
  aria-live="polite"
  aria-atomic="true"
>
  <span
    class="product-enquiry-toast-icon"
    id="productEnquiryToastIcon"
    aria-hidden="true"
  >✓</span>

  <div class="product-enquiry-toast-copy">
    <strong id="productEnquiryToastTitle">
      Enquiry submitted successfully
    </strong>
    <p id="productEnquiryToastText"></p>
  </div>

  <button
    type="button"
    class="product-enquiry-toast-close"
    id="productEnquiryToastClose"
    aria-label="Close notification"
  >×</button>

  <span
    class="product-enquiry-toast-progress"
    aria-hidden="true"
  ></span>
</div>

<main class="store-page">
      <div class="container">
        <div class="empty-state glass-card">
          <h1>Product not found</h1>
          <p>The requested product is inactive or unavailable.</p>
          <a class="primary-btn" href="products.php">
            View Products
          </a>
        </div>
      </div>
    </main>
    <?php
    require __DIR__ . '/includes/storefront-footer.php';
    exit;
}

$productId = (int)$product['id'];

$stmt = $pdo->prepare(
    "SELECT image_path, alt_text
     FROM product_images
     WHERE product_id = :product_id
       AND status = 'active'
     ORDER BY sort_order, id"
);
$stmt->execute(['product_id' => $productId]);
$productImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    "SELECT *
     FROM product_color_variants
     WHERE product_id = :product_id
       AND status = 'active'
     ORDER BY sort_order, color_name"
);
$stmt->execute(['product_id' => $productId]);
$colors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    "SELECT *
     FROM product_design_variants
     WHERE product_id = :product_id
       AND status = 'active'
     ORDER BY sort_order, design_name"
);
$stmt->execute(['product_id' => $productId]);
$designs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mode = sf_purchase_mode($pdo, $product);
$effectivePrice = sf_effective_price($product);

$mainImage = sf_media_path(
    $product['thumbnail_path'],
    'banner.png'
);

$gallery = array_merge(
    [[
        'image_path' => $mainImage,
        'alt_text' => $product['product_name'],
    ]],
    $productImages
);

$pageTitle = ($product['meta_title'] ?: $product['product_name'])
    . ' | '
    . sf_setting($pdo, 'company_name', 'Ramki Cards');

$topStripItems = [];
require __DIR__ . '/includes/storefront-header.php';

$cartStatus = trim((string)($_GET['cart'] ?? ''));

$enquiryToast = $_SESSION['product_enquiry_toast'] ?? [];
unset($_SESSION['product_enquiry_toast']);

$enquiryNumber = trim((string)(
    $enquiryToast['number']
    ?? $_GET['enquiry']
    ?? ''
));

$enquiryToastMessage = trim((string)(
    $enquiryToast['message']
    ?? ''
));

$error = trim((string)($_GET['error'] ?? ''));
?>

<main class="store-page">
  <div class="container">
    <div class="product-detail-grid">
      <section class="store-panel glass-card">
        <img
          src="<?= sf_e($mainImage); ?>"
          alt="<?= sf_e($product['product_name']); ?>"
          class="product-main-image"
          id="mainProductImage"
        >

        <?php if (count($gallery) > 1): ?>
          <div class="product-gallery">
            <?php foreach ($gallery as $image): ?>
              <img
                src="<?= sf_e(sf_media_path(
                    $image['image_path'] ?? '',
                    $mainImage
                )); ?>"
                alt="<?= sf_e(
                    $image['alt_text']
                    ?: $product['product_name']
                ); ?>"
                loading="lazy"
                data-gallery-image
              >
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="product-detail-copy">
        <div class="eyebrow">
          <?= sf_e($product['category_name']); ?>
        </div>

        <h1><?= sf_e($product['product_name']); ?></h1>

        <?php if (!empty($product['sku'])): ?>
          <div class="moq-note">
            SKU: <?= sf_e($product['sku']); ?>
          </div>
        <?php endif; ?>

        <div class="detail-price">
          <?= sf_e(sf_money($effectivePrice)); ?>

          <?php if (
              $effectivePrice < (float)$product['base_price']
          ): ?>
            <span class="price-old">
              <?= sf_e(sf_money($product['base_price'])); ?>
            </span>
          <?php endif; ?>
        </div>

        <div class="notice">
          Minimum order:
          <strong><?= (int)$product['minimum_order_qty']; ?></strong>
          · Quantity step:
          <strong><?= (int)$product['quantity_step']; ?></strong>
        </div>

        <div class="product-description">
          <?= nl2br(sf_e(
              $product['description']
              ?: $product['short_description']
              ?: 'Contact us for complete product details and customization options.'
          )); ?>
        </div>

        <?php if ($cartStatus === 'added'): ?>
          <div class="purchase-message success">
            Product added to your cart.
            <a href="cart.php">View cart</a>
          </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
          <div class="purchase-message error">
            <?= sf_e($error); ?>
          </div>
        <?php endif; ?>

        <?php if (in_array($mode, ['checkout', 'both'], true)): ?>
          <?php if ($customerLoggedIn): ?>
          <form
            action="add_to_cart.php"
            method="POST"
            class="purchase-box js-add-to-cart-form"
            id="buy"
          >
            <h3>Buy / Add to Cart</h3>

            <input
              type="hidden"
              name="csrf_token"
              value="<?= sf_e(sf_csrf_token()); ?>"
            >

            <input
              type="hidden"
              name="product_id"
              value="<?= $productId; ?>"
            >

            <input
              type="hidden"
              name="return_url"
              value="product.php?slug=<?= rawurlencode(
                  (string)$product['slug']
              ); ?>#buy"
            >

            <div class="purchase-grid">
              <?php if ($colors): ?>
                <div>
                  <label for="buyColor">Colour</label>
                  <select id="buyColor" name="color_variant_id" required>
                    <option value="">Select Colour</option>
                    <?php foreach ($colors as $color): ?>
                      <option value="<?= (int)$color['id']; ?>">
                        <?= sf_e($color['color_name']); ?>
                        <?php if ((float)$color['price_adjustment'] !== 0.0): ?>
                          (<?= sf_e(sf_money(
                              $color['price_adjustment']
                          )); ?>)
                        <?php endif; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>

              <?php if ($designs): ?>
                <div>
                  <label for="buyDesign">Design</label>
                  <select
                    id="buyDesign"
                    name="design_variant_id"
                    required
                  >
                    <option value="">Select Design</option>
                    <?php foreach ($designs as $design): ?>
                      <option value="<?= (int)$design['id']; ?>">
                        <?= sf_e($design['design_name']); ?>
                        <?php if ((float)$design['price_adjustment'] !== 0.0): ?>
                          (<?= sf_e(sf_money(
                              $design['price_adjustment']
                          )); ?>)
                        <?php endif; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>

              <div>
                <label for="buyQuantity">Quantity</label>
                <input
                  id="buyQuantity"
                  type="number"
                  name="quantity"
                  min="<?= (int)$product['minimum_order_qty']; ?>"
                  step="<?= (int)$product['quantity_step']; ?>"
                  value="<?= (int)$product['minimum_order_qty']; ?>"
                  required
                >
              </div>

              <div class="full">
                <label for="buyNotes">Customization notes</label>
                <textarea
                  id="buyNotes"
                  name="notes"
                  maxlength="1000"
                  placeholder="Names, language, colour, printing or delivery notes"
                ></textarea>
              </div>
            </div>

            <button
              class="submit-btn"
              type="submit"
              data-add-button
            >
              Add to Cart
            </button>
          </form>
          <?php else: ?>
            <div class="purchase-box" id="buy">
              <h3>Buy / Add to Cart</h3>

              <div class="login-required-card">
                <span
                  class="login-required-icon"
                  aria-hidden="true"
                >🔐</span>

                <div>
                  <h3>Login to add this product</h3>
                  <p>
                    Product details and enquiries are available without login.
                    Sign in only when you are ready to add this product to
                    your cart.
                  </p>
                </div>

                <a
                  class="primary-btn login-required-button"
                  href="<?= sf_e(sf_login_required_url(
                      'product.php?slug='
                      . rawurlencode((string)$product['slug'])
                      . '#buy'
                  )); ?>"
                >
                  Login & Continue →
                </a>

                <small>
                  After login, you will return to this product.
                </small>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array($mode, ['enquiry', 'both'], true)): ?>
          <form
            action="submit_product_enquiry.php"
            method="POST"
            class="purchase-box js-product-enquiry-form"
            id="enquiry"
          >
            <h3>Product Enquiry</h3>

            <input
              type="hidden"
              name="csrf_token"
              value="<?= sf_e(sf_csrf_token()); ?>"
            >

            <input
              type="hidden"
              name="product_id"
              value="<?= $productId; ?>"
            >

            <div class="purchase-grid">
              <div>
                <label for="enquiryName">Name</label>
                <input
                  id="enquiryName"
                  type="text"
                  name="name"
                  maxlength="150"
                  required
                >
              </div>

              <div>
                <label for="enquiryPhone">Mobile</label>
                <input
                  id="enquiryPhone"
                  type="tel"
                  name="mobile"
                  pattern="[0-9]{10}"
                  maxlength="10"
                  required
                >
              </div>

              <div class="full">
                <label for="enquiryEmail">Email (Optional)</label>
                <input
                  id="enquiryEmail"
                  type="email"
                  name="email"
                  maxlength="190"
                >
              </div>

              <?php if ($colors): ?>
                <div>
                  <label for="enquiryColor">Colour</label>
                  <select
                    id="enquiryColor"
                    name="color_variant_id"
                    required
                  >
                    <option value="">Select Colour</option>
                    <?php foreach ($colors as $color): ?>
                      <option value="<?= (int)$color['id']; ?>">
                        <?= sf_e($color['color_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>

              <?php if ($designs): ?>
                <div>
                  <label for="enquiryDesign">Design</label>
                  <select
                    id="enquiryDesign"
                    name="design_variant_id"
                    required
                  >
                    <option value="">Select Design</option>
                    <?php foreach ($designs as $design): ?>
                      <option value="<?= (int)$design['id']; ?>">
                        <?= sf_e($design['design_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>

              <div>
                <label for="enquiryQuantity">Required Quantity</label>
                <input
                  id="enquiryQuantity"
                  type="number"
                  name="quantity"
                  min="<?= (int)$product['minimum_order_qty']; ?>"
                  step="<?= (int)$product['quantity_step']; ?>"
                  value="<?= (int)$product['minimum_order_qty']; ?>"
                  required
                >
              </div>

              <div class="full">
                <label for="enquiryNotes">Requirements</label>
                <textarea
                  id="enquiryNotes"
                  name="notes"
                  maxlength="1500"
                  placeholder="Enter customization, event or delivery requirements"
                ></textarea>
              </div>
            </div>

            <button
              class="submit-btn"
              type="submit"
              data-enquiry-submit
            >
              Submit Product Enquiry
            </button>
          </form>
        <?php endif; ?>
      </section>
    </div>
  </div>
</main>

<script>
document.querySelectorAll('[data-gallery-image]').forEach(image => {
  image.addEventListener('click', () => {
    const main = document.getElementById('mainProductImage');

    if (main) {
      main.src = image.src;
      main.alt = image.alt;
    }
  });
});

(() => {
  'use strict';

  const form = document.querySelector('.js-product-enquiry-form');
  const button = form?.querySelector('[data-enquiry-submit]');
  const toast = document.getElementById('productEnquiryToast');
  const toastIcon = document.getElementById(
    'productEnquiryToastIcon'
  );
  const toastTitle = document.getElementById(
    'productEnquiryToastTitle'
  );
  const toastText = document.getElementById(
    'productEnquiryToastText'
  );
  const toastClose = document.getElementById(
    'productEnquiryToastClose'
  );

  let toastTimer = 0;

  const closeToast = () => {
    if (!toast) {
      return;
    }

    window.clearTimeout(toastTimer);
    toast.classList.remove('show');
  };

  const showToast = (
    type,
    title,
    message
  ) => {
    if (!toast || !toastTitle || !toastText || !toastIcon) {
      return;
    }

    window.clearTimeout(toastTimer);

    toast.classList.toggle('error', type === 'error');
    toastIcon.textContent = type === 'error' ? '!' : '✓';
    toastTitle.textContent = title;
    toastText.textContent = message;

    toast.classList.remove('show');

    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        toast.classList.add('show');
      });
    });

    toastTimer = window.setTimeout(closeToast, 5500);
  };

  const parseJson = async response => {
    const text = await response.text();

    try {
      return JSON.parse(text);
    } catch {
      throw new Error(
        text.trim()
        || 'The enquiry service returned an invalid response.'
      );
    }
  };

  form?.addEventListener('submit', async event => {
    event.preventDefault();

    if (!form.reportValidity()) {
      return;
    }

    const originalText =
      button?.textContent || 'Submit Product Enquiry';

    if (button) {
      button.disabled = true;
      button.textContent = 'Submitting...';
    }

    const controller = new AbortController();
    const requestTimeout = window.setTimeout(() => {
      controller.abort();
    }, 20000);

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        signal: controller.signal
      });

      const result = await parseJson(response);

      if (!response.ok || !result.success) {
        throw new Error(
          result.message || 'Unable to submit the enquiry.'
        );
      }

      const enquiryNumber =
        result.data?.enquiry_number || '';

      showToast(
        'success',
        'Enquiry submitted successfully',
        result.data?.message
          || (
            enquiryNumber
              ? `Your enquiry ${enquiryNumber} has been received. Our team will contact you shortly.`
              : 'Your enquiry has been received. Our team will contact you shortly.'
          )
      );

      form.reset();

      const quantity = form.querySelector(
        'input[name="quantity"]'
      );

      if (quantity && quantity.defaultValue) {
        quantity.value = quantity.defaultValue;
      }
    } catch (error) {
      const message = error?.name === 'AbortError'
        ? 'The request took too long. The button has been reset. Please check the enquiry list before submitting again.'
        : error.message || 'Please try again.';

      showToast(
        'error',
        'Unable to confirm enquiry',
        message
      );
    } finally {
      window.clearTimeout(requestTimeout);

      if (button) {
        button.disabled = false;
        button.textContent = originalText;
      }
    }
  });

  toastClose?.addEventListener('click', closeToast);

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      closeToast();
    }
  });

  <?php if ($enquiryNumber !== ''): ?>
  showToast(
    'success',
    'Enquiry submitted successfully',
    <?= json_encode(
        $enquiryToastMessage !== ''
            ? $enquiryToastMessage
            : 'Your enquiry '
                . $enquiryNumber
                . ' has been received. Our team will contact you shortly.',
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    ); ?>
  );
  <?php endif; ?>

  const currentUrl = new URL(window.location.href);

  if (currentUrl.searchParams.has('enquiry')) {
    currentUrl.searchParams.delete('enquiry');

    window.history.replaceState(
      {},
      document.title,
      currentUrl.pathname
        + currentUrl.search
        + currentUrl.hash
    );
  }
})();
</script>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
