<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Method not allowed');}
verify_csrf();$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);if(!$id||$id<1){http_response_code(400);exit('ID không hợp lệ.');}
$s=$pdo->prepare('DELETE FROM network_services WHERE id=?');$s->execute([$id]);flash('Đã xóa gói dịch vụ mạng.');redirect('admin_services.php');
