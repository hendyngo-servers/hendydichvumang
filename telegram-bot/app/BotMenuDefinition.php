<?php
declare(strict_types=1);

namespace Hendy;

/**
 * Canonical member menu. Keep the home screen focused on the active customer
 * journeys; secondary account, wallet and community actions remain available
 * through their existing commands/submenus.
 */
final class BotMenuDefinition
{
    public const ITEMS = [
        ['action' => 'live', 'label' => '🎮 Mini Game Live', 'description' => 'Chọn nhà cái và đặt gói Mini Game Live.', 'style' => 'primary'],
        ['action' => 'tools', 'label' => '🧰 Thuê Tool', 'description' => 'Xem danh mục công cụ và dịch vụ cho thuê.', 'style' => 'primary'],
        ['action' => 'codes', 'label' => '📡 Code / mã dịch vụ', 'description' => 'Mở kho Code Telegram và các mã dịch vụ.', 'style' => 'primary'],
        ['action' => 'otp', 'label' => '📱 OTP SMS', 'description' => 'Chọn dịch vụ OTP SMS đang được mở bán.', 'style' => 'primary'],
        ['action' => 'proxy', 'label' => '🌐 Proxy', 'description' => 'Xem và đặt mua gói Proxy khả dụng.', 'style' => 'primary'],
        ['action' => 'vip', 'label' => '👑 Sản phẩm VIP', 'description' => 'Xem kho sản phẩm VIP và quyền lợi thành viên.', 'style' => 'success'],
        ['action' => 'matchmaking', 'label' => '⚔️ Ghép đôi', 'description' => 'Tìm đối thủ và quản lý trận ghép đôi.', 'style' => 'success'],
        ['action' => 'vietsub', 'label' => '🎬 Vietsub', 'description' => 'Gửi liên kết video để tạo tác vụ phụ đề.', 'style' => 'primary'],
        ['action' => 'gift', 'label' => '🎁 Giftcode', 'description' => 'Nhập mã Giftcode để nhận ưu đãi hợp lệ.', 'style' => 'success'],
        ['action' => 'ref', 'label' => '🔗 Giới thiệu', 'description' => 'Lấy liên kết giới thiệu và xem thống kê REF.', 'style' => 'primary'],
        ['action' => 'orders', 'label' => '📄 Đơn hàng', 'description' => 'Theo dõi đơn hàng và trạng thái xử lý.', 'style' => 'success'],
        ['action' => 'history', 'label' => '🗂 Lịch sử', 'description' => 'Xem lịch sử giao dịch và tác vụ của bạn.', 'style' => 'primary'],
        ['action' => 'ai-features', 'label' => '🤖 Tính năng AI', 'description' => 'Mở các công cụ AI đang được hệ thống cho phép.', 'style' => 'primary'],
        ['action' => 'home', 'label' => '🏠 Menu chính', 'description' => 'Tải lại menu chính HENDY.', 'style' => 'danger'],
    ];

    /**
     * Alternating 2-1 rows create the staggered layout requested for Telegram.
     * Admin remains appended separately and is only visible to administrators.
     */
    public const LAYOUT = [
        ['live', 'tools'],
        ['codes'],
        ['otp', 'proxy'],
        ['vip'],
        ['matchmaking', 'vietsub'],
        ['gift'],
        ['ref', 'orders'],
        ['history'],
        ['ai-features', 'home'],
    ];

    // Child/legacy actions stay registered for existing callback compatibility.
    public const ACTIONS = [
        'live', 'tools', 'codes', 'otp', 'proxy', 'vip', 'matchmaking',
        'vietsub', 'gift', 'ref', 'orders', 'history', 'ai-features', 'home',
        'music-ai', 'image-ai', 'ai-cover', 'group', 'news', 'admin',
        'viptools', 'miniapp', 'jobs', 'abcvip', 'support',
    ];
}
