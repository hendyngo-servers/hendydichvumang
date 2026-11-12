<?php
require_once 'includes/db.php';
$current_cat = isset($_GET['cat']) ? $_GET['cat'] : 'all';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AppStore - Kho Ứng Dụng & Dịch Vụ Mạng</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="brand">
                <i class="fa-brands fa-microsoft"></i>
                <span>AppStore</span>
            </div>
            <nav class="side-nav">
                <a href="index.php?cat=all" class="<?php echo ($current_page == 'index.php' && $current_cat == 'all') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-house"></i> Trang chủ
                </a>
                <a href="index.php?cat=app" class="<?php echo ($current_page == 'index.php' && $current_cat == 'app') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-layer-group"></i> Ứng dụng
                </a>
                <a href="index.php?cat=game" class="<?php echo ($current_page == 'index.php' && $current_cat == 'game') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gamepad"></i> Trò chơi
                </a>
                <a href="dichvumang.php" class="<?php echo $current_page == 'dichvumang.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-network-wired"></i> Dịch vụ mạng
                </a>
                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                    <a href="admin.php" style="color: #2563eb; margin-top: 20px;">
                        <i class="fa-solid fa-gauge-high"></i> Quản trị (Admin)
                    </a>
                <?php endif; ?>
            </nav>
        </aside>

        <div class="main-wrapper">
            <header class="top-bar">
                <form action="index.php" method="GET" class="search-box">
                    <input type="hidden" name="cat" value="<?php echo htmlspecialchars($current_cat); ?>">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="Tìm kiếm ứng dụng, trò chơi..." value="<?php echo htmlspecialchars($search_query); ?>">
                </form>

                <div class="user-auth-area">
                    <?php if (isset($_SESSION['username'])): ?>
                        <div class="user-logged">
                            <span class="avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></span>
                            <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <a href="logout.php" class="btn-logout" title="Đăng xuất"><i class="fa-solid fa-right-from-bracket"></i></a>
                        </div>
                    <?php else: ?>
                        <div class="auth-links">
                            <a href="login.php" class="btn-login">Đăng nhập</a>
                            <a href="register.php" class="btn-register">Đăng ký</a>
                        </div>
                    <?php endif; ?>
                </div>
            </header>
