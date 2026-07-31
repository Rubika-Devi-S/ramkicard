<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/includes/storefront.php';

$customerLoggedIn = sf_customer_logged_in();

$companyName = sf_setting($pdo, 'company_name', 'Ramki Cards');
$entranceLogoPath = sf_media_path(
    sf_setting($pdo, 'logo_path', 'logo.png'),
    'logo.png'
);
$phoneNumber = sf_setting($pdo, 'phone_number', '96299 54411');
$secondPhone = sf_setting(
    $pdo,
    'secondary_phone_number',
    '96299 54421'
);
$whatsappNumber = sf_setting($pdo, 'whatsapp_number', $phoneNumber);
$emailAddress = sf_setting(
    $pdo,
    'email_address',
    'info@ramkicards.com'
);
$address = sf_setting(
    $pdo,
    'address',
    'Chennai, Tamil Nadu, India'
);
$instagramHandle = sf_setting(
    $pdo,
    'instagram_handle',
    '@ramkicards'
);

$hero = sf_section($pdo, 'hero');
$servicesSection = sf_section($pdo, 'services');
$customSection = sf_section($pdo, 'custom_design');
$whySection = sf_section($pdo, 'why_choose');
$testimonialSection = sf_section($pdo, 'testimonials');
$contactSection = sf_section($pdo, 'contact');

$heroItems = sf_section_items($pdo, 'hero_features');
$services = sf_section_items($pdo, 'services');
$customSteps = sf_section_items($pdo, 'custom_design');
$whyItems = sf_section_items($pdo, 'why_choose');
$testimonials = sf_section_items($pdo, 'testimonials');
$topItems = sf_section_items($pdo, 'top_strip');

if (!$heroItems) {
    $heroItems = [
        [
            'icon_class' => '💎',
            'item_title' => 'Premium Quality',
            'item_subtitle' => '25 Years of Trust',
        ],
        [
            'icon_class' => '✍',
            'item_title' => 'Custom Designs',
            'item_subtitle' => 'Tailored for You',
        ],
        [
            'icon_class' => '🚚',
            'item_title' => 'Fast Delivery',
            'item_subtitle' => 'Pan India',
        ],
    ];
}

if (!$services) {
    $services = [
        ['icon_class' => '💍', 'item_title' => 'Wedding Cards', 'item_content' => 'Elegant designs for your special day.'],
        ['icon_class' => '🎨', 'item_title' => 'Multi-Color Invitations', 'item_content' => 'Vibrant and premium printing.'],
        ['icon_class' => '🛍️', 'item_title' => 'Thamboolam Bags', 'item_content' => 'Traditional and custom-made bags.'],
        ['icon_class' => '🎁', 'item_title' => 'Return Gifts', 'item_content' => 'Beautiful gifts for your guests.'],
        ['icon_class' => '📅', 'item_title' => 'Calendars', 'item_content' => 'Monthly, daily and table-top calendars.'],
        ['icon_class' => '📓', 'item_title' => 'Diaries', 'item_content' => 'Premium business and personal diaries.'],
    ];
}

if (!$customSteps) {
    $customSteps = [
        ['icon_class' => '▦', 'item_title' => 'Choose Design', 'item_content' => 'Browse our exclusive collections and pick your favourite.'],
        ['icon_class' => '✎', 'item_title' => 'Customize Details', 'item_content' => 'Add names, date, colours, fonts, language and more.'],
        ['icon_class' => '👁', 'item_title' => 'Preview', 'item_content' => 'Review your card design and request changes.'],
        ['icon_class' => '🚚', 'item_title' => 'Print & Deliver', 'item_content' => 'We print with perfection and deliver to your doorstep.'],
    ];
}

if (!$whyItems) {
    $whyItems = [
        ['icon_class' => '🏆', 'item_title' => '25 Years of Trust', 'item_content' => "People's trusted choice for wedding cards."],
        ['icon_class' => '💎', 'item_title' => 'Premium Quality Printing', 'item_content' => 'Finest materials with perfect finishing.'],
        ['icon_class' => '🎨', 'item_title' => 'Traditional & Modern Designs', 'item_content' => 'Wide range of timeless and trendy designs.'],
        ['icon_class' => '₹', 'item_title' => 'Affordable Pricing', 'item_content' => 'Best quality cards at reasonable prices.'],
        ['icon_class' => '🚚', 'item_title' => 'Fast Delivery', 'item_content' => 'On-time delivery across India.'],
        ['icon_class' => 'அ', 'item_title' => 'Tamil & English Cards', 'item_content' => 'Bilingual options to suit your needs.'],
    ];
}

if (!$testimonials) {
    $testimonials = [
        ['item_title' => 'Priya S.', 'item_subtitle' => 'Chennai', 'item_content' => 'The design, print quality and finishing were absolutely amazing. Our guests loved the cards.'],
        ['item_title' => 'Arun K.', 'item_subtitle' => 'Coimbatore', 'item_content' => 'Excellent service and on-time delivery. They understood exactly what we wanted.'],
        ['item_title' => 'Meera R.', 'item_subtitle' => 'Bangalore', 'item_content' => 'Beautiful collection with so many options. The customization was perfect.'],
    ];
}

$topStripItems = [];

if ($topItems) {
    foreach ($topItems as $item) {
        $topStripItems[] = [
            'icon' => $item['icon_class'] ?: '✨',
            'text' => $item['item_title'] ?: $item['item_content'],
        ];
    }
} else {
    $topStripItems = [
        ['icon' => '✨', 'text' => 'Premium Wedding Cards with luxury finishes'],
        ['icon' => '🚚', 'text' => 'Fast delivery across Tamil Nadu & India'],
        ['icon' => '🎨', 'text' => 'Custom Tamil & English invitation designs'],
        ['icon' => '📞', 'text' => 'Call / WhatsApp: ' . $phoneNumber],
        ['icon' => '💌', 'text' => 'Bulk order discounts available'],
    ];
}

$categories = sf_active_categories($pdo, 8);
$featuredProducts = sf_featured_products($pdo, 8);

$pageTitle = ($hero['section_title'] ?? $companyName)
    . ' | Wedding Cards Manufacturer';

require __DIR__ . '/includes/storefront-header.php';

$heroImage = sf_media_path(
    $hero['background_image_path'] ?? '',
    'banner.png'
);
?>

<style>
:root {
    --home-opening-maroon: #741525;
    --home-opening-maroon-dark: #4c0915;
    --home-opening-gold-light: #f7dd9e;
}

html.home-opening-active,
body.home-opening-active {
    overflow: hidden !important;
}

/*
 * The opening screen is hidden by default.
 * JavaScript enables it only for the first direct index.php visit.
 */
.home-opening-screen {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    pointer-events: auto;
    animation: homeOpeningScreenHide 0s linear 3.2s forwards;
}

html.home-opening-active .home-opening-screen {
    display: flex;
}

html.home-opening-skip .home-opening-screen {
    display: none !important;
    animation: none !important;
}

.home-opening-screen.finished {
    pointer-events: none;
}

.home-opening-panel {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 50.5%;
    background:
        radial-gradient(circle at center,
            rgba(174, 58, 75, 0.45),
            transparent 55%),
        linear-gradient(135deg,
            var(--home-opening-maroon-dark),
            var(--home-opening-maroon));
    will-change: transform;
}

.home-opening-panel-left {
    left: 0;
    transform-origin: left center;
    animation:
        homePanelOpenLeft 1s cubic-bezier(0.77, 0, 0.18, 1) 1.55s forwards;
}

.home-opening-panel-right {
    right: 0;
    transform-origin: right center;
    animation:
        homePanelOpenRight 1s cubic-bezier(0.77, 0, 0.18, 1) 1.55s forwards;
}

.home-opening-center {
    position: relative;
    z-index: 4;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 280px;
    padding: 24px;
    text-align: center;
    color: #fff;
    animation:
        homeOpeningContentLeave 0.55s ease 1.45s forwards;
}

.home-opening-logo-wrap {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 148px;
    height: 148px;
    margin-bottom: 24px;
}

.home-opening-ring {
    position: absolute;
    inset: 0;
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
}

.home-opening-ring.ring-one {
    animation: homeRingExpandOne 1.4s ease-out infinite;
}

.home-opening-ring.ring-two {
    animation: homeRingExpandTwo 1.4s ease-out 0.35s infinite;
}

.home-opening-ring.ring-three {
    animation: homeRingExpandThree 1.4s ease-out 0.7s infinite;
}

.home-opening-logo-box {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 112px;
    height: 112px;
    padding: 19px;
    overflow: hidden;
    border: 2px solid rgba(255, 255, 255, 0.82);
    border-radius: 50%;
    background:
        linear-gradient(145deg,
            #942b3c 0%,
            var(--home-opening-maroon) 45%,
            var(--home-opening-maroon-dark) 100%);
    box-shadow:
        0 24px 55px rgba(0, 0, 0, 0.3),
        0 0 0 8px rgba(255, 255, 255, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
    opacity: 0;
    transform: scale(0.35) rotate(-20deg);
    animation:
        homeOpeningLogoEnter 0.85s cubic-bezier(0.34, 1.56, 0.64, 1) 0.15s forwards;
}

.home-opening-logo-box::before {
    content: '';
    position: absolute;
    top: -30%;
    left: -80%;
    width: 60%;
    height: 160%;
    transform: rotate(20deg);
    background:
        linear-gradient(90deg,
            transparent,
            rgba(255, 255, 255, 0.22),
            transparent);
    animation: homeOpeningLogoShine 2.4s ease-in-out 0.8s infinite;
}

.home-opening-logo-box img {
    position: relative;
    z-index: 2;
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
    filter: brightness(0) saturate(100%) invert(100%);
}

.home-opening-logo-fallback {
    position: absolute;
    z-index: 1;
    display: none;
    color: #fff;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 2.1rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -2px;
}

.home-opening-company {
    margin: 0 0 6px;
    color: #fff;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: clamp(1.85rem, 4vw, 2.55rem);
    font-weight: 800;
    letter-spacing: 0.3px;
    opacity: 0;
    transform: translateY(20px);
    animation: homeOpeningTextEnter 0.6s ease-out 0.65s forwards;
}

.home-opening-subtitle {
    margin: 0;
    color: var(--home-opening-gold-light);
    font-family: 'Poppins', Arial, sans-serif;
    font-size: 0.82rem;
    font-weight: 500;
    letter-spacing: 3px;
    text-transform: uppercase;
    opacity: 0;
    transform: translateY(14px);
    animation: homeOpeningTextEnter 0.55s ease-out 0.85s forwards;
}

.home-opening-progress {
    position: relative;
    width: 150px;
    height: 3px;
    margin-top: 24px;
    overflow: hidden;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.18);
    opacity: 0;
    animation: homeOpeningTextEnter 0.45s ease-out 1s forwards;
}

.home-opening-progress::after {
    content: '';
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    border-radius: inherit;
    background:
        linear-gradient(90deg,
            var(--home-opening-gold-light),
            #fff,
            var(--home-opening-gold-light));
    animation: homeOpeningProgress 0.8s ease-in-out 1.05s forwards;
}

@keyframes homeOpeningLogoEnter {
    0% {
        opacity: 0;
        transform: scale(0.35) rotate(-20deg);
    }

    70% {
        opacity: 1;
        transform: scale(1.08) rotate(3deg);
    }

    100% {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
}

@keyframes homeOpeningTextEnter {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes homeOpeningProgress {
    from {
        transform: translateX(-100%);
    }

    to {
        transform: translateX(0);
    }
}

@keyframes homeRingExpandOne {
    0% {
        opacity: 0.55;
        transform: scale(0.72);
    }

    100% {
        opacity: 0;
        transform: scale(1.45);
    }
}

@keyframes homeRingExpandTwo {
    0% {
        opacity: 0.45;
        transform: scale(0.72);
    }

    100% {
        opacity: 0;
        transform: scale(1.65);
    }
}

@keyframes homeRingExpandThree {
    0% {
        opacity: 0.35;
        transform: scale(0.72);
    }

    100% {
        opacity: 0;
        transform: scale(1.85);
    }
}

@keyframes homeOpeningLogoShine {
    0% {
        left: -80%;
    }

    45%,
    100% {
        left: 150%;
    }
}

@keyframes homeOpeningContentLeave {
    from {
        opacity: 1;
        transform: scale(1);
    }

    to {
        opacity: 0;
        transform: scale(0.82);
    }
}

@keyframes homePanelOpenLeft {
    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(-102%);
    }
}

@keyframes homePanelOpenRight {
    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(102%);
    }
}

@keyframes homeOpeningScreenHide {
    to {
        visibility: hidden;
        pointer-events: none;
    }
}

@media (max-width: 575.98px) {
    .home-opening-logo-wrap {
        width: 128px;
        height: 128px;
    }

    .home-opening-logo-box {
        width: 96px;
        height: 96px;
        padding: 16px;
    }

    .home-opening-company {
        font-size: 1.85rem;
    }

    .home-opening-subtitle {
        font-size: 0.7rem;
        letter-spacing: 2.2px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .home-opening-screen {
        display: none !important;
    }
}
</style>

<script>
(() => {
    'use strict';

    const storageKey = 'ramki-home-entry-animation-seen-v1';

    const navigationEntry =
        performance.getEntriesByType?.('navigation')?.[0];

    const navigationType =
        navigationEntry?.type || 'navigate';

    const hasSectionHash =
        window.location.hash.trim() !== '';

    const hasActionQuery =
        window.location.search.trim() !== '';

    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
    ).matches;

    let alreadySeen = false;
    let sameWebsiteReferrer = false;

    try {
        alreadySeen =
            sessionStorage.getItem(storageKey) === '1';
    } catch {
        alreadySeen = false;
    }

    try {
        if (document.referrer) {
            const referrerUrl = new URL(document.referrer);

            sameWebsiteReferrer =
                referrerUrl.origin === window.location.origin;
        }
    } catch {
        sameWebsiteReferrer = false;
    }

    /*
     * Show only when:
     * - index.php is opened directly for the first time in this tab
     * - it is not a browser refresh
     * - it is not browser back/forward navigation
     * - it is not an internal website navigation
     * - it does not contain #services, #about, #contact, etc.
     * - it does not contain an action/status query string
     */
    const shouldShow =
        !alreadySeen
        && navigationType === 'navigate'
        && !sameWebsiteReferrer
        && !hasSectionHash
        && !hasActionQuery
        && !reduceMotion;

    window.RamkiHomeEntryAnimation = {
        shouldShow,
        storageKey
    };

    if (!shouldShow) {
        document.documentElement.classList.add(
            'home-opening-skip'
        );

        return;
    }

    /*
     * Store immediately so reloads or actions cannot replay it.
     */
    try {
        sessionStorage.setItem(storageKey, '1');
    } catch {
        // The Navigation Timing checks still prevent refresh replay.
    }

    document.documentElement.classList.add(
        'home-opening-active'
    );

    document.body.classList.add(
        'home-opening-active'
    );
})();
</script>

<div class="home-opening-screen" id="homeOpeningScreen" aria-hidden="true">
    <div class="home-opening-panel home-opening-panel-left"></div>
    <div class="home-opening-panel home-opening-panel-right"></div>

    <div class="home-opening-center">
        <div class="home-opening-logo-wrap">
            <span class="home-opening-ring ring-one"></span>
            <span class="home-opening-ring ring-two"></span>
            <span class="home-opening-ring ring-three"></span>

            <div class="home-opening-logo-box">
                <span class="home-opening-logo-fallback">RC</span>
                <img src="<?= sf_e($entranceLogoPath); ?>" alt="" onerror="handleHomeOpeningLogoError(this)">
            </div>
        </div>

        <h2 class="home-opening-company">
            <?= sf_e($companyName); ?>
        </h2>

        <p class="home-opening-subtitle">
            Premium Invitation Experience
        </p>

        <div class="home-opening-progress"></div>
    </div>
</div>

<section class="hero" style="background-image:url('<?= sf_e($heroImage); ?>') !important">
    <div class="container hero-grid">
        <div class="hero-content">
            <div class="eyebrow">
                <?= sf_e(
            $hero['section_subtitle']
            ?? '25 Years of Trusted Excellence'
        ); ?>
            </div>

            <h2>
                <span>
                    <?= sf_e($hero['section_title'] ?? $companyName); ?>
                </span><br>
                <?= sf_e(
            json_decode(
                (string)($hero['additional_settings'] ?? ''),
                true
            )['heading_line_2']
            ?? 'Suba Nigalichi....'
        ); ?>
            </h2>

            <p>
                <?= nl2br(sf_e(
            $hero['section_content']
            ?? "Manufacturer & Dealer of Wedding Cards · Multi-Color Invitations · Thamboolam Bags · Return Gifts · Calendars & Diaries — People's Trusted Choice."
        )); ?>
            </p>

            <div class="hero-buttons">
                <a href="<?= sf_e(
              (string)($hero['button_url'] ?? 'products.php')
          ); ?>" class="primary-btn">
                    <?= sf_e($hero['button_text'] ?? 'View Products'); ?> →
                </a>

                <a href="index.php#contact" class="secondary-btn">
                    Get a Quote ✎
                </a>
            </div>

            <div class="hero-features">
                <?php foreach (array_slice($heroItems, 0, 3) as $item): ?>
                <div class="mini-feature">
                    <div class="icon">
                        <?= sf_e($item['icon_class'] ?: '✦'); ?>
                    </div>
                    <div>
                        <h4><?= sf_e($item['item_title']); ?></h4>
                        <p>
                            <?= sf_e(
                    $item['item_subtitle']
                    ?: $item['item_content']
                ); ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($categories): ?>
<section class="section home-categories" id="categories">
    <div class="container">
        <div class="section-title">
            <div class="decor-line"><i></i></div>
            <span>Browse by type</span>
            <h2>Featured <em>Categories</em></h2>
        </div>

        <div class="collections-grid">
            <?php foreach ($categories as $category): ?>
            <a class="collection-card glass-card" href="products.php?category=<?= rawurlencode(
              (string)$category['slug']
          ); ?>">
                <div class="collection-img">
                    <img class="product-photo" src="<?= sf_e(sf_media_path(
                  $category['image_path'] ?? '',
                  'banner.png'
              )); ?>" alt="<?= sf_e($category['category_name']); ?>" loading="lazy">
                </div>
                <h3><?= sf_e($category['category_name']); ?></h3>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($featuredProducts): ?>
<section class="section home-products" id="products">
    <div class="container">
        <div class="section-title">
            <div class="decor-line"><i></i></div>
            <span>Selected for you</span>
            <h2>Featured <em>Products</em></h2>
        </div>

        <div class="products-grid">
            <?php foreach ($featuredProducts as $product): ?>
            <?php
        $effectivePrice = sf_effective_price($product);
        $mode = sf_purchase_mode($pdo, $product);
        $quickAdd = sf_quick_add_configuration($pdo, $product);
        $quickAddAvailable =
            $quickAdd['available']
            && sf_product_can_quick_add($product);
        ?>
            <article class="product-card glass-card">
                <?php if ($effectivePrice < (float)$product['base_price']): ?>
                <span class="badge">Offer</span>
                <?php endif; ?>

                <a href="product.php?slug=<?= rawurlencode(
              (string)$product['slug']
          ); ?>">
                    <div class="product-img">
                        <img class="product-photo" src="<?= sf_e(sf_media_path(
                    $product['thumbnail_path'],
                    'banner.png'
                )); ?>" alt="<?= sf_e($product['product_name']); ?>" loading="lazy">
                    </div>
                </a>

                <div class="product-body">
                    <h3 class="product-name-english">
                        <?= sf_e($product['product_name']); ?>
                    </h3>

                    <?php if (!empty($product['product_name_tamil'])): ?>
                    <div class="product-name-tamil" lang="ta">
                        <?= sf_e($product['product_name_tamil']); ?>
                    </div>
                    <?php endif; ?>

                    <div class="price">
                        <?= sf_e(sf_money($effectivePrice)); ?>
                        <?php if ($effectivePrice < (float)$product['base_price']): ?>
                        <span class="price-old">
                            <?= sf_e(sf_money($product['base_price'])); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="moq-note">
                        Minimum order:
                        <?= (int)$product['minimum_order_qty']; ?>
                    </div>

                    <div class="product-actions <?= $mode === 'both' ? 'two' : ''; ?>">
                        <?php if (in_array($mode, ['checkout', 'both'], true)): ?>
                          <?php if ($quickAddAvailable): ?>
                            <form
                              action="add_to_cart.php"
                              method="POST"
                              class="quick-add-form js-add-to-cart-form"
                            >
                              <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= sf_e(sf_csrf_token()); ?>"
                              >
                              <input
                                type="hidden"
                                name="product_id"
                                value="<?= (int)$product['id']; ?>"
                              >
                              <input
                                type="hidden"
                                name="quantity"
                                value="<?= (int)$product['minimum_order_qty']; ?>"
                              >
                              <input
                                type="hidden"
                                name="color_variant_id"
                                value="<?= (int)$quickAdd['color_variant_id']; ?>"
                              >
                              <input
                                type="hidden"
                                name="design_variant_id"
                                value="<?= (int)$quickAdd['design_variant_id']; ?>"
                              >
                              <input
                                type="hidden"
                                name="return_url"
                                value="index.php#products"
                              >

                              <button
                                type="submit"
                                class="product-action-btn primary"
                                data-add-button
                              >
                                Add to Cart
                              </button>
                            </form>
                          <?php elseif (!sf_product_can_quick_add($product)): ?>
                            <button
                              type="button"
                              class="product-action-btn primary"
                              disabled
                            >
                              Out of Stock
                            </button>
                          <?php else: ?>
                            <a
                              class="product-action-btn primary"
                              href="product.php?slug=<?= rawurlencode(
                                  (string)$product['slug']
                              ); ?>#buy"
                            >
                              Choose Options
                            </a>
                          <?php endif; ?>
                        <?php endif; ?>

                        <?php if (in_array($mode, ['enquiry', 'both'], true)): ?>
                        <a class="product-action-btn" href="product.php?slug=<?= rawurlencode(
                      (string)$product['slug']
                  ); ?>#enquiry">Enquiry Now</a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="gallery-btn-wrap">
            <a href="products.php" class="primary-btn">
                View All Products →
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" id="services">
    <div class="container">
        <div class="section-title">
            <div class="decor-line"><i></i></div>
            <h2>
                <?= sf_e($servicesSection['section_title'] ?? 'Our'); ?>
                <em>
                    <?= sf_e(
              $servicesSection['section_subtitle']
              ?? 'Services'
          ); ?>
                </em>
            </h2>
        </div>

        <div class="features-grid">
            <?php foreach ($services as $item): ?>
            <div class="feature-card glass-card">
                <div class="feature-icon">
                    <?= sf_e($item['icon_class'] ?: '✦'); ?>
                </div>
                <h3><?= sf_e($item['item_title']); ?></h3>
                <p><?= sf_e($item['item_content']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section custom-section" id="custom">
    <div class="container custom-grid">
        <div class="custom-content">
            <span>
                <?= sf_e(
            $customSection['section_subtitle']
            ?? 'Make it truly yours'
        ); ?>
            </span>

            <h2>
                <?= nl2br(sf_e(
            $customSection['section_title']
            ?? 'Customize Every Detail of Your Wedding Card'
        )); ?>
            </h2>

            <p>
                <?= nl2br(sf_e(
            $customSection['section_content']
            ?? 'From names and dates to colors, fonts, language and design, we create invitations that are uniquely yours.'
        )); ?>
            </p>
        </div>

        <div class="steps">
            <?php foreach ($customSteps as $index => $item): ?>
            <div class="step-card">
                <div class="step-no"><?= $index + 1; ?></div>
                <div class="step-icon">
                    <?= sf_e($item['icon_class'] ?: '✦'); ?>
                </div>
                <h3><?= sf_e($item['item_title']); ?></h3>
                <p><?= sf_e($item['item_content']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" id="why">
    <div class="container">
        <div class="section-title">
            <div class="decor-line"><i></i></div>
            <h2>
                <?= sf_e($whySection['section_title'] ?? 'Why Choose'); ?>
                <em>
                    <?= sf_e(
              $whySection['section_subtitle']
              ?? $companyName . '?'
          ); ?>
                </em>
            </h2>
        </div>

        <div class="features-grid">
            <?php foreach ($whyItems as $item): ?>
            <div class="feature-card glass-card">
                <div class="feature-icon">
                    <?= sf_e($item['icon_class'] ?: '✦'); ?>
                </div>
                <h3><?= sf_e($item['item_title']); ?></h3>
                <p><?= sf_e($item['item_content']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section testimonial-contact" id="contact">
    <div class="container">
        <div class="section-title">
            <div class="decor-line"><i></i></div>
            <h2>
                <?= sf_e(
            $testimonialSection['section_title']
            ?? 'What Our Customers Say'
        ); ?>
            </h2>
        </div>

        <div class="testimonials-grid">
            <?php foreach (array_slice($testimonials, 0, 6) as $item): ?>
            <div class="testimonial-card glass-card">
                <div class="stars">★★★★★</div>
                <p><?= sf_e($item['item_content']); ?></p>

                <div class="person">
                    <div class="avatar">
                        <?= sf_e(strtoupper(substr(
                  trim((string)$item['item_title']),
                  0,
                  1
              )) ?: 'R'); ?>
                    </div>
                    <div>
                        <h4><?= sf_e($item['item_title']); ?></h4>
                        <span><?= sf_e($item['item_subtitle']); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="contact-grid">
            <div class="enquiry-card glass-card">
                <h2>
                    <?= sf_e(
              $contactSection['section_title']
              ?? "Let's Create Something Beautiful Together"
          ); ?>
                </h2>

                <p>
                    <?= sf_e(
              $contactSection['section_subtitle']
              ?? 'Share your requirements and we will get back to you shortly.'
          ); ?>
                </p>

                <div class="purchase-message" id="enquiryMessage" hidden></div>

                <form action="send_enquiry.php" method="POST" id="enquiryForm">
                    <input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>">

                    <div class="form-grid">
                        <div class="form-group full">
                            <input type="text" name="name" placeholder="Name" maxlength="150" required>
                        </div>

                        <div class="form-group full">
                            <input type="tel" name="mobile" placeholder="10-digit Mobile Number" inputmode="numeric"
                                pattern="[0-9]{10}" maxlength="10" required>
                        </div>

                        <div class="form-group full">
                            <input type="email" name="email" placeholder="Email Address (Optional)" maxlength="190">
                        </div>

                        <div class="form-group full">
                            <select name="event" id="eventSelect" required>
                                <option value="">Select Function / Event</option>
                                <option value="Wedding">Wedding</option>
                                <option value="Engagement">Engagement</option>
                                <option value="Reception">Reception</option>
                                <option value="House Warming">House Warming</option>
                                <option value="Birthday">Birthday</option>
                                <option value="Corporate Event">Corporate Event</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group full other-event-field" id="otherEventField">
                            <input type="text" name="other_event" placeholder="Please specify your event"
                                maxlength="150">
                        </div>

                        <div class="form-group full">
                            <input type="date" name="date" min="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group full">
                            <input type="text" name="location" placeholder="Location" maxlength="255" required>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" id="enquirySubmitButton">Submit Enquiry ✈</button>
                </form>
            </div>

            <div class="contact-side">
                <div class="info-card glass-card">
                    <h3>Get in Touch</h3>
                    <p>We are here to help you!</p>

                    <div class="info-list">
                        <div class="info-item">
                            <div class="i">📍</div>
                            <div>
                                <b><?= sf_e($companyName); ?></b><br>
                                <?= nl2br(sf_e($address)); ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="i">☎</div>
                            <div>
                                <a href="tel:<?= sf_e(sf_phone_digits($phoneNumber)); ?>">
                                    <?= sf_e($phoneNumber); ?>
                                </a>
                                <?php if ($secondPhone !== ''): ?>
                                <br>
                                <a href="tel:<?= sf_e(sf_phone_digits($secondPhone)); ?>">
                                    <?= sf_e($secondPhone); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="i">✉</div>
                            <div>
                                <a href="mailto:<?= sf_e($emailAddress); ?>">
                                    <?= sf_e($emailAddress); ?>
                                </a>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="i">📷</div>
                            <div>Instagram: <?= sf_e($instagramHandle); ?></div>
                        </div>
                    </div>
                </div>

                <div class="whatsapp-card glass-card">
                    <div class="wa-icon">☎</div>
                    <h3>Quick Enquiry on WhatsApp</h3>
                    <p>Chat with us directly for a fast response.</p>
                    <?php
          $contactWhatsAppUrl = sf_whatsapp_url(
              $whatsappNumber,
              'Hello Ramki Cards, I would like to enquire about your invitation cards.'
          );

          ?>

                    <a
                        href="<?= sf_e($contactWhatsAppUrl); ?>"
                        class="wa-btn"
                        target="_blank"
                        rel="noopener"
                    >
                        Chat on WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="success-overlay" id="successOverlay">
    <div class="success-box">
        <span class="icon">🎉</span>
        <h2>Thank You!</h2>
        <p id="successText">
            Your enquiry has been submitted successfully.<br>
            Our team will get back to you shortly.
        </p>
        <button type="button" class="btn-close" id="successClose">Continue Browsing</button>
    </div>
</div>

<script>
function handleHomeOpeningLogoError(image) {
    image.style.display = 'none';

    const fallback = image.parentElement?.querySelector(
        '.home-opening-logo-fallback'
    );

    if (fallback) {
        fallback.style.display = 'block';
    }
}

(() => {
    'use strict';

    const openingScreen = document.getElementById(
        'homeOpeningScreen'
    );

    const entryState =
        window.RamkiHomeEntryAnimation
        || { shouldShow: false };

    let openingFinished = false;

    const removeOpeningClasses = () => {
        document.documentElement.classList.remove(
            'home-opening-active',
            'home-opening-skip'
        );

        document.body.classList.remove(
            'home-opening-active'
        );
    };

    const removeOpeningImmediately = () => {
        openingFinished = true;
        removeOpeningClasses();
        openingScreen?.remove();
    };

    const finishHomeOpeningAnimation = () => {
        if (openingFinished) {
            return;
        }

        openingFinished = true;
        removeOpeningClasses();

        openingScreen?.classList.add('finished');

        window.setTimeout(() => {
            openingScreen?.remove();
        }, 1100);
    };

    /*
     * No animation for:
     * - Refresh
     * - Form/action redirect
     * - Returning from another website page
     * - #services, #custom, #why, #about, #contact navigation
     * - Browser back/forward restoration
     */
    if (!entryState.shouldShow) {
        removeOpeningImmediately();
        return;
    }

    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            removeOpeningImmediately();
        }
    });

    window.addEventListener('load', () => {
        window.setTimeout(
            finishHomeOpeningAnimation,
            2050
        );
    });

    /*
     * Safety fallback if an external resource loads slowly.
     */
    window.setTimeout(
        finishHomeOpeningAnimation,
        3200
    );
})();

(() => {
    'use strict';

    const form = document.getElementById('enquiryForm');
    const eventSelect = document.getElementById('eventSelect');
    const otherField = document.getElementById('otherEventField');
    const messageBox = document.getElementById('enquiryMessage');
    const submitButton = document.getElementById('enquirySubmitButton');
    const overlay = document.getElementById('successOverlay');
    const successText = document.getElementById('successText');

    eventSelect?.addEventListener('change', () => {
        const show = eventSelect.value === 'Other';
        otherField?.classList.toggle('show', show);

        const input = otherField?.querySelector('input');
        if (input) {
            input.required = show;
        }
    });

    function showMessage(type, text) {
        if (!messageBox) return;

        messageBox.hidden = false;
        messageBox.className = `purchase-message ${type}`;
        messageBox.textContent = text;
    }

    function closeSuccess() {
        overlay?.classList.remove('show');
    }

    document.getElementById('successClose')
        ?.addEventListener('click', closeSuccess);

    overlay?.addEventListener('click', event => {
        if (event.target === overlay) {
            closeSuccess();
        }
    });

    form?.addEventListener('submit', async event => {
        event.preventDefault();

        if (!form.reportValidity()) {
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';
        messageBox.hidden = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || 'Unable to submit your enquiry.'
                );
            }

            successText.innerHTML =
                `Your enquiry <strong>${result.data.enquiry_number}</strong> ` +
                `has been submitted successfully.<br>` +
                `Our team will contact you shortly.`;

            overlay.classList.add('show');
            form.reset();
            otherField?.classList.remove('show');

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        } catch (error) {
            showMessage(
                'error',
                error.message || 'Something went wrong. Please try again.'
            );
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Submit Enquiry ✈';
        }
    });
})();
</script>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>