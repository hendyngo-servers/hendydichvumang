<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
require_once __DIR__ . '/includes/website_catalog.php';

$product = ['title'=>'','category'=>'','price'=>'','description'=>'','external_url'=>'','status'=>'published'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['title','category','price','description','external_url'] as $key) {
        $product[$key] = trim((string)($_POST[$key] ?? ''));
    }
    $product['status'] = (string)($_POST['status'] ?? 'published');
    if (mb_strlen($product['title']) < 2 || mb_strlen($product['title']) > 180) {
        $error = 'Tên sản phẩm cần từ 2 đến 180 ký tự.';
    } elseif (mb_strlen($product['category']) > 100 || mb_strlen($product['price']) > 100 || mb_strlen($product['description']) > 12000) {
        $error = 'Một trường dữ liệu vượt quá độ dài cho phép.';
    } elseif ($product['description'] === '') {
        $error = 'Mô tả không được để trống.';
    } elseif (!in_array($product['status'], ['published','draft'], true)) {
        $error = 'Trạng thái không hợp lệ.';
    } elseif (!website_valid_https_url($product['external_url'], true)) {
        $error = 'Liên kết dịch vụ phải là HTTPS hợp lệ.';
    } else {
        try {
            $pdo->beginTransaction();
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                $stmt = $pdo->prepare('INSERT INTO products(title,category,price,description,external_url,status) VALUES(?,?,?,?,?,?) RETURNING id');
                $stmt->execute([$product['title'],$product['category'],$product['price'],$product['description'],$product['external_url'] ?: null,$product['status']]);
                $id = (int)$stmt->fetchColumn();
            } else {
                $stmt = $pdo->prepare('INSERT INTO products(title,category,price,description,external_url,status) VALUES(?,?,?,?,?,?)');
                $stmt->execute([$product['title'],$product['category'],$product['price'],$product['description'],$product['external_url'] ?: null,$product['status']]);
                $id = (int)$pdo->lastInsertId();
            }
            website_upload_product_screenshots($pdo, $id);
            $pdo->commit();
            flash('Đã thêm mục vào danh mục Website.');
            redirect('/admin-products.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Website catalog create failed: ' . $exception::class . ' code ' . (string)$exception->getCode());
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Không thể lưu mục Website. Vui lòng kiểm tra dữ liệu và quyền tải ảnh.';
        }
    }
}
$pageTitle='Thêm mục Website | HENDY';$activeMenu='admin-products';require __DIR__.'/includes/header.php';
?>
<div class="store-admin-heading"><div><div class="store-eyebrow">WEBSITE CATALOGUE</div><h1>Thêm sản phẩm / dịch vụ</h1><p class="store-muted">Mục công khai sẽ xuất hiện trong danh mục trang chủ.</p></div></div>
<?php if($error):?><div class="store-admin-alert error"><?=e($error)?></div><?php endif;?>
<form class="store-admin-form" method="post" enctype="multipart/form-data">
  <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
  <?php require __DIR__ . '/admin-product-fields.php'; ?>
  <div class="form-actions"><a class="store-button ghost" href="/admin-products.php">Hủy</a><button class="store-button primary" type="submit">Lưu mục Website</button></div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
