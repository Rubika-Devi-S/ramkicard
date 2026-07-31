<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$slug = trim((string)($_GET['slug'] ?? ''));

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
$enquiryNumber = trim((string)($_GET['enquiry'] ?? ''));
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

        <h1>
          <?= sf_e($product['product_name']); ?>
        </h1>

        <?php if (!empty($product['product_name_tamil'])): ?>
          <div
            class="product-title-tamil"
            lang="ta"
          >
            <?= sf_e($product['product_name_tamil']); ?>
          </div>
        <?php endif; ?>

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

        <?php if ($enquiryNumber !== ''): ?>
          <div class="purchase-message success">
            Your enquiry
            <strong><?= sf_e($enquiryNumber); ?></strong>
            has been received.
          </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
          <div class="purchase-message error">
            <?= sf_e($error); ?>
          </div>
        <?php endif; ?>

        <?php if (in_array($mode, ['checkout', 'both'], true)): ?>
          <form
            action="add_to_cart.php"
            method="POST"
            class="purchase-box"
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

            <button class="submit-btn" type="submit">
              Add to Cart
            </button>
          </form>
        <?php endif; ?>

        <?php if (in_array($mode, ['enquiry', 'both'], true)): ?>
          <form
            action="submit_product_enquiry.php"
            method="POST"
            class="purchase-box"
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

            <button class="submit-btn" type="submit">
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
</script>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
