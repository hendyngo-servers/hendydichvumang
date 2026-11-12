<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
require_once __DIR__ . '/includes/store.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Method not allowed');}
verify_csrf();$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);if(!$id||$id<1){http_response_code(400);exit('ID không hợp lệ.');}
app_store_delete_app($pdo,(int)$id);flash('Đã xóa ứng dụng và ảnh liên quan.');redirect('admin.php');
