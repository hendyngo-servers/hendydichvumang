<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Method not allowed'); }
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
if (!$id || !$productId) { flash('Thông tin ảnh không hợp lệ.','error'); redirect('/admin-products.php'); }
try {
    $query = $pdo->prepare('SELECT image_path FROM screenshots WHERE id=? AND product_id=?');
    $query->execute([$id,$productId]);
    $path = $query->fetchColumn();
    if ($path !== false) {
        $delete = $pdo->prepare('DELETE FROM screenshots WHERE id=? AND product_id=?');
        $delete->execute([$id,$productId]);
        delete_local_image((string)$path);
        flash('Đã xóa ảnh khỏi thư viện.');
    } else {
        flash('Ảnh không tồn tại.','error');
    }
} catch (Throwable $exception) {
    error_log('Website screenshot delete failed: ' . $exception::class);
    flash('Không thể xóa ảnh.','error');
}
redirect('/app_store/admin-product-edit.php?id='.(int)$productId);
