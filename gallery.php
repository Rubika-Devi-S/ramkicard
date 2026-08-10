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

$pageTitle = 'Invitation Gallery | ' . $companyName;
$whatsappUrl = sf_whatsapp_url(
    $whatsappNumber,
    'Hello ' . $companyName . ', I would like help choosing an invitation design from your gallery.'
);

require __DIR__ . '/includes/storefront-header.php';
?>

<script>document.documentElement.classList.add('ramki-motion-enabled');</script>

<main class="store-inner-page gallery-page">
    <section class="gallery-hero">
        <div class="container gallery-hero-inner ramki-emerge">
            <span class="page-eyebrow">The Ramki Collection</span>
            <h1>Invitation designs made to become <em>beautiful memories.</em></h1>
            <p>Explore traditional, floral and contemporary invitation cards. Select any image for a closer look.</p>
            <div class="page-actions">
                <a class="page-action page-action-primary" href="products.php">Shop Invitations <span>→</span></a>
                <a class="page-action page-action-outline" href="services.php">Explore Our Services</a>
            </div>
        </div>
    </section>

    <section class="section gallery-collection" aria-labelledby="galleryTitle">
        <div class="container">
            <div class="gallery-heading-row ramki-emerge">
                <div class="section-title page-section-title page-section-title-left">
                    <div class="decor-line"></div>
                    <span>Browse our work</span>
                    <h2 id="galleryTitle">Find a design that feels <em>like yours</em></h2>
                </div>
                <p class="gallery-result-count"><strong data-gallery-count><?= count($galleryItems); ?></strong> designs</p>
            </div>

            <div class="gallery-filter-bar ramki-emerge" role="group" aria-label="Filter gallery by category">
                <button type="button" class="is-active" data-gallery-filter="all" aria-pressed="true">All Designs</button>
                <?php foreach ($galleryCategories as $slug => $label): ?>
                    <button type="button" data-gallery-filter="<?= sf_e($slug); ?>" aria-pressed="false"><?= sf_e($label); ?></button>
                <?php endforeach; ?>
            </div>

            <div class="gallery-modern-grid" data-gallery-grid>
                <?php foreach ($galleryItems as $index => $item): ?>
                    <?php $fallback = 'assets/images/gallery/gallery_image_' . str_pad((string)(($index % 12) + 1), 2, '0', STR_PAD_LEFT) . '.png'; ?>
                    <article class="gallery-modern-card ramki-emerge" data-gallery-item data-category="<?= sf_e($item['category_slug']); ?>" style="--ramki-delay: <?= min($index % 8, 7) * 45; ?>ms">
                        <button
                            class="gallery-preview-button"
                            type="button"
                            data-gallery-open
                            data-image="<?= sf_e($item['src']); ?>"
                            data-fallback="<?= sf_e($fallback); ?>"
                            data-title="<?= sf_e($item['title']); ?>"
                            data-category-label="<?= sf_e($item['category']); ?>"
                            aria-label="Preview <?= sf_e($item['title']); ?>">
                            <img
                                src="<?= sf_e($item['src']); ?>"
                                data-fallback-src="<?= sf_e($fallback); ?>"
                                alt="<?= sf_e($item['alt']); ?>"
                                loading="<?= $index < 4 ? 'eager' : 'lazy'; ?>"
                                decoding="async"
                                onerror="if(this.dataset.fallbackSrc && this.src.indexOf(this.dataset.fallbackSrc) === -1){this.src=this.dataset.fallbackSrc;}else{this.onerror=null;}">
                            <span class="gallery-card-shade" aria-hidden="true"></span>
                            <span class="gallery-card-copy">
                                <small><?= sf_e($item['category']); ?></small>
                                <strong><?= sf_e($item['title']); ?></strong>
                                <span>View design <b>↗</b></span>
                            </span>
                        </button>
                        <a class="gallery-product-link" href="<?= sf_e($item['product_url']); ?>">Explore collection <span>→</span></a>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="gallery-no-results" data-gallery-empty hidden>
                <span>✦</span>
                <h3>No designs in this category yet</h3>
                <p>Please select another category or view all designs.</p>
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
                <a class="page-action page-action-gold" href="<?= sf_e($whatsappUrl); ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
                <a class="page-action page-action-light" href="services.php">How Customisation Works</a>
            </div>
        </div>
    </section>
</main>

<div class="gallery-lightbox" data-gallery-lightbox hidden aria-hidden="true">
    <button class="gallery-lightbox-backdrop" type="button" data-gallery-close aria-label="Close image preview"></button>
    <div class="gallery-lightbox-dialog" role="dialog" aria-modal="true" aria-labelledby="galleryLightboxTitle">
        <button class="gallery-lightbox-close" type="button" data-gallery-close aria-label="Close image preview">×</button>
        <button class="gallery-lightbox-nav gallery-lightbox-prev" type="button" data-gallery-prev aria-label="Previous design">←</button>
        <figure>
            <img src="" alt="" data-gallery-lightbox-image>
            <figcaption>
                <small data-gallery-lightbox-category></small>
                <strong id="galleryLightboxTitle" data-gallery-lightbox-title></strong>
            </figcaption>
        </figure>
        <button class="gallery-lightbox-nav gallery-lightbox-next" type="button" data-gallery-next aria-label="Next design">→</button>
    </div>
</div>

<script>
(() => {
    const cards = [...document.querySelectorAll('[data-gallery-item]')];
    const filterButtons = [...document.querySelectorAll('[data-gallery-filter]')];
    const count = document.querySelector('[data-gallery-count]');
    const empty = document.querySelector('[data-gallery-empty]');
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
        history.replaceState(null, '', filter === 'all' ? 'gallery.php' : `gallery.php?category=${encodeURIComponent(filter)}`);
    };

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

    filterButtons.forEach(button => button.addEventListener('click', () => setFilter(button.dataset.galleryFilter || 'all')));
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
        }), { threshold: .1, rootMargin: '0px 0px -6% 0px' });
        revealItems.forEach(item => observer.observe(item));
    }
})();
</script>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
