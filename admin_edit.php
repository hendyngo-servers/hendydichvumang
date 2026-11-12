<?php
require_once __DIR__.'/includes/admin_check.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);$statement=$pdo->prepare("SELECT * FROM products WHERE id=?");$statement->execute([$id]);$product=$statement->fetch();
if(!$product){http_response_code(404);exit('Không tìm thấy sản phẩm.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$title=trim((string)($_POST['title']??''));$category=trim((string)($_POST['category']??''));$price=trim((string)($_POST['price']??''));$description=trim((string)($_POST['description']??''));$url=trim((string)($_POST['external_url']??''));$status=($_POST['status']??'published')==='draft'?'draft':'published';
 if($title===''||$description==='')$error='Vui lòng nhập tiêu đề và mô tả.';
 elseif($url!==''&&!filter_var($url,FILTER_VALIDATE_URL))$error='Đường dẫn ngoài không hợp lệ.';
 else try{
  $pdo->beginTransaction();$statement=$pdo->prepare("UPDATE products SET title=?,category=?,price=?,description=?,external_url=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
  $statement->execute([$title,$category,$price,$description,$url?:null,$status,$id]);
  foreach(($_FILES['screenshots']['name']??[]) as $i=>$name){
   if(($_FILES['screenshots']['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
   $file=['name'=>$name,'tmp_name'=>$_FILES['screenshots']['tmp_name'][$i],'error'=>$_FILES['screenshots']['error'][$i],'size'=>$_FILES['screenshots']['size'][$i]];
   $path=store_screenshot($file);if($path){$statement=$pdo->prepare("INSERT INTO screenshots(product_id,image_path) VALUES(?,?)");$statement->execute([$id,$path]);}
  }
  $pdo->commit();flash('Đã cập nhật sản phẩm.');redirect('admin.php');
 }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();$error=$exception instanceof RuntimeException?$exception->getMessage():'Không thể cập nhật sản phẩm.';}
 $product=array_merge($product,['title'=>$title,'category'=>$category,'price'=>$price,'description'=>$description,'external_url'=>$url,'status'=>$status]);
}
$statement=$pdo->prepare("SELECT * FROM screenshots WHERE product_id=? ORDER BY sort_order,id");$statement->execute([$id]);$shots=$statement->fetchAll();
$pageTitle='Chỉnh sửa | HENDY';require __DIR__.'/includes/header.php';
?><div class="form-page"><a class="back-link" href="admin.php">← Quản trị</a><h1>Chỉnh sửa sản phẩm</h1><?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form class="editor-card" method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><?php require __DIR__.'/includes/product_fields.php';?><button class="button primary">Lưu thay đổi</button></form>
<?php if($shots): ?><section class="manage-screens"><h2>Ảnh hiện tại</h2><div class="screens-grid"><?php foreach($shots as $shot): $image=screenshot_url($shot['image_path']);if($image==='')continue;?>
<div><img src="<?=e($image)?>" alt=""><form method="post" action="admin_delete_screenshot.php" onsubmit="return confirm('Xóa ảnh này?')"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$shot['id']?>"><button class="link-danger">Xóa ảnh</button></form></div>
<?php endforeach;?></div></section><?php endif;?></div><?php require __DIR__.'/includes/footer.php';?>
