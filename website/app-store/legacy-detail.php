<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy sản phẩm | HENDY';
    $layout='website'; require __DIR__ . '/includes/header.php';
    echo '<section class="empty-state"><h2>Không tìm thấy sản phẩm</h2><a href="/">Quay lại trang chủ</a></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'published' LIMIT 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy sản phẩm | HENDY';
    $layout = 'website';
    require __DIR__ . '/includes/header.php';
    echo '<section class="empty-state"><h2>Không tìm thấy sản phẩm</h2><a href="index.php">Quay lại trang chủ</a></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT image_path, caption FROM screenshots WHERE product_id = ? ORDER BY sort_order, id');
$stmt->execute([$id]);
$screenshots = $stmt->fetchAll();

$externalUrl = trim((string)($product['external_url'] ?? ''));
$externalParts = $externalUrl !== '' ? parse_url($externalUrl) : false;
$externalUrlIsSafe = is_array($externalParts)
    && strtolower((string)($externalParts['scheme'] ?? '')) === 'https'
    && !empty($externalParts['host'])
    && !isset($externalParts['user'])
    && !isset($externalParts['pass']);

$pageTitle = (string)$product['title'] . ' | HENDY Cybertech';
$layout = 'website';
require __DIR__ . '/includes/header.php';
?>
<a class="back-link" href="index.php">← Quay lại kho sản phẩm</a>
<section class="product-detail">
  <div class="eyebrow"><?=e((string)($product['category'] ?: 'SẢN PHẨM HENDY'))?></div>
  <h1><?=e((string)$product['title'])?></h1>
  <div class="product-detail-price"><?=e((string)($product['price'] ?: 'Liên hệ'))?></div>
  <div class="product-detail-description"><?=nl2br(e((string)$product['description']))?></div>
  <?php if ($externalUrlIsSafe): ?>
    <a class="button primary" href="<?=e($externalUrl)?>" target="_blank" rel="noopener noreferrer">Mở dịch vụ ↗</a>
  <?php endif; ?>
</section>
<?php if ($screenshots): ?>
<section class="product-detail-gallery">
  <h2>Hình ảnh sản phẩm</h2>
  <div class="product-grid">
    <?php foreach ($screenshots as $shot): $imageUrl = screenshot_url((string)$shot['image_path']); if ($imageUrl === '') continue; ?>
      <figure class="product-card">
        <img src="<?=e($imageUrl)?>" alt="<?=e((string)($shot['caption'] ?: $product['title']))?>" loading="lazy">
        <?php if (trim((string)$shot['caption']) !== ''): ?><figcaption><?=e((string)$shot['caption'])?></figcaption><?php endif; ?>
      </figure>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
