<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
if (current_user()) redirect(is_admin() ? 'admin.php' : 'index.php');

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Vui lòng nhập email và mật khẩu hợp lệ.';
    } else {
        $statement = $pdo->prepare('SELECT id,name,email,password,role FROM users WHERE email=? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if ($user && password_verify($password, (string)$user['password'])) {
            session_regenerate_id(true);
            unset($user['password']);
            $_SESSION['user'] = $user;
            flash('Đăng nhập thành công.');
            redirect(($user['role'] ?? 'user') === 'admin' ? 'admin.php' : 'index.php');
        }
        $error = 'Email hoặc mật khẩu không chính xác.';
    }
}
$pageTitle = 'Đăng nhập | HENDY';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <form class="auth-card" method="post" action="login.php">
    <div class="eyebrow">WELCOME BACK</div><h1>Đăng nhập</h1>
    <p class="muted">Truy cập tài khoản HENDY của bạn.</p>
    <?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
    <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="<?=e($email)?>" required autocomplete="email">
    <label for="password">Mật khẩu</label>
    <input id="password" type="password" name="password" required autocomplete="current-password">
    <button class="button primary full" type="submit">Đăng nhập</button>
    <p class="auth-foot">Chưa có tài khoản? <a href="register.php">Đăng ký</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
