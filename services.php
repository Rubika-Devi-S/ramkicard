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

$pageTitle = 'Our Services | ' . $companyName;
$whatsappUrl = sf_whatsapp_url(
    $whatsappNumber,
    'Hello ' . $companyName . ', I would like to know more about your invitation and printing services.'
);

require __DIR__ . '/includes/storefront-header.php';
?>

<script>
document.documentElement.classList.add('ramki-motion-enabled');
</script>

<style>
.service-gallery-section {
    scroll-margin-top: 105px;
}

.ramki-motion-enabled .ramki-emerge {
    opacity: 0;
    transform: translate3d(0, 38px, 0);
    filter: blur(5px);
    transition:
        opacity .82s cubic-bezier(.22, 1, .36, 1),
        transform .82s cubic-bezier(.22, 1, .36, 1),
        filter .7s ease;
    transition-delay: var(--ramki-delay, 0ms);
    will-change: opacity, transform, filter;
}

.ramki-motion-enabled .ramki-emerge-left {
    transform: translate3d(-52px, 12px, 0);
}

.ramki-motion-enabled .ramki-emerge-right {
    transform: translate3d(52px, 12px, 0);
}

.ramki-motion-enabled .ramki-emerge-scale {
    transform: translate3d(0, 24px, 0) scale(.94);
}

.ramki-motion-enabled .ramki-emerge.is-visible {
    opacity: 1;
    transform: translate3d(0, 0, 0) scale(1);
    filter: blur(0);
}

.service-detail-card,
.page-process-card,
.service-carousel-slide {
    transition:
        opacity .82s cubic-bezier(.22, 1, .36, 1),
        transform .35s cubic-bezier(.22, 1, .36, 1),
        filter .7s ease,
        box-shadow .35s ease,
        border-color .35s ease;
}

.ramki-motion-enabled .service-detail-card.is-visible:hover,
.ramki-motion-enabled .page-process-card.is-visible:hover {
    transform: translateY(-8px);
}

.service-detail-card:hover .service-detail-icon,
.page-process-card:hover .page-process-icon {
    animation: ramkiIconPulse .72s cubic-bezier(.22, 1, .36, 1);
}

.page-hero-note.is-visible {
    animation: ramkiGentleFloat 5.8s ease-in-out 1s infinite;
}

.service-carousel-slide:hover .service-carousel-image img {
    transform: scale(1.055);
}

.service-carousel-image img {
    transition: transform .65s cubic-bezier(.22, 1, .36, 1);
}

@keyframes ramkiGentleFloat {
    0%, 100% { translate: 0 0; }
    50% { translate: 0 -8px; }
}

@keyframes ramkiIconPulse {
    0% { transform: scale(1) rotate(0); }
    45% { transform: scale(1.13) rotate(-5deg); }
    100% { transform: scale(1) rotate(0); }
}

@media (max-width: 700px) {
    .ramki-motion-enabled .ramki-emerge,
    .ramki-motion-enabled .ramki-emerge-left,
    .ramki-motion-enabled .ramki-emerge-right {
        transform: translate3d(0, 25px, 0);
        filter: blur(3px);
    }

    .ramki-motion-enabled .ramki-emerge.is-visible {
        transform: translate3d(0, 0, 0);
        filter: blur(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .ramki-motion-enabled .ramki-emerge,
    .ramki-motion-enabled .ramki-emerge.is-visible {
        opacity: 1 !important;
        transform: none !important;
        filter: none !important;
        transition: none !important;
        animation: none !important;
    }

    .page-hero-note.is-visible,
    .service-detail-card:hover .service-detail-icon,
    .page-process-card:hover .page-process-icon {
        animation: none !important;
    }
}
</style>

<main class="store-inner-page services-page">
    <section class="page-hero page-hero-services">
        <div class="container page-hero-grid">
            <div class="page-hero-copy ramki-emerge ramki-emerge-left">
                <span class="page-eyebrow">What We Do</span>
                <h1>Beautiful details for every <em>celebration.</em></h1>
                <p class="page-lead">
                    <?= sf_e($servicesSection['section_content'] ?? 'From invitation design and premium printing to thoughtful celebration essentials, we bring every detail together with care.'); ?>
                </p>
                <div class="page-actions">
                    <a class="page-action page-action-primary" href="products.php">Explore Collections <span>→</span></a>
                    <a class="page-action page-action-outline" href="gallery.php">View Invitation Gallery</a>
                </div>
            </div>
            <div class="page-hero-note ramki-emerge ramki-emerge-right" style="--ramki-delay: 120ms" aria-label="Ramki Cards service promise">
                <span class="page-hero-note-mark"><?= sf_e($experienceNumber); ?>+</span>
                <strong>Years of trusted craftsmanship</strong>
                <p>Traditional warmth, modern design and dependable delivery.</p>
            </div>
        </div>
    </section>

    <section class="section page-section" aria-labelledby="servicesHeading">
        <div class="container">
            <div class="section-title page-section-title ramki-emerge">
                <div class="decor-line"></div>
                <span>Made for your occasion</span>
                <h2 id="servicesHeading"><?= sf_e($servicesSection['section_title'] ?? 'Our'); ?> <em><?= sf_e($servicesSection['section_subtitle'] ?? 'Services'); ?></em></h2>
                <p>Choose a service and our team can help tailor it to your celebration.</p>
            </div>

            <div class="service-detail-grid">
                <?php foreach ($services as $serviceIndex => $service): ?>
                    <?php
                    $serviceImage = trim((string)($service['image_path'] ?? ''));
                    $serviceLink = trim((string)($service['link_url'] ?? '')) ?: 'products.php';
                    $serviceLinkText = trim((string)($service['link_text'] ?? '')) ?: 'Explore options';
                    ?>
                    <article class="service-detail-card ramki-emerge" style="--ramki-delay: <?= min($serviceIndex, 5) * 85; ?>ms">
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

    <section class="section page-process-section" aria-labelledby="processHeading">
        <div class="container">
            <div class="section-title page-section-title ramki-emerge">
                <div class="decor-line"></div>
                <span>Simple from start to finish</span>
                <h2 id="processHeading"><?= sf_e($customSection['section_title'] ?? 'Your idea,'); ?> <em><?= sf_e($customSection['section_subtitle'] ?? 'beautifully made'); ?></em></h2>
            </div>
            <div class="page-process-grid">
                <?php foreach ($customSteps as $index => $step): ?>
                    <article class="page-process-card ramki-emerge" style="--ramki-delay: <?= min($index, 4) * 95; ?>ms">
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
        <div class="container page-cta-inner ramki-emerge ramki-emerge-scale">
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
    const items = [...document.querySelectorAll('.ramki-emerge')];
    if (!items.length) return;

    const revealAll = () => items.forEach(item => item.classList.add('is-visible'));
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reducedMotion || !('IntersectionObserver' in window)) {
        revealAll();
        return;
    }

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, {
        threshold: 0.12,
        rootMargin: '0px 0px -8% 0px'
    });

    items.forEach(item => observer.observe(item));

    window.setTimeout(() => {
        items.forEach(item => {
            if (item.getBoundingClientRect().top < window.innerHeight * 1.15) {
                item.classList.add('is-visible');
            }
        });
    }, 450);

    window.setTimeout(revealAll, 4500);
})();
</script>


<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
