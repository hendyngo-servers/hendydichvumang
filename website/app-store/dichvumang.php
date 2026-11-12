<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
$service=null;
if($id){
 $s=$pdo->prepare("SELECT * FROM network_services WHERE id=? AND status='published'");$s->execute([$id]);$service=$s->fetch();
}
if($id && $service){
 $pageTitle=$service['name'].' | Dịch vụ mạng HENDY';$activeMenu='services';require __DIR__.'/includes/header.php';
 ?>
 <a class="store-back-link" href="/app_store/dichvumang.php">← Tất cả dịch vụ mạng</a>
 <section class="store-service-detail">
  <div class="store-eyebrow">HENDY NETWORK SERVICES</div><h1><?=e($service['name'])?></h1>
  <div class="store-service-price"><?=e($service['price'])?></div>
  <div class="store-service-facts"><span>Loại dịch vụ: <?=e($service['category'])?></span><?php if($service['duration']!==''):?><span>Thời hạn: <?=e($service['duration'])?></span><?php endif;?><?php if($service['bandwidth']!==''):?><span>Băng thông: <?=e($service['bandwidth'])?></span><?php endif;?></div>
  <div class="store-prose"><?=nl2br(e($service['description']))?></div>
  <?php if(trim($service['features'])!==''): ?><h2>Thông tin & tính năng</h2><div class="store-prose"><?=nl2br(e($service['features']))?></div><?php endif;?>
  <?php if($service['order_url']!==''):?><a class="store-button primary" href="<?=e($service['order_url'])?>" target="_blank" rel="noopener noreferrer">Đăng ký dịch vụ ↗</a><?php endif;?>
 </section>
 <?php require __DIR__.'/includes/footer.php';exit;
}
$s=$pdo->query("SELECT * FROM network_services WHERE status='published' ORDER BY sort_order,id DESC");$services=$s->fetchAll();
$activeMenu='services';$pageTitle='Dịch vụ mạng | HENDY';require __DIR__.'/includes/header.php';
?>
<section class="store-hero services-hero"><div class="store-eyebrow">HENDY NETWORK SERVICES</div><h1>Giải pháp <span>dịch vụ mạng</span></h1><p>Các gói kết nối và dịch vụ mạng được HENDY cung cấp.</p></section>
<section class="store-section-head"><div><div class="store-eyebrow">NETWORK COLLECTION</div><h2>Các gói dịch vụ</h2><p class="store-muted">Xem chi tiết thông tin và thời hạn từng gói.</p></div></section>
<div class="store-service-grid">
<?php foreach($services as $item):?>
<article class="store-service-card">
 <?php if($item['image_url']!==''):?><img src="<?=e($item['image_url'])?>" alt="<?=e($item['name'])?>" loading="lazy"><?php else:?><div class="store-service-symbol">⌁</div><?php endif;?>
 <div class="store-service-card-body"><span class="store-category-tag static"><?=e($item['category'])?></span><h3><?=e($item['name'])?></h3><p><?=e(mb_strimwidth(strip_tags($item['description']),0,145,'…','UTF-8'))?></p>
 <div class="store-service-facts"><?php if($item['duration']!==''):?><span><?=e($item['duration'])?></span><?php endif;?><?php if($item['bandwidth']!==''):?><span><?=e($item['bandwidth'])?></span><?php endif;?></div>
 <div class="store-service-card-footer"><strong><?=e($item['price'])?></strong><a href="/app_store/dichvumang.php?id=<?=(int)$item['id']?>">Chi tiết →</a></div></div>
</article>
<?php endforeach;?>
</div>
<?php if(!$services):?><div class="store-empty"><h3>Chưa có gói dịch vụ mạng</h3><p>Thông tin dịch vụ sẽ được cập nhật tại đây.</p></div><?php endif;?>
<?php require __DIR__.'/includes/footer.php'; ?>
