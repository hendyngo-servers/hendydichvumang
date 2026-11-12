<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
require_once __DIR__ . '/includes/store.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);$s=$pdo->prepare('SELECT * FROM apps WHERE id=?');$s->execute([$id?:0]);$app=$s->fetch();if(!$app){http_response_code(404);exit('Không tìm thấy ứng dụng.');}
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
 else try{
  $pdo->beginTransaction();$s=$pdo->prepare('UPDATE apps SET name=?,dev=?,category=?,rating=?,price=?,icon=?,version=?,size=?,short_description=?,long_description=?,download_link=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
  $s->execute([$app['name'],$app['dev'],$app['category'],$rating,$app['price'],$app['icon'],$app['version'],$app['size'],$app['short_description'],$app['long_description'],$app['download_link'],$app['status'],$id]);
  app_store_upload_screenshots($pdo,(int)$id);$pdo->commit();flash('Đã cập nhật ứng dụng.');redirect('admin.php');
 }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();$error=$ex instanceof RuntimeException?$ex->getMessage():'Không thể cập nhật ứng dụng.';}
}
$s=$pdo->prepare('SELECT * FROM app_screenshots WHERE app_id=? ORDER BY sort_order,id');$s->execute([(int)$id]);$shots=$s->fetchAll();
$pageTitle='Chỉnh sửa ứng dụng | HENDY';$activeMenu='admin';require __DIR__.'/includes/header.php';
?>
<div class="store-form-page"><a class="store-back-link" href="admin.php">← Quản trị ứng dụng</a><h1>Chỉnh sửa ứng dụng #<?=(int)$id?></h1><?php if($error):?><div class="store-alert error"><?=e($error)?></div><?php endif;?>
<form class="store-editor" method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><?php require __DIR__.'/includes/app_fields.php';?><div class="store-form-actions"><a class="store-button ghost" href="admin.php">Hủy</a><button class="store-button primary">Lưu thay đổi</button></div></form>
<?php if($shots):?><section class="store-manage-shots"><h2>Ảnh hiện tại</h2><div class="store-shot-grid"><?php foreach($shots as $shot):$url=app_store_image_url($shot['image_url']);if($url==='')continue;?><div><img src="<?=e($url)?>" alt=""><form method="post" action="admin_delete_screenshot.php" onsubmit="return confirm('Xóa ảnh này?')"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$shot['id']?>"><button class="store-danger-link">Xóa ảnh</button></form></div><?php endforeach;?></div></section><?php endif;?>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
