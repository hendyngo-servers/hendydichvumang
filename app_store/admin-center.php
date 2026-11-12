<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';
$appCount=(int)$pdo->query('SELECT COUNT(*) FROM apps')->fetchColumn();
$publishedApps=(int)$pdo->query("SELECT COUNT(*) FROM apps WHERE status='published'")->fetchColumn();
$serviceCount=(int)$pdo->query('SELECT COUNT(*) FROM network_services')->fetchColumn();
$publishedServices=(int)$pdo->query("SELECT COUNT(*) FROM network_services WHERE status='published'")->fetchColumn();
$legacyProducts=(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$botAdminUrl=trim((string)envv('BOT_ADMIN_URL','https://hendydichvumang-telegram-bot.onrender.com/admin'));
$parts=parse_url($botAdminUrl);
if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))$botAdminUrl='https://hendydichvumang-telegram-bot.onrender.com/admin';
$pageTitle='Trung tâm quản trị HENDY';$activeMenu='admin-center';require __DIR__.'/includes/header.php';
?>
<section class="store-admin-heading"><div><div class="store-eyebrow">HENDY DIGITAL ECOSYSTEM · CONTROL CENTER</div><h1>Trung tâm quản trị tổng hợp</h1><p class="store-muted">Một điểm vào cho Website, App Store, dịch vụ mạng và Telegram Bot.</p></div><span class="store-status published">ADMIN SESSION ACTIVE</span></section>
<div class="store-stat-grid">
<article class="store-stat"><span>Ứng dụng & trò chơi</span><strong><?=$appCount?></strong><small><?=$publishedApps?> đang công khai</small></article>
<article class="store-stat"><span>Gói dịch vụ mạng</span><strong><?=$serviceCount?></strong><small><?=$publishedServices?> đang công khai</small></article>
<article class="store-stat"><span>Catalogue Website cũ</span><strong><?=$legacyProducts?></strong><small>Dữ liệu cũ được giữ nguyên</small></article>
</div>
<section class="store-section-head"><div><div class="store-eyebrow">QUẢN LÝ KINH DOANH</div><h2>Các phân hệ Website</h2><p class="store-muted">Các phân hệ Website dùng chung phiên đăng nhập và database.</p></div></section>
<div class="store-admin-hub-grid">
<a class="store-admin-hub-card" href="/app_store/admin.php"><span>▦</span><b>Quản trị App Store</b><small>Quản lý ứng dụng, trò chơi, ảnh, giá, phiên bản và trạng thái phát hành.</small><strong>Mở phân hệ →</strong></a>
<a class="store-admin-hub-card" href="/app_store/admin_services.php"><span>⌁</span><b>Quản trị dịch vụ mạng</b><small>Quản lý gói, giá, thời hạn, băng thông, nội dung và liên kết đăng ký.</small><strong>Mở phân hệ →</strong></a>
<a class="store-admin-hub-card" href="/"><span>🌐</span><b>Website HENDY</b><small>Xem trang chủ và catalogue cũ đang được lưu giữ.</small><strong>Mở Website →</strong></a>
</div>
<section class="store-section-head"><div><div class="store-eyebrow">TELEGRAM OPERATIONS</div><h2>Quản trị Telegram Bot</h2><p class="store-muted">Mở Dashboard Bot để vận hành menu, ví, đơn hàng, bảo trì, Proxy và CSKH.</p></div></section>
<div class="store-admin-hub-grid"><a class="store-admin-hub-card bot-card" href="<?=e($botAdminUrl)?>" target="_blank" rel="noopener noreferrer"><span>🤖</span><b>HENDY BOT · Admin Dashboard</b><small>Dashboard Bot xác thực riêng bằng Telegram ADMIN_ID / ADMIN_IDS.</small><strong>Mở Dashboard Bot ↗</strong></a></div>
<div class="notice warn">Website và Telegram Bot vẫn dùng hai Neon database/role riêng. Trung tâm này gom điều hướng và quản lý Website; Bot mở Dashboard riêng. Không tự ghép tài khoản hoặc chuyển số dư giữa hai hệ thống.</div>
<?php require __DIR__.'/includes/footer.php'; ?>
