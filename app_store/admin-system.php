<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_check.php';

$dbOk = false;
try { $dbOk = (int)$pdo->query('SELECT 1')->fetchColumn() === 1; }
catch (Throwable $exception) { error_log('Website admin DB probe failed: '.$exception::class); }
$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$storageOk = neon_storage_configured();
$botAdminUrl = trim((string)envv('BOT_ADMIN_URL','https://hendydichvumang-telegram-bot.onrender.com/admin'));
$botParts = parse_url($botAdminUrl);
$botUrlOk = is_array($botParts) && strtolower((string)($botParts['scheme']??''))==='https'
    && !empty($botParts['host']) && !isset($botParts['user']) && !isset($botParts['pass']);
if (!$botUrlOk) $botAdminUrl='https://hendydichvumang-telegram-bot.onrender.com/admin';
$pageTitle='Trạng thái hệ thống | HENDY';$activeMenu='admin-system';require __DIR__.'/includes/header.php';
?>
<div class="store-admin-heading"><div><div class="store-eyebrow">PLATFORM HEALTH & CONFIGURATION</div><h1>Trạng thái hệ thống</h1><p class="store-muted">Tổng hợp trạng thái Website; không hiển thị giá trị Environment hoặc khóa bí mật.</p></div><span class="store-status published">PRIVATE ADMIN VIEW</span></div>
<div class="store-status-grid">
  <article class="store-status-card <?=$dbOk?'ok':'warn'?>"><strong>● PostgreSQL Website</strong><small><?=$dbOk?'Kết nối database thành công.':'Không thể kiểm tra kết nối database.'?></small></article>
  <article class="store-status-card <?=$storageOk?'ok':'warn'?>"><strong>● Neon Object Storage</strong><small><?=$storageOk?'Đã cấu hình endpoint và khóa ký; giá trị bí mật được ẩn.':'Chưa cấu hình; ảnh sử dụng local uploads fallback.'?></small></article>
  <article class="store-status-card <?=$botUrlOk?'ok':'warn'?>"><strong>● Telegram Bot Dashboard</strong><small><?=$botUrlOk?'Đã có URL HTTPS hợp lệ.':'URL Bot chưa hợp lệ; đang dùng đường dẫn mặc định.'?></small><a href="<?=e($botAdminUrl)?>" target="_blank" rel="noopener noreferrer">Mở Dashboard Bot ↗</a></article>
  <article class="store-status-card ok"><strong>● Xác thực Website</strong><small>Cookie phiên HttpOnly, SameSite=Lax và Secure khi chạy HTTPS.</small></article>
  <article class="store-status-card <?=$adminCount>0?'ok':'warn'?>"><strong>● Phân quyền Admin</strong><small><?=$adminCount?> Admin trên <?=$userCount?> tài khoản Website.</small></article>
  <article class="store-status-card ok"><strong>● Bảo vệ biểu mẫu</strong><small>CSRF, prepared statements, kiểm tra quyền phía máy chủ và kiểm tra HTTPS cho link ngoài.</small></article>
</div>
<section class="store-section-head"><div><div class="store-eyebrow">QUICK OPERATIONS</div><h2>Đường dẫn vận hành</h2></div></section>
<div class="store-admin-hub-grid">
  <a class="store-admin-hub-card" href="/healthz.php" target="_blank" rel="noopener noreferrer"><span>♥</span><b>Health check Website</b><small>Kiểm tra tình trạng dịch vụ và kết nối database.</small><strong>Mở healthz →</strong></a>
  <a class="store-admin-hub-card" href="/admin-users.php"><span>♙</span><b>Quản lý tài khoản</b><small>Kiểm tra vai trò thành viên và Admin.</small><strong>Mở phân quyền →</strong></a>
  <a class="store-admin-hub-card" href="/admin-products.php"><span>▤</span><b>Quản lý danh mục</b><small>Kiểm tra dữ liệu được hiển thị trên trang chủ.</small><strong>Mở danh mục →</strong></a>
</div>
<div class="notice warn">API keys của OpenAI, ElevenLabs và Telegram Bot phải được cài trong Render Environment của Bot service. Không nhập các khóa này vào Website, trình duyệt, bảng quản trị Website hoặc nội dung Telegram.</div>
<a class="store-back-link" href="/admin.php">← Trung tâm quản trị tổng hợp</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
