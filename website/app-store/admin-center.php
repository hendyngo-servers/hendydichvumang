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
$recentUsers=$pdo->query('SELECT id,name,email,role,created_at FROM users ORDER BY created_at DESC,id DESC LIMIT 6')->fetchAll();
$dbReady=(int)$pdo->query('SELECT 1')->fetchColumn()===1;
$botAdminUrl=trim((string)envv('BOT_ADMIN_URL','https://hendydichvumang-telegram-bot.onrender.com/admin'));
$parts=parse_url($botAdminUrl);
if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))$botAdminUrl='https://hendydichvumang-telegram-bot.onrender.com/admin';
$botOrigin='https://'.(string)(parse_url($botAdminUrl,PHP_URL_HOST)?:'hendydichvumang-telegram-bot.onrender.com');
$botOnline=false;$botStatus='Chưa kiểm tra được Bot API.';
if(function_exists('curl_init')){
  $curl=curl_init($botOrigin.'/api/health');
  if($curl!==false){
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>4,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    $health=is_string($body)?json_decode($body,true):null;
    $botOnline=$status>=200&&$status<300&&is_array($health)&&($health['status']??'')==='ok';
    $botStatus=$botOnline?'Bot API đang hoạt động.':'Bot API chưa phản hồi thành công (HTTP '.$status.').';
  }
}
$pageTitle='HENDY Control Center';$activeMenu='admin-center';require __DIR__.'/includes/header.php';
?>
<section class="store-admin-heading">
 <div><div class="store-eyebrow">HENDY DIGITAL ECOSYSTEM · CONTROL CENTER</div><h1>Trung tâm điều khiển</h1><p class="store-muted">Một điểm quản lý các phân hệ Website và truy cập an toàn vào Bot Operations.</p></div>
 <span class="store-status published">WEBSITE ADMIN ACTIVE</span>
</section>
<div class="store-control-status-grid">
 <article class="store-status-card <?=$dbReady?'ok':'warn'?>"><strong>● Website PostgreSQL</strong><small><?=$dbReady?'Kết nối ổn định.':'Không thể kết nối cơ sở dữ liệu.'?></small></article>
 <article class="store-status-card <?=$botOnline?'ok':'warn'?>"><strong>● Telegram Bot API</strong><small><?=e($botStatus)?></small><a href="<?=e($botAdminUrl)?>" target="_blank" rel="noopener noreferrer">Mở Bot Dashboard ↗</a></article>
 <article class="store-status-card ok"><strong>● Phiên quản trị Website</strong><small>Đã xác thực bằng tài khoản Website; quyền Admin được kiểm tra ở máy chủ.</small></article>
 <article class="store-status-card <?=$adminCount>0?'ok':'warn'?>"><strong>● Quản trị viên</strong><small><?=$adminCount?> Admin trên <?=$userCount?> tài khoản Website.</small></article>
</div>
<div class="store-stat-grid">
 <article class="store-stat"><span>Thành viên Website</span><strong><?=$userCount?></strong><small><?=$adminCount?> tài khoản quản trị</small></article>
 <article class="store-stat"><span>Danh mục Website</span><strong><?=$legacyProducts?></strong><small><?=$publishedProducts?> công khai · <?=$draftProducts?> bản nháp</small></article>
 <article class="store-stat"><span>App Store</span><strong><?=$appCount?></strong><small><?=$publishedApps?> ứng dụng/trò chơi công khai</small></article>
 <article class="store-stat"><span>Dịch vụ mạng</span><strong><?=$serviceCount?></strong><small><?=$publishedServices?> gói công khai</small></article>
</div>
<section class="store-section-head"><div><div class="eyebrow">WEBSITE MANAGEMENT</div><h2>Các khu vực quản trị</h2><p class="store-muted">Các phân hệ Website dùng chung phiên đăng nhập và PostgreSQL hiện tại.</p></div></section>
<div class="store-admin-hub-grid">
 <a class="store-admin-hub-card" href="/admin-products.php"><span>🌐</span><b>Website & danh mục</b><small>Quản lý sản phẩm, nội dung trang chủ, mô tả, hình ảnh và trạng thái công khai.</small><strong><?=$legacyProducts?> mục · Mở quản lý →</strong></a>
 <a class="store-admin-hub-card" href="/app_store/admin.php"><span>▦</span><b>App Store</b><small>Quản lý ứng dụng, trò chơi, thư viện ảnh, giá và phiên bản phát hành.</small><strong><?=$appCount?> mục · Mở quản lý →</strong></a>
 <a class="store-admin-hub-card" href="/app_store/admin_services.php"><span>⌁</span><b>Dịch vụ mạng</b><small>Quản lý gói, giá, thời hạn, băng thông, mô tả và link đăng ký.</small><strong><?=$serviceCount?> gói · Mở quản lý →</strong></a>
 <a class="store-admin-hub-card" href="/admin-users.php"><span>♙</span><b>Thành viên Website</b><small>Tìm tài khoản và phân quyền Website Admin. Không hiển thị mật khẩu.</small><strong><?=$userCount?> tài khoản · Mở quản lý →</strong></a>
 <a class="store-admin-hub-card" href="/admin-system.php"><span>⚙</span><b>Hệ thống & bảo mật</b><small>Kiểm tra database, Object Storage, cấu hình và trạng thái vận hành.</small><strong>Xem trạng thái →</strong></a>
 <a class="store-admin-hub-card bot-card" href="<?=e($botAdminUrl)?>" target="_blank" rel="noopener noreferrer"><span>🤖</span><b>Telegram Bot Operations</b><small>Quản trị thành viên Telegram, ví, đơn hàng, thanh toán, Code Telegram, MiniGame, Proxy, AI, menu, bảo trì và nhật ký. Xác thực Admin Telegram riêng.</small><strong><?=$botOnline?'API đang phản hồi':'Mở khu vực vận hành Bot'?> ↗</strong></a>
</div>
<section class="store-section-head"><div><div class="eyebrow">RECENT MEMBERS</div><h2>Thành viên mới</h2></div><a class="store-button ghost" href="/admin-users.php">Tất cả thành viên →</a></section>
<div class="store-admin-table-wrap"><table class="store-admin-table"><thead><tr><th>Thành viên</th><th>Email</th><th>Vai trò</th><th>Ngày đăng ký</th></tr></thead><tbody>
<?php foreach($recentUsers as $user):?><tr><td><strong><?=e((string)$user['name'])?></strong><small>#<?=(int)$user['id']?></small></td><td><?=e((string)$user['email'])?></td><td><span class="store-status <?=$user['role']==='admin'?'published':'draft'?>"><?=$user['role']==='admin'?'Admin':'Thành viên'?></span></td><td><?=e(date('d/m/Y H:i',strtotime((string)$user['created_at'])))?></td></tr><?php endforeach;?>
</tbody></table><?php if(!$recentUsers):?><div class="store-empty">Chưa có tài khoản Website.</div><?php endif;?></div>
<div class="notice warn">Website và Telegram Bot tiếp tục có database cùng cơ chế xác thực riêng. Control Center là đầu mối Website và liên kết an toàn sang Bot Dashboard; không tự ghép tài khoản hoặc chuyển số dư giữa hai hệ thống.</div>
<?php require __DIR__.'/includes/footer.php'; ?>
