<?php
require_once 'includes/db.php';
require_once 'includes/admin_check.php';

$error = '';
$success = '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("SELECT * FROM apps WHERE id = ?");
$stmt->execute([$id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    header("Location: admin.php");
    exit;
}

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
        $stmt = $pdo->prepare("UPDATE apps SET name = ?, dev = ?, category = ?, rating = ?, price = ?, icon = ?, version = ?, size = ?, `desc` = ?, long_desc = ?, download_link = ? WHERE id = ?");
        $stmt->execute([$name, $dev, $category, $rating, $price, $icon, $version, $size, $desc, $long_desc, $download_link, $id]);

        if (isset($_FILES['screenshots']) && !empty($_FILES['screenshots']['name'][0])) {
            $file_count = count($_FILES['screenshots']['name']);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['screenshots']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['screenshots']['tmp_name'][$i];
                    $ext = strtolower(pathinfo($_FILES['screenshots']['name'][$i], PATHINFO_EXTENSION));

                    if (in_array($ext, $allowed_extensions)) {
                        $new_filename = 'shot_' . uniqid() . '.' . $ext;
                        $upload_path = 'uploads/screenshots/' . $new_filename;

                        if (move_uploaded_file($tmp_name, $upload_path)) {
                            $stmt_shot = $pdo->prepare("INSERT INTO app_screenshots (app_id, image_url) VALUES (?, ?)");
                            $stmt_shot->execute([$id, $upload_path]);
                        }
                    }
                }
            }
        }

        $success = 'Cập nhật thành công!';
        $stmt = $pdo->prepare("SELECT * FROM apps WHERE id = ?");
        $stmt->execute([$id]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$stmt_shots = $pdo->prepare("SELECT * FROM app_screenshots WHERE app_id = ?");
$stmt_shots->execute([$id]);
$screenshots = $stmt_shots->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chỉnh sửa ứng dụng - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-body">
    <div class="admin-container form-card">
        <div class="form-header">
            <h2>Chỉnh Sửa Ứng Dụng: #<?php echo $app['id']; ?></h2>
            <a href="admin.php" class="btn-secondary">Quay lại</a>
        </div>
        <?php if (!empty($error)): ?><div class="alert error"><?php echo $error; ?></div><?php endif; ?>
        <?php if (!empty($success)): ?><div class="alert success"><?php echo $success; ?></div><?php endif; ?>

        <form action="admin_edit.php?id=<?php echo $app['id']; ?>" method="POST" enctype="multipart/form-data" class="admin-form">
            <div class="form-row">
                <div class="form-group"><label>Tên ứng dụng *</label><input type="text" name="name" value="<?php echo htmlspecialchars($app['name']); ?>" required></div>
                <div class="form-group"><label>Nhà phát triển *</label><input type="text" name="dev" value="<?php echo htmlspecialchars($app['dev']); ?>" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Danh mục</label><select name="category"><option value="app" <?php echo $app['category'] === 'app' ? 'selected' : ''; ?>>Ứng dụng</option><option value="game" <?php echo $app['category'] === 'game' ? 'selected' : ''; ?>>Trò chơi</option></select></div>
                <div class="form-group"><label>Đánh giá sao</label><input type="number" step="0.1" name="rating" value="<?php echo $app['rating']; ?>" min="0" max="5"></div>
                <div class="form-group"><label>Giá tiền</label><input type="text" name="price" value="<?php echo htmlspecialchars($app['price']); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Phiên bản</label><input type="text" name="version" value="<?php echo htmlspecialchars($app['version']); ?>"></div>
                <div class="form-group"><label>Dung lượng</label><input type="text" name="size" value="<?php echo htmlspecialchars($app['size']); ?>"></div>
            </div>
            <div class="form-group"><label>Link Icon *</label><input type="text" name="icon" value="<?php echo htmlspecialchars($app['icon']); ?>" required></div>
            
            <div class="form-group screenshot-manager-box">
                <label>Quản lý ảnh chụp màn hình</label>
                <div class="existing-screenshots">
                    <?php foreach ($screenshots as $shot): ?>
                        <div class="shot-thumbnail-card">
                            <img src="<?php echo htmlspecialchars($shot['image_url']); ?>" alt="">
                            <a href="admin_delete_screenshot.php?shot_id=<?php echo $shot['id']; ?>&app_id=<?php echo $id; ?>" class="btn-remove-shot" onclick="return confirm('Xóa ảnh này?');"><i class="fa-solid fa-xmark"></i></a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <label style="margin-top: 15px; display: block;">Tải lên ảnh mới</label>
                <input type="file" name="screenshots[]" multiple accept="image/*" class="file-input">
            </div>

            <div class="form-group"><label>Mô tả ngắn *</label><textarea name="desc" rows="2" required><?php echo htmlspecialchars($app['desc']); ?></textarea></div>
            <div class="form-group"><label>Mô tả chi tiết</label><textarea name="long_desc" rows="5"><?php echo htmlspecialchars($app['long_desc']); ?></textarea></div>
            <div class="form-group"><label>Link tải</label><input type="text" name="download_link" value="<?php echo htmlspecialchars($app['download_link']); ?>"></div>
            <button type="submit" class="btn-submit">Lưu thay đổi</button>
        </form>
    </div>
</body>
</html>
