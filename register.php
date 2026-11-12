<?php
require_once 'includes/db.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Vui lòng điền đầy đủ tất cả các trường!';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Tên đăng nhập hoặc Email đã tồn tại!';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$username, $email, $hashed_password])) {
                $success = 'Đăng ký thành công! Bạn có thể <a href="login.php">đăng nhập ngay</a>.';
            } else {
                $error = 'Đã có lỗi xảy ra, vui lòng thử lại.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký tài khoản - AppStore</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <h2>Tạo tài khoản AppStore</h2>
        <?php if (!empty($error)): ?><div class="alert error"><?php echo $error; ?></div><?php endif; ?>
        <?php if (!empty($success)): ?><div class="alert success"><?php echo $success; ?></div><?php endif; ?>

        <form action="register.php" method="POST">
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-submit">Đăng ký</button>
        </form>
        <p class="auth-redirect">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
    </div>
</body>
</html>
