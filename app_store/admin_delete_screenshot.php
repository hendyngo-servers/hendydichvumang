<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Method not allowed');}
verify_csrf();$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);if(!$id||$id<1){http_response_code(400);exit('ID không hợp lệ.');}
$s=$pdo->prepare('SELECT image_url,app_id FROM app_screenshots WHERE id=?');$s->execute([$id]);$shot=$s->fetch();
if($shot){$d=$pdo->prepare('DELETE FROM app_screenshots WHERE id=?');$d->execute([$id]);app_store_delete_image((string)$shot['image_url']);flash('Đã xóa ảnh chụp màn hình.');redirect('admin_edit.php?id='.(int)$shot['app_id']);}
redirect('admin.php');
