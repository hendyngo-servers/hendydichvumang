<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';

$q = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$sql = "SELECT p.*,
        (SELECT image_path FROM screenshots s WHERE s.product_id = p.id ORDER BY s.sort_order, s.id LIMIT 1) AS cover
        FROM products p WHERE p.status = 'published'";
$params = [];
if ($q !== '') {
    $sql .= " AND (p.title ILIKE ? OR p.description ILIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($category !== '') {
    $sql .= ' AND p.category = ?';
    $params[] = $category;
}
$sql .= ' ORDER BY p.created_at DESC, p.id DESC LIMIT 24';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE status = 'published' AND category <> '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$botUrl = telegram_bot_url();
$pageTitle = 'HENDY | Hệ sinh thái số';
$activeMenu = 'website-home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="eyebrow">HENDY DIGITAL ECOSYSTEM · DIGITAL SOLUTIONS</div>
  <h1>Một hệ sinh thái.<br><span>Nhiều giải pháp số.</span></h1>
  <p>Khám phá ứng dụng, trò chơi, dịch vụ mạng và tiện ích công nghệ HENDY trong cùng một nền tảng.</p>
  <div class="home-actions">
    <a class="button primary" href="/app_store/">▦ Khám phá App Store</a>
    <a class="button" href="/app_store/dichvumang.php">⌁ Dịch vụ mạng</a>
    <a class="button" href="/bot.php">🤖 HENDY Telegram Bot</a>
  </div>
  <div class="hero-orb orb-one" aria-hidden="true"></div><div class="hero-orb orb-two" aria-hidden="true"></div>
  <div class="hero-meta"><span>01 / HENDY DIGITAL</span><span>ỨNG DỤNG · DỊCH VỤ · TỰ ĐỘNG HÓA</span></div>
</section>

<section class="home-collections" aria-label="Các phân hệ HENDY">
  <a href="/app_store/"><span class="collection-icon">▦</span><b>APP STORE</b><small>Ứng dụng & trò chơi</small><strong>Khám phá kho ứng dụng →</strong></a>
  <a href="/app_store/dichvumang.php"><span class="collection-icon">⌁</span><b>DỊCH VỤ MẠNG</b><small>Proxy, kết nối và gói dịch vụ</small><strong>Xem dịch vụ mạng →</strong></a>
  <a href="/bot.php"><span class="collection-icon">🤖</span><b>TELEGRAM BOT</b><small>MiniGame Live, Code Telegram, ví và công cụ AI</small><strong>Khám phá Bot HENDY →</strong></a>
</section>

<section id="catalog" class="section-head">
  <div><div class="eyebrow">HENDY COLLECTION</div><h2>Khám phá sản phẩm</h2><p class="muted">Các sản phẩm và giải pháp đang được công khai.</p></div>
  <form class="search-form" method="get" action="/">
    <input name="q" value="<?=e($q)?>" maxlength="120" placeholder="Tìm kiếm sản phẩm...">
    <select name="category"><option value="">Tất cả danh mục</option>
      <?php foreach ($categories as $itemCategory): ?>
        <option value="<?=e((string)$itemCategory)?>" <?=$category === $itemCategory ? 'selected' : ''?>><?=e((string)$itemCategory)?></option>
      <?php endforeach; ?>
    </select>
    <button class="button primary" type="submit">Tìm kiếm</button>
  </form>
</section>
<div class="product-grid">
<?php foreach ($products as $product): $cover = screenshot_url($product['cover'] ?? null); ?>
  <article class="product-card">
    <a class="product-cover" href="/detail.php?id=<?=(int)$product['id']?>">
      <?php if ($cover !== ''): ?><img src="<?=e($cover)?>" alt="<?=e((string)$product['title'])?>" loading="lazy"><?php else: ?><div class="cover-placeholder">HENDY ✦</div><?php endif; ?>
      <span class="category-tag"><?=e((string)($product['category'] ?: 'Dịch vụ'))?></span>
    </a>
    <div class="product-info">
      <h3><a href="/detail.php?id=<?=(int)$product['id']?>"><?=e((string)$product['title'])?></a></h3>
      <p><?=e(mb_strimwidth(strip_tags((string)$product['description']),0,140,'…','UTF-8'))?></p>
      <div class="product-bottom"><strong><?=e((string)($product['price'] ?: 'Liên hệ'))?></strong><a href="/detail.php?id=<?=(int)$product['id']?>">Chi tiết →</a></div>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php if (!$products): ?>
  <div class="empty-state"><div>✦</div><h3>Chưa có sản phẩm phù hợp</h3><p>Hãy thử thay đổi từ khóa hoặc danh mục. Bạn cũng có thể khám phá App Store và Dịch vụ mạng.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
