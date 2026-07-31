<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$slug = trim((string)($_GET['slug'] ?? ''));

if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    exit('Page not found.');
}

$stmt = $pdo->prepare(
    "SELECT *
     FROM site_sections
     WHERE page_slug = :page_slug
       AND status = 'active'
     ORDER BY sort_order, id
     LIMIT 1"
);

$stmt->execute(['page_slug' => $slug]);
$page = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$page) {
    http_response_code(404);
    $pageTitle = 'Page Not Found';
    $topStripItems = [];
    require __DIR__ . '/includes/storefront-header.php';
    ?>
    <main class="store-page">
      <div class="container">
        <div class="empty-state glass-card">
          <h1>Page not found</h1>
          <p>This content has not been published from the admin panel.</p>
          <a href="index.php" class="primary-btn">Return Home</a>
        </div>
      </div>
    </main>
    <?php
    require __DIR__ . '/includes/storefront-footer.php';
    exit;
}

$pageTitle = ($page['section_title'] ?: ucfirst(str_replace('-', ' ', $slug)))
    . ' | '
    . sf_setting($pdo, 'company_name', 'Ramki Cards');

$topStripItems = [];
require __DIR__ . '/includes/storefront-header.php';
?>

<main class="store-page">
  <div class="container">
    <article class="store-panel glass-card">
      <div class="section-title">
        <div class="decor-line"><i></i></div>
        <h2><?= sf_e($page['section_title']); ?></h2>

        <?php if (!empty($page['section_subtitle'])): ?>
          <p><?= sf_e($page['section_subtitle']); ?></p>
        <?php endif; ?>
      </div>

      <div class="product-description">
        <?= nl2br(sf_e($page['section_content'])); ?>
      </div>
    </article>
  </div>
</main>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
