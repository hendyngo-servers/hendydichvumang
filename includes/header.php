<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
$pageTitle = $pageTitle ?? 'HENDY | Kho ứng dụng & dịch vụ';
$notice = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);
$telegramBotUrl = telegram_bot_url();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#101827">
<title><?=e($pageTitle)?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php"><span class="brand-mark">H</span><span>HENDY<small>CYBERTECH</small></span></a>
  <nav>
    <a href="index.php">Trang chủ</a>
    <a href="app_store/index.php">App Store</a>
    <a href="app_store/dichvumang.php">Dịch vụ mạng</a>
    <?php if ($telegramBotUrl): ?><a class="nav-cta" href="<?=e($telegramBotUrl)?>" target="_blank" rel="noopener noreferrer">Telegram Bot ↗</a><?php endif; ?>
    <?php if (is_admin()): ?><a href="app_store/admin.php">Quản trị</a><?php endif; ?>
    <?php if (current_user()): ?>
      <span class="nav-user"><?=e((string)current_user()['name'])?></span>
      <form method="post" action="logout.php" class="nav-logout">
        <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
        <button type="submit">Đăng xuất</button>
      </form>
    <?php else: ?>
      <a href="login.php">Đăng nhập</a>
      <a class="nav-cta" href="register.php">Tạo tài khoản</a>
    <?php endif; ?>
  </nav>
</header>
<main class="container">
<?php if ($notice): ?><div class="alert <?=e((string)($notice['type'] ?? 'success'))?>"><?=e((string)($notice['message'] ?? ''))?></div><?php endif; ?>
