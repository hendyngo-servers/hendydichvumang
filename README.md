# HENDY DỊCH VỤ MẠNG — Static Site + API + PostgreSQL

Mã nguồn được triển khai theo kiến trúc tách biệt: **Render Static Site** phân phối giao diện qua CDN, **Render Web Service** chạy API/Telegram webhook/tác vụ tự động, và **PostgreSQL** có thể đặt tại Render hoặc nhà cung cấp bên ngoài.

## 1. Kiến trúc

| Thành phần | Trách nhiệm |
|---|---|
| Render Static Site | Giao diện Trung tâm khách hàng và Admin Dashboard; HTML/CSS/JS thuần, responsive, tiếng Việt; phân phối CDN |
| Render Web Service | Node.js 22, Express 5, REST API, xác thực Telegram Mini App, Telegram webhook, đăng nhập Admin |
| PostgreSQL | Hồ sơ khách hàng, ví, sổ giao dịch, danh mục dịch vụ, ticket, session, audit log, cấu hình/lịch sử automation và kho module chờ duyệt |
| Telegram Bot | grammY là bot chính; aiogram 3.31.0 là bot thử nghiệm Python riêng với token khác; cả hai dùng Node API và PostgreSQL chung |
| Automation scheduler | Dọn session, báo ticket mới, nhắc ticket đang chờ; PostgreSQL advisory lock tránh chạy trùng |
| Aiogram Pilot | Bot Python riêng, Router/Filters/FSM, webhook có secret, PostgreSQL FSM; gọi Node API bằng service key |
| GitHub Actions | Kiểm tra Node.js + PostgreSQL 18, compile/test Python aiogram và build Static Site |

Static Site chỉ chứa tài nguyên frontend, không giữ secret và không kết nối database trực tiếp.

## 2. Cấu trúc chính

```text
.
├── .github/workflows/
│   ├── ci.yml
│   └── render-live-check.yml
├── migrations/
│   ├── 001_init.sql
│   ├── 002_remove_placeholders.sql
│   ├── 003_automations.sql
│   └── 004_aiogram_fsm.sql
├── services/aiogram_bot/
│   ├── main.py
│   ├── config.py
│   ├── api_client.py
│   ├── fsm_storage.py
│   ├── middlewares.py
│   ├── routers/
│   └── tests/
├── docs/AIOGRAM-INTEGRATION.md
├── public/
│   ├── index.html
│   ├── admin.html
│   ├── config.js
│   ├── app.js
│   ├── admin.js
│   └── styles.css
├── scripts/
│   ├── build-static.js
│   ├── render-live-check.js
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

- Render Free Web Service tự spin down sau 15 phút không nhận HTTP/WebSocket traffic; lần truy cập kế tiếp cần chờ service khởi động lại (thường khoảng một phút). Bộ hẹn giờ trong API dừng theo process, nên các automation có thể chạy trễ.
- Render PostgreSQL Free giới hạn 1 GB, tự hết hạn sau 30 ngày và không có backup tự động. Không dùng database Free này để lưu ví, thanh toán hoặc dữ liệu khách hàng production lâu dài.
- Render Cron Job không có gói Free. Muốn chạy Cron trực tiếp trên Render cần chọn gói trả phí; lựa chọn khác là dùng scheduler bên ngoài gọi lệnh bên dưới.

Khi cần chạy định kỳ ổn định, cấu hình Render Cron Job (trả phí) hoặc scheduler bên ngoài để chạy:

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
6. Dùng connection string trực tiếp hoặc pooler ở chế độ session. Không dùng transaction-pooling endpoint cho phiên bản này vì migration và khóa chống chạy automation trùng nhau sử dụng PostgreSQL session advisory lock.
7. Migrations tự động chạy khi API khởi động. Sao lưu database trước khi chạy migration production.

| Biến | Ý nghĩa |
|---|---|
| `DATABASE_URL` | PostgreSQL connection string (bắt buộc) |
| `PGSSLMODE` | `require` mặc định production; `verify-full` để xác minh certificate; `disable` chỉ dùng local/test phù hợp |
| `PGSSL_CA_CERT` | CA PEM nếu dùng `verify-full` |
| `PG_POOL_MAX` | Kích thước pool, mặc định 5, tối đa 20 |

Nếu database nằm trên Render cùng khu vực, có thể dùng internal URL và cấu hình SSL theo yêu cầu của database. Không đưa DATABASE_URL vào Static Site, HTML/JS hoặc trình duyệt.

## 5. Triển khai Render Blueprint

Mở [Render Blueprint](https://dashboard.render.com/blueprint/new?repo=https://github.com/hendyngo-servers/hendydichvumang), chọn repository `hendyngo-servers/hendydichvumang`, branch `main`, file `render.yaml`, rồi Apply.

Blueprint khai báo ba dịch vụ, không tạo database:

- `hendydichvumang-api`: Node.js Web Service, Node 22, health check `/healthz`.
- `hendydichvumang-site`: Static Site, build ra `dist-site`, CDN, route `/admin` đến trang quản trị.
- `hendydichvumang-aiogram-bot`: Python Web Service thử nghiệm, chạy bot aiogram độc lập với grammY.

Sau khi tạo:

1. Trong API Web Service, đặt `DATABASE_URL` là connection string PostgreSQL riêng vừa tạo.
2. Giữ `PGSSLMODE=require` cho PostgreSQL bên ngoài.
3. Đặt `ADMIN_USERNAME` và `ADMIN_PASSWORD` mạnh, tối thiểu 16 ký tự.
4. Đặt `BOT_TOKEN` nếu dùng grammY chính và `ADMIN_TELEGRAM_ID` để nhận thông báo ticket. `BOT_SERVICE_KEY` được Blueprint tự sinh để xác thực dịch vụ aiogram.
5. Đợi Static Site deploy xong, lấy URL dạng `https://<ten-site>.onrender.com`, rồi đặt URL này vào `PUBLIC_APP_URL` của API.
6. Giữ `TELEGRAM_WEBHOOK_SECRET` do Blueprint tự sinh. Khi API có HTTPS và BOT_TOKEN, webhook tự đăng ký vào API URL; Mini App sử dụng URL Static Site.
7. Kiểm tra `https://<api-url>/healthz`, cần có `status: ok` và `database: ready`.
8. Mở `https://<static-site-url>/` và `https://<static-site-url>/admin`. Đặt URL Static Site làm Mini App URL trong BotFather.
9. Nếu bật aiogram pilot, nhập `AIOGRAM_BOT_TOKEN` của một bot thử nghiệm khác grammY; giữ `AIOGRAM_WEBHOOK_SECRET` do Render tự sinh; đặt `PUBLIC_APP_URL` về Static Site. Chỉ deploy Python service sau khi API và migration PostgreSQL đã Live.

Biến API cần khai báo:

| Key | Bắt buộc | Ghi chú |
|---|---|---|
| `NODE_ENV` | Có | `production` |
| `DATABASE_URL` | Có | PostgreSQL ở Render hoặc bên ngoài |
| `PGSSLMODE` | Có | Mặc định `require` |
| `ADMIN_USERNAME` | Có | Tên đăng nhập Admin |
| `ADMIN_PASSWORD` | Có | Tối thiểu 16 ký tự |
| `PUBLIC_APP_URL` | Có | URL HTTPS của Static Site |
| `BOT_TOKEN` | Tùy chọn | Token BotFather của grammY chính; để trống nếu chưa bật bot |
| `BOT_SERVICE_KEY` | Tùy chọn | Blueprint tự sinh; khóa riêng cho dịch vụ aiogram gọi API nội bộ |
| `TELEGRAM_WEBHOOK_SECRET` | Khi bật bot | Blueprint tự sinh |
| `ADMIN_TELEGRAM_ID` | Tùy chọn | Telegram ID nhận thông báo ticket |
| `LEGACY_CUSTOMER_URL` | Tùy chọn | URL HTTPS trang khách hàng PHP cũ, không kèm token/secret |
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

GitHub Actions kiểm tra cú pháp Node.js, chạy test với PostgreSQL 18, cài aiogram 3.31.0, compile Python, chạy unit/integration test FSM và build thử Static Site.

Cập nhật source/Blueprint không đồng nghĩa với việc đã deploy lên Render. Sau khi Apply Blueprint, nhập database URL và secrets, hãy kiểm tra deploy cùng endpoint `/healthz` trước khi đưa hệ thống vào sử dụng. Gói Free có thể ngủ; không dùng database Free hết hạn/lưu trữ hạn chế cho ví hoặc thanh toán production lâu dài.

## Render Live Verification — kiểm tra hai dịch vụ thật

Repository có lệnh xác minh Render bằng API key riêng, không đưa Render API key vào frontend hay mã nguồn. Lệnh chỉ xác nhận khi cả hai service có deployment mới nhất trạng thái LIVE, đúng workspace/repository/nhánh/commit, URL công khai truy cập được, và API kết nối PostgreSQL thành công.

### GitHub Actions định kỳ

Mở Settings → Secrets and variables → Actions và cấu hình:

**Secret (không công khai):**
- RENDER_API_KEY: Render API key, không phải Bot Token.

**Variables:**
- RENDER_LIVE_CHECK_ENABLED = true
- RENDER_WORKSPACE_ID = ID workspace Render.
- RENDER_STATIC_SERVICE_ID = ID Static Site (tùy chọn, script tự dò nếu bỏ trống).
- RENDER_API_SERVICE_ID = ID Web Service (tùy chọn, script tự dò nếu bỏ trống).
- STATIC_SITE_URL = URL HTTPS Static Site.
- API_BASE_URL = URL HTTPS Web Service.
- RENDER_AIOGRAM_CHECK_ENABLED = `true` để bật xác minh bot pilot (mặc định tắt).
- RENDER_AIOGRAM_SERVICE_ID = ID dịch vụ Python (tùy chọn, script tự dò theo tên).
- AIOGRAM_BASE_URL = URL HTTPS dịch vụ Python (tùy chọn, mặc định URL Render).

Workflow .github/workflows/render-live-check.yml chạy mỗi 15 phút và có thể chạy thủ công trong tab Actions. Script tự tìm service hendydichvumang-site và hendydichvumang-api trong workspace (có thể cung cấp ID để cố định), sau đó kiểm tra đúng service, deployment live + commit, Static Site / /admin /config.js, API /healthz và /api/config. Khi bật `RENDER_AIOGRAM_CHECK_ENABLED=true`, script xác minh thêm service `hendydichvumang-aiogram-bot`: deployment Live và `/healthz` phải trả `database=ready`, `webhook=ready`, `framework=aiogram`. Nếu API service chưa được tạo, kiểm tra trả về tên service hiện có và chỉ rõ service còn thiếu.

### Chạy thủ công

Yêu cầu Node.js 22+:

```bash
RENDER_API_KEY=... \
RENDER_WORKSPACE_ID=... \
RENDER_STATIC_SERVICE_ID=... \
RENDER_API_SERVICE_ID=... \
STATIC_SITE_URL=https://<static-site>.onrender.com \
API_BASE_URL=https://<api-service>.onrender.com \
npm run verify:render-live
```

Không truyền giá trị secret vào terminal được ghi lại hoặc commit. Trên Windows PowerShell, đặt biến bằng $env:TEN_BIEN="gia_tri" rồi chạy npm run verify:render-live.

### Giới hạn kiểm tra

Trạng thái deployment live chỉ là một điều kiện. Lệnh còn gọi endpoint thật và kiểm tra PostgreSQL, nhưng không thực hiện giao dịch tiền, không gửi OTP và không giả lập thao tác mua hàng. Chỉ kết quả thành công mới xác nhận hai dịch vụ đang Live và endpoint cơ bản hoạt động tại thời điểm kiểm tra.


## Aiogram — bot Python thử nghiệm

Phân tích source upstream, quyết định module, kiến trúc tích hợp, bảo mật và hướng dẫn thử nghiệm nằm tại [docs/AIOGRAM-INTEGRATION.md](docs/AIOGRAM-INTEGRATION.md). Aiogram chạy thành dịch vụ Python riêng với token khác grammY; Node.js API và PostgreSQL vẫn quản lý dữ liệu nghiệp vụ. Không dùng chung Bot Token giữa hai framework.

Biến cần cấu hình cho dịch vụ Python: `AIOGRAM_BOT_TOKEN` (token bot thử nghiệm riêng), `AIOGRAM_WEBHOOK_SECRET` (Render tự sinh), `HENDY_API_BASE_URL`, `HENDY_BOT_SERVICE_KEY`, `DATABASE_URL`, `PGSSLMODE`, `PUBLIC_APP_URL` và `AIOGRAM_FSM_TTL_HOURS`. Blueprint liên kết API key và PostgreSQL từ API Web Service; không đưa secrets vào frontend.




## 10. Kho module có kiểm duyệt (tích hợp chọn lọc từ HENDY-BOT-CYBERTECH)

Hệ thống hỗ trợ lưu trữ artifact/module trong PostgreSQL để quản trị viên gửi tệp, kiểm tra checksum SHA-256 và duyệt trước khi đưa vào quy trình phát hành. Đây là kho tệp không thực thi mã.

- Chỉ nhận JSON, Markdown, TXT và CSS UTF-8, tối đa 1 MB/tệp.
- Từ chối PHP, JavaScript, HTML, SVG, archive và mọi MIME không nằm trong allowlist.
- Tệp được lưu dạng BYTEA trong PostgreSQL với trạng thái pending_review; API không đưa nội dung tệp ra frontend công khai.
- Chỉ quản trị viên đã đăng nhập mới được liệt kê, tải nội dung hoặc duyệt.
- Duyệt chỉ đổi trạng thái thành approved hoặc rejected; không có chức năng chạy file, ghi đè source code hoặc tự deploy.
- Mọi thao tác upload/review đều có audit log và SHA-256 để đối chiếu.

API:
| Method | Path | Chức năng |
|---|---|---|
| GET | /api/admin/modules | Danh sách artifact |
| POST | /api/admin/modules/:moduleKey | Upload raw body application/octet-stream; tên file ở X-File-Name, MIME ở X-Artifact-Mime |
| PATCH | /api/admin/modules/:id/review | Duyệt hoặc từ chối artifact đang chờ |
| GET | /api/admin/modules/:id/content | Tải artifact nội bộ với quyền Admin |

Migration 006_module_artifacts.sql tạo bảng lưu trữ. Đây là phần có thể tái sử dụng an toàn từ ý tưởng ModuleRegistry của archive. Không sao chép PHP runtime hoặc chạy tệp upload trong Node/Python. Muốn phát hành code phải tạo pull request, qua review và CI rồi mới deploy.

### Những phần archive chưa được nhập nguyên xi

- PHP BotService.php / ApiController.php: không chép vào Node/grammY/Aiogram vì sẽ tạo backend nghiệp vụ thứ hai.
- Schema ví, đơn hàng, giao dịch D1/MySQL: không nhập vào PostgreSQL hiện tại và không dual-write.
- Ratchet WebSocket: ý tưởng event log + PostgreSQL NOTIFY có thể port riêng; không mang nguyên PHP process vào Render Node service.
- App Store / Google Play / Microsoft Store connectors: chưa có credential/tài khoản nhà phát hành và một số adapter chỉ là khung; không bật tính năng publish.
- Queue/Vietsub/payment: chỉ tích hợp khi có provider thật, chữ ký webhook, idempotency, retry và đối soát.
