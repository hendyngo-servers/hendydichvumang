<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
require_once __DIR__ . '/includes/store.php';
$app=['name'=>'','dev'=>'','category'=>'app','rating'=>'5.0','price'=>'Miễn phí','icon'=>'','version'=>'1.0.0','size'=>'','short_description'=>'','long_description'=>'','download_link'=>'','status'=>'published'];
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 foreach(['name','dev','price','icon','version','size','short_description','long_description','download_link'] as $key)$app[$key]=trim((string)($_POST[$key]??$app[$key]));
 $app['category']=(string)($_POST['category']??'app');$app['status']=(string)($_POST['status']??'published');$app['rating']=(string)($_POST['rating']??'5');
 $rating=filter_var($app['rating'],FILTER_VALIDATE_FLOAT);
 if($app['name']===''||$app['dev']===''||$app['short_description']==='')$error='Tên, nhà phát triển và mô tả ngắn là bắt buộc.';
 elseif(!in_array($app['category'],['app','game'],true)||!in_array($app['status'],['published','draft'],true))$error='Danh mục hoặc trạng thái không hợp lệ.';
 elseif($rating===false||$rating<0||$rating>5)$error='Đánh giá phải từ 0 đến 5.';
 elseif(!app_store_valid_https_url($app['icon'])||!app_store_valid_https_url($app['download_link'],true))$error='Icon và link tải xuống phải là URL HTTPS hợp lệ.';
 elseif(mb_strlen($app['name'])>150||mb_strlen($app['dev'])>150||mb_strlen($app['price'])>50||mb_strlen($app['version'])>20||mb_strlen($app['size'])>20)$error='Một trường dữ liệu vượt quá độ dài cho phép.';
 else try{
  $pdo->beginTransaction();
  if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql'){
   $s=$pdo->prepare('INSERT INTO apps(name,dev,category,rating,price,icon,version,size,short_description,long_description,download_link,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id');
   $s->execute([$app['name'],$app['dev'],$app['category'],$rating,$app['price'],$app['icon'],$app['version'],$app['size'],$app['short_description'],$app['long_description'],$app['download_link'],$app['status']]);$appId=(int)$s->fetchColumn();
  }else{
   $s=$pdo->prepare('INSERT INTO apps(name,dev,category,rating,price,icon,version,size,short_description,long_description,download_link,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
   $s->execute([$app['name'],$app['dev'],$app['category'],$rating,$app['price'],$app['icon'],$app['version'],$app['size'],$app['short_description'],$app['long_description'],$app['download_link'],$app['status']]);$appId=(int)$pdo->lastInsertId();
  }
  app_store_upload_screenshots($pdo,$appId);$pdo->commit();flash('Đã thêm ứng dụng vào kho.');redirect('admin.php');
 }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();$error=$ex instanceof RuntimeException?$ex->getMessage():'Không thể lưu ứng dụng. Vui lòng kiểm tra database và quyền tải ảnh.';}
}
$pageTitle='Thêm ứng dụng | HENDY';$activeMenu='admin';require __DIR__.'/includes/header.php';
?>
<div class="store-form-page"><a class="store-back-link" href="admin.php">← Quản trị ứng dụng</a><h1>Thêm ứng dụng / trò chơi</h1><?php if($error):?><div class="store-alert error"><?=e($error)?></div><?php endif;?>
<form class="store-editor" method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><?php require __DIR__.'/includes/app_fields.php';?><div class="store-form-actions"><a class="store-button ghost" href="admin.php">Hủy</a><button class="store-button primary">Lưu ứng dụng</button></div></form></div>
<?php require __DIR__.'/includes/footer.php'; ?>
