<?php
require_once 'includes/db.php';
include 'includes/header.php';

$current_cat = isset($_GET['cat']) ? $_GET['cat'] : 'all';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT * FROM apps WHERE 1=1";
$params = [];

if ($current_cat !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $current_cat;
}

if (!empty($search_query)) {
    $sql .= " AND (name LIKE ? OR dev LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filtered_apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="content">
    <?php if ($current_cat === 'all' && empty($search_query)): ?>
    <section class="hero-banner">
        <div class="hero-info">
            <span class="badge">Nổi bật tuần này</span>
            <h1>Khám phá thế giới ứng dụng đỉnh cao</h1>
            <p>Hàng ngàn phần mềm làm việc hiệu quả và tựa game bom tấn đang chờ bạn tải về.</p>
            <a href="index.php?cat=game" class="btn-primary">Khám phá Trò chơi</a>
        </div>
    </section>
    <?php endif; ?>

    <div class="section-header">
        <h2>
            <?php 
                if (!empty($search_query)) {
                    echo 'Kết quả tìm kiếm cho: "' . htmlspecialchars($search_query) . '"';
                } elseif ($current_cat === 'app') {
                    echo 'Ứng dụng phổ biến';
                } elseif ($current_cat === 'game') {
                    echo 'Trò chơi thịnh hành';
                } else {
                    echo 'Đề xuất cho bạn';
                }
            ?>
        </h2>
    </div>

    <?php if (count($filtered_apps) > 0): ?>
        <div class="app-grid">
            <?php foreach ($filtered_apps as $app): ?>
                <div class="app-card" onclick="window.location.href='detail.php?id=<?php echo $app['id']; ?>'" style="cursor: pointer;">
                    <div class="app-card-top">
                        <img src="<?php echo htmlspecialchars($app['icon']); ?>" alt="" class="app-icon">
                        <div class="app-meta">
                            <h3 class="app-title"><?php echo htmlspecialchars($app['name']); ?></h3>
                            <p class="app-dev"><?php echo htmlspecialchars($app['dev']); ?></p>
                            <div class="app-rating">
                                <span><?php echo $app['rating']; ?></span>
                                <i class="fa-solid fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="app-desc"><?php echo htmlspecialchars($app['desc']); ?></p>
                    <div class="app-card-footer">
                        <span class="app-price"><?php echo htmlspecialchars($app['price']); ?></span>
                        <span class="btn-get">Xem chi tiết</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>Không tìm thấy ứng dụng nào phù hợp với yêu cầu của bạn.</p>
            <a href="index.php" class="btn-get">Quay lại trang chủ</a>
        </div>
    <?php endif; ?>
</main>
        </div>
    </div>
</body>
</html>
