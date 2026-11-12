<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
$pageTitle = $pageTitle ?? 'HENDY Digital Ecosystem';
$activeMenu = $activeMenu ?? '';
$layout = $layout ?? 'store';
$notice = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);
$searchValue = trim((string)($_GET['q'] ?? ''));
$botAdminUrl = trim((string)envv('BOT_ADMIN_URL', 'https://hendydichvumang-telegram-bot.onrender.com/admin'));
$botAdminParts = parse_url($botAdminUrl);
if (!is_array($botAdminParts) || strtolower((string)($botAdminParts['scheme'] ?? '')) !== 'https'
    || empty($botAdminParts['host']) || isset($botAdminParts['user']) || isset($botAdminParts['pass'])) {
    $botAdminUrl = 'https://hendydichvumang-telegram-bot.onrender.com/admin';
}
if ($layout === 'website'):
?>
<!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#101827"><title><?=e($pageTitle)?></title>
<link rel="stylesheet" href="/app_store/home.css"></head><body>
<header class="topbar">
<a class="brand" href="/"><span class="brand-mark">H</span><span>HENDY<small>CYBERTECH</small></span></a>
<nav>
<a href="/">Trang chủ</a><a href="/app_store/index.php">App Store</a><a href="/app_store/dichvumang.php">Dịch vụ mạng</a>
<a class="nav-cta" href="https://hendydichvumang-telegram-bot.onrender.com/" target="_blank" rel="noopener noreferrer">Telegram Bot ↗</a>
<?php if (is_admin()): ?><a href="/admin.php">Quản trị</a><?php endif; ?>
<?php if (current_user()): ?>
<span class="nav-user"><?=e((string)(current_user()['name'] ?? 'Thành viên'))?></span>
<form method="post" action="/logout.php" class="nav-logout"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><button type="submit">Đăng xuất</button></form>
<?php else: ?><a href="/login.php">Đăng nhập</a><a class="nav-cta" href="/register.php">Tạo tài khoản</a><?php endif; ?>
</nav></header><main class="container">
<?php if ($notice): ?><div class="alert <?=e((string)($notice['type'] ?? 'success'))?>"><?=e((string)($notice['message'] ?? ''))?></div><?php endif; ?>
<?php else: ?>
<!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b1220"><title><?=e($pageTitle)?></title>
<link rel="stylesheet" href="/app_store/style.css">
<?php if ($activeMenu === 'admin-center'): ?><link rel="stylesheet" href="/app_store/admin-center.css"><?php endif; ?>
</head><body class="store-body"><div class="store-shell">
<aside class="store-sidebar">
<a class="store-brand" href="/"><span class="store-brand-mark">H</span><span>HENDY<small>DIGITAL ECOSYSTEM</small></span></a>
<div class="store-nav-caption">HỆ SINH THÁI</div>
<nav class="store-nav">
<a href="/">⌂ <span>Website HENDY</span></a>
<a class="<?=$activeMenu==='home'?'active':''?>" href="/app_store/index.php">▦ <span>App Store</span></a>
<a class="<?=$activeMenu==='apps'?'active':''?>" href="/app_store/index.php?category=app">◈ <span>Ứng dụng</span></a>
<a class="<?=$activeMenu==='games'?'active':''?>" href="/app_store/index.php?category=game">🎮 <span>Trò chơi</span></a>
<a class="<?=$activeMenu==='services'?'active':''?>" href="/app_store/dichvumang.php">⌁ <span>Dịch vụ mạng</span></a>
<a href="https://hendydichvumang-telegram-bot.onrender.com/" target="_blank" rel="noopener noreferrer">🤖 <span>Telegram Bot ↗</span></a>
</nav>
<?php if (is_admin()): ?>
<div class="store-nav-caption">TRUNG TÂM QUẢN TRỊ</div>
<nav class="store-nav">
<a class="<?=$activeMenu==='admin-center'?'active':''?>" href="/admin.php">▣ <span>Tổng quan quản trị</span></a>
<a class="<?=$activeMenu==='admin'?'active':''?>" href="/app_store/admin.php">▦ <span>Quản trị App Store</span></a>
<a class="<?=$activeMenu==='admin-services'?'active':''?>" href="/app_store/admin_services.php">⌁ <span>Quản trị dịch vụ mạng</span></a>
<a href="<?=e($botAdminUrl)?>" target="_blank" rel="noopener noreferrer">🛡️ <span>Quản trị Telegram Bot ↗</span></a>
</nav>
<?php endif; ?>
<div class="store-sidebar-bottom">
<?php if (current_user()): ?>
<span><?=e((string)(current_user()['name'] ?? 'Admin'))?></span>
<form method="post" action="/logout.php"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><button class="store-button ghost" type="submit">Đăng xuất</button></form>
<?php else: ?><a href="/login.php">Đăng nhập</a><?php endif; ?>
</div></aside>
<div class="store-main"><header class="store-topbar">
<form class="store-search" action="/app_store/index.php" method="get"><span>⌕</span><input name="q" value="<?=e($searchValue)?>" placeholder="Tìm ứng dụng, trò chơi...">
<?php if (!empty($_GET['category']) && in_array($_GET['category'], ['app','game'], true)): ?><input type="hidden" name="category" value="<?=e((string)$_GET['category'])?>"><?php endif; ?>
</form>
<div class="store-account">
<?php if (current_user()): ?><span><?=e((string)(current_user()['name'] ?? 'Thành viên'))?></span><a href="/">Tài khoản</a>
<?php else: ?><a href="/login.php">Đăng nhập</a><a class="store-account-cta" href="/register.php">Đăng ký</a><?php endif; ?>
</div></header><main class="store-content">
<?php if ($notice): ?><div class="store-alert <?=e((string)($notice['type'] ?? 'success'))?>"><?=e((string)($notice['message'] ?? ''))?></div><?php endif; ?>
<?php endif; ?>
