<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
$items=$pdo->query("SELECT a.*,(SELECT COUNT(*) FROM app_screenshots s WHERE s.app_id=a.id) AS screenshot_count FROM apps a ORDER BY a.created_at DESC,a.id DESC")->fetchAll();
$pageTitle='Quản trị ứng dụng | HENDY';$activeMenu='admin';require __DIR__.'/includes/header.php';
?>
<div class="store-admin-heading"><div><div class="store-eyebrow">APP STORE CONTROL</div><h1>Quản lý ứng dụng & trò chơi</h1><p class="store-muted">Thêm, chỉnh sửa, công khai hoặc ẩn sản phẩm trong kho.</p><a class="store-back-link" href="/admin.php">← Trung tâm quản trị tổng hợp</a></div><a class="store-button primary" href="admin_add.php">＋ Thêm ứng dụng</a></div>
<div class="store-admin-table-wrap"><table class="store-admin-table"><thead><tr><th>Ứng dụng</th><th>Danh mục</th><th>Giá</th><th>Ảnh</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach($items as $item):?><tr><td><strong><?=e($item['name'])?></strong><small>#<?=(int)$item['id']?> · <?=e($item['dev'])?></small></td><td><?=e($item['category']==='game'?'Trò chơi':'Ứng dụng')?></td><td><?=e($item['price'])?></td><td><?=(int)$item['screenshot_count']?></td><td><span class="store-status <?=e($item['status'])?>"><?=$item['status']==='published'?'Công khai':'Bản nháp'?></span></td><td class="store-actions"><a href="detail.php?id=<?=(int)$item['id']?>">Xem</a><a href="admin_edit.php?id=<?=(int)$item['id']?>">Sửa</a><form method="post" action="admin_delete.php" onsubmit="return confirm('Xóa ứng dụng và toàn bộ ảnh?')"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$item['id']?>"><button class="store-danger-link">Xóa</button></form></td></tr><?php endforeach;?>
</tbody></table><?php if(!$items):?><div class="store-empty"><h3>Chưa có ứng dụng nào</h3><a href="admin_add.php">Thêm ứng dụng đầu tiên</a></div><?php endif;?></div>
<?php require __DIR__.'/includes/footer.php'; ?>
