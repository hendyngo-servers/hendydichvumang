<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
$botUrl=telegram_bot_url();
$botAdminUrl=trim((string)envv('BOT_ADMIN_URL','https://hendydichvumang-telegram-bot.onrender.com/admin'));
$pageTitle='HENDY Telegram Bot | Digital Ecosystem';$activeMenu='bot-public';
require __DIR__.'/includes/header.php';
?>
<section class="hero bot-hero"><div class="eyebrow">HENDY CYBERTECH · TELEGRAM AUTOMATION</div><h1>HENDY <span>Telegram Bot</span></h1><p>Một điểm truy cập cho các tiện ích HENDY: MiniGame Live, Code Telegram, OTP/Proxy, ví thành viên, VIP, công cụ AI và trung tâm hỗ trợ.</p>
<div class="home-actions"><?php if($botUrl):?><a class="button primary" href="<?=e($botUrl)?>" target="_blank" rel="noopener noreferrer">🚀 Mở HENDY Bot trên Telegram</a><?php else:?><span class="button">Telegram Bot URL chưa được cấu hình</span><?php endif;?>
<a class="button" href="/app_store/">▦ App Store</a><a class="button" href="/app_store/dichvumang.php">⌁ Dịch vụ mạng</a>
<?php if(is_admin()):?><a class="button" href="<?=e($botAdminUrl)?>" target="_blank" rel="noopener noreferrer">🛡️ Bot Admin Dashboard</a><?php endif;?></div></section>
<section class="store-section-head"><div><div class="eyebrow">HENDY SERVICES</div><h2>Các phân hệ Telegram</h2><p class="store-muted">Các thao tác thành viên thực hiện trong Telegram. Quản trị Bot dùng phiên xác thực Admin riêng.</p></div></section>
<div class="home-collections bot-feature-grid">
<a href="<?=e($botUrl??'#')?>"><span class="collection-icon">🎮</span><b>MINIGAME LIVE</b><small>Chọn nhà cái, xem gói dịch vụ, thanh toán Ví Chính và theo dõi đơn hàng.</small><strong>Mở Bot →</strong></a>
<a href="<?=e($botUrl??'#')?>"><span class="collection-icon">📡</span><b>CODE TELEGRAM</b><small>Đọc mã từ kênh, tiếp nhận username và xử lý yêu cầu qua hàng chờ Admin.</small><strong>Mở Bot →</strong></a>
<a href="<?=e($botUrl??'#')?>"><span class="collection-icon">🌐</span><b>OTP & PROXY</b><small>Danh mục dịch vụ, thuê Proxy và quản lý các gói đang mở bán.</small><strong>Mở Bot →</strong></a>
<a href="<?=e($botUrl??'#')?>"><span class="collection-icon">💳</span><b>VÍ & THANH TOÁN</b><small>Yêu cầu nạp tiền, theo dõi số dư và lịch sử giao dịch.</small><strong>Mở Bot →</strong></a>
<a href="<?=e($botUrl??'#')?>"><span class="collection-icon">👑</span><b>VIP & GIỚI THIỆU</b><small>Hạng thành viên, quyền lợi, điểm danh và thống kê giới thiệu.</small><strong>Mở Bot →</strong></a>
<a href="<?=e($botUrl??'#')?>"><span class="collection-icon">🎵</span><b>AI MEDIA & AI COVER</b><small>Các tác vụ AI chỉ mở khi provider, worker, lưu trữ và quyền sử dụng được cấu hình.</small><strong>Xem trong Bot →</strong></a>
</div>
<section class="store-description-block"><h2>Một Website thống nhất</h2><p class="store-prose">Trang chủ, App Store, Dịch vụ mạng, giới thiệu Telegram Bot, chính sách và điều khoản đều dùng chung Website HENDY. Telegram Bot vẫn chạy backend riêng để tiếp nhận webhook và xử lý nghiệp vụ an toàn.</p><p class="store-prose"><a href="/privacy">Chính sách bảo mật</a> · <a href="/terms">Điều khoản sử dụng</a></p></section>
<?php require __DIR__.'/includes/footer.php'; ?>
