<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$companyName = sf_setting($pdo, 'company_name', 'Ramki Cards');
$phoneNumber = sf_setting($pdo, 'phone_number', '96299 54411');
$whatsappNumber = sf_setting($pdo, 'whatsapp_number', $phoneNumber);
$topItems = sf_section_items($pdo, 'top_strip');
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
    ];
}

$galleryItems = [];
$usedPaths = [];

try {
    $galleryStatement = $pdo->query(
        "SELECT
            pi.image_path,
            COALESCE(NULLIF(pi.alt_text, ''), p.product_name) AS alt_text,
            p.product_name,
            p.slug AS product_slug,
            c.category_name,
            c.slug AS category_slug
         FROM product_images pi
         INNER JOIN products p ON p.id = pi.product_id
         INNER JOIN categories c ON c.id = p.category_id
         WHERE pi.status = 'active'
           AND p.status = 'active'
           AND p.deleted_at IS NULL
           AND c.status = 'active'
           AND c.deleted_at IS NULL
           AND pi.image_path IS NOT NULL
           AND pi.image_path <> ''
         ORDER BY p.is_featured DESC, pi.sort_order, pi.id DESC
         LIMIT 30"
    );

    foreach ($galleryStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $path = trim((string)($row['image_path'] ?? ''));
        if ($path === '' || isset($usedPaths[$path])) {
            continue;
        }

        $usedPaths[$path] = true;
        $galleryItems[] = [
            'src' => sf_media_path($path, 'banner.png'),
            'alt' => (string)($row['alt_text'] ?? $row['product_name']),
            'title' => (string)$row['product_name'],
            'category' => (string)$row['category_name'],
            'category_slug' => (string)$row['category_slug'],
            'product_url' => 'product.php?slug=' . rawurlencode((string)$row['product_slug']),
            'is_sample' => false,
        ];
    }
} catch (Throwable $exception) {
    // The curated image collection below keeps the gallery available.
}

$sampleGallery = [
    ['file' => 'gallery_image_01.png', 'title' => 'Royal Maroon Wedding Suite', 'category' => 'Wedding Cards', 'slug' => 'wedding-cards'],
    ['file' => 'gallery_image_02.png', 'title' => 'Emerald Heritage Invitation', 'category' => 'Luxury Designs', 'slug' => 'luxury-designs'],
    ['file' => 'gallery_image_03.png', 'title' => 'Blush Floral Wedding Card', 'category' => 'Floral Designs', 'slug' => 'floral-designs'],
    ['file' => 'gallery_image_04.png', 'title' => 'Traditional Peacock Invitation', 'category' => 'Traditional Cards', 'slug' => 'traditional-cards'],
    ['file' => 'gallery_image_05.png', 'title' => 'Rose Gold Laser-Cut Card', 'category' => 'Luxury Designs', 'slug' => 'luxury-designs'],
    ['file' => 'gallery_image_06.png', 'title' => 'Ivory Bow Invitation Box', 'category' => 'Invitation Boxes', 'slug' => 'invitation-boxes'],
    ['file' => 'gallery_image_07.png', 'title' => 'Midnight Blue Floral Card', 'category' => 'Floral Designs', 'slug' => 'floral-designs'],
    ['file' => 'gallery_image_08.png', 'title' => 'Classic Ivory Gold Invitation', 'category' => 'Wedding Cards', 'slug' => 'wedding-cards'],
    ['file' => 'gallery_image_09.png', 'title' => 'Ganesha Ceremony Card', 'category' => 'Traditional Cards', 'slug' => 'traditional-cards'],
    ['file' => 'gallery_image_10.png', 'title' => 'Premium Laser-Cut Invitation', 'category' => 'Luxury Designs', 'slug' => 'luxury-designs'],
    ['file' => 'gallery_image_11.png', 'title' => 'Save the Date Floral Card', 'category' => 'Engagement Cards', 'slug' => 'engagement-cards'],
    ['file' => 'gallery_image_12.png', 'title' => 'Burgundy Invitation Gift Box', 'category' => 'Invitation Boxes', 'slug' => 'invitation-boxes'],
];

foreach ($sampleGallery as $sample) {
    $galleryItems[] = [
        'src' => 'assets/images/gallery/' . $sample['file'],
        'alt' => $sample['title'],
        'title' => $sample['title'],
        'category' => $sample['category'],
        'category_slug' => $sample['slug'],
        'product_url' => 'products.php?category=' . rawurlencode($sample['slug']),
        'is_sample' => true,
    ];
}

$galleryCategories = [];
foreach ($galleryItems as $item) {
    $slug = trim((string)$item['category_slug']);
    if ($slug !== '' && !isset($galleryCategories[$slug])) {
        $galleryCategories[$slug] = (string)$item['category'];
    }
}

/*
 * Customer reviews carousel. Prefers gallery-specific testimonials,
 * falls back to the shared home-page testimonials, then to a curated
 * sample list so the section always has content and photos.
 */
$galleryReviews = sf_section_items($pdo, 'testimonials', 'gallery');

if (!$galleryReviews) {
    $galleryReviews = sf_section_items($pdo, 'testimonials');
}

if (!$galleryReviews) {
    $galleryReviews = [
        ['item_title' => 'Priya S.', 'item_subtitle' => 'Chennai', 'item_content' => 'The design, print quality and finishing were absolutely amazing. Our guests loved the cards.'],
        ['item_title' => 'Arun K.', 'item_subtitle' => 'Coimbatore', 'item_content' => 'Excellent service and on-time delivery. They understood exactly what we wanted for our wedding.'],
        ['item_title' => 'Meera R.', 'item_subtitle' => 'Bangalore', 'item_content' => 'Beautiful collection with so many options in the gallery. The customisation was perfect.'],
        ['item_title' => 'Karthik V.', 'item_subtitle' => 'Madurai', 'item_content' => 'Loved how easy it was to pick a design from the gallery and get it personalised for us.'],
        ['item_title' => 'Divya N.', 'item_subtitle' => 'Trichy', 'item_content' => 'Premium paper, gorgeous foiling and quick turnaround. Highly recommend Ramki Cards.'],
        ['item_title' => 'Suresh B.', 'item_subtitle' => 'Salem', 'item_content' => 'The team guided us through every step, from design to delivery. Truly a five-star experience.'],
    ];
}

$reviewSampleImages = [
    'assets/images/testimonials/customer_01.jpg',
    'assets/images/testimonials/customer_02.jpg',
    'assets/images/testimonials/customer_03.jpg',
    'assets/images/testimonials/customer_04.jpg',
    'assets/images/testimonials/customer_05.jpg',
    'assets/images/testimonials/customer_06.jpg',
];

$pageTitle = 'Invitation Gallery | ' . $companyName;
$whatsappUrl = sf_whatsapp_url(
    $whatsappNumber,
    'Hello ' . $companyName . ', I would like help choosing an invitation design from your gallery.'
);

require __DIR__ . '/includes/storefront-header.php';
?>

<script>
document.documentElement.classList.add('ramki-motion-enabled');
</script>

<style>
/* Compact responsive layout scoped to gallery.php. */
.gallery-page {
    max-width: 100%;
    overflow-x: clip;
}

.gallery-page img {
    display: block;
    max-width: 100%;
}

@media (max-width: 900px) {
    .gallery-page .container {
        width: min(100% - 32px, 1200px);
    }

    .gallery-page .gallery-premium-hero {
        min-height: 0;
        padding: 46px 0 40px;
    }

    .gallery-page .gallery-premium-hero .page-hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 32px;
        align-items: start;
    }

    .gallery-page .page-hero-copy,
    .gallery-page .gallery-hero-showcase {
        width: 100%;
        min-width: 0;
        max-width: 680px;
        margin-inline: auto;
    }

    .gallery-page .gallery-hero-showcase {
        height: 430px;
        justify-self: center;
    }

    .gallery-page .gallery-heading-row {
        align-items: flex-end;
        gap: 18px;
    }

    .gallery-page .page-cta-inner {
        align-items: flex-start;
        flex-direction: column;
        gap: 22px;
    }
}

@media (max-width: 680px) {
    .gallery-page .container {
        width: min(100% - 24px, 1200px);
    }

    .gallery-page .gallery-premium-hero {
        padding: 32px 0 30px;
    }

    .gallery-page .gallery-premium-hero .page-hero-grid {
        gap: 24px;
    }

    .gallery-page .page-eyebrow {
        font-size: 10px;
        letter-spacing: .13em;
    }

    .gallery-page .page-hero-copy h1 {
        max-width: 100%;
        margin-bottom: 14px;
        font-size: clamp(34px, 10.8vw, 47px);
        line-height: 1.02;
        overflow-wrap: anywhere;
    }

    .gallery-page .page-lead {
        max-width: 100%;
        font-size: 13px;
        line-height: 1.62;
    }

    .gallery-page .page-actions {
        display: grid;
        width: 100%;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-top: 19px;
    }

    .gallery-page .page-action {
        display: inline-flex;
        min-width: 0;
        min-height: 44px;
        align-items: center;
        justify-content: center;
        padding: 10px 11px;
        font-size: 10px;
        line-height: 1.3;
        text-align: center;
        white-space: normal;
    }

    .gallery-page .services-hero-trust {
        display: grid;
        width: 100%;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 7px;
        margin-top: 18px;
        overflow: visible;
    }

    .gallery-page .services-hero-trust>span {
        min-width: 0;
        flex-direction: column;
        justify-content: center;
        gap: 5px;
        padding: 9px 4px;
        text-align: center;
    }

    .gallery-page .services-hero-trust strong {
        width: 100%;
        font-size: 9px;
        white-space: normal;
    }

    .gallery-page .gallery-hero-showcase {
        width: min(100%, 420px);
        height: clamp(300px, 89vw, 365px);
    }

    .gallery-page .services-hero-main-image {
        width: 79%;
        height: 88%;
        right: 1%;
        border-width: 5px;
        border-radius: 20px;
    }

    .gallery-page .services-hero-accent-image {
        width: 37%;
        height: 38%;
        left: 1px;
        bottom: 3px;
        border-width: 5px;
        border-radius: 15px;
    }

    .gallery-page .services-signature-card {
        width: 60%;
        right: 0;
        bottom: 0;
        padding: 12px 14px;
        border-radius: 14px;
    }

    .gallery-page .services-signature-card strong {
        font-size: 18px;
    }

    .gallery-page .services-signature-card p {
        margin-top: 4px;
        font-size: 9px;
        line-height: 1.42;
    }

    .gallery-page .services-hero-ghost {
        opacity: .04;
    }

    .gallery-page .gallery-collection,
    .gallery-page .gallery-reviews-section {
        padding-block: 44px;
    }

    .gallery-page .gallery-heading-row {
        align-items: flex-start;
        flex-direction: column;
        gap: 10px;
    }

    .gallery-page .page-section-title,
    .gallery-page .page-section-title-left {
        width: 100%;
        max-width: 100%;
        text-align: left;
    }

    .gallery-page .page-section-title h2 {
        font-size: clamp(29px, 8.6vw, 39px);
        line-height: 1.08;
        overflow-wrap: anywhere;
    }

    .gallery-page .gallery-heading-meta {
        width: 100%;
        justify-content: flex-start;
    }

    .gallery-page .gallery-carousel-controls {
        display: none;
    }

    .gallery-page .gallery-filter-bar {
        display: grid;
        width: 100%;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        padding: 0;
        overflow: visible;
    }

    .gallery-page .gallery-filter-bar button {
        width: 100%;
        min-width: 0;
        min-height: 38px;
        justify-content: center;
        padding: 8px 9px;
        border-radius: 12px;
        font-size: 10px;
        line-height: 1.25;
        text-align: center;
        white-space: normal;
    }

    .gallery-page .gallery-carousel {
        margin-top: 22px;
    }

    .gallery-page .gallery-carousel-viewport,
    .gallery-page .reviews-carousel-viewport {
        overflow: visible;
        scroll-snap-type: none;
    }

    .gallery-page .gallery-carousel-track {
        display: grid;
        width: 100%;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        transform: none !important;
    }

    .gallery-page .gallery-carousel-slide {
        width: auto;
        min-width: 0;
        max-width: none;
        flex: none;
        flex-basis: auto;
        border-radius: 14px;
        scroll-snap-align: none;
    }

    .gallery-page .gallery-preview-button {
        min-height: 0;
        aspect-ratio: 4 / 5;
        border-radius: 14px 14px 0 0;
    }

    .gallery-page .gallery-card-copy {
        gap: 4px;
        padding: 15px 11px 12px;
        transform: none;
    }

    .gallery-page .gallery-card-copy small {
        font-size: 7px;
        letter-spacing: .09em;
    }

    .gallery-page .gallery-card-copy strong {
        display: -webkit-box;
        overflow: hidden;
        font-size: clamp(14px, 4.2vw, 17px);
        line-height: 1.16;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .gallery-page .gallery-card-copy>span {
        gap: 4px;
        opacity: 1;
        font-size: 9px;
    }

    .gallery-page .gallery-product-link {
        gap: 5px;
        padding: 10px 9px;
        font-size: 9px;
        line-height: 1.2;
    }

    .gallery-page .gallery-no-results {
        padding: 30px 16px;
    }

    .gallery-page .reviews-carousel-track {
        display: grid;
        width: 100%;
        grid-template-columns: minmax(0, 1fr);
        gap: 12px;
        transform: none !important;
    }

    .gallery-page .review-carousel-card {
        width: auto;
        min-width: 0;
        max-width: none;
        flex: none;
        flex-basis: auto;
        padding: 23px 19px 20px;
        scroll-snap-align: none;
    }

    .gallery-page .gallery-cta-section {
        padding-block: 44px;
    }

    .gallery-page .page-cta-inner h2 {
        font-size: clamp(29px, 8.8vw, 40px);
        line-height: 1.08;
        overflow-wrap: anywhere;
    }

    .gallery-page .page-cta-inner .page-actions {
        margin-top: 0;
    }

    .gallery-lightbox {
        padding: 10px;
    }

    .gallery-lightbox-dialog {
        width: min(100%, 520px);
        max-height: calc(100dvh - 20px);
    }

    .gallery-lightbox-dialog figure {
        max-height: calc(100dvh - 20px);
    }

    .gallery-lightbox-dialog figure>img {
        max-height: calc(100dvh - 100px);
        object-fit: contain;
    }

    .gallery-lightbox-dialog figcaption {
        align-items: flex-start;
        flex-direction: column;
        gap: 3px;
        padding: 11px 12px;
    }

    .gallery-lightbox-prev {
        left: 7px;
    }

    .gallery-lightbox-next {
        right: 7px;
    }
}

@media (max-width: 420px) {

    .gallery-page .page-actions,
    .gallery-page .page-cta-inner .page-actions {
        grid-template-columns: minmax(0, 1fr);
    }

    .gallery-page .gallery-hero-showcase {
        height: 290px;
    }

    .gallery-page .services-signature-card {
        width: 63%;
    }

    .gallery-page .gallery-filter-bar {
        gap: 7px;
    }

    .gallery-page .gallery-carousel-track {
        gap: 8px;
    }

    .gallery-page .gallery-product-link {
        padding-inline: 7px;
        font-size: 8px;
    }
}

@media (max-width: 340px) {
    .gallery-page .gallery-filter-bar {
        grid-template-columns: minmax(0, 1fr);
    }

    .gallery-page .gallery-carousel-track {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (prefers-reduced-motion: reduce) {

    .gallery-page .ramki-emerge,
    .gallery-page .ramki-emerge.is-visible {
        opacity: 1 !important;
        transform: none !important;
        transition: none !important;
    }
}
</style>

<main class="store-inner-page gallery-page services-premium-page">
    <section class="page-hero page-hero-services services-premium-hero gallery-premium-hero">
        <span class="services-hero-ghost services-hero-ghost-one" aria-hidden="true"></span>
        <span class="services-hero-ghost services-hero-ghost-two" aria-hidden="true"></span>
        <div class="container page-hero-grid">
            <div class="page-hero-copy ramki-emerge ramki-emerge-left">
                <span class="page-eyebrow">The Ramki Collection</span>
                <h1>Invitation designs made for <em>beautiful memories.</em></h1>
                <p class="page-lead">Explore traditional, floral and contemporary invitation cards, thoughtfully curated
                    and ready to personalise.</p>
                <div class="page-actions">
                    <a class="page-action page-action-primary" href="#galleryCollection">View Designs <span>→</span></a>
                    <a class="page-action page-action-outline" href="services.php">Explore Our Services</a>
                </div>
                <div class="services-hero-trust" aria-label="Ramki Cards gallery highlights">
                    <span><b aria-hidden="true">✦</b><strong><?= count($galleryItems); ?>+ Designs</strong></span>
                    <span><b aria-hidden="true">⌕</b><strong>Quick Preview</strong></span>
                    <span><b aria-hidden="true">⌁</b><strong>Customisable</strong></span>
                </div>
            </div>

            <div class="services-hero-showcase gallery-hero-showcase ramki-emerge ramki-emerge-right"
                style="--ramki-delay: 120ms">
                <span class="services-hero-ring" aria-hidden="true"></span>
                <div class="services-hero-main-image">
                    <img src="assets/images/services/04_return_gifts.jpg"
                        alt="Blush floral wedding invitation from the Ramki Cards gallery" loading="eager"
                        decoding="async" fetchpriority="high">
                </div>
                <div class="services-hero-accent-image">
                    <img src="assets/images/services/01_wedding_cards.jpg"
                        alt="Traditional ceremony invitation from the Ramki Cards gallery" loading="lazy"
                        decoding="async">
                </div>
                <div class="services-signature-card" aria-label="Ramki Cards gallery promise">
                    <span>Ramki Cards</span>
                    <strong>Premium Finish
                    </strong>
                    <p>Quality printing with elegant detailing.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section gallery-collection" id="galleryCollection" aria-labelledby="galleryTitle">
        <div class="container">
            <div class="gallery-heading-row ramki-emerge">
                <div class="section-title page-section-title page-section-title-left">
                    <div class="decor-line"></div>
                    <span>Browse our work</span>
                    <h2 id="galleryTitle">Find a design that feels <em>like yours</em></h2>
                </div>
                <div class="gallery-heading-meta">
                    <p class="gallery-result-count"><strong data-gallery-count><?= count($galleryItems); ?></strong>
                        designs</p>
                    <div class="gallery-carousel-controls">
                        <button type="button" class="gallery-carousel-btn" data-gallery-prev-slide
                            aria-label="Previous designs">←</button>
                        <button type="button" class="gallery-carousel-btn" data-gallery-next-slide
                            aria-label="Next designs">→</button>
                    </div>
                </div>
            </div>

            <div class="gallery-filter-bar ramki-emerge" role="group" aria-label="Filter gallery by category">
                <button type="button" class="is-active" data-gallery-filter="all" aria-pressed="true">All
                    Designs</button>
                <?php foreach ($galleryCategories as $slug => $label): ?>
                <button type="button" data-gallery-filter="<?= sf_e($slug); ?>"
                    aria-pressed="false"><?= sf_e($label); ?></button>
                <?php endforeach; ?>
            </div>

            <div class="gallery-carousel ramki-emerge">
                <div class="gallery-carousel-viewport" data-gallery-viewport>
                    <div class="gallery-carousel-track" data-gallery-grid>
                        <?php foreach ($galleryItems as $index => $item): ?>
                        <?php $fallback = 'assets/images/gallery/gallery_image_' . str_pad((string)(($index % 12) + 1), 2, '0', STR_PAD_LEFT) . '.png'; ?>
                        <article class="gallery-carousel-slide" data-gallery-item
                            data-category="<?= sf_e($item['category_slug']); ?>">
                            <button class="gallery-preview-button" type="button" data-gallery-open
                                data-image="<?= sf_e($item['src']); ?>" data-fallback="<?= sf_e($fallback); ?>"
                                data-title="<?= sf_e($item['title']); ?>"
                                data-category-label="<?= sf_e($item['category']); ?>"
                                aria-label="Preview <?= sf_e($item['title']); ?>">
                                <img src="<?= sf_e($item['src']); ?>" data-fallback-src="<?= sf_e($fallback); ?>"
                                    alt="<?= sf_e($item['alt']); ?>" loading="<?= $index < 4 ? 'eager' : 'lazy'; ?>"
                                    decoding="async"
                                    onerror="if(this.dataset.fallbackSrc && this.src.indexOf(this.dataset.fallbackSrc) === -1){this.src=this.dataset.fallbackSrc;}else{this.onerror=null;}">
                                <span class="gallery-card-shade" aria-hidden="true"></span>
                                <span class="gallery-card-copy">
                                    <small><?= sf_e($item['category']); ?></small>
                                    <strong><?= sf_e($item['title']); ?></strong>
                                    <span>View design <b>↗</b></span>
                                </span>
                            </button>
                            <a class="gallery-product-link" href="<?= sf_e($item['product_url']); ?>">Explore collection
                                <span>→</span></a>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="gallery-no-results" data-gallery-empty hidden>
                <span>✦</span>
                <h3>No designs in this category yet</h3>
                <p>Please select another category or view all designs.</p>
            </div>
        </div>
    </section>

    <section class="section gallery-reviews-section" aria-labelledby="galleryReviewsTitle">
        <div class="container">
            <div class="gallery-heading-row ramki-emerge">
                <div class="section-title page-section-title page-section-title-left">
                    <div class="decor-line"></div>
                    <span>Loved by our customers</span>
                    <h2 id="galleryReviewsTitle">Real stories from <em>real celebrations</em></h2>
                </div>
                <div class="gallery-heading-meta">
                    <div class="gallery-carousel-controls">
                        <button type="button" class="gallery-carousel-btn" data-review-prev
                            aria-label="Previous reviews">←</button>
                        <button type="button" class="gallery-carousel-btn" data-review-next
                            aria-label="Next reviews">→</button>
                    </div>
                </div>
            </div>

            <div class="reviews-carousel ramki-emerge">
                <div class="reviews-carousel-viewport" data-review-viewport>
                    <div class="reviews-carousel-track">
                        <?php foreach ($galleryReviews as $reviewIndex => $review): ?>
                        <?php
                            $reviewImageSource = (string)($review['item_image'] ?? $review['image_path'] ?? '');
                            $reviewImage = sf_media_path(
                                $reviewImageSource,
                                $reviewSampleImages[$reviewIndex % count($reviewSampleImages)]
                            );
                            $reviewName = trim((string)($review['item_title'] ?? ''));
                            $reviewInitial = $reviewName !== '' ? strtoupper(substr($reviewName, 0, 1)) : 'R';
                            ?>
                        <article class="review-carousel-card">
                            <span class="review-quote-mark" aria-hidden="true">&ldquo;</span>
                            <div class="review-stars" aria-hidden="true">★★★★★</div>
                            <p><?= sf_e($review['item_content'] ?? ''); ?></p>
                            <div class="review-person">
                                <span class="review-avatar">
                                    <span class="review-avatar-fallback"><?= sf_e($reviewInitial); ?></span>
                                    <img src="<?= sf_e($reviewImage); ?>" alt="<?= sf_e($reviewName); ?>" loading="lazy"
                                        decoding="async" onerror="this.style.display='none';">
                                </span>
                                <div>
                                    <h4><?= sf_e($reviewName); ?></h4>
                                    <span><?= sf_e($review['item_subtitle'] ?? ''); ?></span>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="page-cta-section gallery-cta-section">
        <div class="container page-cta-inner ramki-emerge">
            <div>
                <span>Make it personal</span>
                <h2>Found a style you love?</h2>
                <p>Share the design with our team and customise the wording, language, colours and finish.</p>
            </div>
            <div class="page-actions">
                <a class="page-action page-action-gold" href="<?= sf_e($whatsappUrl); ?>" target="_blank"
                    rel="noopener">Chat on WhatsApp</a>
                <a class="page-action page-action-light" href="services.php">How Customisation Works</a>
            </div>
        </div>
    </section>
</main>

<div class="gallery-lightbox" data-gallery-lightbox hidden aria-hidden="true">
    <button class="gallery-lightbox-backdrop" type="button" data-gallery-close
        aria-label="Close image preview"></button>
    <div class="gallery-lightbox-dialog" role="dialog" aria-modal="true" aria-labelledby="galleryLightboxTitle">
        <button class="gallery-lightbox-close" type="button" data-gallery-close
            aria-label="Close image preview">×</button>
        <button class="gallery-lightbox-nav gallery-lightbox-prev" type="button" data-gallery-prev
            aria-label="Previous design">←</button>
        <figure>
            <img src="" alt="" data-gallery-lightbox-image>
            <figcaption>
                <small data-gallery-lightbox-category></small>
                <strong id="galleryLightboxTitle" data-gallery-lightbox-title></strong>
            </figcaption>
        </figure>
        <button class="gallery-lightbox-nav gallery-lightbox-next" type="button" data-gallery-next
            aria-label="Next design">→</button>
    </div>
</div>

<script>
(() => {
    const cards = [...document.querySelectorAll('[data-gallery-item]')];
    const filterButtons = [...document.querySelectorAll('[data-gallery-filter]')];
    const count = document.querySelector('[data-gallery-count]');
    const empty = document.querySelector('[data-gallery-empty]');
    const galleryViewport = document.querySelector('[data-gallery-viewport]');
    const galleryPrevBtn = document.querySelector('[data-gallery-prev-slide]');
    const galleryNextBtn = document.querySelector('[data-gallery-next-slide]');
    const lightbox = document.querySelector('[data-gallery-lightbox]');
    const lightboxImage = lightbox?.querySelector('[data-gallery-lightbox-image]');
    const lightboxTitle = lightbox?.querySelector('[data-gallery-lightbox-title]');
    const lightboxCategory = lightbox?.querySelector('[data-gallery-lightbox-category]');
    let visibleCards = cards;
    let currentIndex = 0;
    let lastTrigger = null;

    const setFilter = filter => {
        visibleCards = [];
        cards.forEach(card => {
            const visible = filter === 'all' || card.dataset.category === filter;
            card.hidden = !visible;
            if (visible) visibleCards.push(card);
        });

        filterButtons.forEach(button => {
            const active = button.dataset.galleryFilter === filter;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (count) count.textContent = String(visibleCards.length);
        if (empty) empty.hidden = visibleCards.length !== 0;
        galleryViewport?.scrollTo({
            left: 0,
            behavior: 'auto'
        });
        updateGalleryControls();
        history.replaceState(null, '', filter === 'all' ? 'gallery.php' :
            `gallery.php?category=${encodeURIComponent(filter)}`);
    };

    const scrollCarousel = (viewport, direction) => {
        if (!viewport) return;
        const slide = viewport.querySelector(':scope > * > :not([hidden])') ||
            viewport.querySelector(':scope > *');
        const step = slide ? slide.getBoundingClientRect().width + 20 : viewport.clientWidth * 0.8;
        viewport.scrollBy({
            left: step * direction,
            behavior: 'smooth'
        });
    };

    const updateGalleryControls = () => {
        if (!galleryViewport || !galleryPrevBtn || !galleryNextBtn) return;
        const maxScroll = galleryViewport.scrollWidth - galleryViewport.clientWidth - 2;
        galleryPrevBtn.disabled = galleryViewport.scrollLeft <= 0;
        galleryNextBtn.disabled = galleryViewport.scrollLeft >= maxScroll;
    };

    galleryPrevBtn?.addEventListener('click', () => scrollCarousel(galleryViewport, -1));
    galleryNextBtn?.addEventListener('click', () => scrollCarousel(galleryViewport, 1));
    galleryViewport?.addEventListener('scroll', updateGalleryControls, {
        passive: true
    });
    window.addEventListener('resize', updateGalleryControls);

    const reviewViewport = document.querySelector('[data-review-viewport]');
    const reviewPrevBtn = document.querySelector('[data-review-prev]');
    const reviewNextBtn = document.querySelector('[data-review-next]');

    const updateReviewControls = () => {
        if (!reviewViewport || !reviewPrevBtn || !reviewNextBtn) return;
        const maxScroll = reviewViewport.scrollWidth - reviewViewport.clientWidth - 2;
        reviewPrevBtn.disabled = reviewViewport.scrollLeft <= 0;
        reviewNextBtn.disabled = reviewViewport.scrollLeft >= maxScroll;
    };

    reviewPrevBtn?.addEventListener('click', () => scrollCarousel(reviewViewport, -1));
    reviewNextBtn?.addEventListener('click', () => scrollCarousel(reviewViewport, 1));
    reviewViewport?.addEventListener('scroll', updateReviewControls, {
        passive: true
    });
    window.addEventListener('resize', updateReviewControls);
    updateReviewControls();

    const openAt = index => {
        if (!lightbox || !visibleCards.length) return;
        currentIndex = (index + visibleCards.length) % visibleCards.length;
        const trigger = visibleCards[currentIndex].querySelector('[data-gallery-open]');
        if (!trigger) return;
        lastTrigger = trigger;
        lightboxImage.src = trigger.dataset.image || '';
        lightboxImage.dataset.fallback = trigger.dataset.fallback || '';
        lightboxImage.alt = trigger.dataset.title || 'Invitation design';
        lightboxTitle.textContent = trigger.dataset.title || '';
        lightboxCategory.textContent = trigger.dataset.categoryLabel || '';
        lightbox.hidden = false;
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.classList.add('gallery-lightbox-open');
        lightbox.querySelector('[data-gallery-close]')?.focus();
    };

    const close = () => {
        if (!lightbox) return;
        lightbox.hidden = true;
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('gallery-lightbox-open');
        lastTrigger?.focus();
    };

    filterButtons.forEach(button => button.addEventListener('click', () => setFilter(button.dataset.galleryFilter ||
        'all')));
    cards.forEach(card => card.querySelector('[data-gallery-open]')?.addEventListener('click', event => {
        visibleCards = cards.filter(item => !item.hidden);
        openAt(visibleCards.indexOf(card));
    }));
    lightbox?.querySelectorAll('[data-gallery-close]').forEach(button => button.addEventListener('click', close));
    lightbox?.querySelector('[data-gallery-prev]')?.addEventListener('click', () => openAt(currentIndex - 1));
    lightbox?.querySelector('[data-gallery-next]')?.addEventListener('click', () => openAt(currentIndex + 1));
    lightboxImage?.addEventListener('error', () => {
        const fallback = lightboxImage.dataset.fallback;
        if (fallback && lightboxImage.src.indexOf(fallback) === -1) lightboxImage.src = fallback;
    });

    document.addEventListener('keydown', event => {
        if (!lightbox || lightbox.hidden) return;
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowLeft') openAt(currentIndex - 1);
        if (event.key === 'ArrowRight') openAt(currentIndex + 1);
    });

    const requested = new URLSearchParams(location.search).get('category') || 'all';
    setFilter(filterButtons.some(button => button.dataset.galleryFilter === requested) ? requested : 'all');

    const revealItems = [...document.querySelectorAll('.ramki-emerge')];
    if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) {
        revealItems.forEach(item => item.classList.add('is-visible'));
    } else {
        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        }), {
            threshold: .1,
            rootMargin: '0px 0px -6% 0px'
        });
        revealItems.forEach(item => observer.observe(item));
    }
})();
</script>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>