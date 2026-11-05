# HENDY DỊCH VỤ MẠNG — Render + PostgreSQL

Mã nguồn thay thế cho phiên bản Cloudflare cũ. Ứng dụng gồm Telegram Bot (grammY), REST API, Telegram Mini App/trung tâm khách hàng, trang quản trị và PostgreSQL.

## Thành phần

- Node.js 22 + Express 5: một Web Service duy nhất, phù hợp triển khai thử nghiệm trên Render Free.
- PostgreSQL: lưu người dùng, ví, giao dịch ví, danh mục dịch vụ, ticket, phiên đăng nhập và audit log.
- Telegram Bot: Reply Keyboard tiếng Việt, lệnh `/start`, `/help`, `/services`, `/balance`, `/support`, webhook có secret header.
- Mini App: xác minh `Telegram.WebApp.initData` bằng HMAC-SHA-256 phía máy chủ; không tin `initDataUnsafe` để xác thực.
- Admin: quản lý danh mục dịch vụ và trạng thái ticket. Đăng nhập bằng thông tin cấu hình riêng trên Render.

## Cấu trúc

```text
.
├── migrations/001_init.sql
├── public/
│   ├── index.html
│   ├── app.js
│   ├── admin.html
│   ├── admin.js
│   └── styles.css
├── src/
│   ├── bot.js
│   ├── config.js
│   ├── db.js
│   ├── security.js
│   ├── server.js
│   └── set-webhook.js
├── test/security.test.js
├── .env.example
├── package.json
└── render.yaml
```

## Triển khai bằng Render Blueprint

1. Mở <https://dashboard.render.com/blueprint/new?repo=https://github.com/hendyngo-servers/hendydichvumang>.
2. Chọn repository `hendyngo-servers/hendydichvumang`, nhánh `main`, sau đó Apply.
3. Blueprint tạo một **Web Service Node.js** `hendydichvumang` gói Free và một PostgreSQL `hendydichvumang-db` gói Free cùng khu vực Singapore. Web Service phục vụ cả API, trang khách hàng, Admin và Telegram webhook; không tạo Static Site riêng.
4. Khi Render yêu cầu các biến có `sync: false`, nhập:
   - `BOT_TOKEN`: token BotFather mới. Không gửi token qua GitHub/README/chat.
   - `ADMIN_USERNAME`: tên đăng nhập quản trị khác tên mặc định nếu có thể.
   - `ADMIN_PASSWORD`: mật khẩu dài, ngẫu nhiên, riêng cho quản trị.
5. `DATABASE_URL` được liên kết tự động từ PostgreSQL. `TELEGRAM_WEBHOOK_SECRET` do Render tự sinh; session dùng token ngẫu nhiên và chỉ lưu hash trong PostgreSQL.
6. Chờ deploy thành công, mở `https://<ten-service>.onrender.com/healthz`. Kết quả mong đợi: `status: ok`, `database: ready`.
7. Cập nhật Menu Button/Mini App URL trong BotFather thành `https://<ten-service>.onrender.com/`.
8. Đặt Telegram webhook. Dùng local hoặc Shell trên máy cá nhân với các biến môi trường lấy từ Render (không lưu vào Git):

```bash
npm install
BOT_TOKEN="..." TELEGRAM_WEBHOOK_SECRET="..." PUBLIC_APP_URL="https://<ten-service>.onrender.com" npm run set-webhook
```

Webhook URL là `https://<ten-service>.onrender.com/telegram/webhook`. Không bật long polling đồng thời với webhook.

## Cấu hình local

```bash
cp .env.example .env
# Điền DATABASE_URL PostgreSQL, BOT_TOKEN, TELEGRAM_WEBHOOK_SECRET,
# ADMIN_USERNAME và ADMIN_PASSWORD.
npm install
npm run check
npm test
npm start
```

Mở `http://localhost:10000/`. Đăng nhập Mini App cần mở qua Telegram vì API yêu cầu initData hợp lệ. Trang quản trị ở `/admin`.

## API

| Method | Path | Mô tả |
|---|---|---|
| GET | `/healthz` | Kiểm tra ứng dụng và PostgreSQL |
| POST | `/telegram/webhook` | Telegram webhook, kiểm tra secret header |
| POST | `/api/telegram/auth` | Xác thực Telegram initData, cấp session |
| GET | `/api/config` | Cấu hình giao diện công khai |
| GET | `/api/me` | Hồ sơ tài khoản đã xác thực |
| GET | `/api/dashboard` | Số dư, ticket gần đây, thống kê dịch vụ |
| GET | `/api/services` | Danh mục dịch vụ đang mở |
| GET/POST | `/api/tickets` | Xem/tạo ticket khách hàng; Admin xem tất cả |
| POST | `/api/admin/login` | Đăng nhập Admin |
| GET | `/api/admin/stats` | Thống kê quản trị |
| GET/POST/PATCH | `/api/admin/services` | Quản lý danh mục dịch vụ |
| PATCH | `/api/admin/tickets/:id` | Cập nhật trạng thái và ghi chú ticket |
| POST | `/api/logout` | Thu hồi session hiện tại |

## Bảo mật

- Không commit `.env`, Bot Token, API key hoặc mật khẩu quản trị.
- Telegram initData được xác minh bằng HMAC-SHA-256, kiểm tra `auth_date` và chữ ký; client không thể tự gán số dư hoặc role.
- Session token ngẫu nhiên chỉ lưu SHA-256 trong PostgreSQL; quyền Admin được kiểm tra ở máy chủ.
- Webhook yêu cầu `X-Telegram-Bot-Api-Secret-Token` trùng khớp secret đã đăng ký với Telegram.
- API giới hạn kích thước JSON, dùng Helmet, CORS allow-list và rate limit.
- Không có thao tác cộng tiền tự động. Không cộng số dư từ ảnh biên lai hoặc việc người dùng mở QR. Muốn tự động nạp tiền cần tích hợp nhà cung cấp thanh toán thật, xác minh chữ ký webhook và chống giao dịch trùng.
- OTP, Proxy, VIP, thuê Tool và cấp phát Code chưa kết nối API nhà cung cấp. Danh mục hiển thị là catalog tư vấn, không giả lập việc hoàn tất dịch vụ.

## Giới hạn Render Free — cần đọc trước khi dùng dữ liệu thật

Render Free Web Service có thể spin down sau 15 phút không có traffic; request đầu tiên sau đó có thể mất khoảng một phút để khởi động. Filesystem của dịch vụ Free là ephemeral, do đó ứng dụng không lưu dữ liệu nghiệp vụ trên đĩa cục bộ.

Render Free PostgreSQL chỉ có 1 GB, hết hạn sau 30 ngày kể từ ngày tạo, không có backup và không có connection pooling được quản lý. Sau khi hết hạn, có thời gian gia hạn 14 ngày để nâng cấp trước khi dữ liệu bị xóa. Đây là môi trường demo/kiểm thử, không phù hợp làm ví hoặc dữ liệu khách hàng production lâu dài. Cần nâng cấp PostgreSQL hoặc xuất backup/di chuyển dữ liệu trước ngày hết hạn.

## Kiểm thử

```bash
npm run verify
```

Bộ test hiện bao gồm xác minh HMAC Telegram, dữ liệu bị sửa, auth_date hết hạn/tương lai, tạo/hash session và webhook secret. Kiểm thử tích hợp cần một PostgreSQL riêng và Bot Token thử nghiệm; chưa thể kiểm chứng thay cho việc chạy trên Render thật.
