# HENDY DỊCH VỤ MẠNG

Một Telegram Bot duy nhất chạy trên Cloudflare Workers và Mini App tiếng Việt chạy trên Cloudflare Pages.

## Sơ đồ hệ thống

```text
Người dùng Telegram
       |
       v
Cloudflare Workers - Telegram Bot Backend
  - Telegram webhook và Telegram Bot API
  - Tin nhắn HTML, Inline Keyboard
  - Menu OTP / Proxy / VIP / Code / Thuê Tool / Minigame Live
  - Điều hướng Mini App và liên kết ngoài
  - Xác thực Telegram WebApp initData bằng HMAC
       |
       +----> Cloudflare Pages - Mini App
                - Giao diện tối ưu mobile
                - Hồ sơ Telegram và bảng thống kê
                - Khu vực tạo ticket hỗ trợ
                - Modal đăng nhập Tool Live / CAPTCHA placeholder
```

## Cấu trúc thư mục

```text
hendydichvumang/
├── package.json
├── .gitignore
├── README.md
├── apps/
│   ├── telegram-worker/
│   │   ├── wrangler.toml
│   │   ├── migrations/
│   │   │   └── 0001_initial_schema.sql  # D1: users, wallets, orders, OTP, Proxy, VIP, tickets
│   │   ├── src/
│   │   │   └── index.js                # Webhook, API, D1/KV, Cron
│   │   └── scripts/
│   │       └── set-webhook.mjs
│   └── mini-app/
│       ├── index.html
│       ├── styles.css
│       └── app.js
├── scripts/
│   ├── bootstrap-cloudflare.mjs        # Tự tạo Worker subdomain, Pages, D1, KV
│   ├── render-cloudflare-config.mjs    # Gắn IDs tài nguyên vào Wrangler
│   ├── generate-webhook-secret.mjs
│   └── sync-github-secrets.mjs
└── .github/workflows/                  # CI/CD triển khai tự động
```

## API Worker

| Method | Endpoint | Chức năng |
|---|---|---|
| GET | /health | Kiểm tra Worker |
| POST | /telegram/webhook | Nhận Telegram Update, yêu cầu secret header |
| POST | /api/telegram/auth | Xác minh Telegram WebApp initData |
| POST | /api/payment/config | Trả thông tin STK/QR sau khi xác thực Mini App |
| GET | /api/services | Danh mục dịch vụ (D1, cache KV 60 giây) |
| POST | /api/dashboard | Hồ sơ, số dư ví và thống kê đọc từ D1 |
| POST | /api/tickets | Lưu ticket mới vào D1 sau khi xác thực Telegram |

D1 migration khởi tạo users, wallet_accounts, wallet_ledger, topup_orders, payment_events, orders, proxy_leases, otp_orders, vip_memberships, vip_checkins, tickets và service_catalog. API xác thực tự đồng bộ người dùng vào D1, Dashboard đọc ví/đơn hàng/ticket, Ticket được lưu thực tế vào D1. KV cache danh mục dịch vụ trong 60 giây.

## Bảo mật Telegram Mini App (initData)

Mini App chỉ gửi `Telegram.WebApp.initData` nguyên bản tới Worker. Không dùng `initDataUnsafe` để xác thực và không gửi Telegram Bot Token xuống trình duyệt.

Quy trình HMAC-SHA-256 chính xác theo Telegram:

1. Lấy `hash` và giữ lại để so sánh.
2. Tạo `data_check_string` từ toàn bộ cặp `key=value` còn lại (loại `hash`), sắp xếp theo tên key tăng dần và nối bằng ký tự LF (`\\n`).
3. Tạo khóa bí mật theo Telegram: `secret_key = HMAC_SHA256(data=BOT_TOKEN, key="WebAppData")`. Với Web Crypto API, truyền khóa `WebAppData` vào `importKey`, rồi ký dữ liệu là Bot Token.
4. Tính `calculated_hash = HMAC_SHA256(key=secret_key, message=data_check_string)`, biểu diễn bằng hex chữ thường.
5. So sánh hash bằng phép so sánh constant-time; từ chối nếu chữ ký sai, thiếu `auth_date`, ngày quá xa trong tương lai hoặc dữ liệu quá hạn.

**Lưu ý:** `initData` được ký, không được mã hóa. HTTPS bảo vệ đường truyền; HMAC bảo vệ tính toàn vẹn và nguồn gốc dữ liệu. Worker giữ Bot Token dưới dạng Secret. Không tin Telegram ID, username, số dư hoặc role do client tự gửi.

Sau bước xác thực, nên nâng cấp thành mô hình session: endpoint đăng nhập xác thực `initData` một lần, cấp bearer token ngẫu nhiên ngắn hạn; chỉ lưu SHA-256 của token trong D1 cùng `user_id`, `expires_at`, `revoked_at`. API nhạy cảm chỉ đọc người dùng từ session đã xác thực. Dùng D1 unique constraint với fingerprint của `initData` để chặn dùng lại cùng dữ liệu đăng nhập; không dùng KV làm khóa chống replay/ghi sổ giao dịch vì KV có eventual consistency. Không ghi `initData`, token phiên, Bot Token hoặc thông tin thanh toán vào log.

Bộ kiểm thử có vector mẫu xác thực được công bố trong tài liệu Telegram để tránh việc test và code cùng mắc một lỗi đảo chiều HMAC.

## Cấu hình bí mật: chỉ cần 3 giá trị gốc

Repository có file mẫu `.env.example`. Tạo file local `.env` từ mẫu và chỉ điền đúng 3 giá trị:

```env
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_ACCOUNT_ID=
BOT_TOKEN=
```

**Không commit file `.env`**. File này đã được `.gitignore` loại trừ; chỉ `.env.example` không chứa dữ liệu bí mật được lưu trong Git.

### Đồng bộ 3 giá trị lên GitHub Secrets một lần

Cài GitHub CLI (`gh`), đăng nhập tài khoản có quyền cập nhật Actions Secrets của repository, sau đó chạy ở thư mục gốc:

```bash
gh auth login
npm run secrets:sync
```

Script đọc `.env` và gửi từng giá trị qua stdin của `gh secret set`, không đưa giá trị bí mật vào command line hoặc in ra log. Ba GitHub Secrets được tạo/cập nhật tự động:

- `CLOUDFLARE_API_TOKEN`
- `CLOUDFLARE_ACCOUNT_ID`
- `BOT_TOKEN`

### Thông tin ngân hàng/QR tùy chọn

Có thể cấu hình trong chính file `.env` và đồng bộ như các bí mật khác:

```env
PAYMENT_BANK_CODE=
PAYMENT_BANK_NAME=
PAYMENT_ACCOUNT_NUMBER=
PAYMENT_ACCOUNT_NAME=
PAYMENT_QR_URL=
PAYMENT_TRANSFER_NOTE_PREFIX=HENDY
SUPPORT_URL=
```

Các trường ngân hàng là tùy chọn. Khi có ngân hàng, STK và tên chủ tài khoản, bot `/nap` và Mini App sẽ hiển thị thông tin nhận tiền. Nếu có `PAYMENT_QR_URL`, Worker dùng URL HTTPS đó; nếu không, Worker tạo URL ảnh VietQR từ `PAYMENT_BANK_CODE`, số tài khoản và nội dung chuyển khoản. QR được tạo để điền sẵn thông tin chuyển khoản, không xác nhận việc tiền đã vào tài khoản. Tài liệu SePay mô tả luồng QR cần kết hợp webhook để xác nhận giao dịch: <https://developer.sepay.vn/vi/sepay-webhooks/tao-qr-va-form-thanh-toan>.

**Chưa bật tự động cộng tiền:** D1 đã có bảng ví, wallet ledger, đơn nạp và payment events, nhưng chưa có endpoint webhook ngân hàng, xác thực chữ ký nhà cung cấp hoặc logic đối soát giao dịch. Không được cộng số dư chỉ vì người dùng mở QR hoặc gửi ảnh biên lai. Muốn tự động xác nhận cần đăng ký SePay hoặc API ngân hàng, lấy API key/HMAC secret từ nhà cung cấp rồi triển khai webhook + D1 transaction chống giao dịch trùng. Những khóa đó không tự sinh được và chỉ nên thêm sau khi kết nối nhà cung cấp.

### Những cấu hình hệ thống tự tạo

Không cần tự tạo GitHub Variables hay GitHub Secret thứ tư:

- `WEBHOOK_SECRET`: sinh ổn định bằng HMAC-SHA-256 từ Bot Token ở mỗi lần deploy; được đưa vào Cloudflare Worker Secret và dùng để đăng ký Telegram webhook.
- `WORKER_API_URL`: tự lấy Workers account subdomain từ Cloudflare API và ghép với tên Worker.
- `BOT_WEBHOOK_URL`: tự ghép `WORKER_API_URL/telegram/webhook`.
- `MINI_APP_URL`: tự suy ra từ Pages project `hendydichvumang`.
- Pages project: tự tạo nếu chưa tồn tại.

Workflow cần Cloudflare API Token có quyền **Workers Scripts Read + Write**, **Pages Read + Write**, **D1 Read + Write** và **Workers KV Storage Read + Write**. Nên giới hạn token trong đúng Cloudflare account đang triển khai. <https://developers.cloudflare.com/workers/ci-cd/external-cicd/github-actions/>

### Tự động tạo tài nguyên và triển khai

Workflow Cloudflare duy nhất chạy tuần tự: tạo/tìm workers.dev subdomain, Pages project, D1 database và KV namespace → render ID vào file Wrangler sinh tự động → áp dụng D1 migration ở remote → deploy Worker → kiểm tra health → đặt Worker Secrets → đăng ký webhook và lệnh Telegram → chèn Worker API URL vào Mini App → deploy Pages. Cách chạy một luồng giúp tránh hai workflow Worker/Pages hủy lượt deploy của nhau.

Cron Triggers được khai báo trong Wrangler: mỗi 5 phút đánh dấu đơn OTP/nạp tiền quá hạn và Proxy tới hạn; lúc 00:00 giờ Việt Nam (17:00 UTC) cập nhật các gói VIP hết hạn. Proxy hết hạn được đánh dấu `revoke_pending`; việc thu hồi thật từ nhà cung cấp cần tích hợp API nhà cung cấp trước khi bật dịch vụ Proxy.

### Khi thêm cấu hình mới trong tương lai

Bổ sung tên secret/biến vào workflow bootstrap hoặc khai báo binding trong `wrangler.toml`; workflow sẽ cập nhật trên Cloudflare khi deploy. Chỉ giữ trong GitHub Secrets những giá trị cần bảo mật; các URL/ID công khai có thể được suy ra hoặc ghi trong cấu hình không nhạy cảm.

## Triển khai Cloudflare

1. Tạo file `.env` từ `.env.example`, điền ba cấu hình gốc và các trường thanh toán/CSKH tùy chọn.
2. Cài GitHub CLI, chạy `gh auth login`, sau đó chạy `npm run secrets:sync` để đồng bộ Secrets lên GitHub.
3. Tạo Cloudflare API Token có quyền Workers Scripts Read/Write, Pages Read/Write, D1 Read/Write và Workers KV Storage Read/Write.
4. Push lên nhánh `main` hoặc chạy workflow trong GitHub Actions.
5. Workflow tự tạo/tìm workers.dev subdomain, Pages project, D1 database và KV namespace; sinh file Wrangler riêng; áp dụng migration D1; deploy Worker; cài Worker Secrets; đăng ký webhook Telegram và danh sách lệnh.
6. Workflow Pages tự lấy URL Worker và deploy Mini App. Không cần tự điền D1 ID, KV ID, Worker URL hay webhook URL.
7. Kiểm tra `https://<worker>.workers.dev/health`; hệ thống phải trả HTTP 200 và `components.database = ready`.

Không commit `.env` hoặc file cấu hình sinh tự động. `apps/telegram-worker/wrangler.toml` là mẫu có placeholder; `wrangler.generated.toml` chỉ được tạo trong lúc bootstrap và được Git bỏ qua.

## Chạy và kiểm tra local

Yêu cầu Node.js 22+:

```bash
npm install
npm run check
npm test
```

Chạy local (cần `.env` có `CLOUDFLARE_API_TOKEN` và `CLOUDFLARE_ACCOUNT_ID`):

```bash
npm run cloudflare:bootstrap
npm run db:migrate:local
npm run local:prepare
npm run dev:worker
```

Lệnh bootstrap tạo `.cloudflare-outputs.json` và `wrangler.generated.toml`; cả hai đều được Git bỏ qua. Khuyến nghị dùng GitHub Actions để deploy production. Lệnh deploy thủ công yêu cầu chạy bootstrap trước, sau đó `npm run db:migrate`, `npm run deploy:worker` và `npm run deploy:pages`.

## Bảo vệ Bot Token và API Key

- `BOT_TOKEN` không được đặt trong mã nguồn, `wrangler.toml`, HTML, JavaScript của Mini App hoặc biến `[vars]`. Production chỉ nhận token bằng `wrangler secret put BOT_TOKEN`, còn Worker đọc qua `env.BOT_TOKEN`.
- GitHub Actions không còn truyền Bot Token và Cloudflare API Token ở cấp toàn bộ job. Mỗi credential chỉ được cấp cho bước thực sự cần dùng; bước cài package, kiểm thử và deploy Pages không nhận Bot Token.
- `.env` và `.dev.vars` được Git bỏ qua. Script tạo `.dev.vars` đặt quyền file chỉ chủ sở hữu đọc/ghi trên hệ thống hỗ trợ POSIX. Không tải các file này lên issue, chat hoặc kho Git.
- Telegram Bot API bắt buộc đặt Bot Token trong đường dẫn của request HTTPS. Worker và script webhook không in URL, body lỗi thô hoặc exception mạng; log chỉ ghi mã lỗi đã rút gọn. Không tự thêm log request URL, header hoặc object `env`.
- Chạy `npm run security:check` để quét các file đang được Git theo dõi trước khi push. Kiểm tra này không thể rà soát lịch sử Git cũ hoặc phát hiện mọi loại secret mã hóa.

**Nếu Bot Token từng được gửi trong chat, commit, log hoặc chia sẻ cho người khác, hãy thu hồi token cũ bằng BotFather và tạo token mới ngay cả khi mã nguồn đã được sửa.** Cập nhật token mới trong file `.env`, chạy `npm run secrets:sync`, sau đó chạy lại workflow triển khai để cập nhật Cloudflare Worker Secret và webhook. Không gửi token mới vào cuộc trò chuyện.

## Lưu ý trạng thái nghiệp vụ

Bot menu và xác thực Mini App có thể hoạt động sau khi cấu hình secrets. Nghiệp vụ thanh toán, số dư, đơn hàng, OTP, Proxy, VIP, kho Code, lưu ticket và đăng nhập Tool Live cần tích hợp nhà cung cấp, cơ sở dữ liệu, phân quyền và quy trình bảo mật trước khi mở cho người dùng thật.


## Tích hợp chọn lọc từ Hendy Server PRO

Webhook Telegram dùng bảng D1 riêng để ghi nhận update_id, khóa xử lý 60 giây, chống giao hàng trùng và cho phép retry khi lỗi. Bản ghi webhook được dọn theo chu kỳ bảo trì. Cơ chế này giảm xử lý lặp nhưng không bảo đảm side effect bên ngoài exactly-once.

Phạm vi tích hợp, schema/binding không tương thích và các module được hoãn được ghi tại docs/HENDY-SERVER-PRO-INTEGRATION.md.
