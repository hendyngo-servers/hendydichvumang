# Rà soát và tái sử dụng HENDY-BOT-CYBERTECH

**Nguồn:** `HENDY-BOT-CYBERTECH-main.zip` do chủ repository cung cấp  
**Đích:** `hendyngo-servers/hendydichvumang`  
**Ngày rà soát:** 05/11/2026

## Kết luận

Không nhập nguyên ứng dụng PHP vào repository hiện tại. ZIP là một hệ thống PHP 8.3 + PDO/PostgreSQL + Ratchet/ReactPHP + Docker/Render; đích hiện là Node.js/Express + grammY, một Aiogram Python service, PostgreSQL và Static Site. Chép nguyên các lớp PHP hoặc schema sẽ tạo hai bộ xử lý nghiệp vụ và hai mô hình dữ liệu cạnh tranh.

Phần đã chuyển đổi và tích hợp là **menu Telegram có cấu hình trong PostgreSQL cho từng bot**. Đây là chức năng phù hợp trực tiếp với hệ thống hiện tại, không cần thay runtime hoặc dữ liệu ví/ticket.

## Phần đã tích hợp

| Thành phần nguồn | Cách dùng trong repository đích |
|---|---|
| `bot_menu_configs` và menu builder trong `BotService.php` | Port sang migration PostgreSQL `005_bot_menu_configs.sql` và helper Node `src/menu-config.js` |
| Nhãn nút, biểu tượng, số cột, lời giới thiệu, persistent/resize | Lưu theo hai khóa `grammy` và `aiogram` |
| Admin API chỉnh menu | `GET /api/admin/bot-menus` và `PATCH /api/admin/bot-menus/:botKey`; bắt buộc session Admin |
| Cấu hình đọc bởi bot | grammY đọc từ PostgreSQL; Aiogram đọc qua API nội bộ `X-Hendy-Service-Key` |
| Admin dashboard | Thêm khu vực “Cấu hình menu Telegram” cho hai bot |

Hành động menu là allowlist cố định: customer, balance, services, support; riêng Aiogram có thêm admin và automation. Người không có quyền quản trị không nhận hai nút quản trị, và đổi nhãn chỉ đổi chữ hiển thị chứ không tạo hành động mới. API kiểm tra dữ liệu đầu vào, giữ nút Trung tâm khách hàng và ghi audit log khi lưu cấu hình.

### File chính

- `migrations/005_bot_menu_configs.sql`
- `src/menu-config.js`
- `src/bot.js`
- `src/server.js`
- `services/aiogram_bot/menu_config.py`
- `services/aiogram_bot/menu_filter.py`
- `services/aiogram_bot/middlewares.py`
- `services/aiogram_bot/routers/*.py`
- `public/admin.html`, `public/admin.js`, `public/styles.css`
- `test/menu-config.test.js`
- `services/aiogram_bot/tests/test_menu_config.py`

## Phần chưa tích hợp và lý do

### Realtime WebSocket

Nguồn có PostgreSQL NOTIFY/LISTEN, event log, ticket xác thực một lần lưu hash và giới hạn origin. Đây là thiết kế tốt để port thành Node WebSocket service cho Admin. Chưa bật trong lượt này vì repository đích chưa có process realtime riêng; cần thêm service Render, xác thực phiên Admin và theo dõi tài nguyên. Không thể khẳng định WSS hoạt động nếu chưa deploy và đo thực tế.

### Video job queue

Nguồn có trạng thái job, giới hạn số job/người dùng, host allowlist và callback HMAC. Nhưng worker phụ thuộc một nhà xử lý video bên ngoài; chưa có hợp đồng/provider production được xác nhận. Không bật worker hoặc nhận URL tùy ý khi chưa cấu hình provider, khóa HMAC và kiểm thử callback thật.

### Payment, VIP, Voucher, Giftcode và ví

Các module PHP này gắn với schema riêng (ví nằm trong `users.balance`, ledger và bảng đơn hàng riêng). HENDY hiện dùng `users`, `wallets`, `tickets`, `service_catalog` và API nghiệp vụ Node. Không copy các migration PHP vì có nguy cơ tạo ví/đơn hàng song song. Muốn chuyển cần mapping dữ liệu, transaction/ledger parity, idempotency, đối soát và test migration riêng.

### Module upload và Web Service Kernel

`ModuleRegistry` chỉ đưa tệp vào vùng chờ, không nên được hiểu là cơ chế cài module an toàn. Upload HTML/SVG cũng cần sandbox/kiểm soát nguồn phục vụ để tránh XSS. `WebServiceKernel` là registry PHP allowlist; đích Node đã có routing/middleware riêng nên không có lợi ích khi chép nguyên mã.

### Đồng bộ secrets Render/GitHub

Manifest biến môi trường và phân loại secret/variable là ý tưởng có thể tái sử dụng. Tuy nhiên, workflow/script trong ZIP có thể tạo, ghi đè hoặc xóa biến từ GitHub/Render. Không nhập script đồng bộ tự động trước khi có chính sách nguồn sự thật, preview/diff, quyền hẹp và quy trình rollback.

## Phát hiện bảo mật quan trọng

File `.env.example` trong ZIP chứa hai chuỗi có hình dạng Bot Token thay vì placeholder. Không đưa file này vào repository đích, log, tài liệu hoặc workflow. Nếu các chuỗi đó là token đã từng dùng, hãy thu hồi chúng trong BotFather và cấp token mới. Toàn bộ token/secret phải nằm trong Render Environment hoặc GitHub Secrets phù hợp, không nằm trong source.

## Kiểm tra nguồn ZIP

- PHP syntax lint trên `app/`, `public/`, `bin/`, `tests/`: không phát hiện lỗi cú pháp.
- `php tests/run.php`: 17 kiểm tra PHP chạy thành công.
- Các kiểm tra này không chứng minh Payment/Video/Realtime đã được kết nối nhà cung cấp hoặc chạy production.

## Kiểm tra repository đích

Migration mới được áp dụng tự động bởi cơ chế migration hiện có khi Node API khởi động. Cần đợi GitHub Actions sau commit mới nhất; sau đó đăng nhập `/admin`, mở “Cấu hình menu Telegram”, lưu cấu hình từng bot và thử lại `/start` trên cả hai bot.

Không cần thêm Bot Token mới để dùng chức năng menu. Aiogram tiếp tục dùng token riêng đã cấu hình, grammY tiếp tục dùng token chính; cả hai đọc chung bảng cấu hình menu trong PostgreSQL.
