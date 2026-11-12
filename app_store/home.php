<?php
require_once __DIR__.'/includes/db.php';
$q=trim((string)($_GET['q']??''));$cat=trim((string)($_GET['category']??''));
$sql="SELECT p.*,(SELECT image_path FROM screenshots s WHERE s.product_id=p.id ORDER BY s.sort_order,s.id LIMIT 1) cover FROM products p WHERE p.status='published'";
$args=[];
if($q!==''){$sql.=" AND (p.title LIKE ? OR p.description LIKE ?)";$args[]="%$q%";$args[]="%$q%";}
if($cat!==''){$sql.=" AND p.category=?";$args[]=$cat;}
$sql.=" ORDER BY p.created_at DESC";
$statement=$pdo->prepare($sql);$statement->execute($args);$products=$statement->fetchAll();
$categories=$pdo->query("SELECT DISTINCT category FROM products WHERE status='published' AND category<>'' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$pageTitle='HENDY | Hệ sinh thái số';$activeMenu='website-home'; require __DIR__.'/includes/header.php';
?>
<section class="hero"><div class="eyebrow">HENDY CYBERTECH • DIGITAL SOLUTIONS</div><h1>Công nghệ kết nối.<br><span>Giá trị thật.</span></h1><p>Khám phá kho ứng dụng, công cụ và dịch vụ kỹ thuật số từ HENDY.</p><div class="home-actions"><a class="button primary" href="/app_store/index.php">Mở App Store →</a><a class="button" href="/app_store/dichvumang.php">Dịch vụ mạng</a><a class="button" href="https://hendydichvumang-telegram-bot.onrender.com/" target="_blank" rel="noopener noreferrer">HENDY Bot ↗</a></div><div class="hero-orb orb-one"></div><div class="hero-orb orb-two"></div></section>
<section class="home-collections"><a href="/app_store/index.php"><b>▦ APP STORE</b><span>Ứng dụng & trò chơi</span></a><a href="/app_store/dichvumang.php"><b>⌁ DỊCH VỤ MẠNG</b><span>Proxy, kết nối và gói dịch vụ</span></a><a href="https://hendydichvumang-telegram-bot.onrender.com/" target="_blank" rel="noopener noreferrer"><b>🤖 TELEGRAM BOT</b><span>Ví, đơn hàng, VIP và CSKH</span></a></section><section id="catalog" class="section-head"><div><div class="eyebrow">HENDY COLLECTION</div><h2>Khám phá dịch vụ</h2><p class="muted">Các sản phẩm và giải pháp đang được cung cấp.</p></div>
<form class="search-form" method="get"><input name="q" value="<?=e($q)?>" placeholder="Tìm kiếm sản phẩm..."><select name="category"><option value="">Tất cả danh mục</option>
<?php foreach($categories as $category): ?><option value="<?=e($category)?>" <?=$cat===$category?'selected':''?>><?=e($category)?></option><?php endforeach;?>
</select><button class="button">Tìm</button></form></section>
<div class="product-grid"><?php foreach($products as $product): $cover=screenshot_url($product['cover']??null); ?>
<article class="product-card"><a class="product-cover" href="/detail.php?id=<?=(int)$product['id']?>">
<?php if($cover!==''): ?><img src="<?=e($cover)?>" alt="<?=e($product['title'])?>" loading="lazy"><?php else: ?><div class="cover-placeholder">HENDY ✦</div><?php endif;?>
<span class="category-tag"><?=e($product['category']?:'Dịch vụ')?></span></a>
<div class="product-info"><h3><a href="/detail.php?id=<?=(int)$product['id']?>"><?=e($product['title'])?></a></h3><p><?=e(mb_strimwidth(strip_tags($product['description']),0,125,'…','UTF-8'))?></p>
<div class="product-bottom"><strong><?=e($product['price']?:'Liên hệ')?></strong><a href="/detail.php?id=<?=(int)$product['id']?>">Chi tiết →</a></div></div></article><?php endforeach;?></div>
<?php if(!$products): ?><div class="empty-state"><div>✦</div><h3>Chưa có sản phẩm phù hợp</h3><p>Hãy thử từ khóa hoặc danh mục khác.</p></div><?php endif;?>
<?php require __DIR__.'/includes/footer.php';?>
