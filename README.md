# HENDY DỊCH VỤ MẠNG
## Telegram Bot đơn + Mini App trên Cloudflare

Repository triển khai **một Telegram Bot duy nhất**. Cloudflare Workers tiếp nhận webhook và xử lý nghiệp vụ bot; Cloudflare Pages phân phối giao diện Mini App tiếng Việt. Bot mở Mini App khi người dùng chọn mục quản lý tài khoản.

---

## 1. SƠ ĐỒ HỆ THỐNG VÀ LUỒNG XỬ LÝ

```text
                    ┌──────────────────────────┐
                    │      NGƯỜI DÙNG           │
                    │ Telegram: nhắn tin / bấm nút│
                    └────────────┬─────────────┘
                                 │
                                 ▼
                    ┌──────────────────────────┐
                    │       TELEGRAM API        │
                    │ Nhận tin nhắn, callback   │
                    └────────────┬─────────────┘
                                 │ HTTPS Webhook
                                 ▼
┌───────────────────────────────────────────────────────────────┐
│          CLOUDFLARE WORKERS — TELEGRAM BOT BACKEND             │
│          Worker: hendydichvumang-bot                           │
│                                                               │
│  1. Nhận POST /telegram/webhook                                │
│  2. Kiểm tra X-Telegram-Bot-Api-Secret-Token                   │
│  3. Phân tích message / callback_query                         │
│  4. Xử lý lệnh /start, /menu, /help và điều hướng menu         │
│  5. Gọi Telegram Bot API để gửi/sửa tin nhắn                   │
│                                                               │
│  Inline Keyboard:                                              │
│   • Minigame Live       • Thuê Tool                            │
│   • Mua hàng / Proxy    • Nạp tiền                             │
│   • Kho công cụ         • Quản lý tài khoản                    │
│   • Giới thiệu / CSKH   • Các nhánh Code và dịch vụ mở rộng    │
│                                                               │
│  API xác thực Mini App: POST /api/telegram/auth                │
│  Kiểm tra chữ ký HMAC và thời hạn Telegram WebApp initData    │
└───────────────────────┬───────────────────────────────────────┘
                        │
             ┌──────────┴───────────┐
             │                      │
             ▼                      ▼
┌────────────────────────┐   ┌────────────────────────────┐
│ TELEGRAM BOT API       │   │ TÙY CHỌN: MINI APP / WEB CŨ│
│ sendMessage            │   │ Mini App mở từ nút web_app │
│ editMessageText        │   │ Web cũ là liên kết mở rộng  │
│ answerCallbackQuery    │   │ và cần cấu hình riêng       │
└────────────────────────┘   └──────────────┬─────────────┘
                                            │
                                            ▼
┌───────────────────────────────────────────────────────────────┐
│       CLOUDFLARE PAGES — MINI APP FRONTEND                     │
│       https://hendydichvumang.pages.dev                        │
│                                                               │
│  • index.html: cấu trúc trang / danh mục dịch vụ               │
│  • styles.css: giao diện responsive, Dark / Neon               │
│  • app.js: tương tác giao diện và Telegram WebApp SDK          │
│  • config.js: địa chỉ API Worker được chèn lúc deploy          │
│                                                               │
│  Luồng xác thực:                                                │
│  Telegram WebApp initData                                      │
│       → POST Worker /api/telegram/auth                         │
│       → Worker kiểm tra chữ ký HMAC + auth_date                │
│       → Trả kết quả xác thực cho Mini App                      │
│                                                               │
│  Khu vực có thể phát triển tiếp:                               │
│  Hồ sơ • Thống kê • Ticket CSKH • Đăng nhập Tool Live           │
│  Popup / Modal và CAPTCHA cần tích hợp API, chính sách và       │
│  nhà cung cấp CAPTCHA trước khi đưa vào sử dụng thực tế.       │
└───────────────────────────────────────────────────────────────┘
```

### Nguyên tắc phân chia

- **Telegram Bot** là điểm tiếp nhận và điều hướng chính; chỉ cấu hình một `BOT_TOKEN`.
- **Cloudflare Workers** xử lý webhook, kiểm tra secret, xử lý callback và xác thực dữ liệu Telegram Mini App.
- **Cloudflare Pages** chỉ phân phối frontend tĩnh. Mini App gọi API Worker qua HTTPS; không đặt Bot Token hoặc secret trong JavaScript phía trình duyệt.
- **Telegram WebApp initData** phải được gửi về Worker để kiểm tra chữ ký. Không dùng `initDataUnsafe` làm bằng chứng xác thực.
- Các chức năng thanh toán, số dư ví, đơn hàng Proxy, kho VIP, OTP, cấp quyền Tool và ticket cần được kết nối backend nghiệp vụ cùng cơ sở dữ liệu trước khi bật giao dịch thật.

> **Trạng thái mã nguồn hiện tại:** webhook, menu cơ bản, điều hướng Mini App và endpoint kiểm tra Telegram WebApp initData đã có trong Worker. Mini App có giao diện dịch vụ và trạng thái xác thực. Các nghiệp vụ mua bán, nạp tiền, OTP/Proxy/VIP, ticket, CAPTCHA và quản trị dữ liệu chưa có backend hoàn chỉnh; không hiển thị chúng như tính năng đã vận hành.

---

## 2. CẤU TRÚC THƯ MỤC (THEO REPOSITORY HIỆN TẠI)

```text
hendydichvumang/
│
├── .github/
│   └── workflows/
│       ├── ci.yml                         # Kiểm tra cú pháp và chạy test
│       ├── deploy-worker.yml              # Deploy Worker, nạp secrets,
│       │                                  # đăng ký Telegram webhook
│       └── deploy-pages.yml               # Build cấu hình và deploy Mini App
│
├── apps/
│   │
│   ├── telegram-worker/                   # BACKEND — CLOUDFLARE WORKERS
│   │   ├── src/
│   │   │   └── index.js                   # Webhook, menu, callback,
│   │   │                                  # Telegram API, xác thực initData
│   │   ├── scripts/
│   │   │   └── set-webhook.mjs            # Đăng ký webhook với Telegram
│   │   ├── test/
│   │   │   └── index.test.js              # Kiểm thử tự động Worker
│   │   └── wrangler.toml                  # Tên Worker, entrypoint,
│   │                                      # biến cấu hình và observability
│   │
│   └── mini-app/                          # FRONTEND — CLOUDFLARE PAGES
│       ├── index.html                     # Khung giao diện tiếng Việt
│       ├── config.js                      # API URL Worker (được thay
│       │                                  # bằng GitHub Actions lúc deploy)
│       ├── styles.css                     # Responsive UI, Dark / Neon
│       └── app.js                         # Telegram WebApp SDK,
│                                          # xác thực và tương tác giao diện
│
├── .editorconfig                          # Quy tắc định dạng mã nguồn
├── .gitignore                             # Loại trừ file local / secret
├── package.json                           # Scripts kiểm tra, test, deploy
└── README.md                              # Tài liệu kiến trúc và triển khai
```

---

## 3. CẤU HÌNH VÀ TRIỂN KHAI

### GitHub Actions Secrets

Tạo trong **Repository → Settings → Secrets and variables → Actions → Secrets**:

| Secret | Mục đích |
|---|---|
| `CLOUDFLARE_API_TOKEN` | Token Cloudflare có quyền cần thiết để deploy Workers và Pages |
| `CLOUDFLARE_ACCOUNT_ID` | Cloudflare Account ID |
| `BOT_TOKEN` | Token của Telegram Bot duy nhất |
| `WEBHOOK_SECRET` | Chuỗi bí mật dùng xác thực webhook Telegram |

### GitHub Actions Variables

Tạo trong cùng trang, mục **Variables**:

| Variable | Giá trị |
|---|---|
| `BOT_WEBHOOK_URL` | URL đầy đủ của Worker, kết thúc bằng `/telegram/webhook` |
| `WORKER_API_URL` | URL gốc HTTPS của Worker, không thêm đường dẫn API |

### Cấu hình Cloudflare

1. Tạo Cloudflare Pages project tên `hendydichvumang`, kết nối repository này và dùng nhánh production `main` (hoặc để workflow deploy bằng Wrangler tạo project theo cấu hình tài khoản).
2. Kiểm tra `MINI_APP_URL` và `MINI_APP_ORIGIN` trong `apps/telegram-worker/wrangler.toml` trỏ đến domain Pages thực tế.
3. Đặt GitHub Secrets và Variables như bảng trên. Không ghi token vào source code, `config.js`, log hoặc commit.
4. Push lên `main` hoặc chạy workflow thủ công trong tab **Actions**.
5. Worker workflow deploy Worker, nạp `BOT_TOKEN` và `WEBHOOK_SECRET` bằng Wrangler Secret rồi gọi script đăng ký webhook.
6. Pages workflow thay placeholder trong `apps/mini-app/config.js` bằng `WORKER_API_URL`, sau đó deploy thư mục `apps/mini-app`.

### Kiểm tra local

Yêu cầu Node.js 22 trở lên:

```bash
npm install
npm run check
npm test
```

Chạy Worker local:

```bash
npm run dev:worker
```

Deploy thủ công:

```bash
npm run deploy:worker
npm run deploy:pages
```

---

## 4. BẢO MẬT VÀ VẬN HÀNH

- Chỉ sử dụng **một Telegram Bot Token** trong hệ thống.
- Không commit `.env`, Bot Token, Cloudflare API Token, webhook secret hoặc thông tin thanh toán.
- Webhook chỉ chấp nhận request có Telegram secret header khớp `WEBHOOK_SECRET`.
- Mini App xác thực `initData` ở Worker; thông tin tên hiển thị từ trình duyệt chỉ phục vụ giao diện trước khi xác thực.
- Chỉ bật giao dịch khi đã có API nghiệp vụ, kiểm tra quyền, lưu trữ dữ liệu và xử lý lỗi tương ứng.
- Sau mỗi lần deploy, kiểm tra trạng thái workflow tại tab **Actions**, health endpoint của Worker tại `/` hoặc `/health`, và thử mở Mini App từ chính bot Telegram.

## Repository

https://github.com/hendyngo-servers/hendydichvumang
