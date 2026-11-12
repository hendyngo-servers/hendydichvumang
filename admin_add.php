<?php
require_once 'includes/db.php';
require_once 'includes/admin_check.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $dev = trim($_POST['dev']);
    $category = $_POST['category'];
    $rating = floatval($_POST['rating']);
    $price = trim($_POST['price']);
    $icon = trim($_POST['icon']);
    $version = trim($_POST['version']);
    $size = trim($_POST['size']);
    $desc = trim($_POST['desc']);
    $long_desc = trim($_POST['long_desc']);
    $download_link = trim($_POST['download_link']);

    if (empty($name) || empty($dev) || empty($icon) || empty($desc)) {
        $error = 'Vui lòng điền đầy đủ các trường bắt buộc!';
    } else {
        $stmt = $pdo->prepare("INSERT INTO apps (name, dev, category, rating, price, icon, version, size, `desc`, long_desc, download_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $dev, $category, $rating, $price, $icon, $version, $size, $desc, $long_desc, $download_link])) {
            $success = 'Thêm ứng dụng thành công! <a href="admin.php">Quay lại danh sách</a>';
        } else {
            $error = 'Đã có lỗi xảy ra.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm ứng dụng mới - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body">
    <div class="admin-container form-card">
        <div class="form-header">
            <h2>Thêm Ứng Dụng Mới</h2>
            <a href="admin.php" class="btn-secondary">Quay lại</a>
        </div>
        <?php if (!empty($error)): ?><div class="alert error"><?php echo $error; ?></div><?php endif; ?>
        <?php if (!empty($success)): ?><div class="alert success"><?php echo $success; ?></div><?php endif; ?>

        <form action="admin_add.php" method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group"><label>Tên ứng dụng *</label><input type="text" name="name" required></div>
                <div class="form-group"><label>Nhà phát triển *</label><input type="text" name="dev" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Danh mục</label><select name="category"><option value="app">Ứng dụng</option><option value="game">Trò chơi</option></select></div>
                <div class="form-group"><label>Đánh giá sao</label><input type="number" step="0.1" name="rating" value="5.0" min="0" max="5"></div>
                <div class="form-group"><label>Giá tiền</label><input type="text" name="price" value="Miễn phí"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Phiên bản</label><input type="text" name="version" value="1.0.0"></div>
                <div class="form-group"><label>Dung lượng</label><input type="text" name="size" value="50 MB"></div>
            </div>
            <div class="form-group"><label>Link Icon (URL) *</label><input type="text" name="icon" required></div>
            <div class="form-group"><label>Mô tả ngắn *</label><textarea name="desc" rows="2" required></textarea></div>
            <div class="form-group"><label>Mô tả chi tiết</label><textarea name="long_desc" rows="5"></textarea></div>
            <div class="form-group"><label>Link tải xuống</label><input type="text" name="download_link" value="#"></div>
            <button type="submit" class="btn-submit">Thêm ứng dụng</button>
        </form>
    </div>
</body>
</html>
