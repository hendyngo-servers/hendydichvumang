<?php
require_once 'includes/db.php';
include 'includes/header.php';

$stmt = $pdo->query("SELECT * FROM network_services ORDER BY id DESC");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="content">
    <section class="hero-banner" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
        <div class="hero-info">
            <span class="badge">Hạ tầng mạng cao cấp</span>
            <h1>Dịch Vụ Mạng & Giải Pháp Tự Động Hóa</h1>
            <p>Cung cấp VPS, Proxy tốc độ cao và máy chủ chuyên dụng phục vụ cho các hệ thống phần mềm.</p>
        </div>
    </section>

    <div class="section-header">
        <h2>Danh sách gói dịch vụ mạng</h2>
    </div>

    <?php if (count($services) > 0): ?>
        <div class="app-grid">
            <?php foreach ($services as $srv): ?>
                <div class="app-card">
                    <div class="app-card-top">
                        <div class="app-icon" style="display: flex; align-items: center; justify-content: center; background: #ecfdf5; color: #059669; font-size: 24px; border-radius: 14px; width: 64px; height: 64px;">
                            <i class="fa-solid fa-server"></i>
                        </div>
                        <div class="app-meta">
                            <h3 class="app-title"><?php echo htmlspecialchars($srv['name']); ?></h3>
                            <p class="app-dev">Tốc độ: <?php echo htmlspecialchars($srv['speed']); ?></p>
                            <div class="app-rating" style="color: #059669; font-size: 0.85rem;">
                                <i class="fa-solid fa-gauge-high"></i> Băng thông: <?php echo htmlspecialchars($srv['bandwidth']); ?>
                            </div>
                        </div>
                    </div>
                    <p class="app-desc"><?php echo htmlspecialchars($srv['description']); ?></p>
                    <div class="app-card-footer">
                        <span class="app-price"><?php echo htmlspecialchars($srv['price']); ?></span>
                        <a href="#" class="btn-get" style="background: #ecfdf5; color: #059669;">Đăng ký</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>Hiện chưa có gói dịch vụ mạng nào.</p>
        </div>
    <?php endif; ?>
</main>
        </div>
    </div>
</body>
</html>
