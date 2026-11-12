<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
$doc=(string)($_GET['doc']??'privacy');if(!in_array($doc,['privacy','terms'],true))$doc='privacy';
$isPrivacy=$doc==='privacy';$pageTitle=$isPrivacy?'Chính sách bảo mật | HENDY':'Điều khoản sử dụng | HENDY';$activeMenu='bot-public';
$sections=$isPrivacy?[
['Chính sách dữ liệu','HENDY xử lý thông tin tài khoản Website, dữ liệu Telegram tối thiểu cần cho việc cung cấp dịch vụ, lịch sử đơn hàng, ví, yêu cầu thanh toán và ticket hỗ trợ.'],
['Mục đích sử dụng','Dữ liệu được dùng để vận hành dịch vụ, xử lý đơn hàng, hỗ trợ khách hàng, ngăn chặn gian lận, bảo vệ hệ thống và tuân thủ nghĩa vụ pháp lý.'],
['Bảo vệ dữ liệu','HENDY sử dụng HTTPS, kiểm soát quyền truy cập, lưu mật khẩu dạng băm và giữ token/khóa API trong cấu hình máy chủ. Không công khai thông tin xác thực trong website hoặc bot.'],
['Nền tảng bên thứ ba','Không yêu cầu mật khẩu, PIN hoặc OTP đăng nhập tài khoản bên thứ ba nếu không có tích hợp được ủy quyền rõ ràng. Các model giọng nói AI chỉ được kích hoạt khi có giấy phép và sự đồng ý hợp lệ.'],
['Liên hệ','Thành viên có thể liên hệ CSKH HENDY qua các kênh hỗ trợ được công bố để yêu cầu trợ giúp về tài khoản hoặc dữ liệu.']
]:[
['Tài khoản','Bạn chịu trách nhiệm về thông tin đăng ký, thiết bị và tài khoản Telegram/Website. Không chia sẻ mã xác thực hoặc liên kết đăng nhập với người khác.'],
['Sản phẩm và dịch vụ','Thông tin, giá, điều kiện, tồn kho và thời hạn hiển thị tại từng sản phẩm hoặc bước xác nhận là thông tin áp dụng cho đơn hàng được chọn.'],
['Thanh toán','Giao dịch qua ví được ghi nhận theo loại ví tại bước xác nhận. Yêu cầu nạp tiền chỉ được ghi có sau khi được đối soát theo quy trình.'],
['Sử dụng hợp pháp','Không sử dụng HENDY để vi phạm pháp luật, xâm phạm quyền của bên thứ ba, phát tán mã độc, lạm dụng hệ thống hoặc vượt qua cơ chế bảo mật.'],
['Tính năng thử nghiệm','Một số tính năng có thể đang phát triển, bảo trì hoặc chưa mở bán. Các tích hợp với nền tảng bên thứ ba chỉ được xem là tự động khi có kết nối được ủy quyền và xác nhận kỹ thuật.'],
['Cập nhật','HENDY có thể cập nhật điều khoản khi thay đổi dịch vụ hoặc yêu cầu pháp lý. Phiên bản đang hiển thị là phiên bản hiện hành.']
];
require __DIR__.'/includes/header.php';
?>
<section class="store-detail-hero"><div><div class="eyebrow">HENDY DIGITAL ECOSYSTEM</div><h1><?=$isPrivacy?'Chính sách bảo mật':'Điều khoản sử dụng'?></h1><p class="store-muted">Áp dụng cho Website, App Store, Dịch vụ mạng và Telegram Bot HENDY.</p><a class="store-back-link" href="/">← Về trang chủ</a></div></section>
<section class="store-description-block"><?php foreach($sections as $section):?><h2><?=e($section[0])?></h2><p class="store-prose"><?=e($section[1])?></p><?php endforeach;?><p class="store-muted">Cập nhật: <?=date('d/m/Y')?></p></section>
<?php require __DIR__.'/includes/footer.php'; ?>
