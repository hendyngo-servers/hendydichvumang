<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
require_once __DIR__ . '/includes/website_catalog.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(404); exit('Không tìm thấy mục Website.'); }
$stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { http_response_code(404); exit('Không tìm thấy mục Website.'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['title','category','price','description','external_url'] as $key) {
        $product[$key] = trim((string)($_POST[$key] ?? $product[$key]));
    }
    $product['status'] = (string)($_POST['status'] ?? $product['status']);
    if (mb_strlen($product['title']) < 2 || mb_strlen($product['title']) > 180) {
        $error = 'Tên sản phẩm cần từ 2 đến 180 ký tự.';
    } elseif (mb_strlen($product['category']) > 100 || mb_strlen($product['price']) > 100 || mb_strlen($product['description']) > 12000) {
        $error = 'Một trường dữ liệu vượt quá độ dài cho phép.';
    } elseif ($product['description'] === '') {
        $error = 'Mô tả không được để trống.';
    } elseif (!in_array($product['status'], ['published','draft'], true)) {
        $error = 'Trạng thái không hợp lệ.';
    } elseif (!website_valid_https_url((string)$product['external_url'], true)) {
        $error = 'Liên kết dịch vụ phải là HTTPS hợp lệ.';
    } else {
        try {
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE products SET title=?,category=?,price=?,description=?,external_url=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $update->execute([$product['title'],$product['category'],$product['price'],$product['description'],$product['external_url'] ?: null,$product['status'],$id]);
            website_upload_product_screenshots($pdo, $id);
            $pdo->commit();
            flash('Đã cập nhật mục Website.');
            redirect('/admin-products.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Website catalog update failed: ' . $exception::class . ' code ' . (string)$exception->getCode());
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Không thể cập nhật mục Website.';
        }
    }
}
$shots = website_product_screenshots($pdo, $id);
$pageTitle='Sửa mục Website | HENDY';$activeMenu='admin-products';require __DIR__.'/includes/header.php';
?>
<div class="store-admin-heading"><div><div class="store-eyebrow">WEBSITE CATALOGUE</div><h1>Chỉnh sửa mục #<?=(int)$id?></h1><p class="store-muted">Cập nhật nội dung, trạng thái và thư viện ảnh.</p></div></div>
<?php if($error):?><div class="store-admin-alert error"><?=e($error)?></div><?php endif;?>
<form class="store-admin-form" method="post" enctype="multipart/form-data">
  <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
  <?php require __DIR__ . '/admin-product-fields.php'; ?>
  <div class="form-actions"><a class="store-button ghost" href="/admin-products.php">Hủy</a><button class="store-button primary" type="submit">Lưu thay đổi</button></div>
</form>
<section class="store-section-head"><div><div class="store-eyebrow">MEDIA LIBRARY</div><h2>Thư viện ảnh hiện tại</h2></div></section>
<div class="store-product-gallery">
<?php foreach($shots as $shot): $url=screenshot_url((string)$shot['image_path']); if($url==='') continue; ?>
<figure><img src="<?=e($url)?>" alt="<?=e((string)$shot['caption'])?>"><figcaption><?=e((string)($shot['caption'] ?: 'Ảnh #'.$shot['id']))?></figcaption>
<form method="post" action="/app_store/admin-product-image-delete.php" onsubmit="return confirm('Xóa ảnh này khỏi thư viện?')">
<input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$shot['id']?>"><input type="hidden" name="product_id" value="<?=(int)$id?>">
<button class="danger" type="submit">Xóa ảnh</button></form></figure>
<?php endforeach; ?>
<?php if(!$shots):?><p class="store-muted">Chưa có ảnh thư viện.</p><?php endif;?>
</div>
<a class="store-back-link" href="/admin-products.php">← Danh mục Website</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
