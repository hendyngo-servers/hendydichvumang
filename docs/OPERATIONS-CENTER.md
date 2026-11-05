# HENDY Operations Center

## Mục tiêu

Trang `/admin` là điểm điều hành tập trung của HENDY. Nó ghép trạng thái API, PostgreSQL, grammY, Aiogram, Render, cấu hình runtime, module, automation và audit logs vào giao diện quản trị hiện tại.

## Những phần đã tích hợp

- Tổng quan runtime API và PostgreSQL: uptime, phiên bản Node.js, kiểm tra DB, migration mới nhất và số liệu nghiệp vụ.
- Hai Telegram bot: grammY là bot chính; Aiogram chạy ở service riêng. Aiogram phải dùng token khác và tiếp tục xác minh danh tính bot grammY trước khi thiết lập webhook.
- Cấu hình runtime: chỉ hiển thị tên biến và trạng thái đã khai báo/chưa khai báo. Các giá trị bí mật không được trả cho trình duyệt.
- Render: tra cứu các service `hendydichvumang-site`, `hendydichvumang-api`, `hendydichvumang-aiogram-bot`; trạng thái deploy gần nhất; nhánh; kiểm kê tên các biến bắt buộc.
- Nhật ký: audit events và lịch sử automation từ PostgreSQL.
- Giữ nguyên các vùng sẵn có: cấu hình menu grammY/Aiogram, automation, kho module chờ duyệt, dịch vụ và ticket CSKH.

## API mới

Các API dưới đây yêu cầu admin session qua header `Authorization: Bearer <session>`:

- `GET /api/admin/operations/overview`
- `GET /api/admin/operations/config`
- `GET /api/admin/operations/render`
- `GET /api/admin/operations/logs?limit=30`

Mọi API sử dụng các bảng hiện có. Không thêm migration, không drop/rename bảng và không reset dữ liệu.

## Kết nối Render (tùy chọn)

Thêm hai biến vào Environment của service `hendydichvumang-api`:

- `RENDER_API_KEY`: API key server-side có quyền đọc service, deployment và environment variables trong workspace. Chỉ cấp quyền tối thiểu cần thiết.
- `RENDER_WORKSPACE_ID`: tùy chọn; dùng để giới hạn truy vấn trong một workspace nếu API key có quyền nhiều workspace.

Không đặt các biến này trong Static Site, Aiogram, Mini App hoặc mã nguồn. Không gửi Render API key xuống trình duyệt.

Render API trả về giá trị env-var cùng với tên biến. Adapter chỉ giữ tên key để tính trạng thái; không trả giá trị, ghi log hoặc lưu DB. Giao diện chỉ cho biết biến bắt buộc đã được khai báo hay còn thiếu. Để thêm/sửa secret thực tế, tiếp tục sử dụng Render Dashboard > service > Environment.

Các biến bắt buộc được kiểm kê:

| Service | Biến |
| --- | --- |
| Static Site | `STATIC_API_BASE_URL` |
| Node API | `DATABASE_URL`, `BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, `BOT_SERVICE_KEY`, `ADMIN_PASSWORD`, `ADMIN_TELEGRAM_ID`, `PUBLIC_APP_URL` |
| Aiogram | `AIOGRAM_BOT_TOKEN`, `AIOGRAM_WEBHOOK_SECRET`, `HENDY_API_BASE_URL`, `HENDY_BOT_SERVICE_KEY`, `DATABASE_URL`, `PGSSLMODE` |

## Nguyên tắc bảo mật và phạm vi

- Bot Token của grammY và Aiogram phải là hai token độc lập.
- Bot Token, Render API key, service key, webhook secret, mật khẩu và session token không được gửi về browser.
- Không lưu credentials vào PostgreSQL, audit log hoặc static assets.
- Render integration hiện chỉ đọc trạng thái; không tự đổi env vars, restart hay trigger deploy.
- Module upload vẫn chỉ là artifact chờ kiểm duyệt, không được thực thi tự động.
- Mọi thao tác điều hành tiếp tục đi qua admin authentication và API hiện có.

## Deploy

1. Push commit lên nhánh `main`; Render Blueprint tự triển khai các service đã được liên kết với repository.
2. Có thể để trống `RENDER_API_KEY`; giao diện sẽ báo chưa kết nối Render nhưng phần tổng quan PostgreSQL, bot, automation và logs vẫn hoạt động.
3. Khi đã thêm Render API key, mở `/admin`, đăng nhập và nhấn **Đồng bộ trạng thái**.
4. Nếu một service chưa được tạo trong Render, dashboard sẽ hiển thị trạng thái chưa tìm thấy thay vì giả lập trạng thái healthy.
