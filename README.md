# HENDY DỊCH VỤ MẠNG — Static Site + API + PostgreSQL

Mã nguồn được triển khai theo kiến trúc tách biệt: **Render Static Site** phân phối giao diện qua CDN, **Render Web Service** chạy API/Telegram webhook/tác vụ tự động, và **PostgreSQL** có thể đặt tại Render hoặc nhà cung cấp bên ngoài.

## 1. Kiến trúc

| Thành phần | Trách nhiệm |
|---|---|
| Render Static Site | Giao diện Trung tâm khách hàng và Admin Dashboard; HTML/CSS/JS thuần, responsive, tiếng Việt; phân phối CDN |
| Render Web Service | Node.js 22, Express 5, REST API, xác thực Telegram Mini App, Telegram webhook, đăng nhập Admin |
| PostgreSQL | Hồ sơ khách hàng, ví, sổ giao dịch, danh mục dịch vụ, ticket, session, audit log, cấu hình/lịch sử automation |
| Telegram Bot | Menu tiếng Việt, lệnh /start, /services, /balance, /support, /help; thông báo ticket cho Admin |
| Automation scheduler | Dọn session, báo ticket mới, nhắc ticket đang chờ; PostgreSQL advisory lock tránh chạy trùng |
| GitHub Actions | Kiểm tra cú pháp, chạy test với PostgreSQL 18 và build thử Static Site |

Static Site chỉ chứa tài nguyên frontend, không giữ secret và không kết nối database trực tiếp.

## 2. Cấu trúc chính

```text
.
├── .github/workflows/ci.yml
├── migrations/
│   ├── 001_init.sql
│   ├── 002_remove_placeholders.sql
│   └── 003_automations.sql
├── public/
│   ├── index.html
│   ├── admin.html
│   ├── config.js
│   ├── app.js
│   ├── admin.js
│   └── styles.css
├── scripts/
│   ├── build-static.js
│   └── run-automations.js
├── src/
│   ├── automation.js
│   ├── bot.js
│   ├── config.js
│   ├── db.js
│   ├── security.js
│   ├── server.js
│   └── set-webhook.js
├── test/
├── .env.example
├── package.json
└── render.yaml
```

## 3. Tự động hóa

Trong Admin Dashboard, mục **Điều phối tác vụ hệ thống** hỗ trợ bật/tắt từng tác vụ, chọn chu kỳ chạy, xem lần chạy gần nhất/trạng thái và bấm **Chạy ngay**.

| Tác vụ | Hành vi |
|---|---|
| Dọn phiên đăng nhập | Xóa session hết hạn và lịch sử automation cũ hơn 180 ngày |
| Thông báo ticket mới | Gửi ticket mới đến Telegram Admin khi có BOT_TOKEN và ADMIN_TELEGRAM_ID |
| Nhắc ticket chờ | Nhắc ticket open/processing đã tồn tại hơn 60 phút; nhắc lại sau mỗi 6 giờ |

Các lần chạy được ghi vào `automation_runs`; cấu hình nằm trong `automation_settings`. Mỗi tác vụ có PostgreSQL advisory lock để tránh hai tiến trình thực thi cùng lúc. Chu kỳ có thể chọn từ 1 phút đến 7 ngày.

Không tự tạo số dư, đơn hàng hoặc giao dịch giả. Việc cộng tiền, hoàn tiền, xác nhận chuyển khoản, cấp OTP/Proxy hay thuê Tool chỉ được triển khai khi có API nhà cung cấp thật, xác minh webhook, chống giao dịch trùng và đối soát.

### Lưu ý gói Free

Web Service Free có thể ngủ khi không có truy cập. Bộ hẹn giờ trong API sẽ tạm dừng trong thời gian service ngủ, nên tác vụ có thể chạy trễ; không dùng Free để cam kết SLA hoặc thông báo đúng phút.

Khi cần chạy định kỳ ổn định, cấu hình Render Cron Job hoặc scheduler bên ngoài để chạy:

```bash
npm run automation:run
```

Lệnh dùng cùng biến môi trường và PostgreSQL, thực hiện các tác vụ đến hạn rồi thoát. Cron của Render dùng UTC; ví dụ mỗi phút là `* * * * *`. Có thể chuyển sang service Always-On phù hợp nếu cần worker liên tục.

## 4. PostgreSQL tại Render hoặc bên ngoài

Blueprint **không tạo database mặc định** và không tự kết nối vào database hiện có. API nhận kết nối qua `DATABASE_URL` (hoặc `EXTERNAL_DATABASE_URL`).

1. Tạo database PostgreSQL riêng tại Render, Neon, Supabase, Railway hoặc nhà cung cấp tương thích.
2. Tạo database/user riêng cho HENDY; tránh dùng superuser hoặc database nghiệp vụ khác.
3. Đặt connection string trong biến môi trường bí mật `DATABASE_URL` của API Web Service.
4. Với PostgreSQL truy cập từ Internet, bật TLS: `PGSSLMODE=require`. Khi cần xác minh đầy đủ CA/hostname, dùng `PGSSLMODE=verify-full` cùng `PGSSL_CA_CERT`.
5. Đặt `PG_POOL_MAX=5` hoặc thấp hơn nếu nhà cung cấp giới hạn kết nối.
6. Migrations tự động chạy khi API khởi động. Sao lưu database trước khi chạy migration production.

| Biến | Ý nghĩa |
|---|---|
| `DATABASE_URL` | PostgreSQL connection string (bắt buộc) |
| `PGSSLMODE` | `require` mặc định production; `verify-full` để xác minh certificate; `disable` chỉ dùng local/test phù hợp |
| `PGSSL_CA_CERT` | CA PEM nếu dùng `verify-full` |
| `PG_POOL_MAX` | Kích thước pool, mặc định 5, tối đa 20 |

Nếu database nằm trên Render cùng khu vực, có thể dùng internal URL và cấu hình SSL theo yêu cầu của database. Không đưa DATABASE_URL vào Static Site, HTML/JS hoặc trình duyệt.

## 5. Triển khai Render Blueprint

Mở [Render Blueprint](https://dashboard.render.com/blueprint/new?repo=https://github.com/hendyngo-servers/hendydichvumang), chọn repository `hendyngo-servers/hendydichvumang`, branch `main`, file `render.yaml`, rồi Apply.

Blueprint tạo hai dịch vụ, không tạo database:

- `hendydichvumang-api`: Node.js Web Service, Node 22, health check `/healthz`.
- `hendydichvumang-site`: Static Site, build ra `dist-site`, CDN, route `/admin` đến trang quản trị.

Sau khi tạo:

1. Trong API Web Service, đặt `DATABASE_URL` là connection string PostgreSQL riêng vừa tạo.
2. Giữ `PGSSLMODE=require` cho PostgreSQL bên ngoài.
3. Đặt `ADMIN_USERNAME` và `ADMIN_PASSWORD` mạnh, tối thiểu 16 ký tự.
4. Đặt `BOT_TOKEN` nếu dùng bot và `ADMIN_TELEGRAM_ID` để nhận thông báo ticket.
5. Đợi Static Site deploy xong, lấy URL dạng `https://<ten-site>.onrender.com`, rồi đặt URL này vào `PUBLIC_APP_URL` của API.
6. Giữ `TELEGRAM_WEBHOOK_SECRET` do Blueprint tự sinh. Khi API có HTTPS và BOT_TOKEN, webhook tự đăng ký vào API URL; Mini App sử dụng URL Static Site.
7. Kiểm tra `https://<api-url>/healthz`, cần có `status: ok` và `database: ready`.
8. Mở `https://<static-site-url>/` và `https://<static-site-url>/admin`. Đặt URL Static Site làm Mini App URL trong BotFather.

Biến API cần khai báo:

| Key | Bắt buộc | Ghi chú |
|---|---|---|
| `NODE_ENV` | Có | `production` |
| `DATABASE_URL` | Có | PostgreSQL ở Render hoặc bên ngoài |
| `PGSSLMODE` | Có | Mặc định `require` |
| `ADMIN_USERNAME` | Có | Tên đăng nhập Admin |
| `ADMIN_PASSWORD` | Có | Tối thiểu 16 ký tự |
| `PUBLIC_APP_URL` | Có | URL HTTPS của Static Site |
| `BOT_TOKEN` | Tùy chọn | Token BotFather; để trống nếu chưa bật bot |
| `TELEGRAM_WEBHOOK_SECRET` | Khi bật bot | Blueprint tự sinh |
| `ADMIN_TELEGRAM_ID` | Tùy chọn | Telegram ID nhận thông báo ticket |
| `PGSSL_CA_CERT` | Tùy chọn | CA PEM nếu dùng `verify-full` |
| `ALLOWED_ORIGINS` | Tùy chọn | Origin bổ sung, phân tách bằng dấu phẩy |

## 6. Chạy local

Yêu cầu Node.js 22+ và PostgreSQL.

```bash
cp .env.example .env
# Sửa DATABASE_URL và các biến trong .env
npm ci
npm run verify
npm start
```

Mở `http://localhost:10000/`. Đăng nhập Mini App yêu cầu mở từ Telegram để có initData hợp lệ. Admin ở `/admin`.

Build Static Site riêng:

```bash
STATIC_API_BASE_URL=https://<api-url> npm run build:static
```

Thư mục publish là `dist-site`. File `config.js` được sinh lúc build chỉ chứa public API origin, không chứa token hoặc database credentials.

## 7. Bảo mật

- Không commit `.env`, Bot Token, API key, mật khẩu hoặc PostgreSQL URL.
- Telegram initData được xác minh HMAC-SHA-256 và kiểm tra thời hạn, duplicate query key ở server.
- Session token ngẫu nhiên; chỉ SHA-256 hash được lưu trong PostgreSQL.
- Admin role được kiểm tra ở backend.
- Telegram webhook xác minh secret header.
- Helmet CSP, CORS allow-list, rate limit, JSON body limit và audit log được bật.
- Nếu một Bot Token thật từng được commit hoặc chia sẻ, hãy thu hồi qua BotFather, tạo token mới và chỉ lưu token mới trong Render Environment.

## 8. API chính

| Method | Path | Chức năng |
|---|---|---|
| GET | `/healthz` | Kiểm tra API và PostgreSQL |
| POST | `/telegram/webhook` | Telegram webhook |
| POST | `/api/telegram/auth` | Xác thực Telegram Mini App |
| GET | `/api/config` | Cấu hình giao diện công khai |
| GET | `/api/me` | Hồ sơ và số lượng ticket |
| GET | `/api/dashboard` | Tổng quan tài khoản |
| GET | `/api/services` | Danh mục dịch vụ công khai |
| GET/POST | `/api/tickets` | Xem/tạo ticket |
| POST | `/api/admin/login` | Đăng nhập Admin |
| GET | `/api/admin/stats` | Thống kê Admin |
| GET/POST/PATCH | `/api/admin/services` | Quản lý danh mục |
| PATCH | `/api/admin/tickets/:id` | Cập nhật trạng thái/ghi chú ticket |
| GET/PATCH | `/api/admin/automations` | Xem/cấu hình tác vụ |
| POST | `/api/admin/automations/:code/run` | Chạy tác vụ ngay |
| POST | `/api/logout` | Thu hồi session |

## 9. Kiểm tra và triển khai

GitHub Actions kiểm tra cú pháp Node.js, chạy test với PostgreSQL 18 và build thử Static Site.

Cập nhật source/Blueprint không đồng nghĩa với việc đã deploy lên Render. Sau khi Apply Blueprint, nhập database URL và secrets, hãy kiểm tra deploy cùng endpoint `/healthz` trước khi đưa hệ thống vào sử dụng. Gói Free có thể ngủ; không dùng database Free hết hạn/lưu trữ hạn chế cho ví hoặc thanh toán production lâu dài.
