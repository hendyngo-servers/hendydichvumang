<?php
declare(strict_types=1);

namespace Hendy;

/** Canonical member menu. All creative AI tools are grouped behind one submenu. */
final class BotMenuDefinition
{
    public const ITEMS = [
        ['action' => 'live', 'label' => '🚀 MINIGAME LIVE', 'description' => 'Chọn nhà cái, xem gói dịch vụ MiniGame Live và thanh toán bằng Ví Chính.', 'style' => 'primary'],
        ['action' => 'otp', 'label' => '▦ OTP & PROXY', 'description' => 'Mở trung tâm OTP, Proxy và ví NYC.', 'style' => 'primary'],
        ['action' => 'wallet', 'label' => '💳 NẠP TIỀN', 'description' => 'Tạo yêu cầu nạp và xem trạng thái VietQR.', 'style' => 'success'],
        ['action' => 'vip', 'label' => '👑 TRUNG TÂM VIP', 'description' => 'Xem cấp VIP, quyền lợi và điểm danh.', 'style' => 'success'],
        ['action' => 'codes', 'label' => '📡 CODE TELEGRAM', 'description' => 'Mở Code Telegram, chọn dịch vụ và gửi tên đăng nhập để nhận mã.', 'style' => 'success'],
        ['action' => 'customer', 'label' => '💠 TRUNG TÂM KHÁCH HÀNG', 'description' => 'Hồ sơ, ví, đơn hàng và hỗ trợ khách hàng.', 'style' => 'primary'],
        ['action' => 'ai-features', 'label' => '🤖 TÍNH NĂNG AI', 'description' => 'Mở AI Cover, tạo âm nhạc AI và chỉnh sửa ảnh AI.', 'style' => 'success'],
        ['action' => 'group', 'label' => '🎊 NHÓM CHAT ↗', 'description' => 'Mở liên kết nhóm chat HENDY.', 'style' => 'primary'],
        ['action' => 'news', 'label' => '📢 KÊNH THÔNG BÁO ↗', 'description' => 'Mở kênh thông báo HENDY.', 'style' => 'primary'],
        ['action' => 'vip-coming-soon', 'label' => '👑 DỊCH VỤ SẮP TRIỂN KHAI', 'description' => 'Xem các dịch vụ mới đang được HENDY hoàn thiện.', 'style' => 'danger'],
    ];

    public const LAYOUT = [
        ['live', 'otp'],
        ['wallet'],
        ['vip', 'codes'],
        ['customer'],
        ['ai-features'],
        ['group'],
        ['news', 'vip-coming-soon'],
        ['admin'],
    ];

    // Child actions are retained for callback dispatch and nested AI menus.
    public const ACTIONS = [
        'live', 'otp', 'wallet', 'vip', 'codes', 'customer',
        'ai-features', 'music-ai', 'image-ai', 'ai-cover',
        'group', 'news', 'vip-coming-soon', 'admin',
        'tools', 'viptools', 'miniapp', 'ref', 'vietsub',
        'history', 'jobs', 'abcvip', 'gift', 'support', 'matchmaking',
    ];
}
