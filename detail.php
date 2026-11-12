<?php
require_once 'includes/db.php';

$app_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("SELECT * FROM apps WHERE id = ?");
$stmt->execute([$app_id]);
$current_app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_app) {
    header("Location: index.php");
    exit;
}

$stmt_img = $pdo->prepare("SELECT image_url FROM app_screenshots WHERE app_id = ?");
$stmt_img->execute([$app_id]);
$screenshots = $stmt_img->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<main class="content">
    <div class="back-nav">
        <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Quay lại Kho ứng dụng</a>
    </div>

    <div class="app-detail-hero">
        <img src="<?php echo htmlspecialchars($current_app['icon']); ?>" alt="" class="detail-icon">
        <div class="detail-meta">
            <h1><?php echo htmlspecialchars($current_app['name']); ?></h1>
            <p class="dev-name"><?php echo htmlspecialchars($current_app['dev']); ?></p>
            
            <div class="detail-stats">
                <span><i class="fa-solid fa-star" style="color: #f59e0b;"></i> <?php echo $current_app['rating']; ?></span>
                <span>•</span>
                <span>Dung lượng: <?php echo htmlspecialchars($current_app['size']); ?></span>
                <span>•</span>
                <span>Phiên bản: v<?php echo htmlspecialchars($current_app['version']); ?></span>
            </div>

            <div class="detail-actions">
                <a href="<?php echo htmlspecialchars($current_app['download_link']); ?>" class="btn-install-large">
                    <i class="fa-solid fa-download"></i> Tải xuống (<?php echo htmlspecialchars($current_app['price']); ?>)
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($screenshots)): ?>
    <div class="section-block">
        <h2>Ảnh chụp màn hình</h2>
        <div class="screenshot-slider">
            <?php foreach ($screenshots as $img): ?>
                <img src="<?php echo htmlspecialchars($img); ?>" alt="Screenshot" class="screenshot-img">
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="section-block">
        <h2>Mô tả</h2>
        <div class="long-desc-box">
            <p><?php echo nl2br(htmlspecialchars($current_app['long_desc'])); ?></p>
        </div>
    </div>
</main>
        </div>
    </div>
</body>
</html>
