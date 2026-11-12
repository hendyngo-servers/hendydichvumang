<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';

$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? 'all');
$sql = "SELECT p.*,(SELECT COUNT(*) FROM screenshots s WHERE s.product_id=p.id) AS image_count
        FROM products p WHERE 1=1";
$args = [];
if ($q !== '') {
    $sql .= " AND (LOWER(p.title) LIKE LOWER(?) OR LOWER(COALESCE(p.description,'')) LIKE LOWER(?))";
    $args[] = '%' . $q . '%';
    $args[] = '%' . $q . '%';
}
if (in_array($status, ['published','draft'], true)) {
    $sql .= " AND p.status=?";
    $args[] = $status;
}
$sql .= " ORDER BY p.updated_at DESC,p.id DESC LIMIT 250";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$items = $stmt->fetchAll();
$pageTitle = 'Danh mục Website | HENDY';
$activeMenu = 'admin-products';
require __DIR__ . '/includes/header.php';
?>
<div class="store-admin-heading">
  <div><div class="store-eyebrow">WEBSITE CATALOGUE CONTROL</div><h1>Danh mục Website</h1><p class="store-muted">Quản lý sản phẩm, dịch vụ, nội dung hiển thị và thư viện ảnh ở trang chủ.</p></div>
  <a class="store-button primary" href="/app_store/admin-product-add.php">＋ Thêm mục mới</a>
</div>
<form class="store-admin-filter" action="/admin-products.php" method="get">
  <input name="q" value="<?=e($q)?>" placeholder="Tìm tên hoặc mô tả sản phẩm...">
  <select name="status"><option value="all">Tất cả trạng thái</option><option value="published" <?=$status==='published'?'selected':''?>>Công khai</option><option value="draft" <?=$status==='draft'?'selected':''?>>Bản nháp</option></select>
  <button class="store-button" type="submit">Lọc danh mục</button>
</form>
<div class="store-admin-table-wrap">
<table class="store-admin-table"><thead><tr><th>Sản phẩm / dịch vụ</th><th>Danh mục</th><th>Giá</th><th>Ảnh</th><th>Trạng thái</th><th>Ngày cập nhật</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($items as $item): ?>
<tr>
  <td><strong><?=e((string)$item['title'])?></strong><small>#<?=(int)$item['id']?></small></td>
  <td><?=e((string)$item['category'])?></td>
  <td><?=e((string)$item['price'])?></td>
  <td><?=(int)$item['image_count']?></td>
  <td><span class="store-status <?=$item['status']==='published'?'published':'draft'?>"><?=$item['status']==='published'?'Công khai':'Bản nháp'?></span></td>
  <td><?=e(date('d/m/Y H:i', strtotime((string)$item['updated_at'])))?></td>
  <td><div class="store-admin-actions">
    <a href="/detail.php?id=<?=(int)$item['id']?>" target="_blank" rel="noopener noreferrer">Xem</a>
    <a href="/app_store/admin-product-edit.php?id=<?=(int)$item['id']?>">Sửa</a>
    <form method="post" action="/app_store/admin-product-delete.php" onsubmit="return confirm('Xóa mục Website và các ảnh liên quan?')">
      <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$item['id']?>">
      <button class="danger" type="submit">Xóa</button>
    </form>
  </div></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php if (!$items): ?><div class="store-empty"><h3>Chưa có mục phù hợp</h3><p>Thêm sản phẩm đầu tiên hoặc đổi bộ lọc.</p><a class="store-button primary" href="/app_store/admin-product-add.php">＋ Thêm mục Website</a></div><?php endif; ?>
</div>
<a class="store-back-link" href="/admin.php">← Trung tâm quản trị tổng hợp</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
