<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/store.php';
$q=trim((string)($_GET['q']??''));
$category=(string)($_GET['category']??'');
$sql="SELECT a.*,(SELECT image_url FROM app_screenshots s WHERE s.app_id=a.id ORDER BY s.sort_order,s.id LIMIT 1) AS cover FROM apps a WHERE a.status='published'";
$params=[];
if($q!==''){
  $like='%'.$q.'%';
  $sql.=" AND (LOWER(a.name) LIKE LOWER(?) OR LOWER(a.dev) LIKE LOWER(?) OR LOWER(a.short_description) LIKE LOWER(?))";
  array_push($params,$like,$like,$like);
}
if(in_array($category,['app','game'],true)){ $sql.=" AND a.category=?";$params[]=$category; }
$sql.=" ORDER BY a.created_at DESC,a.id DESC";
$s=$pdo->prepare($sql);$s->execute($params);$apps=$s->fetchAll();
$activeMenu=$category==='app'?'apps':($category==='game'?'games':'home');
$pageTitle='Kho ứng dụng & trò chơi | HENDY';
require __DIR__.'/includes/header.php';
?>
<section class="store-hero">
  <div class="store-eyebrow">HENDY CYBERTECH · DIGITAL COLLECTION</div>
  <h1>Khám phá <span>ứng dụng & trò chơi</span></h1>
  <p>Kho ứng dụng, công cụ và trò chơi được tuyển chọn trong hệ sinh thái HENDY.</p>
  <div class="store-hero-actions"><a class="store-button primary" href="#store-catalog">Khám phá ngay ↓</a><a class="store-button ghost" href="dichvumang.php">Xem dịch vụ mạng</a></div>
  <div class="store-hero-orbit orbit-a"></div><div class="store-hero-orbit orbit-b"></div>
</section>
<section id="store-catalog" class="store-section-head">
 <div><div class="store-eyebrow">HENDY COLLECTION</div><h2>Kho ứng dụng</h2><p class="store-muted">Lựa chọn sản phẩm phù hợp với nhu cầu của bạn.</p></div>
 <div class="store-filter">
  <a class="<?=!in_array($category,['app','game'],true)?'selected':''?>" href="index.php">Tất cả</a>
  <a class="<?=$category==='app'?'selected':''?>" href="index.php?category=app">Ứng dụng</a>
  <a class="<?=$category==='game'?'selected':''?>" href="index.php?category=game">Trò chơi</a>
 </div>
</section>
<div class="store-app-grid">
<?php foreach($apps as $app): $cover=app_store_image_url($app['cover']?:null); ?>
<article class="store-app-card">
 <a class="store-app-cover" href="detail.php?id=<?=(int)$app['id']?>">
  <?php if($cover!==''): ?><img src="<?=e($cover)?>" alt="<?=e($app['name'])?>" loading="lazy"><?php else: ?><div class="store-cover-placeholder">HENDY ✦</div><?php endif; ?>
  <span class="store-category-tag"><?=$app['category']==='game'?'TRÒ CHƠI':'ỨNG DỤNG'?></span>
 </a>
 <div class="store-app-info">
  <div class="store-app-title-row"><div><h3><a href="detail.php?id=<?=(int)$app['id']?>"><?=e($app['name'])?></a></h3><p><?=e($app['dev'])?></p></div><img src="<?=e($app['icon'])?>" alt="" loading="lazy"></div>
  <p class="store-app-description"><?=e(mb_strimwidth(strip_tags($app['short_description']),0,125,'…','UTF-8'))?></p>
  <div class="store-app-meta"><span>★ <?=number_format((float)$app['rating'],1)?></span><strong><?=e($app['price']?:'Miễn phí')?></strong><a href="detail.php?id=<?=(int)$app['id']?>">Chi tiết →</a></div>
 </div>
</article>
<?php endforeach; ?>
</div>
<?php if(!$apps): ?><div class="store-empty"><h3>Chưa có ứng dụng phù hợp</h3><p>Thử thay đổi từ khóa hoặc danh mục tìm kiếm.</p></div><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
