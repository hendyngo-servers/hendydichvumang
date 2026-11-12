<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Method not allowed'); }
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { flash('Mã mục không hợp lệ.','error'); redirect('/admin-products.php'); }
try {
    $pdo->beginTransaction();
    $query = $pdo->prepare('SELECT image_path FROM screenshots WHERE product_id=?');
    $query->execute([$id]);
    $images = $query->fetchAll(PDO::FETCH_COLUMN);
    $delete = $pdo->prepare('DELETE FROM products WHERE id=?');
    $delete->execute([$id]);
    $deleted = $delete->rowCount();
    $pdo->commit();
    if ($deleted > 0) {
        foreach ($images as $image) delete_local_image((string)$image);
        flash('Đã xóa mục Website và thư viện ảnh liên quan.');
    } else {
        flash('Mục Website không còn tồn tại.');
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Website catalog delete failed: ' . $exception::class);
    flash('Không thể xóa mục Website.','error');
}
redirect('/admin-products.php');
