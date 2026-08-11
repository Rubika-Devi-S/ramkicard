<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$companyName = sf_setting($pdo, 'company_name', 'Ramki Cards');
$phoneNumber = sf_setting($pdo, 'phone_number', '96299 54411');
$whatsappNumber = sf_setting($pdo, 'whatsapp_number', $phoneNumber);

$servicesSection = sf_section($pdo, 'services');
$services = sf_section_items($pdo, 'services');
$customSection = sf_section($pdo, 'custom_design');
$customSteps = sf_section_items($pdo, 'custom_design');
$topItems = sf_section_items($pdo, 'top_strip');

if (!$services) {
    $services = [
        ['icon_class' => '💍', 'item_title' => 'Wedding Cards', 'item_content' => 'Elegant and premium invitation designs crafted to make your special day memorable.'],
        ['icon_class' => '🎨', 'item_title' => 'Multi-Color Invitations', 'item_content' => 'Vibrant, eye-catching invitations with rich colours and premium printing.'],
        ['icon_class' => '🛍️', 'item_title' => 'Thamboolam Bags', 'item_content' => 'Traditional and custom-made thamboolam bags for every celebration.'],
        ['icon_class' => '🎁', 'item_title' => 'Return Gifts', 'item_content' => 'Thoughtful and beautifully packed return gifts that make guests feel special.'],
        ['icon_class' => '📅', 'item_title' => 'Calendars', 'item_content' => 'Custom monthly, daily and table-top calendars for homes and businesses.'],
        ['icon_class' => '📓', 'item_title' => 'Diaries', 'item_content' => 'Premium personal and business diaries with custom branding.'],
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

$serviceFallbackImages = [
    'wedding cards' => 'assets/images/services/01_wedding_cards.jpg',
    'multi color invitations' => 'assets/images/services/02_multi_color_invitations.jpg',
    'thamboolam bags' => 'assets/images/services/03_thamboolam_bags.jpg',
    'return gifts' => 'assets/images/services/04_return_gifts.jpg',
    'calendars' => 'assets/images/services/05_calendars.jpg',
    'diaries' => 'assets/images/services/06_diaries.jpg',
];
$serviceFallbackList = array_values($serviceFallbackImages);
$normaliseServiceTitle = static function (string $title): string {
    $normalised = strtolower(trim($title));
    $normalised = (string) preg_replace('/[^a-z0-9]+/', ' ', $normalised);
    return trim($normalised);
};

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

    0%,
    100% {
        translate: 0 0;
    }

    50% {
        translate: 0 -8px;
    }
}

@keyframes ramkiIconPulse {
    0% {
        transform: scale(1) rotate(0);
    }

    45% {
        transform: scale(1.13) rotate(-5deg);
    }

    100% {
        transform: scale(1) rotate(0);
    }
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

/* Page-scoped responsive safeguards for the premium Services layout. */
.services-premium-page {
    max-width: 100%;
    overflow-x: clip;
}

.services-premium-page img {
    display: block;
    max-width: 100%;
}

@media (max-width: 900px) {
    .services-premium-page .container {
        width: min(100% - 32px, 1200px);
    }

    .services-premium-page .services-premium-hero {
        min-height: 0;
        padding: 48px 0 42px;
    }

    .services-premium-page .services-premium-hero .page-hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 34px;
        align-items: start;
    }

    .services-premium-page .page-hero-copy,
    .services-premium-page .services-hero-showcase {
        width: 100%;
        min-width: 0;
        max-width: 680px;
        margin-inline: auto;
    }

    .services-premium-page .services-hero-showcase {
        height: 440px;
        justify-self: center;
    }

    .services-premium-page .service-detail-grid,
    .services-premium-page .page-process-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .services-premium-page .page-cta-inner {
        align-items: flex-start;
        flex-direction: column;
        gap: 24px;
    }
}

@media (max-width: 640px) {
    .services-premium-page .container {
        width: min(100% - 24px, 1200px);
    }

    .services-premium-page .services-premium-hero {
        padding: 34px 0 32px;
    }

    .services-premium-page .services-premium-hero .page-hero-grid {
        gap: 26px;
    }

    .services-premium-page .page-eyebrow {
        font-size: 10px;
        letter-spacing: .13em;
    }

    .services-premium-page .page-hero-copy h1 {
        max-width: 100%;
        margin-bottom: 15px;
        font-size: clamp(35px, 11vw, 48px);
        line-height: 1.02;
        overflow-wrap: anywhere;
    }

    .services-premium-page .page-lead {
        max-width: 100%;
        font-size: 13px;
        line-height: 1.65;
    }

    .services-premium-page .page-actions {
        display: grid;
        width: 100%;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    .services-premium-page .page-action {
        display: inline-flex;
        min-width: 0;
        min-height: 46px;
        align-items: center;
        justify-content: center;
        padding: 11px 12px;
        font-size: 10px;
        line-height: 1.3;
        text-align: center;
        white-space: normal;
    }

    .services-premium-page .services-hero-trust {
        display: grid;
        width: 100%;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 7px;
        overflow: visible;
    }

    .services-premium-page .services-hero-trust>span {
        min-width: 0;
        flex-direction: column;
        justify-content: center;
        gap: 6px;
        padding: 10px 5px;
        text-align: center;
    }

    .services-premium-page .services-hero-trust strong {
        width: 100%;
        font-size: 9px;
        white-space: normal;
    }

    .services-premium-page .services-hero-showcase {
        width: min(100%, 430px);
        height: clamp(310px, 91vw, 380px);
    }

    .services-premium-page .services-hero-main-image {
        width: 79%;
        height: 88%;
        right: 1%;
        border-width: 5px;
        border-radius: 21px;
    }

    .services-premium-page .services-hero-accent-image {
        width: 37%;
        height: 38%;
        left: 1px;
        bottom: 3px;
        border-width: 5px;
        border-radius: 15px;
    }

    .services-premium-page .services-signature-card {
        width: 59%;
        right: 0;
        bottom: 0;
        padding: 13px 14px;
        border-radius: 15px;
    }

    .services-premium-page .services-signature-card strong {
        font-size: 18px;
    }

    .services-premium-page .services-signature-card p {
        margin-top: 5px;
        font-size: 9px;
        line-height: 1.45;
    }

    .services-premium-page .page-section,
    .services-premium-page .page-process-section {
        padding-block: 50px;
    }

    .services-premium-page .page-section-title h2 {
        font-size: clamp(31px, 9vw, 42px);
        overflow-wrap: anywhere;
    }

    .services-premium-page .service-detail-grid,
    .services-premium-page .page-process-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 14px;
    }

    .services-premium-page .service-premium-card {
        min-height: clamp(330px, 108vw, 390px);
        border-radius: 18px;
    }

    .services-premium-page .service-premium-copy {
        padding: 24px 20px 20px;
        transform: none;
    }

    .services-premium-page .service-premium-description,
    .services-premium-page .service-premium-action {
        opacity: 1;
    }

    .services-premium-page .service-premium-title {
        font-size: clamp(23px, 7vw, 28px);
    }

    .services-premium-page .page-process-card {
        min-width: 0;
        padding: 24px 20px;
    }

    .services-premium-page .page-cta-section {
        padding-block: 46px;
    }

    .services-premium-page .page-cta-inner h2 {
        font-size: clamp(30px, 9vw, 42px);
        overflow-wrap: anywhere;
    }

    .services-premium-page .page-cta-inner .page-actions {
        margin-top: 0;
    }

    .services-premium-page .services-hero-ghost {
        opacity: .045;
    }
}

@media (max-width: 420px) {

    .services-premium-page .page-actions,
    .services-premium-page .page-cta-inner .page-actions {
        grid-template-columns: minmax(0, 1fr);
    }

    .services-premium-page .services-hero-showcase {
        height: 302px;
    }

    .services-premium-page .services-signature-card {
        width: 62%;
    }

    .services-premium-page .service-premium-card {
        min-height: 325px;
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

<main class="store-inner-page services-page services-premium-page">
    <section class="page-hero page-hero-services services-premium-hero">
        <span class="services-hero-ghost services-hero-ghost-one" aria-hidden="true"></span>
        <span class="services-hero-ghost services-hero-ghost-two" aria-hidden="true"></span>
        <div class="container page-hero-grid">
            <div class="page-hero-copy ramki-emerge ramki-emerge-left">
                <span class="page-eyebrow">The Ramki Service Experience</span>
                <h1>Beautiful details for every <em>celebration.</em></h1>
                <p class="page-lead">
                    <?= sf_e($servicesSection['section_content'] ?? 'From invitation design and premium printing to thoughtful celebration essentials, we bring every detail together with care.'); ?>
                </p>
                <div class="page-actions">
                    <a class="page-action page-action-primary" href="products.php">Explore Collections
                        <span>→</span></a>
                    <a class="page-action page-action-outline" href="gallery.php">View Invitation Gallery</a>
                </div>
                <div class="services-hero-trust" aria-label="Ramki Cards service highlights">
                    <span><b aria-hidden="true">✦</b><strong>Premium Printing</strong></span>
                    <span><b aria-hidden="true">அ</b><strong>Tamil &amp; English</strong></span>
                    <span><b aria-hidden="true">⌁</b><strong>Custom Designs</strong></span>
                </div>
            </div>
            <div class="services-hero-showcase ramki-emerge ramki-emerge-right" style="--ramki-delay: 120ms">
                <span class="services-hero-ring" aria-hidden="true"></span>
                <div class="services-hero-main-image">
                    <img src="assets/uploads/services/01_wedding_cards.jpg"
                        alt="Premium wedding invitation design by Ramki Cards" loading="eager" decoding="async"
                        fetchpriority="high">
                </div>
                <div class="services-hero-accent-image">
                    <img src="assets/uploads/services/02_multi_color_invitations.jpg"
                        alt="Multi-colour invitation collection by Ramki Cards" loading="lazy" decoding="async">
                </div>
                <div class="services-signature-card" aria-label="Ramki Cards service promise">
                    <span>Ramki Cards</span>
                    <strong>Signature Craft</strong>
                    <p>Thoughtful design and beautiful finishing.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section page-section" aria-labelledby="servicesHeading">
        <div class="container">
            <div class="section-title page-section-title ramki-emerge">
                <div class="decor-line"></div>
                <span>Made for your occasion</span>
                <h2 id="servicesHeading"><?= sf_e($servicesSection['section_title'] ?? 'Our'); ?>
                    <em><?= sf_e($servicesSection['section_subtitle'] ?? 'Services'); ?></em>
                </h2>
                <p>Choose a service and our team can help tailor it to your celebration.</p>
            </div>

            <div class="service-detail-grid">
                <?php foreach ($services as $serviceIndex => $service): ?>
                <?php
                    $serviceTitle = trim((string)($service['item_title'] ?? 'Our Service'));
                    $serviceKey = $normaliseServiceTitle($serviceTitle);
                    $fallbackImage = $serviceFallbackImages[$serviceKey]
                        ?? $serviceFallbackList[$serviceIndex % count($serviceFallbackList)];
                    $adminImage = trim((string)($service['image_path'] ?? ''));
                    $serviceImage = $adminImage !== ''
                        ? sf_media_path($adminImage, $fallbackImage)
                        : $fallbackImage;
                    $serviceLink = trim((string)($service['link_url'] ?? '')) ?: 'products.php';
                    $serviceLinkText = trim((string)($service['link_text'] ?? '')) ?: 'Explore options';
                    ?>
                <article class="service-detail-card service-premium-card ramki-emerge"
                    style="--ramki-delay: <?= min($serviceIndex, 5) * 85; ?>ms">
                    <a class="service-premium-link" href="<?= sf_e($serviceLink); ?>"
                        aria-label="Explore <?= sf_e($serviceTitle); ?>">
                        <img class="service-premium-image" src="<?= sf_e($serviceImage); ?>"
                            data-fallback-src="<?= sf_e($fallbackImage); ?>" alt="<?= sf_e($serviceTitle); ?>"
                            loading="lazy" decoding="async"
                            onerror="if(this.dataset.fallbackSrc && this.src.indexOf(this.dataset.fallbackSrc) === -1){this.src=this.dataset.fallbackSrc;}else{this.onerror=null;}">
                        <span class="service-premium-shade" aria-hidden="true"></span>
                        <span class="service-detail-icon"
                            aria-hidden="true"><?= sf_e($service['icon_class'] ?: '✦'); ?></span>
                        <span class="service-premium-copy">
                            <?php if (!empty($service['item_subtitle'])): ?>
                            <small><?= sf_e($service['item_subtitle']); ?></small>
                            <?php endif; ?>
                            <strong class="service-premium-title"><?= sf_e($serviceTitle); ?></strong>
                            <span
                                class="service-premium-description"><?= sf_e($service['item_content'] ?? 'Customised with quality materials and careful finishing.'); ?></span>
                            <span class="service-premium-action"><?= sf_e($serviceLinkText); ?> <b>→</b></span>
                        </span>
                    </a>
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
                <h2 id="processHeading"><?= sf_e($customSection['section_title'] ?? 'Your idea,'); ?>
                    <em><?= sf_e($customSection['section_subtitle'] ?? 'beautifully made'); ?></em>
                </h2>
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
                <a class="page-action page-action-gold" href="<?= sf_e($whatsappUrl); ?>" target="_blank"
                    rel="noopener">Chat on WhatsApp</a>
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