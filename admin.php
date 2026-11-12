<?php
require_once 'includes/db.php';
require_once 'includes/admin_check.php';

$stmt = $pdo->query("SELECT * FROM apps ORDER BY id DESC");
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản trị Ứng dụng - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-body">
    <div class="admin-container">
        <header class="admin-header">
            <h1><i class="fa-solid fa-gauge-high"></i> Quản Trị Kho Ứng Dụng</h1>
            <div class="admin-actions">
                <a href="admin_add.php" class="btn-primary"><i class="fa-solid fa-plus"></i> Thêm ứng dụng</a>
                <a href="admin_services.php" class="btn-secondary" style="background: #e0f2fe; color: #0369a1;"><i class="fa-solid fa-network-wired"></i> Quản lý Dịch vụ mạng</a>
                <a href="index.php" class="btn-secondary"><i class="fa-solid fa-house"></i> Về trang chủ</a>
            </div>
        </header>

        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Icon</th>
                        <th>Tên ứng dụng</th>
                        <th>Nhà phát triển</th>
                        <th>Danh mục</th>
                        <th>Giá</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($apps) > 0): ?>
                        <?php foreach ($apps as $app): ?>
                            <tr>
                                <td>#<?php echo $app['id']; ?></td>
                                <td><img src="<?php echo htmlspecialchars($app['icon']); ?>" alt="" class="table-icon"></td>
                                <td><strong><?php echo htmlspecialchars($app['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($app['dev']); ?></td>
                                <td>
                                    <span class="badge-cat <?php echo $app['category']; ?>">
                                        <?php echo $app['category'] === 'app' ? 'Ứng dụng' : 'Trò chơi'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($app['price']); ?></td>
                                <td>
                                    <a href="admin_edit.php?id=<?php echo $app['id']; ?>" class="btn-edit" style="margin-right: 10px; color: #2563eb; text-decoration: none; font-weight: 600;">
                                        <i class="fa-solid fa-pen-to-square"></i> Sửa
                                    </a>
                                    <a href="admin_delete.php?id=<?php echo $app['id']; ?>" class="btn-delete" onclick="return confirm('Bạn có chắc chắn muốn xóa ứng dụng này không?');">
                                        <i class="fa-solid fa-trash"></i> Xóa
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align: center; padding: 20px;">Chưa có ứng dụng nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
