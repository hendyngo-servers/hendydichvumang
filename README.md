# HENDY Cybertech — Website + Telegram Bot

Repository hợp nhất hai ứng dụng HENDY nhưng giữ runtime và dữ liệu tách biệt.

## Cấu trúc

- **Website danh mục** ở thư mục gốc: PHP 8.3 + Apache + MySQL. Có trang chủ, tìm kiếm sản phẩm, đăng ký/đăng nhập, chi tiết sản phẩm, Admin CRUD và upload ảnh.
- **Telegram Bot** tại telegram-bot/: PHP 8.3 + Apache + PostgreSQL. Có Telegram webhook, Inline Keyboard, Mini App, Trung tâm khách hàng, Admin Dashboard, ví, đơn hàng, VIP, Proxy/OTP, Giftcode, REF và CSKH.

## Nguyên tắc triển khai

Hai ứng dụng chạy thành **hai Render Web Service riêng** và dùng **hai database riêng**:

| Ứng dụng | Thư mục / Docker context | Database |
|---|---|---|
| Website | Repository root (./Dockerfile) | MySQL/MariaDB qua DB_HOST, DB_NAME, DB_USER, DB_PASSWORD |
| Telegram Bot | telegram-bot/ (telegram-bot/Dockerfile) | PostgreSQL qua DATABASE_URL |

Blueprint render.yaml chỉ khai báo service Telegram Bot. Không đổi Dockerfile hoặc biến môi trường của Web Service Website đang chạy.

## Cấu hình website

Giữ các biến MySQL hiện tại. Có thể khai báo TELEGRAM_BOT_URL=https://t.me/your_bot_username để hiện liên kết Bot trong thanh điều hướng Website. Chỉ chấp nhận HTTPS thuộc t.me hoặc telegram.me.

## Cấu hình Bot

Đọc telegram-bot/README.md và docs/TELEGRAM-BOT-INTEGRATION.md.

- Chỉ cấu hình một bot production: BOT1_TOKEN.
- Cấu hình PostgreSQL riêng cho Bot bằng DATABASE_URL; tuyệt đối không trỏ vào MySQL website.
- ADMIN_ID hoặc ADMIN_IDS chứa Telegram ID quản trị.
- Render tự cấp URL HTTPS và ứng dụng dùng RENDER_EXTERNAL_URL để đăng ký webhook.
- Không commit token, database URL, mật khẩu hoặc secret. Không điền giá trị thật vào .env.example.

## CI

GitHub Actions kiểm tra cú pháp PHP website và chạy Composer lint/test của Bot. CI không cần credential production hoặc truy cập database thật.

## Bảo toàn dữ liệu

Không có migration tự động giữa hai database. Không tự đồng bộ số dư, ví, đơn hàng hay tài khoản giữa MySQL và PostgreSQL. Liên kết tài khoản Website với Telegram cần quy trình xác minh/one-time code riêng trước khi triển khai.
