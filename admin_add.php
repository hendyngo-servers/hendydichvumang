<?php
require_once __DIR__.'/includes/admin_check.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$title=trim((string)($_POST['title']??''));$category=trim((string)($_POST['category']??''));$price=trim((string)($_POST['price']??''));$description=trim((string)($_POST['description']??''));$url=trim((string)($_POST['external_url']??''));$status=($_POST['status']??'published')==='draft'?'draft':'published';
 if($title===''||$description==='')$error='Vui lòng nhập tiêu đề và mô tả.';
 elseif($url!==''&&!filter_var($url,FILTER_VALIDATE_URL))$error='Đường dẫn ngoài không hợp lệ.';
 else try{
  $pdo->beginTransaction();
  if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql'){
   $statement=$pdo->prepare("INSERT INTO products(title,category,price,description,external_url,status) VALUES(?,?,?,?,?,?) RETURNING id");
   $statement->execute([$title,$category,$price,$description,$url?:null,$status]);$productId=(int)$statement->fetchColumn();
  }else{
   $statement=$pdo->prepare("INSERT INTO products(title,category,price,description,external_url,status) VALUES(?,?,?,?,?,?)");
   $statement->execute([$title,$category,$price,$description,$url?:null,$status]);$productId=(int)$pdo->lastInsertId();
  }
  foreach(($_FILES['screenshots']['name']??[]) as $i=>$name){
   if(($_FILES['screenshots']['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
   $file=['name'=>$name,'tmp_name'=>$_FILES['screenshots']['tmp_name'][$i],'error'=>$_FILES['screenshots']['error'][$i],'size'=>$_FILES['screenshots']['size'][$i]];
   $path=store_screenshot($file);
   if($path){$statement=$pdo->prepare("INSERT INTO screenshots(product_id,image_path) VALUES(?,?)");$statement->execute([$productId,$path]);}
  }
  $pdo->commit();flash('Đã thêm sản phẩm.');redirect('admin.php');
 }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();$error=$exception instanceof RuntimeException?$exception->getMessage():'Không thể lưu sản phẩm.';}
}
$pageTitle='Thêm sản phẩm | HENDY';require __DIR__.'/includes/header.php';
?><div class="form-page"><a class="back-link" href="admin.php">← Quản trị</a><h1>Thêm sản phẩm</h1><?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form class="editor-card" method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><?php require __DIR__.'/includes/product_fields.php';?><button class="button primary">Lưu sản phẩm</button></form></div><?php require __DIR__.'/includes/footer.php';?>
