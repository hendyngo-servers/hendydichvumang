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
| POST | /api/dashboard | Trả hồ sơ đã xác thực và trạng thái thống kê |
| POST | /api/tickets | Kiểm tra yêu cầu hỗ trợ; hiện trả 503 đến khi cấu hình kho lưu |

Các thống kê số dư/đơn hàng/ticket được để trống có chủ đích cho đến khi kết nối cơ sở dữ liệu. Form ticket, đăng nhập Tool Live và CAPTCHA là giao diện tích hợp; chưa ghi nhận ticket hay xác thực thông tin đăng nhập khi chưa có dịch vụ backend tương ứng.

## Cấu hình bí mật

Trong GitHub → Settings → Secrets and variables → Actions → Secrets, tạo:

- `CLOUDFLARE_API_TOKEN`: quyền deploy Workers và Pages.
- `CLOUDFLARE_ACCOUNT_ID`: Cloudflare Account ID.
- `BOT_TOKEN`: token của bot Telegram duy nhất.
- `WEBHOOK_SECRET`: chuỗi 1–256 ký tự gồm chữ, số, dấu gạch dưới hoặc gạch ngang.

Trong Actions → Variables, tạo:

- `BOT_WEBHOOK_URL`: URL Worker kết thúc bằng `/telegram/webhook`.
- `WORKER_API_URL`: URL gốc HTTPS của Worker, ví dụ `https://hendydichvumang-bot.<subdomain>.workers.dev`.

Không gửi các token này trong chat, không commit vào repository và không đặt chúng trong JavaScript frontend.

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
