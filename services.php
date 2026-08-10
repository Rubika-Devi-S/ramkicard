<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$companyName = sf_setting($pdo, 'company_name', 'Ramki Cards');
$phoneNumber = sf_setting($pdo, 'phone_number', '96299 54411');
$whatsappNumber = sf_setting($pdo, 'whatsapp_number', $phoneNumber);

$servicesSection = sf_section($pdo, 'services');
$services = sf_section_items($pdo, 'services');
$heroItems = sf_section_items($pdo, 'hero_features');
$customSection = sf_section($pdo, 'custom_design');
$customSteps = sf_section_items($pdo, 'custom_design');
$topItems = sf_section_items($pdo, 'top_strip');

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

$experienceNumber = '25';
$experienceText = [];
foreach ($heroItems as $item) {
    $experienceText[] = (string)($item['item_title'] ?? '');
    $experienceText[] = (string)($item['item_subtitle'] ?? '');
    $experienceText[] = (string)($item['item_content'] ?? '');
}
if (preg_match('/(\d+)\s*\+?\s*years?/i', implode(' ', $experienceText), $experienceMatch)) {
    $experienceNumber = $experienceMatch[1];
}

$topStripItems = [];
foreach ($topItems as $item) {
    $topStripItems[] = [
        'icon' => $item['icon_class'] ?: '✨',
        'text' => $item['item_title'] ?: $item['item_content'],
    ];
}
if (!$topStripItems) {
    $topStripItems = [
        ['icon' => '✨', 'text' => 'Premium Wedding Cards with luxury finishes'],
        ['icon' => '🚚', 'text' => 'Fast delivery across Tamil Nadu & India'],
        ['icon' => '🎨', 'text' => 'Custom Tamil & English invitation designs'],
        ['icon' => '📞', 'text' => 'Call / WhatsApp: ' . $phoneNumber],
        ['icon' => '💌', 'text' => 'Bulk order discounts available'],
    ];
}

$gallery = [];
$galleryPaths = [];

try {
    $galleryStatement = $pdo->query(
        "SELECT pi.image_path, COALESCE(NULLIF(pi.alt_text, ''), p.product_name) AS alt_text,
                p.product_name, p.slug
         FROM product_images pi
         INNER JOIN products p ON p.id = pi.product_id
         WHERE pi.status = 'active'
           AND p.status = 'active'
           AND p.deleted_at IS NULL
         ORDER BY p.is_featured DESC, pi.sort_order ASC, pi.id DESC
         LIMIT 16"
    );

    foreach ($galleryStatement->fetchAll(PDO::FETCH_ASSOC) as $image) {
        $path = trim((string)($image['image_path'] ?? ''));
        if ($path === '' || isset($galleryPaths[$path])) {
            continue;
        }
        $galleryPaths[$path] = true;
        $gallery[] = $image;
    }

    if (count($gallery) < 8) {
        $thumbnailStatement = $pdo->query(
            "SELECT thumbnail_path AS image_path, product_name AS alt_text, product_name, slug
             FROM products
             WHERE status = 'active'
               AND deleted_at IS NULL
               AND thumbnail_path <> ''
             ORDER BY is_featured DESC, id DESC
             LIMIT 16"
        );

        foreach ($thumbnailStatement->fetchAll(PDO::FETCH_ASSOC) as $image) {
            $path = trim((string)($image['image_path'] ?? ''));
            if ($path === '' || isset($galleryPaths[$path])) {
                continue;
            }
            $galleryPaths[$path] = true;
            $gallery[] = $image;
            if (count($gallery) >= 16) {
                break;
            }
        }
    }
} catch (Throwable $exception) {
    // Keep the page usable if gallery tables are temporarily unavailable.
    $gallery = [];
}

$pageTitle = 'Our Services | ' . $companyName;
$whatsappUrl = sf_whatsapp_url(
    $whatsappNumber,
    'Hello ' . $companyName . ', I would like to know more about your invitation and printing services.'
);

require __DIR__ . '/includes/storefront-header.php';
?>

<main class="store-inner-page services-page">
    <section class="page-hero page-hero-services">
        <div class="container page-hero-grid">
            <div class="page-hero-copy">
                <span class="page-eyebrow">What We Do</span>
                <h1>Beautiful details for every <em>celebration.</em></h1>
                <p class="page-lead">
                    <?= sf_e($servicesSection['section_content'] ?? 'From invitation design and premium printing to thoughtful celebration essentials, we bring every detail together with care.'); ?>
                </p>
                <div class="page-actions">
                    <a class="page-action page-action-primary" href="products.php">Explore Collections <span>→</span></a>
                    <a class="page-action page-action-outline" href="<?= sf_e($whatsappUrl); ?>" target="_blank" rel="noopener">Talk to Our Team</a>
                </div>
            </div>
            <div class="page-hero-note" aria-label="Ramki Cards service promise">
                <span class="page-hero-note-mark"><?= sf_e($experienceNumber); ?>+</span>
                <strong>Years of trusted craftsmanship</strong>
                <p>Traditional warmth, modern design and dependable delivery.</p>
            </div>
        </div>
    </section>

    <section class="section page-section" aria-labelledby="servicesHeading">
        <div class="container">
            <div class="section-title page-section-title">
                <div class="decor-line"></div>
                <span>Made for your occasion</span>
                <h2 id="servicesHeading"><?= sf_e($servicesSection['section_title'] ?? 'Our'); ?> <em><?= sf_e($servicesSection['section_subtitle'] ?? 'Services'); ?></em></h2>
                <p>Choose a service and our team can help tailor it to your celebration.</p>
            </div>

            <div class="service-detail-grid">
                <?php foreach ($services as $service): ?>
                    <?php
                    $serviceImage = trim((string)($service['image_path'] ?? ''));
                    $serviceLink = trim((string)($service['link_url'] ?? '')) ?: 'products.php';
                    $serviceLinkText = trim((string)($service['link_text'] ?? '')) ?: 'Explore options';
                    ?>
                    <article class="service-detail-card">
                        <?php if ($serviceImage !== ''): ?>
                            <a class="service-detail-image" href="<?= sf_e($serviceLink); ?>" aria-label="<?= sf_e($service['item_title'] ?? 'Service'); ?>">
                                <img src="<?= sf_e(sf_media_path($serviceImage, 'banner.png')); ?>" alt="<?= sf_e($service['item_title'] ?? 'Ramki Cards service'); ?>" loading="lazy">
                            </a>
                        <?php endif; ?>
                        <div class="service-detail-body">
                            <span class="service-detail-icon" aria-hidden="true"><?= sf_e($service['icon_class'] ?: '✦'); ?></span>
                            <h3><?= sf_e($service['item_title'] ?? 'Our Service'); ?></h3>
                            <?php if (!empty($service['item_subtitle'])): ?>
                                <strong class="service-detail-subtitle"><?= sf_e($service['item_subtitle']); ?></strong>
                            <?php endif; ?>
                            <p><?= sf_e($service['item_content'] ?? 'Customised with quality materials and careful finishing.'); ?></p>
                            <a class="service-card-link" href="<?= sf_e($serviceLink); ?>"><?= sf_e($serviceLinkText); ?> <span>→</span></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section service-gallery-section" aria-labelledby="galleryHeading">
        <div class="container">
            <div class="section-title page-section-title page-section-title-left">
                <div class="decor-line"></div>
                <span>From our collection</span>
                <h2 id="galleryHeading">A closer look at <em>our work</em></h2>
                <p>Swipe on mobile or use the arrows to explore invitation details and finishes.</p>
            </div>

            <?php if ($gallery): ?>
                <div class="service-carousel" data-service-carousel>
                    <div class="service-carousel-toolbar">
                        <span class="service-carousel-count"><strong data-carousel-current>1</strong> / <?= count($gallery); ?></span>
                        <div class="service-carousel-controls">
                            <button type="button" data-carousel-prev aria-label="Previous gallery items">←</button>
                            <button type="button" data-carousel-next aria-label="Next gallery items">→</button>
                        </div>
                    </div>
                    <div class="service-carousel-viewport" data-carousel-viewport tabindex="0">
                        <div class="service-carousel-track">
                            <?php foreach ($gallery as $index => $image): ?>
                                <a class="service-carousel-slide" href="product.php?slug=<?= rawurlencode((string)$image['slug']); ?>" data-carousel-slide>
                                    <div class="service-carousel-image">
                                        <img src="<?= sf_e(sf_media_path((string)$image['image_path'], 'banner.png')); ?>" alt="<?= sf_e($image['alt_text'] ?? $image['product_name']); ?>" loading="<?= $index < 3 ? 'eager' : 'lazy'; ?>">
                                    </div>
                                    <div class="service-carousel-caption">
                                        <span>View design</span>
                                        <strong><?= sf_e($image['product_name']); ?></strong>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="service-gallery-empty">
                    <span>✦</span>
                    <p>New gallery images will appear here automatically when product images are added in the admin panel.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section page-process-section" aria-labelledby="processHeading">
        <div class="container">
            <div class="section-title page-section-title">
                <div class="decor-line"></div>
                <span>Simple from start to finish</span>
                <h2 id="processHeading"><?= sf_e($customSection['section_title'] ?? 'Your idea,'); ?> <em><?= sf_e($customSection['section_subtitle'] ?? 'beautifully made'); ?></em></h2>
            </div>
            <div class="page-process-grid">
                <?php foreach ($customSteps as $index => $step): ?>
                    <article class="page-process-card">
                        <span class="page-process-number"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT); ?></span>
                        <span class="page-process-icon" aria-hidden="true"><?= sf_e($step['icon_class'] ?: '✦'); ?></span>
                        <h3><?= sf_e($step['item_title'] ?? 'Step'); ?></h3>
                        <p><?= sf_e($step['item_content'] ?? 'Our team guides you through every detail.'); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="page-cta-section">
        <div class="container page-cta-inner">
            <div>
                <span>Custom ideas welcome</span>
                <h2>Have something special in mind?</h2>
                <p>Tell us your occasion, quantity and design idea. We’ll help you find the right finish.</p>
            </div>
            <div class="page-actions">
                <a class="page-action page-action-gold" href="<?= sf_e($whatsappUrl); ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
                <a class="page-action page-action-light" href="index.php#contact">Send an Enquiry</a>
            </div>
        </div>
    </section>
</main>

<script>
(() => {
    const carousel = document.querySelector('[data-service-carousel]');
    if (!carousel) return;

    const viewport = carousel.querySelector('[data-carousel-viewport]');
    const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
    const current = carousel.querySelector('[data-carousel-current]');
    const previous = carousel.querySelector('[data-carousel-prev]');
    const next = carousel.querySelector('[data-carousel-next]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let timer = null;

    const closestIndex = () => {
        const left = viewport.scrollLeft;
        let best = 0;
        let distance = Number.POSITIVE_INFINITY;
        slides.forEach((slide, index) => {
            const delta = Math.abs(slide.offsetLeft - left);
            if (delta < distance) {
                distance = delta;
                best = index;
            }
        });
        return best;
    };

    const updateCurrent = () => {
        if (current) current.textContent = String(closestIndex() + 1);
    };

    const move = direction => {
        viewport.scrollBy({
            left: viewport.clientWidth * 0.88 * direction,
            behavior: reducedMotion ? 'auto' : 'smooth'
        });
    };

    previous?.addEventListener('click', () => move(-1));
    next?.addEventListener('click', () => move(1));
    viewport.addEventListener('scroll', updateCurrent, { passive: true });

    const startAuto = () => {
        if (reducedMotion || slides.length < 2) return;
        window.clearInterval(timer);
        timer = window.setInterval(() => {
            const atEnd = viewport.scrollLeft + viewport.clientWidth >= viewport.scrollWidth - 8;
            viewport.scrollTo({
                left: atEnd ? 0 : viewport.scrollLeft + viewport.clientWidth * 0.88,
                behavior: 'smooth'
            });
        }, 5500);
    };

    const stopAuto = () => window.clearInterval(timer);
    carousel.addEventListener('mouseenter', stopAuto);
    carousel.addEventListener('mouseleave', startAuto);
    carousel.addEventListener('focusin', stopAuto);
    carousel.addEventListener('focusout', startAuto);
    carousel.addEventListener('touchstart', stopAuto, { passive: true });
    carousel.addEventListener('touchend', startAuto, { passive: true });
    window.addEventListener('resize', updateCurrent);
    startAuto();
})();
</script>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
