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
│   │   ├── src/
│   │   │   └── index.js
│   │   └── scripts/
│   │       └── set-webhook.mjs
│   └── mini-app/
│       ├── index.html
│       ├── styles.css
│       └── app.js
└── .github/workflows/   (CI/CD triển khai tự động)
```

## API Worker

| Method | Endpoint | Chức năng |
|---|---|---|
| GET | /health | Kiểm tra Worker |
| POST | /telegram/webhook | Nhận Telegram Update, yêu cầu secret header |
| POST | /api/telegram/auth | Xác minh Telegram WebApp initData |
| POST | /api/payment/config | Trả thông tin STK/QR sau khi xác thực Mini App |
| POST | /api/dashboard | Trả hồ sơ đã xác thực và trạng thái thống kê |
| POST | /api/tickets | Kiểm tra yêu cầu hỗ trợ; hiện trả 503 đến khi cấu hình kho lưu |

Các thống kê số dư/đơn hàng/ticket được để trống có chủ đích cho đến khi kết nối cơ sở dữ liệu. Form ticket, đăng nhập Tool Live và CAPTCHA là giao diện tích hợp; chưa ghi nhận ticket hay xác thực thông tin đăng nhập khi chưa có dịch vụ backend tương ứng.

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

**Chưa bật tự động cộng tiền:** repository hiện chưa có D1 wallet/ledger, bảng đơn nạp, endpoint webhook ngân hàng hoặc kiểm tra chữ ký nhà cung cấp. Không được cộng số dư chỉ vì người dùng mở QR hoặc gửi ảnh biên lai. Muốn tự động xác nhận cần đăng ký SePay hoặc API ngân hàng, lấy API key/HMAC secret từ nhà cung cấp, rồi triển khai webhook + D1 transaction chống giao dịch trùng. Những khóa đó không tự sinh được và chỉ nên thêm sau khi kết nối nhà cung cấp.

### Những cấu hình hệ thống tự tạo

Không cần tự tạo GitHub Variables hay GitHub Secret thứ tư:

- `WEBHOOK_SECRET`: sinh ổn định bằng HMAC-SHA-256 từ Bot Token ở mỗi lần deploy; được đưa vào Cloudflare Worker Secret và dùng để đăng ký Telegram webhook.
- `WORKER_API_URL`: tự lấy Workers account subdomain từ Cloudflare API và ghép với tên Worker.
- `BOT_WEBHOOK_URL`: tự ghép `WORKER_API_URL/telegram/webhook`.
- `MINI_APP_URL`: tự suy ra từ Pages project `hendydichvumang`.
- Pages project: tự tạo nếu chưa tồn tại.

Workflow cần Cloudflare API Token có quyền tài khoản **Workers Scripts Read + Write** và **Pages Read + Write**. Nên giới hạn token trong đúng Cloudflare account đang triển khai. Tài liệu Cloudflare khuyến nghị dùng GitHub Secrets thay vì lưu API Token trong repository. <https://developers.cloudflare.com/workers/ci-cd/external-cicd/github-actions/>

### Khi thêm cấu hình mới trong tương lai

Bổ sung tên secret/biến vào workflow bootstrap hoặc khai báo binding trong `wrangler.toml`; workflow sẽ cập nhật trên Cloudflare khi deploy. Chỉ giữ trong GitHub Secrets những giá trị cần bảo mật; các URL/ID công khai có thể được suy ra hoặc ghi trong cấu hình không nhạy cảm.

## Triển khai

1. Tạo Cloudflare Pages project tên `hendydichvumang`.
2. Kiểm tra `MINI_APP_URL` và `MINI_APP_ORIGIN` trong `apps/telegram-worker/wrangler.toml` đúng domain Pages thực tế.
3. Thêm đủ GitHub Secrets và Variables như trên.
4. Push lên nhánh `main` hoặc chạy workflow trong tab Actions.
5. Workflow Worker deploy backend, đặt secret và đăng ký webhook Telegram. Workflow Pages thay placeholder API URL trong HTML rồi deploy frontend.

## Chạy và kiểm tra local

Yêu cầu Node.js 22+:

```bash
npm install
npm run check
npm test
npm run dev:worker
```

Deploy thủ công:

```bash
npm run deploy:worker
npm run deploy:pages
npm run webhook:set
```

## Lưu ý trạng thái nghiệp vụ

Bot menu và xác thực Mini App có thể hoạt động sau khi cấu hình secrets. Nghiệp vụ thanh toán, số dư, đơn hàng, OTP, Proxy, VIP, kho Code, lưu ticket và đăng nhập Tool Live cần tích hợp nhà cung cấp, cơ sở dữ liệu, phân quyền và quy trình bảo mật trước khi mở cho người dùng thật.
