# HENDY DỊCH VỤ MẠNG — Render Edition

Repository này đã chuyển runtime sang **Render Web Service (Node.js 22) + Render PostgreSQL**. Mã nguồn ứng dụng đầy đủ nằm trong gói tar nén được chia thành các tệp base64 `app-source.part-01.b64` đến `app-source.part-07.b64`. Render tự nối, giải nén gói, cài dependencies và chạy kiểm thử trước khi khởi động.

## Triển khai một lần bằng Blueprint

1. Mở [Render Blueprint](https://dashboard.render.com/blueprint/new?repo=https://github.com/hendyngo-servers/hendydichvumang).
2. Chọn nhánh `main`, xem trước tài nguyên và nhấn **Apply**.
3. Khi Render yêu cầu giá trị bí mật, nhập `BOT_TOKEN` mới, `ADMIN_USERNAME` và `ADMIN_PASSWORD`. Không commit các giá trị này vào Git.
4. Sau khi deploy, mở `https://<ten-dich-vu>.onrender.com/healthz`. Kết quả hợp lệ phải có `status: "ok"` và `database: "ready"`.
5. Đặt URL Mini App trong BotFather; đăng ký webhook bằng `npm run set-webhook` trong Shell của Web Service. Xem hướng dẫn đầy đủ trong README bên trong gói mã nguồn.

## Cấu hình

Blueprint tự tạo PostgreSQL và liên kết nội bộ qua `DATABASE_URL`; tự sinh `TELEGRAM_WEBHOOK_SECRET`. Chỉ cần cung cấp `BOT_TOKEN`, `ADMIN_USERNAME` và `ADMIN_PASSWORD` trên Render Dashboard. `PUBLIC_APP_URL` có thể đặt thành URL dịch vụ Render nếu cần dùng webhook/Mini App với hostname cố định.

## Giới hạn gói Free — cần đọc trước khi dùng dữ liệu thật

- Web Service Free có thể sleep sau 15 phút không có request; request đầu sau khi ngủ có thể phải chờ cold start.
- PostgreSQL Free có giới hạn 1 GB, hết hạn sau 30 ngày và không có backup tự động. Dữ liệu có thể bị xóa sau thời gian gia hạn theo chính sách Render.
- Vì vậy cấu hình này phù hợp thử nghiệm/đánh giá, không phù hợp lưu số dư hoặc dữ liệu khách hàng lâu dài. Xuất dữ liệu và nâng cấp/migrate DB trước khi hết hạn.

## Mã nguồn

Để xem mã nguồn đầy đủ sau khi tải repository:

```bash
cat app-source.part-*.b64 | base64 -d | tar -xz
npm install
npm run verify
npm start
``

Ứng dụng sử dụng tiếng Việt mặc định, PostgreSQL migration tự chạy khi khởi động, xác thực Telegram Mini App bằng HMAC-SHA-256, token phiên ngẫu nhiên được lưu dưới dạng SHA-256, webhook có secret header, và admin được bảo vệ bằng thông tin đăng nhập từ biến môi trường.

Các tính năng nạp tiền tự động, OTP, Proxy, thuê Tool, VIP và cấp phát Code chưa được kết nối nhà cung cấp thật; giao diện/danh mục không được giả lập là đã có giao dịch. Không tự cộng số dư nếu chưa tích hợp webhook thanh toán đã xác thực.
