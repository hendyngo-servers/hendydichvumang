<?php
declare(strict_types=1);

namespace Hendy;

/** Canonical commercial HENDY Inline Keyboard; member layout mirrors the supplied reference. */
final class BotMenuDefinition
{
    public const ITEMS = [
        ['action' => 'live', 'label' => '🚀 MINIGAME LIVE', 'description' => 'Chọn nhóm Mini Game Live và quản lý tài khoản.', 'style' => 'primary'],
        ['action' => 'otp', 'label' => '▦ OTP & PROXY', 'description' => 'Mở trung tâm OTP, Proxy và ví NYC.', 'style' => 'danger'],
        ['action' => 'wallet', 'label' => '💳 NẠP TIỀN', 'description' => 'Tạo yêu cầu nạp và xem trạng thái VietQR.', 'style' => 'success'],
        ['action' => 'vip', 'label' => '👑 TRUNG TÂM VIP', 'description' => 'Xem cấp VIP, quyền lợi và điểm danh.', 'style' => 'primary'],
        ['action' => 'codes', 'label' => '🎁 MUA LÌ XÌ - CODE MÀN - CODE TELE', 'description' => 'Mở kho lì xì, mã màn hình và mã Telegram.', 'style' => 'success'],
        ['action' => 'customer', 'label' => '💠 TRUNG TÂM KHÁCH HÀNG', 'description' => 'Hồ sơ, ví, đơn hàng và hỗ trợ khách hàng.', 'style' => 'primary'],
        ['action' => 'music-ai', 'label' => '🎵 TẠO ÂM NHẠC AI', 'description' => 'Tạo nhạc từ mô tả bằng AI.', 'style' => 'success'],
        ['action' => 'image-ai', 'label' => '🖼️ CHỈNH SỬA ẢNH AI', 'description' => 'Gửi ảnh và mô tả cách bạn muốn chỉnh sửa.', 'style' => 'primary'],
        ['action' => 'group', 'label' => '🎊 NHÓM CHAT ↗', 'description' => 'Mở liên kết nhóm chat HENDY.', 'style' => 'primary'],
        ['action' => 'news', 'label' => '📢 KÊNH THÔNG BÁO ↗', 'description' => 'Mở kênh thông báo HENDY.', 'style' => 'success'],
        ['action' => 'vip-coming-soon', 'label' => '👑 DỊCH VỤ SẮP TRIỂN KHAI', 'description' => 'Xem các dịch vụ mới đang được HENDY hoàn thiện.', 'style' => 'primary'],
    ];

    public const LAYOUT = [
        ['live', 'otp'],
        ['wallet'],
        ['vip', 'codes'],
        ['customer'],
        ['music-ai', 'image-ai'],
        ['group'],
        ['news', 'vip-coming-soon'],
        ['admin'],
    ];

    // Keep hidden and legacy actions accepted by the API editor and old callbacks.
    public const ACTIONS = [
        'live', 'otp', 'wallet', 'vip', 'codes', 'customer',
        'music-ai', 'image-ai',
        'group', 'news', 'vip-coming-soon', 'admin',
        'tools', 'viptools', 'miniapp', 'ref', 'vietsub',
        'history', 'jobs', 'abcvip', 'gift', 'support', 'matchmaking',
    ];
}
