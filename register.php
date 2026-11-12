<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
if (current_user()) redirect('index.php');

$error = '';
$name = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $error = 'Tên phải từ 2 đến 100 ký tự.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 10) {
        $error = 'Mật khẩu cần tối thiểu 10 ký tự.';
    } else {
        try {
            $statement = $pdo->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'user')");
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            flash('Tạo tài khoản thành công. Hãy đăng nhập.');
            redirect('login.php');
        } catch (PDOException $exception) {
            $error = in_array((string)$exception->getCode(), ['23000', '23505'], true)
                ? 'Email này đã được đăng ký.'
                : 'Không thể tạo tài khoản lúc này.';
        }
    }
}
$pageTitle = 'Đăng ký | HENDY';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <form class="auth-card" method="post" action="register.php">
    <div class="eyebrow">JOIN HENDY</div><h1>Tạo tài khoản</h1>
    <p class="muted">Đăng ký để sử dụng tiện ích thành viên.</p>
    <?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
    <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
    <label for="name">Họ và tên</label>
    <input id="name" name="name" value="<?=e($name)?>" maxlength="100" required autocomplete="name">
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="<?=e($email)?>" required autocomplete="email">
    <label for="password">Mật khẩu (ít nhất 10 ký tự)</label>
    <input id="password" type="password" name="password" minlength="10" required autocomplete="new-password">
    <button class="button primary full" type="submit">Tạo tài khoản</button>
    <p class="auth-foot">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
