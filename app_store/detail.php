<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/store.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
$s=$pdo->prepare('SELECT * FROM apps WHERE id=?');$s->execute([$id?:0]);$app=$s->fetch();
if(!$app||($app['status']!=='published'&&!is_admin())){
 http_response_code(404);$pageTitle='Không tìm thấy ứng dụng';require __DIR__.'/includes/header.php';
 echo '<section class="store-empty"><h2>Không tìm thấy ứng dụng</h2><a href="index.php">Quay lại kho ứng dụng</a></section>';
 require __DIR__.'/includes/footer.php';exit;
}
$s=$pdo->prepare('SELECT * FROM app_screenshots WHERE app_id=? ORDER BY sort_order,id');$s->execute([(int)$app['id']]);$shots=$s->fetchAll();
$pageTitle=$app['name'].' | HENDY App Store';$activeMenu=$app['category']==='game'?'games':'apps';
require __DIR__.'/includes/header.php';
?>
<a class="store-back-link" href="index.php">← Quay lại kho ứng dụng</a>
<section class="store-detail-hero">
 <img class="store-detail-icon" src="<?=e($app['icon'])?>" alt="<?=e($app['name'])?>">
 <div class="store-detail-info"><div class="store-eyebrow"><?=e($app['category']==='game'?'TRÒ CHƠI':'ỨNG DỤNG')?> · HENDY APP STORE</div>
  <h1><?=e($app['name'])?></h1><p class="store-muted">Nhà phát triển: <?=e($app['dev'])?></p>
  <div class="store-detail-stats"><span>★ <?=number_format((float)$app['rating'],1)?></span><span><?=e($app['size']?:'Dung lượng chưa cập nhật')?></span><span>Phiên bản <?=e($app['version'])?></span></div>
  <div class="store-detail-price"><?=e($app['price']?:'Miễn phí')?></div>
  <?php if($app['download_link']!==''): ?><a class="store-button primary" href="<?=e($app['download_link'])?>" target="_blank" rel="noopener noreferrer">Tải xuống ↗</a><?php endif; ?>
  <?php if(is_admin()): ?><a class="store-button ghost" href="admin_edit.php?id=<?=(int)$app['id']?>">Chỉnh sửa</a><?php endif; ?>
 </div>
</section>
<section class="store-description-block"><div class="store-eyebrow">GIỚI THIỆU</div><h2>Mô tả ứng dụng</h2><div class="store-prose"><?=nl2br(e($app['long_description']?:$app['short_description']))?></div></section>
<?php if($shots): ?><section class="store-screens"><div class="store-eyebrow">GALLERY</div><h2>Ảnh chụp màn hình</h2><div class="store-shot-grid">
<?php foreach($shots as $shot): $url=app_store_image_url($shot['image_url']);if($url==='')continue; ?><a href="<?=e($url)?>" target="_blank" rel="noopener noreferrer"><img src="<?=e($url)?>" alt="<?=e($app['name'])?>" loading="lazy"></a><?php endforeach;?>
</div></section><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
