# HENDY Cybertech — Website, App Store và Telegram Bot

Monorepo dùng một repository GitHub với hai Render Web Service được quản lý bằng Blueprint `render.yaml`.

| Thành phần | Render service | Runtime | Database |
|---|---|---|---|
| Website chính + App Store | `hendydichvumang-website` | PHP 8.3 / Apache | Neon `hendydichvumang_website` |
| Telegram Bot duy nhất | `hendydichvumang-telegram-bot` | PHP 8.3 / Apache | Neon `hendydichvumang` |

## Website và App Store

Website gốc dùng `index.php` để hiển thị danh mục sản phẩm/dịch vụ hiện có. Module `app_store/` là kho ứng dụng/trò chơi và danh sách gói dịch vụ mạng theo sơ đồ App Store.

- Kho ứng dụng: `/app_store/` hoặc `/app_store/index.php`
- Chi tiết ứng dụng: `/app_store/detail.php?id=...`
- Dịch vụ mạng: `/app_store/dichvumang.php`
- Quản trị ứng dụng: `/app_store/admin.php`
- Quản trị dịch vụ: `/app_store/admin_services.php`
- Website catalogue: `/`; chi tiết mục catalogue: `/detail.php?id=...`

App Store dùng chung `includes/db.php`, PDO, session, bảng `users`, vai trò Admin và CSRF helpers của Website. Không tạo thêm bảng users hoặc tài khoản Admin mặc định. Các file `app_store/login.php`, `register.php`, `logout.php` chỉ chuyển sang hệ thống đăng nhập Website đang dùng.

Production website chạy Neon PostgreSQL với role `hendy_website_app`. Migration cộng thêm App Store là `app_store/database.postgresql.sql`; bản MySQL tương thích là `app_store/database.sql`. Không chạy MySQL schema trên Neon.

Ảnh chụp màn hình dùng Neon Object Storage khi `NEON_STORAGE_FUNCTION_URL` và `NEON_STORAGE_FUNCTION_KEY` được cấu hình; thư mục `app_store/uploads/screenshots/` là phương án fallback. Thư mục upload chặn thực thi PHP.

## Database và tài khoản

- Website PostgreSQL schema: `database/website-postgresql.sql`.
- App Store PostgreSQL migration: `app_store/database.postgresql.sql`.
- Bot PostgreSQL schema: `telegram-bot/database/schema.sql`.
- Website và Bot dùng database/role riêng. Không tự chuyển dữ liệu ví, đơn hàng hay user từ Bot sang Website.
- Admin Website chỉ được khởi tạo khi `ADMIN_EMAIL` và `ADMIN_PASSWORD` được cài đặt trong Render và chưa có Admin; không có thông tin đăng nhập mặc định trong source.

## Telegram Bot

Bot production chỉ sử dụng `BOT1_TOKEN`. Runtime chỉ chấp nhận bot key `primary`, chỉ đăng ký webhook `/webhook/telegram`. `BOT2_TOKEN`, bot phụ và webhook thứ hai đã bị loại khỏi cấu hình runtime. Xem `telegram-bot/README.md` và `docs/TELEGRAM-BOT-INTEGRATION.md` để biết chi tiết.

## Render Blueprint

Blueprint root là `render.yaml`, khai báo hai Docker Web Service hiện có. Website dùng Docker context `.`; Bot dùng `./telegram-bot`. Blueprint đã được tạo và adopt service hiện có; bật Auto Sync khi có commit xanh. Giữ secrets trong Render Environment, không commit chúng vào repository.

## CI

GitHub Actions chạy Website PHP lint, Bot Composer validate/lint/tests, quét secret và chạy build/test Neon Function.

## Bảo mật

- PDO prepared statements; password hashing và session regeneration.
- CSRF cho các form ghi dữ liệu.
- Kiểm tra quyền Admin phía server.
- Link ngoài yêu cầu HTTPS ở các form App Store.
- File upload giới hạn MIME, kích thước và tên file ngẫu nhiên.
- Không chứa Bot Token, DATABASE_URL hoặc private signing key trong source.
