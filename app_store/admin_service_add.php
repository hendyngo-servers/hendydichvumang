<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
require_once __DIR__ . '/includes/store.php';
$service=['name'=>'','category'=>'Dịch vụ mạng','price'=>'Liên hệ','duration'=>'','bandwidth'=>'','description'=>'','features'=>'','image_url'=>'','order_url'=>'','status'=>'published','sort_order'=>'0'];$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 foreach(['name','category','price','duration','bandwidth','description','features','image_url','order_url'] as $key)$service[$key]=trim((string)($_POST[$key]??''));
 $service['status']=(string)($_POST['status']??'published');$service['sort_order']=(string)($_POST['sort_order']??'0');
 if($service['name']===''||$service['description']==='')$error='Tên gói và mô tả là bắt buộc.';
 elseif(!in_array($service['status'],['published','draft'],true))$error='Trạng thái không hợp lệ.';
 elseif(!app_store_valid_https_url($service['image_url'],true)||!app_store_valid_https_url($service['order_url'],true))$error='Ảnh và link đăng ký phải là URL HTTPS hợp lệ.';
 elseif(!ctype_digit($service['sort_order'])||(int)$service['sort_order']>999999)$error='Thứ tự hiển thị không hợp lệ.';
 else try{
  $s=$pdo->prepare('INSERT INTO network_services(name,category,price,duration,bandwidth,description,features,image_url,order_url,status,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
  $s->execute([$service['name'],$service['category'],$service['price'],$service['duration'],$service['bandwidth'],$service['description'],$service['features'],$service['image_url'],$service['order_url'],$service['status'],(int)$service['sort_order']]);
  flash('Đã thêm gói dịch vụ mạng.');redirect('admin_services.php');
 }catch(Throwable $ex){$error='Không thể lưu gói dịch vụ. Vui lòng kiểm tra database.';}
}
$pageTitle='Thêm dịch vụ mạng | HENDY';$activeMenu='admin-services';require __DIR__.'/includes/header.php';
?>
<div class="store-form-page"><a class="store-back-link" href="admin_services.php">← Quản trị dịch vụ mạng</a><h1>Thêm gói dịch vụ mạng</h1><?php if($error):?><div class="store-alert error"><?=e($error)?></div><?php endif;?>
<form class="store-editor" method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><?php require __DIR__.'/includes/service_fields.php';?><div class="store-form-actions"><a class="store-button ghost" href="admin_services.php">Hủy</a><button class="store-button primary">Lưu gói dịch vụ</button></div></form></div>
<?php require __DIR__.'/includes/footer.php'; ?>
