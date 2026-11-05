# HENDY DỊCH VỤ MẠNG — Render Web Service

Mã nguồn triển khai tập trung trên **một Render Web Service**. Node.js/Express phục vụ API, Telegram webhook, Telegram Mini App, trang khách hàng và Admin Dashboard. PostgreSQL lưu dữ liệu nghiệp vụ.

## 1. Kiến trúc

- **Render Web Service (Free):** Node.js 22, Express 5, grammY, REST API và toàn bộ giao diện tĩnh.
- **Render PostgreSQL (Free):** tài khoản Telegram, ví, sổ giao dịch, danh mục dịch vụ, ticket CSKH, session và audit log.
- **Telegram Bot:** webhook HTTPS, Reply Keyboard tiếng Việt, các lệnh /start, /services, /balance, /support và /help.
- **Mini App:** xác thực chữ ký Telegram initData ở backend; không tin dữ liệu người dùng gửi từ trình duyệt.
- **Admin:** đăng nhập bằng biến môi trường riêng, quản lý dịch vụ và trạng thái ticket.
- **Không cần Static Site, Cloudflare Worker, Docker hoặc persistent disk.**

## 2. Cấu trúc mã nguồn

```text
.
├── .github/workflows/ci.yml
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
├── package-lock.json
└── render.yaml
```

## 3. Triển khai bằng Render Blueprint

1. Mở [Render Blueprint](https://dashboard.render.com/blueprint/new?repo=https://github.com/hendyngo-servers/hendydichvumang).
2. Chọn repository `hendyngo-servers/hendydichvumang`, nhánh `main`, file `render.yaml`, rồi Apply.
3. Blueprint tạo:
   - Web Service `hendydichvumang`, runtime Node, gói Free, khu vực Singapore.
   - PostgreSQL `hendydichvumang-db`, gói Free, cùng khu vực.
4. Render yêu cầu nhập các giá trị `sync: false`:
   - `BOT_TOKEN`: token BotFather mới (có thể để trống nếu chưa bật bot).
   - `ADMIN_USERNAME`: tên đăng nhập quản trị.
   - `ADMIN_PASSWORD`: mật khẩu ngẫu nhiên dài ít nhất 16 ký tự.
5. Render tự liên kết `DATABASE_URL` và sinh `TELEGRAM_WEBHOOK_SECRET`. Không tạo biến `PORT` thủ công.
6. Chờ deploy ở trạng thái Live. Mở `https://<service-name>.onrender.com/healthz`; cần thấy `status: ok` và `database: ready`.
7. Mở `https://<service-name>.onrender.com/admin` để đăng nhập quản trị.
8. Đặt URL Mini App trong BotFather thành URL Web Service, ví dụ `https://<service-name>.onrender.com/`. Khi có `BOT_TOKEN`, ứng dụng tự đăng ký webhook lúc khởi động.

## 4. Cấu hình thủ công nếu không dùng Blueprint

- Runtime: Node
- Branch: `main`
- Build command: `npm ci --no-audit --no-fund && npm run verify`
- Start command: `npm start`
- Health check path: `/healthz`
- Instance plan: Free
- Tạo Render PostgreSQL cùng khu vực Singapore và gán internal connection string vào `DATABASE_URL`.

Environment variables:
| Key | Bắt buộc | Ghi chú |
|---|---|---|
| `NODE_ENV` | Có | `production` |
| `DATABASE_URL` | Có | Internal URL của Render PostgreSQL |
| `ADMIN_USERNAME` | Có | Tên đăng nhập Admin |
| `ADMIN_PASSWORD` | Có | Tối thiểu 16 ký tự |
| `BOT_TOKEN` | Tùy chọn | Token bot Telegram |
| `TELEGRAM_WEBHOOK_SECRET` | Khi có bot | Có thể dùng giá trị ngẫu nhiên do Render sinh |
| `PUBLIC_APP_URL` | Không | Mặc định dùng `RENDER_EXTERNAL_URL` |
| `ALLOWED_ORIGINS` | Không | Danh sách origin phân tách bằng dấu phẩy |

## 5. Chạy local

Yêu cầu Node.js 22+ và PostgreSQL đang chạy.

```bash
cp .env.example .env
# Sửa DATABASE_URL và các biến môi trường trong .env
npm ci
npm run verify
npm start
```

Mở `http://localhost:10000/`. Đăng nhập Mini App cần mở từ Telegram để có initData hợp lệ. Admin ở `/admin`.

## 6. API chính

| Method | Path | Chức năng |
|---|---|---|
| GET | `/healthz` | Health check ứng dụng và PostgreSQL |
| POST | `/telegram/webhook` | Telegram webhook, xác minh secret header |
| POST | `/api/telegram/auth` | Xác minh Telegram initData và cấp session |
| GET | `/api/config` | Cấu hình giao diện công khai |
| GET | `/api/me` | Hồ sơ và số dư tài khoản |
| GET | `/api/dashboard` | Tổng quan tài khoản |
| GET | `/api/services` | Danh mục dịch vụ công khai |
| GET/POST | `/api/tickets` | Xem/tạo ticket CSKH |
| POST | `/api/admin/login` | Đăng nhập Admin |
| GET | `/api/admin/stats` | Thống kê Admin |
| GET/POST/PATCH | `/api/admin/services` | Quản lý danh mục dịch vụ |
| PATCH | `/api/admin/tickets/:id` | Cập nhật trạng thái và ghi chú ticket |
| POST | `/api/logout` | Thu hồi session |

## 7. Bảo mật và dữ liệu

- Không commit `.env`, Bot Token, API key hoặc mật khẩu.
- Telegram initData được xác minh HMAC-SHA-256 và kiểm tra thời gian, dữ liệu trùng khóa, user id.
- Session là token ngẫu nhiên; chỉ SHA-256 hash được lưu trong PostgreSQL.
- Admin role được xác định ở backend, không nhận role từ client.
- Webhook xác minh `X-Telegram-Bot-Api-Secret-Token`.
- Helmet CSP, CORS allow-list, giới hạn JSON body và rate limit được bật.
- Không có API tự cộng tiền, tự xác nhận chuyển khoản, cấp OTP/Proxy hoặc giao dịch giả. Những tính năng đó chỉ được bật sau khi tích hợp nhà cung cấp thật, xác minh webhook và chống giao dịch trùng.
- Các danh mục dịch vụ ban đầu để trống; Admin tự thêm dịch vụ thật.

## 8. Giới hạn gói Free

Render Free Web Service có thể spin down sau thời gian không có traffic, nên request đầu tiên sau khi ngủ có thể khởi động chậm. Filesystem của Web Service Free là ephemeral; dữ liệu nghiệp vụ phải lưu PostgreSQL.

Render Free PostgreSQL có giới hạn 1 GB, hết hạn sau 30 ngày, không có backup và có thời gian gia hạn 14 ngày sau khi hết hạn trước khi bị xóa. Gói này phù hợp thử nghiệm, không phù hợp lưu ví hoặc dữ liệu khách hàng production lâu dài. Hãy nâng cấp hoặc di chuyển/backup dữ liệu trước hạn.

## 9. Kiểm thử

```bash
npm run verify
```

CI GitHub chạy kiểm tra cú pháp Node.js và bộ test bảo mật trên mỗi lần push. CI thành công không thay thế kiểm thử kết nối PostgreSQL, Telegram Bot và deploy thực tế trong tài khoản Render.
