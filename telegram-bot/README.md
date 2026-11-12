# HENDY BOT CYBERTECH

Telegram Bot backend dùng PHP 8.3 + PostgreSQL, đặt trong `telegram-bot/` của monorepo `hendyngo-servers/hendydichvumang`. Website và Bot là hai Render Web Service riêng, dùng hai Neon database/role độc lập.

## Thành phần

- Một Telegram bot production duy nhất, dùng `BOT1_TOKEN`.
- Telegram webhook, menu Inline Keyboard, xác thực Telegram Web App và API.
- Trang giới thiệu công khai, Trung tâm khách hàng và Dashboard Admin.
- Ví, yêu cầu nạp tiền, giao dịch, sản phẩm/đơn hàng, voucher, VIP, referral, ticket CSKH.
- Các phân hệ Mini Game, Tool, Code, OTP, Proxy và dịch vụ VIP.
- Video job worker, callback HMAC, tùy chọn WebSocket realtime.
- PostgreSQL schema/migration, Docker và Render Blueprint.

Không có token, mật khẩu database hoặc khóa signing thật trong source. Tất cả credential phải cấu hình bằng Render Environment/GitHub Secrets.

## URL

| Mục đích | Đường dẫn |
|---|---|
| Trang chủ giới thiệu | `/` |
| Dashboard Admin | `/admin` |
| Trung tâm khách hàng | `/customer` |
| Telegram Mini App | `/miniapp` |
| API discovery | `/api/v1` |
| Public config | `/api/v1/public/config` |
| Health check | `/health` hoặc `/healthz` |
| Chính sách bảo mật | `/privacy` |
| Điều khoản | `/terms` |
| Webhook Telegram duy nhất | `/webhook/telegram` |
| Video callback | `/webhook/video` |
| Bank callback (mặc định khóa) | `/webhook/bank` |

Trang chủ công khai dùng `apps/website/landing.html` với CSS/JS tự host trong `public/assets/`. Trung tâm khách hàng dùng `apps/website/index.html`; Admin dùng `apps/admin-dashboard/index.html`. Trang `apps/admin-dashboard/legacy-login.html` là trang trao đổi vé đăng nhập website một lần, vẫn được route `/legacy-login` sử dụng.

## Cấu hình

Các biến quan trọng cấu hình tại Render Environment:

- `BOT1_TOKEN`: token của bot Telegram duy nhất.
- `ADMIN_ID` hoặc `ADMIN_IDS`: Telegram ID của quản trị viên.
- `DATABASE_URL`: Neon PostgreSQL database `hendydichvumang`, role `hendy_bot_app`.
- `DATABASE_SSL=true` cho Neon.
- `WEBHOOK_AUTO_REGISTER=true`; Render cung cấp `RENDER_EXTERNAL_URL`.
- `TELEGRAM_WEBHOOK_SECRET` là tùy chọn; để trống, backend tạo secret ngẫu nhiên 256-bit và lưu trong PostgreSQL.
- `BANK_WEBHOOK_ENABLED=false` cho đến khi có adapter kiểm tra chữ ký theo ngân hàng.
- `NEON_STORAGE_FUNCTION_URL` và `NEON_STORAGE_FUNCTION_KEY` chỉ đặt nếu bật Object Storage.

Runtime chỉ chấp nhận khóa bot `primary`, chỉ đọc `BOT1_TOKEN`, chỉ đăng ký webhook `/webhook/telegram`; các biến Bot thứ hai cũ nếu còn tồn tại trong Render sẽ không được sử dụng. Không đưa token Bot hoặc secret vào URL, HTML, JavaScript, log hoặc commit.

Liên kết Bot công khai được lấy từ Telegram `getMe()` trong bước đăng ký webhook và lưu trong `app_settings`. Không cần chép username Bot thủ công.

## Chạy local

Yêu cầu PHP 8.3+, Composer, PostgreSQL, extensions `curl`, `mbstring`, `pdo_pgsql`.

```bash
cp .env.example .env
composer install
php bin/validate-config.php
php -S 127.0.0.1:8080 -t public
```

Điền credential local của riêng bạn trong file `.env`; file này bị ignore. Schema production được quản lý bằng migration riêng; `RUN_MIGRATIONS_ON_START=false` để tránh tài khoản runtime tự chạy DDL.

## Render / Docker

Blueprint ở repository root: `render.yaml`.

- Bot build dùng `dockerfilePath: ./telegram-bot/Dockerfile`.
- Docker context: `./telegram-bot`.
- Render service: `hendydichvumang-telegram-bot`.
- Health check: `/healthz`.
- Database: Neon `hendydichvumang`, không dùng chung database Website.

Khi đã adopt Blueprint, các Git push trên `main` tự sync/deploy theo `autoDeployTrigger: checksPass`. Không tạo thêm Web Service thủ công trùng tên.

## Dashboard Admin

Admin UI hiện có các phân hệ tổng quan, thành viên, đơn hàng, nạp tiền, ticket CSKH, voucher, giftcode, menu Bot, runtime, module upload, video jobs, operations, HomeProxy và audit log. Mọi API `/api/admin/*` kiểm tra allowlist Admin ở backend; xác thực Telegram không thay thế phân quyền server-side.

## Kiểm tra

```bash
composer validate --no-check-publish
composer lint
composer test
php bin/validate-config.php
php scripts/secret-scan.php
```

GitHub Actions chạy lint/test Bot, lint PHP Website và test Neon Function.

## Bảo mật và ranh giới dữ liệu

- Bot Token chỉ đọc từ environment; không có Bot phụ.
- Webhook xác thực `X-Telegram-Bot-Api-Secret-Token` với so sánh an toàn.
- Telegram Mini App initData được xác minh HMAC ở backend.
- SQL dùng PDO prepared statements; giao dịch ví dùng transaction/idempotency.
- Bank callback để tắt cho đến khi xác minh chữ ký nhà cung cấp.
- File upload được đưa vào vùng kiểm duyệt, không tự thực thi.
- Website và Bot dùng hai Neon database/role riêng. Không tự đồng bộ số dư, đơn hàng hoặc hồ sơ tài khoản giữa hai database.

Nếu Bot Token cũ từng bị commit, hãy thu hồi token tại BotFather, tạo token mới và cập nhật `BOT1_TOKEN` trong Render; chỉ xóa file khỏi commit mới không xóa token khỏi Git history.
