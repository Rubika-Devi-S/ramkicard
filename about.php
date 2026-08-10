<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$companyName = sf_setting($pdo, 'company_name', 'Ramki Cards');
$phoneNumber = sf_setting($pdo, 'phone_number', '96299 54411');
$whatsappNumber = sf_setting($pdo, 'whatsapp_number', $phoneNumber);

$hero = sf_section($pdo, 'hero');
$whySection = sf_section($pdo, 'why_choose');
$customSection = sf_section($pdo, 'custom_design');
$testimonialSection = sf_section($pdo, 'testimonials');
$heroItems = sf_section_items($pdo, 'hero_features');
$whyItems = sf_section_items($pdo, 'why_choose');
$customSteps = sf_section_items($pdo, 'custom_design');
$testimonials = sf_section_items($pdo, 'testimonials');
$topItems = sf_section_items($pdo, 'top_strip');

if (!$heroItems) {
    $heroItems = [
        ['icon_class' => '💎', 'item_title' => 'Premium Quality', 'item_subtitle' => '25 Years of Trust'],
        ['icon_class' => '✍', 'item_title' => 'Custom Designs', 'item_subtitle' => 'Tailored for You'],
        ['icon_class' => '🚚', 'item_title' => 'Fast Delivery', 'item_subtitle' => 'Pan India'],
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

if (!$customSteps) {
    $customSteps = [
        ['icon_class' => '▦', 'item_title' => 'Choose Design', 'item_content' => 'Browse our exclusive collections and pick your favourite.'],
        ['icon_class' => '✎', 'item_title' => 'Customize Details', 'item_content' => 'Add names, date, colours, fonts, language and more.'],
        ['icon_class' => '👁', 'item_title' => 'Preview', 'item_content' => 'Review your card design and request changes.'],
        ['icon_class' => '🚚', 'item_title' => 'Print & Deliver', 'item_content' => 'We print with perfection and deliver to your doorstep.'],
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

$experienceNumber = '25';
$experienceSource = [];
foreach (array_merge($heroItems, $whyItems) as $item) {
    $experienceSource[] = (string)($item['item_title'] ?? '');
    $experienceSource[] = (string)($item['item_subtitle'] ?? '');
    $experienceSource[] = (string)($item['item_content'] ?? '');
}

if (preg_match('/(\d+)\s*\+?\s*years?/i', implode(' ', $experienceSource), $experienceMatch)) {
    $experienceNumber = $experienceMatch[1];
}

$experienceLabel = $experienceNumber . '+ Years';
$heroImage = sf_media_path($hero['background_image_path'] ?? '', 'banner.png');
$aboutLead = trim((string)($whySection['section_content'] ?? ''));
if ($aboutLead === '') {
    $aboutLead = 'For more than ' . $experienceNumber . ' years, families have trusted us to turn meaningful moments into beautifully printed invitations and celebration keepsakes.';
}

$pageTitle = 'About Us | ' . $companyName;
$whatsappUrl = sf_whatsapp_url(
    $whatsappNumber,
    'Hello ' . $companyName . ', I would like to know more about your custom invitation services.'
);

require __DIR__ . '/includes/storefront-header.php';
?>

<main class="store-inner-page about-page">
    <section class="page-hero page-hero-about">
        <div class="container page-hero-grid">
            <div class="page-hero-copy">
                <span class="page-eyebrow">Our Story</span>
                <h1>Celebrating beginnings for <em><?= sf_e($experienceLabel); ?></em></h1>
                <p class="page-lead"><?= sf_e($aboutLead); ?></p>
                <div class="page-actions">
                    <a class="page-action page-action-primary" href="products.php">View Collections <span>→</span></a>
                    <a class="page-action page-action-outline" href="index.php#contact">Contact Us</a>
                </div>
            </div>
            <div class="about-hero-emblem" aria-label="<?= sf_e($experienceLabel); ?> of experience">
                <strong><?= sf_e($experienceNumber); ?></strong>
                <span>Years of<br>Trusted Excellence</span>
            </div>
        </div>
    </section>

    <section class="section about-story-section" aria-labelledby="storyHeading">
        <div class="container about-story-grid">
            <div class="about-story-visual">
                <img src="<?= sf_e($heroImage); ?>" alt="<?= sf_e($companyName); ?> invitation craftsmanship" loading="eager">
                <div class="about-experience-badge">
                    <strong><?= sf_e($experienceLabel); ?></strong>
                    <span>of trust & craft</span>
                </div>
            </div>
            <div class="about-story-copy">
                <div class="section-title page-section-title page-section-title-left">
                    <div class="decor-line"></div>
                    <span>The Ramki Cards journey</span>
                    <h2 id="storyHeading">Tradition in every detail, <em>care in every card.</em></h2>
                </div>
                <p class="about-story-lead">
                    What began with a love for meaningful invitations has grown into a complete celebration-printing experience for families across generations.
                </p>
                <p>
                    At <?= sf_e($companyName); ?>, we bring together traditional Indian aesthetics, contemporary layouts, quality materials and careful finishing. Whether your celebration calls for something timeless, colourful or completely custom, our team helps shape the details around your story.
                </p>
                <div class="about-signature-line">
                    <span>✦</span>
                    <p>Designed with patience. Printed with precision. Delivered with care.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="about-metrics-section" aria-label="Ramki Cards experience highlights">
        <div class="container about-metrics-grid">
            <article><strong><?= sf_e($experienceNumber); ?>+</strong><span>Years of Experience</span></article>
            <article><strong>Pan India</strong><span>Delivery Support</span></article>
            <article><strong>தமிழ் + EN</strong><span>Tamil & English Designs</span></article>
            <article><strong>Custom</strong><span>Design & Printing</span></article>
        </div>
    </section>

    <section class="section page-section" aria-labelledby="whyHeading">
        <div class="container">
            <div class="section-title page-section-title">
                <div class="decor-line"></div>
                <span>Why families choose us</span>
                <h2 id="whyHeading"><?= sf_e($whySection['section_title'] ?? 'Built on'); ?> <em><?= sf_e($whySection['section_subtitle'] ?? 'trust'); ?></em></h2>
                <p>Experience matters most when it shows up in the little things.</p>
            </div>
            <div class="about-values-grid">
                <?php foreach ($whyItems as $item): ?>
                    <article class="about-value-card">
                        <span class="about-value-icon" aria-hidden="true"><?= sf_e($item['icon_class'] ?: '✦'); ?></span>
                        <div>
                            <h3><?= sf_e($item['item_title'] ?? 'Ramki Cards'); ?></h3>
                            <p><?= sf_e($item['item_content'] ?? 'Thoughtful service for every celebration.'); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section page-process-section about-process-section" aria-labelledby="aboutProcessHeading">
        <div class="container">
            <div class="section-title page-section-title">
                <div class="decor-line"></div>
                <span>Our way of working</span>
                <h2 id="aboutProcessHeading">Personal from idea to <em>delivery</em></h2>
                <p><?= sf_e($customSection['section_content'] ?? 'A clear, collaborative process keeps your invitation true to the celebration you imagined.'); ?></p>
            </div>
            <div class="page-process-grid">
                <?php foreach ($customSteps as $index => $step): ?>
                    <article class="page-process-card">
                        <span class="page-process-number"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT); ?></span>
                        <span class="page-process-icon" aria-hidden="true"><?= sf_e($step['icon_class'] ?: '✦'); ?></span>
                        <h3><?= sf_e($step['item_title'] ?? 'Step'); ?></h3>
                        <p><?= sf_e($step['item_content'] ?? 'We guide you through every detail.'); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section about-voices-section" aria-labelledby="voicesHeading">
        <div class="container">
            <div class="section-title page-section-title">
                <div class="decor-line"></div>
                <span>Customer voices</span>
                <h2 id="voicesHeading"><?= sf_e($testimonialSection['section_title'] ?? 'Stories from'); ?> <em><?= sf_e($testimonialSection['section_subtitle'] ?? 'our families'); ?></em></h2>
            </div>
            <div class="about-voices-grid">
                <?php foreach (array_slice($testimonials, 0, 3) as $testimonial): ?>
                    <blockquote class="about-voice-card">
                        <span class="about-quote-mark">“</span>
                        <p><?= sf_e($testimonial['item_content'] ?? 'A wonderful experience from design to delivery.'); ?></p>
                        <div class="about-voice-meta">
                            <strong><?= sf_e($testimonial['item_title'] ?? 'Customer'); ?></strong>
                            <?php if (!empty($testimonial['item_subtitle'])): ?>
                                <span><?= sf_e($testimonial['item_subtitle']); ?></span>
                            <?php endif; ?>
                        </div>
                    </blockquote>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="page-cta-section">
        <div class="container page-cta-inner">
            <div>
                <span>Your celebration, your way</span>
                <h2>Let’s create something memorable.</h2>
                <p>Explore the collection or speak directly with our team about a custom design.</p>
            </div>
            <div class="page-actions">
                <a class="page-action page-action-gold" href="products.php">Explore Products</a>
                <a class="page-action page-action-light" href="<?= sf_e($whatsappUrl); ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
