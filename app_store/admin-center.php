<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';

$appCount=(int)$pdo->query('SELECT COUNT(*) FROM apps')->fetchColumn();
$publishedApps=(int)$pdo->query("SELECT COUNT(*) FROM apps WHERE status='published'")->fetchColumn();
$serviceCount=(int)$pdo->query('SELECT COUNT(*) FROM network_services')->fetchColumn();
$publishedServices=(int)$pdo->query("SELECT COUNT(*) FROM network_services WHERE status='published'")->fetchColumn();
$legacyProducts=(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$publishedProducts=(int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='published'")->fetchColumn();
$draftProducts=(int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='draft'")->fetchColumn();
$userCount=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$adminCount=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$recentUsers=$pdo->query('SELECT id,name,email,role,created_at FROM users ORDER BY created_at DESC,id DESC LIMIT 5')->fetchAll();

$botAdminUrl=trim((string)envv('BOT_ADMIN_URL','https://hendydichvumang-telegram-bot.onrender.com/admin'));
$parts=parse_url($botAdminUrl);
if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))$botAdminUrl='https://hendydichvumang-telegram-bot.onrender.com/admin';
$pageTitle='Trung tâm quản trị HENDY';$activeMenu='admin-center';require __DIR__.'/includes/header.php';
?>
<section class="store-admin-heading">
<div><div class="store-eyebrow">HENDY DIGITAL ECOSYSTEM · CONTROL CENTER</div><h1>Trung tâm quản trị tổng hợp</h1><p class="store-muted">Quản lý nội dung Website, App Store, dịch vụ mạng, thành viên và kết nối Telegram Bot.</p></div>
<span class="store-status published">ADMIN SESSION ACTIVE</span>
</section>

<div class="store-stat-grid store-stat-grid-4">
<article class="store-stat"><span>Thành viên Website</span><strong><?=$userCount?></strong><small><?=$adminCount?> tài khoản quản trị</small></article>
<article class="store-stat"><span>Danh mục Website</span><strong><?=$legacyProducts?></strong><small><?=$publishedProducts?> công khai · <?=$draftProducts?> bản nháp</small></article>
<article class="store-stat"><span>App Store</span><strong><?=$appCount?></strong><small><?=$publishedApps?> ứng dụng/trò chơi công khai</small></article>
<article class="store-stat"><span>Dịch vụ mạng</span><strong><?=$serviceCount?></strong><small><?=$publishedServices?> gói đang công khai</small></article>
</div>

<section class="store-section-head"><div><div class="store-eyebrow">QUẢN LÝ KINH DOANH</div><h2>Các phân hệ Website</h2><p class="store-muted">Các thao tác quản trị dùng chung phiên đăng nhập Website và database hiện tại.</p></div></section>
<div class="store-admin-hub-grid">
<a class="store-admin-hub-card" href="/admin-products.php"><span>▤</span><b>Danh mục Website</b><small>Thêm/sửa sản phẩm, mô tả, trạng thái, link HTTPS và thư viện ảnh ở trang chủ.</small><strong><?=$legacyProducts?> mục · Mở quản lý →</strong></a>
<a class="store-admin-hub-card" href="/admin-users.php"><span>♙</span><b>Thành viên & Admin</b><small>Tìm tài khoản, xem vai trò và phân quyền. Không xem mật khẩu hoặc xóa tài khoản.</small><strong><?=$userCount?> tài khoản · Mở quản lý →</strong></a>
<a class="store-admin-hub-card" href="/admin-system.php"><span>⚙</span><b>Trạng thái hệ thống</b><small>Kiểm tra database, Object Storage, cấu hình Website và các liên kết vận hành.</small><strong>Xem trạng thái →</strong></a>
<a class="store-admin-hub-card" href="/app_store/admin.php"><span>▦</span><b>Quản trị App Store</b><small>Quản lý ứng dụng, trò chơi, ảnh, giá, phiên bản và trạng thái phát hành.</small><strong><?=$appCount?> mục · Mở quản lý →</strong></a>
<a class="store-admin-hub-card" href="/app_store/admin_services.php"><span>⌁</span><b>Quản trị dịch vụ mạng</b><small>Quản lý gói, giá, thời hạn, băng thông, nội dung và link đăng ký.</small><strong><?=$serviceCount?> gói · Mở quản lý →</strong></a>
<a class="store-admin-hub-card" href="/"><span>🌐</span><b>Xem Website HENDY</b><small>Mở trang công khai để kiểm tra nội dung và trải nghiệm thành viên.</small><strong>Mở Website →</strong></a>
</div>

<section class="store-section-head"><div><div class="store-eyebrow">TÀI KHOẢN MỚI</div><h2>Thành viên vừa đăng ký</h2></div><a class="store-button ghost" href="/admin-users.php">Tất cả thành viên →</a></section>
<div class="store-admin-table-wrap">
<table class="store-admin-table"><thead><tr><th>Thành viên</th><th>Email</th><th>Vai trò</th><th>Ngày đăng ký</th></tr></thead><tbody>
<?php foreach($recentUsers as $user):?>
<tr><td><strong><?=e((string)$user['name'])?></strong><small>#<?=(int)$user['id']?></small></td><td><?=e((string)$user['email'])?></td><td><span class="store-status <?=$user['role']==='admin'?'published':'draft'?>"><?=$user['role']==='admin'?'Admin':'Thành viên'?></span></td><td><?=e(date('d/m/Y H:i',strtotime((string)$user['created_at'])))?></td></tr>
<?php endforeach;?>
</tbody></table>
<?php if(!$recentUsers):?><div class="store-empty">Chưa có tài khoản nào.</div><?php endif;?>
</div>

<section class="store-section-head"><div><div class="store-eyebrow">TELEGRAM OPERATIONS</div><h2>Quản trị Telegram Bot</h2><p class="store-muted">Vận hành thành viên Telegram, ví, đơn hàng, ticket CSKH, menu, bảo trì/nâng cấp, Proxy và AI.</p></div></section>
<div class="store-admin-hub-grid">
<a class="store-admin-hub-card bot-card" href="<?=e($botAdminUrl)?>" target="_blank" rel="noopener noreferrer"><span>🤖</span><b>HENDY BOT · Admin Dashboard</b><small>Dashboard Bot xác thực riêng bằng Telegram ADMIN_ID / ADMIN_IDS. Khóa API AI được giữ trong Render Environment của Bot, không hiển thị tại Website.</small><strong>Mở Dashboard Bot ↗</strong></a>
</div>
<div class="notice warn">Website và Telegram Bot vẫn sử dụng hai Neon database/role riêng. Trang này quản trị Website; Bot mở Dashboard riêng. Không tự ghép tài khoản hoặc chuyển số dư giữa hai hệ thống.</div>
<?php require __DIR__.'/includes/footer.php'; ?>
