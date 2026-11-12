<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $role = (string)($_POST['role'] ?? '');
    $currentId = (int)(current_user()['id'] ?? 0);
    if (!$id || !in_array($role, ['user','admin'], true)) {
        $error = 'Thông tin phân quyền không hợp lệ.';
    } elseif ($id === $currentId && $role !== 'admin') {
        $error = 'Bạn không thể tự hạ quyền Admin của tài khoản đang đăng nhập.';
    } else {
        try {
            $pdo->beginTransaction();
            $query = $pdo->prepare('SELECT id,role FROM users WHERE id=? FOR UPDATE');
            $query->execute([$id]);
            $target = $query->fetch();
            if (!$target) {
                $error = 'Không tìm thấy tài khoản.';
            } elseif ($target['role'] === 'admin' && $role === 'user') {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
                if ($count <= 1) $error = 'Không thể hạ quyền Admin cuối cùng.';
            }
            if ($error === '') {
                $update = $pdo->prepare('UPDATE users SET role=? WHERE id=?');
                $update->execute([$role,$id]);
                $pdo->commit();
                $message = 'Đã cập nhật quyền tài khoản.';
            } else {
                $pdo->rollBack();
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Website user role update failed: ' . $exception::class . ' code ' . (string)$exception->getCode());
            $error = 'Không thể cập nhật quyền tài khoản.';
        }
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$roleFilter = (string)($_GET['role'] ?? 'all');
$sql = 'SELECT id,name,email,role,created_at FROM users WHERE 1=1';
$args = [];
if ($q !== '') {
    $sql .= ' AND (LOWER(name) LIKE LOWER(?) OR LOWER(email) LIKE LOWER(?))';
    $args[] = '%'.$q.'%';
    $args[] = '%'.$q.'%';
}
if (in_array($roleFilter,['user','admin'],true)) { $sql .= ' AND role=?'; $args[] = $roleFilter; }
$sql .= ' ORDER BY created_at DESC,id DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$users = $stmt->fetchAll();
$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$pageTitle='Thành viên Website | HENDY';$activeMenu='admin-users';require __DIR__.'/includes/header.php';
?>
<div class="store-admin-heading"><div><div class="store-eyebrow">IDENTITY & ACCESS CONTROL</div><h1>Thành viên & Admin</h1><p class="store-muted">Tìm kiếm tài khoản và phân quyền Website. Không hiển thị mật khẩu hoặc cho phép xóa tài khoản tại đây.</p></div><span class="store-status published"><?=$adminCount?> ADMIN</span></div>
<?php if($message):?><div class="store-admin-alert"><?=e($message)?></div><?php endif;?>
<?php if($error):?><div class="store-admin-alert error"><?=e($error)?></div><?php endif;?>
<form class="store-admin-filter" action="/admin-users.php" method="get">
<input name="q" value="<?=e($q)?>" placeholder="Tìm theo tên hoặc email...">
<select name="role"><option value="all">Tất cả vai trò</option><option value="user" <?=$roleFilter==='user'?'selected':''?>>Thành viên</option><option value="admin" <?=$roleFilter==='admin'?'selected':''?>>Admin</option></select>
<button class="store-button" type="submit">Lọc tài khoản</button>
</form>
<div class="store-admin-table-wrap"><table class="store-admin-table"><thead><tr><th>Tài khoản</th><th>Email</th><th>Vai trò</th><th>Ngày tham gia</th><th>Cập nhật quyền</th></tr></thead><tbody>
<?php foreach($users as $user):?>
<tr>
<td><strong><?=e((string)$user['name'])?></strong><small>#<?=(int)$user['id']?></small></td>
<td><?=e((string)$user['email'])?></td>
<td><span class="store-status <?=$user['role']==='admin'?'published':'draft'?>"><?=$user['role']==='admin'?'Admin':'Thành viên'?></span></td>
<td><?=e(date('d/m/Y',strtotime((string)$user['created_at'])))?></td>
<td><form method="post" class="store-admin-actions"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$user['id']?>">
<select name="role"><option value="user" <?=$user['role']==='user'?'selected':''?>>Thành viên</option><option value="admin" <?=$user['role']==='admin'?'selected':''?>>Admin</option></select><button type="submit">Lưu</button></form></td>
</tr>
<?php endforeach;?>
</tbody></table><?php if(!$users):?><div class="store-empty">Không tìm thấy tài khoản phù hợp.</div><?php endif;?></div>
<div class="notice warn">Không thể tự hạ quyền tài khoản đang đăng nhập hoặc hạ quyền Admin cuối cùng. Việc thay đổi vai trò được bảo vệ bằng CSRF và kiểm tra phía máy chủ.</div>
<a class="store-back-link" href="/admin.php">← Trung tâm quản trị tổng hợp</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
