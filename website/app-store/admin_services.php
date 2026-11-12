<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
$items=$pdo->query('SELECT * FROM network_services ORDER BY sort_order,id DESC')->fetchAll();
$pageTitle='Quản trị dịch vụ mạng | HENDY';$activeMenu='admin-services';require __DIR__.'/includes/header.php';
?>
<div class="store-admin-heading"><div><div class="store-eyebrow">NETWORK SERVICES CONTROL</div><h1>Quản lý dịch vụ mạng</h1><p class="store-muted">Quản lý gói, thời hạn, băng thông và thông tin đăng ký.</p><a class="store-back-link" href="/admin.php">← Trung tâm quản trị tổng hợp</a></div><a class="store-button primary" href="admin_service_add.php">＋ Thêm gói dịch vụ</a></div>
<div class="store-admin-table-wrap"><table class="store-admin-table"><thead><tr><th>Tên gói</th><th>Danh mục</th><th>Giá</th><th>Thời hạn</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach($items as $item):?><tr><td><strong><?=e($item['name'])?></strong><small>#<?=(int)$item['id']?></small></td><td><?=e($item['category'])?></td><td><?=e($item['price'])?></td><td><?=e($item['duration'])?></td><td><span class="store-status <?=e($item['status'])?>"><?=$item['status']==='published'?'Công khai':'Bản nháp'?></span></td><td class="store-actions"><a href="dichvumang.php?id=<?=(int)$item['id']?>">Xem</a><a href="admin_service_edit.php?id=<?=(int)$item['id']?>">Sửa</a><form method="post" action="admin_service_delete.php" onsubmit="return confirm('Xóa gói dịch vụ này?')"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$item['id']?>"><button class="store-danger-link">Xóa</button></form></td></tr><?php endforeach;?>
</tbody></table><?php if(!$items):?><div class="store-empty"><h3>Chưa có gói dịch vụ</h3><a href="admin_service_add.php">Thêm gói đầu tiên</a></div><?php endif;?></div>
<?php require __DIR__.'/includes/footer.php'; ?>
