# Tích hợp chọn lọc từ Hendy Server PRO

Repository đích: https://github.com/hendyngo-servers/hendydichvumang

Nguồn tham khảo: https://github.com/ngogiaidy56-eng/Hendy-Server-PRO

## Đã tích hợp

- Bộ điều phối lệnh Telegram, callback và hội thoại theo trạng thái (`BotDispatcher`), tách logic định tuyến khỏi transport.
- Kho phiên hội thoại D1 có TTL, giới hạn kích thước JSON và tự xóa phiên hết hạn hoặc dữ liệu lỗi.
- Luồng tạo ticket hỗ trợ trực tiếp trong Telegram: nhập tiêu đề, nhập nội dung, ghi ticket vào bảng `tickets`, xóa phiên sau khi hoàn tất.
- Audit log lưu metadata tối thiểu cho sự kiện tạo ticket; không ghi nội dung hội thoại, token hoặc payload webhook.
- Logger có allowlist trường và loại bỏ dữ liệu message/callback tùy ý.
- Migration mới `0003_sessions_and_audit_logs.sql` chỉ thêm `user_sessions` và `audit_logs`; không thay thế bảng người dùng, ví, ledger, thanh toán hoặc `telegram_webhook_updates`.
- Tác vụ Cron dọn phiên hội thoại hết hạn.

## Chủ động không chép nguyên trạng

- Không nhập migration ví, payment, webhook hoặc user schema của dự án nguồn vì không tương thích với D1 hiện tại.
- Không chép Admin Dashboard nguồn: cơ chế token qua query string và lưu token trong localStorage không đạt yêu cầu bảo mật. Chưa mở endpoint quản trị mới trong đợt này.
- Không chép `.dev.vars.example` nguồn vì trước đây có các giá trị mang hình dạng credential; chỉ sử dụng GitHub Secrets và Cloudflare Worker Secrets.
- Không chép `telegram.js` nguồn: lớp Telegram API hiện tại đã có `redirect: "error"`, timeout và lỗi đã chuẩn hóa, an toàn hơn bản nguồn.
- Không bật Durable Objects, Queues hoặc R2 chỉ bằng cách thêm binding. Các tài nguyên này cần workflow bootstrap, luồng xử lý thực tế và kiểm thử riêng.

## Lược đồ và triển khai

Workflow triển khai hiện có áp dụng D1 migrations trước khi deploy Worker. Vì vậy, migration 0003 được chạy trước khi mã Worker mới sử dụng kho phiên và audit log. Các truy vấn dọn phiên chỉ chạy sau khi migration đã được áp dụng.

## Bảo mật vận hành

- Giữ `BOT_TOKEN` trong GitHub Secret/Cloudflare Worker Secret, không đưa vào source code, Mini App, URL hoặc log.
- Không dùng `?token=` để xác thực Admin Dashboard.
- Không ghi nội dung message, callback data, thông tin ngân hàng hoặc dữ liệu phiên vào log.
- Nếu credential từng được commit ở repository nguồn, hãy xoay vòng credential đó trước khi dùng lại ở production.

## Giới hạn còn lại

- Ticket tạo từ bot hiện được lưu trong D1; giao diện quản trị để phân công/xử lý ticket là hạng mục riêng.
- Chưa tích hợp trực tiếp các nhà cung cấp OTP/Proxy, cổng đối soát ngân hàng hoặc Core Backend.
- WebSocket/DO, Queue/DLQ và R2 sẽ chỉ được bật ở các thay đổi độc lập sau khi có binding, migration hạ tầng, retry policy và kiểm thử tương ứng.
