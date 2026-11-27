# Tích hợp website nguồn bundles1.zip

## Kết quả

Nội dung phù hợp từ website Webcard trong bundles1.zip đã được chuyển thành trang tĩnh HENDY tại /dich-vu/. Trang sử dụng bộ nhận diện, màu sắc, điều hướng, footer và component dùng chung của website/public/.

Các nhóm dịch vụ được giữ theo nội dung nguồn: phát triển phần mềm theo yêu cầu, tư vấn công nghệ, bảo trì phần mềm, phát triển ứng dụng di động, quản lý dự án và hỗ trợ khách hàng.

## Những phần không sao chép nguyên trạng

- Không đưa api.php vào website/public/: mã này là PHP handler của Webcard và phụ thuộc môi trường PHP/mail server.
- Không giữ domain icof5p1f.cloudfly.vn, địa chỉ, số điện thoại, email và tên doanh nghiệp mẫu trong bản public HENDY.
- Không giữ nội dung testimonials chưa được xác minh.
- Không nhập toàn bộ bộ thư viện Webcard/flag assets dư thừa; ảnh trong gói nguồn là placeholder, không phải ảnh minh họa sử dụng được.
- Không thêm biểu mẫu HTML giả không có backend. CTA hiện dẫn đến Telegram Bot và nhóm hỗ trợ đã có trong website.

## Kiểm tra

- Trang mới: website/public/dich-vu/index.html
- CSS riêng: website/public/dich-vu/services.css
- Trang chủ và sitemap có liên kết đến /dich-vu/.
- CI kiểm tra sự tồn tại của trang, stylesheet và các chuỗi nhận diện cũ cần loại bỏ.
- Toàn bộ giao diện mới vẫn là static HTML/CSS/JS, không thêm PHP hay thông tin bí mật.
