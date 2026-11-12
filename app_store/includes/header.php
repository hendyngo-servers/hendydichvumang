<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
$pageTitle = $pageTitle ?? 'HENDY App Store';
$activeMenu = $activeMenu ?? '';
$notice = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);
$searchValue = trim((string)($_GET['q'] ?? ''));
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b1220">
<title><?=e($pageTitle)?></title>
<link rel="stylesheet" href="style.css">
</head>
<body class="store-body">
<div class="store-shell">
<aside class="store-sidebar">
  <a class="store-brand" href="index.php"><span class="store-brand-mark">H</span><span>HENDY<small>APP STORE</small></span></a>
  <div class="store-nav-caption">KHÁM PHÁ</div>
  <nav class="store-nav">
    <a class="<?=$activeMenu==='home'?'active':''?>" href="index.php">⌂ <span>Trang chủ</span></a>
    <a class="<?=$activeMenu==='apps'?'active':''?>" href="index.php?category=app">▦ <span>Ứng dụng</span></a>
    <a class="<?=$activeMenu==='games'?'active':''?>" href="index.php?category=game">◈ <span>Trò chơi</span></a>
    <a class="<?=$activeMenu==='services'?'active':''?>" href="dichvumang.php">⌁ <span>Dịch vụ mạng</span></a>
  </nav>
  <?php if (is_admin()): ?>
  <div class="store-nav-caption">QUẢN TRỊ</div>
  <nav class="store-nav">
    <a class="<?=$activeMenu==='admin'?'active':''?>" href="admin.php">⚙ <span>Ứng dụng & trò chơi</span></a>
    <a class="<?=$activeMenu==='admin-services'?'active':''?>" href="admin_services.php">◫ <span>Gói dịch vụ mạng</span></a>
  </nav>
  <?php endif; ?>
  <div class="store-sidebar-bottom">
    <a href="../index.php">← Website HENDY</a>
    <a href="../">HENDY Cybertech ↗</a>
  </div>
</aside>
<div class="store-main">
<header class="store-topbar">
  <form class="store-search" action="index.php" method="get">
    <span>⌕</span><input name="q" value="<?=e($searchValue)?>" placeholder="Tìm ứng dụng, trò chơi...">
    <?php if (!empty($_GET['category']) && in_array($_GET['category'], ['app','game'], true)): ?>
      <input type="hidden" name="category" value="<?=e((string)$_GET['category'])?>">
    <?php endif; ?>
  </form>
  <div class="store-account">
    <?php if (current_user()): ?>
      <span><?=e((string)(current_user()['name'] ?? 'Thành viên'))?></span>
      <a href="../index.php">Tài khoản</a>
    <?php else: ?>
      <a href="../login.php">Đăng nhập</a><a class="store-account-cta" href="../register.php">Đăng ký</a>
    <?php endif; ?>
  </div>
</header>
<main class="store-content">
<?php if ($notice): ?><div class="store-alert <?=e((string)($notice['type'] ?? 'success'))?>"><?=e((string)($notice['message'] ?? ''))?></div><?php endif; ?>
