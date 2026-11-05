# Trạng thái tích hợp nghiệp vụ HENDY Cybertech

## Kiến trúc

- Node.js + PostgreSQL tiếp tục là nguồn dữ liệu chính duy nhất.
- Bot chính grammY đặt tại `apps/bot_chinh/`; cấu hình menu nghiệp vụ được đọc từ PostgreSQL qua Node API.
- Bot phụ Aiogram đặt tại `apps/bot_phu/`; code mới dùng namespace `apps.bot_phu`. `services.aiogram_bot` chỉ còn là compatibility shim phục vụ triển khai cũ.
- Mini App giữ xác thực Telegram và API Node.js hiện hữu. Theme bảy màu từ ZIP được tái sử dụng trong CSS/component hiện tại; không chép trang PHP vì trang PHP gọi các endpoint hiện chưa có trong Node API.

## Migration commerce

Migration `007_commerce_readiness.sql` tạo các bảng riêng cho:
- Thanh toán: `payment_intents`, `payment_reconciliation_batches`, `payment_reconciliation_items`.
- Đơn hàng: `commerce_orders`, `commerce_order_items`, `commerce_order_events`.
- VIP: `vip_tiers`, `vip_memberships`.
- Voucher: `vouchers`, `voucher_redemptions`.
- Giftcode: `giftcodes`, `giftcode_redemptions`.
- Referral: `referral_codes`, `referral_attributions`, `referral_rewards`.
- Video Jobs: `video_jobs`.
- Sổ cái giao dịch: `commerce_ledger_entries`.

Đây là schema nền tảng, chưa phải triển khai hoàn tất các luồng nghiệp vụ. Không ghi số dư hoặc xác nhận giao dịch trực tiếp từ frontend.

## Cơ chế khóa

Tất cả module được seed với `enabled=false`, `backend_ready=false`, `reconciliation_verified=false`. API `GET /api/commerce/status` yêu cầu phiên đăng nhập. Các prefix `/api/payments`, `/api/orders`, `/api/vip`, `/api/vouchers`, `/api/giftcodes`, `/api/referrals`, `/api/video-jobs` yêu cầu đăng nhập và trả HTTP 503 cho đến khi đủ điều kiện.

Ngoài cờ PostgreSQL, code Node.js còn có khóa `implementationReady=false` độc lập. Vì thế sửa cờ trong database không thể tự kích hoạt module. Chỉ đổi khóa code sau khi hoàn thiện API, unit/integration tests, kiểm tra cạnh tranh và idempotency, cấu hình nhà cung cấp thực (nếu có), webhook chữ ký, audit log và quy trình đối soát thực tế.

## Điều kiện mở module

1. Backend Node.js hoàn chỉnh với validation phía máy chủ; không tin giá/số tiền/giảm giá gửi từ trình duyệt.
2. Mọi thay đổi ví phải dùng PostgreSQL transaction, row lock và idempotency key.
3. Sổ cái append-only phải liên kết wallet transaction, order, payment intent và reference duy nhất.
4. Xác minh chữ ký webhook, chống replay, kiểm tra số tiền/đơn vị tiền tệ và chống xử lý lặp.
5. Đối soát hai chiều với sao kê nhà cung cấp; báo cáo khoản thiếu, trùng hoặc lệch số tiền.
6. Có kiểm thử tích hợp PostgreSQL, kiểm thử retry/concurrency và audit log.
7. Có phê duyệt vận hành trước khi bật feature flag và cờ implementation trong source.

## Token bot

- Bot chính grammY: `BOT1_TOKEN`.
- Bot phụ Aiogram: `BOT2_TOKEN`.
- `BOT_TOKEN` và `AIOGRAM_BOT_TOKEN` chỉ được đọc làm bí danh chuyển tiếp để tránh dừng dịch vụ; sau khi cấu hình hai biến mới trong Render có thể gỡ biến cũ.
- Hai token phải thuộc hai bot Telegram khác nhau. Không ghi token vào source, log, URL hoặc frontend. Nếu token từng nằm trong ZIP công khai, hãy thu hồi và tạo token mới qua BotFather.
