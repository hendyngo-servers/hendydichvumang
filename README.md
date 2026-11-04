# Hendy Dịch Vụ Mạng — Telegram Bot + Mini App

Mã nguồn triển khai theo kiến trúc **một Telegram bot duy nhất**, dùng Cloudflare Workers cho Telegram webhook/API và Cloudflare Pages cho Mini App giao diện tiếng Việt.

> Bản hiện tại là nền tảng menu + webhook + xác thực Telegram Mini App. Các nghiệp vụ thanh toán, ví, đơn hàng Proxy, kho tài khoản, thuê Tool và quản trị cần API nghiệp vụ và cơ sở dữ liệu thực tế; không có giao dịch giả.

## Cấu trúc

```text
.github/workflows/  CI + deploy Workers + deploy Pages
apps/telegram-worker/  Telegram webhook/API trên Cloudflare Workers
apps/mini-app/  Giao diện Mini App tiếng Việt trên Cloudflare Pages
package.json
README.md
```

## Triển khai

1. Tạo Cloudflare Pages project `hendydichvumang`, production branch `main`.
2. GitHub Settings → Secrets and variables → Actions: thêm `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID`, `BOT_TOKEN`, `WEBHOOK_SECRET`.
3. Thêm Variables `BOT_WEBHOOK_URL` và `WORKER_API_URL` theo hostname Worker thực tế.
4. Cập nhật `MINI_APP_URL` và `MINI_APP_ORIGIN` trong `apps/telegram-worker/wrangler.toml` theo Pages domain.
5. Push lên `main`; GitHub Actions sẽ kiểm tra, deploy Worker, đăng ký webhook và deploy Pages.

Chạy kiểm tra: `npm install && npm run check && npm test`.

Chỉ dùng một `BOT_TOKEN`. Không commit token hoặc secret. Xem README đầy đủ sau khi các tệp nguồn được tải lên.