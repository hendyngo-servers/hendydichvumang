<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
$pageTitle = $pageTitle ?? 'HENDY Digital Ecosystem';
$activeMenu = $activeMenu ?? '';
$notice = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);
$searchValue = trim((string)($_GET['q'] ?? ''));
$botAdminUrl = trim((string)envv('BOT_ADMIN_URL', 'https://hendydichvumang-telegram-bot.onrender.com/admin'));
$botAdminParts = parse_url($botAdminUrl);
if (!is_array($botAdminParts) || strtolower((string)($botAdminParts['scheme'] ?? '')) !== 'https'
    || empty($botAdminParts['host']) || isset($botAdminParts['user']) || isset($botAdminParts['pass'])) {
    $botAdminUrl = 'https://hendydichvumang-telegram-bot.onrender.com/admin';
    $botAdminParts = parse_url($botAdminUrl);
}
$botPublicUrl = 'https://' . (string)($botAdminParts['host'] ?? 'hendydichvumang-telegram-bot.onrender.com');
$botCustomerUrl = $botPublicUrl . '/customer';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b1220">
<meta name="description" content="HENDY DIGITAL ECOSYSTEM — Website, App Store và dịch vụ mạng trong một nền tảng.">
<title><?=e($pageTitle)?></title>
<link rel="stylesheet" href="/app_store/style.css">
<link rel="stylesheet" href="/app_store/home.css">
<link rel="stylesheet" href="/app_store/unified.css">
<?php if (str_starts_with($activeMenu, 'admin')): ?>
<link rel="stylesheet" href="/app_store/admin-center.css">
<?php endif; ?>
</head>
<body class="store-body">
<div class="store-shell">
<aside class="store-sidebar">
<a class="store-brand" href="/">
<span class="store-brand-mark">H</span>
<span>HENDY<small>DIGITAL ECOSYSTEM</small></span>
</a>
<div class="store-nav-caption">HỆ SINH THÁI HENDY</div>
<nav class="store-nav" aria-label="Điều hướng chính">
<a class="<?=$activeMenu==='website-home'?'active':''?>" href="/">⌂ <span>Trang chủ</span></a>
<a class="<?=in_array($activeMenu,['home','apps','games','detail'],true)?'active':''?>" href="/app_store/">▦ <span>App Store</span></a>
<a class="<?=$activeMenu==='apps'?'active':''?>" href="/app_store/index.php?category=app">◈ <span>Ứng dụng</span></a>
<a class="<?=$activeMenu==='games'?'active':''?>" href="/app_store/index.php?category=game">🎮 <span>Trò chơi</span></a>
<a class="<?=$activeMenu==='services'?'active':''?>" href="/app_store/dichvumang.php">⌁ <span>Dịch vụ mạng</span></a>
<a href="<?=e($botCustomerUrl)?>" target="_blank" rel="noopener noreferrer">♙ <span>Trung tâm khách hàng ↗</span></a>
<a href="<?=$botPublicUrl?>/" target="_blank" rel="noopener noreferrer">🤖 <span>Telegram Bot ↗</span></a>
</nav>
<?php if (is_admin()): ?>
<div class="store-nav-caption">TRUNG TÂM QUẢN TRỊ</div>
<nav class="store-nav" aria-label="Điều hướng quản trị">
<a class="<?=$activeMenu==='admin-center'?'active':''?>" href="/admin.php">▣ <span>Tổng quan</span></a>
<a class="<?=$activeMenu==='admin-products'?'active':''?>" href="/admin-products.php">▤ <span>Website</span></a>
<a class="<?=$activeMenu==='admin'?'active':''?>" href="/app_store/admin.php">▦ <span>App Store</span></a>
<a class="<?=$activeMenu==='admin-services'?'active':''?>" href="/app_store/admin_services.php">⌁ <span>Dịch vụ mạng</span></a>
<a class="<?=$activeMenu==='admin-users'?'active':''?>" href="/admin-users.php">♙ <span>Thành viên</span></a>
<a class="<?=$activeMenu==='admin-system'?'active':''?>" href="/admin-system.php">⚙ <span>Hệ thống</span></a>
<a href="<?=e($botAdminUrl)?>" target="_blank" rel="noopener noreferrer">🛡️ <span>Bot Dashboard ↗</span></a>
</nav>
<?php endif; ?>
<div class="store-sidebar-bottom">
<?php if (current_user()): ?>
<span><?=e((string)(current_user()['name'] ?? 'Thành viên'))?></span>
<form method="post" action="/logout.php">
<input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
<button class="store-button ghost" type="submit">Đăng xuất</button>
</form>
<?php else: ?>
<a href="/login.php">Đăng nhập</a>
<?php endif; ?>
</div>
</aside>
<div class="store-main">
<header class="store-topbar">
<div class="store-topbar-context">
<span>HENDY DIGITAL ECOSYSTEM</span>
<strong><?=e($pageTitle)?></strong>
</div>
<div class="store-account">
<?php if (current_user()): ?>
<span><?=e((string)(current_user()['name'] ?? 'Thành viên'))?></span>
<a href="<?=$botCustomerUrl?>" target="_blank" rel="noopener noreferrer">Khách hàng ↗</a>
<?php else: ?>
<a href="/login.php">Đăng nhập</a>
<a class="store-account-cta" href="/register.php">Đăng ký</a>
<?php endif; ?>
</div>
</header>
<main class="store-content<?=$activeMenu==='website-home'?' website-home-content':''?>">
<?php if ($notice): ?>
<div class="store-alert <?=e((string)($notice['type'] ?? 'success'))?>"><?=e((string)($notice['message'] ?? ''))?></div>
<?php endif; ?>
