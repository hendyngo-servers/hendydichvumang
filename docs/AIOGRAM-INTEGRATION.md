# Phân tích mã nguồn aiogram và phương án tích hợp HENDY

## 1. Kết luận kiến trúc

HENDY không chuyển grammY sang aiogram ngay. Repository giữ Node.js/Express làm API nghiệp vụ và giữ grammY làm bot chính. Aiogram được thêm dưới dạng dịch vụ Python độc lập cho bot thử nghiệm, dùng token Telegram khác hoàn toàn, gọi Node API qua service-to-service key và dùng chung PostgreSQL cho dữ liệu FSM.

Telegram chỉ có một webhook đang hoạt động cho mỗi Bot Token. Hai framework không được dùng chung một token. Khi khởi động, bot aiogram gọi endpoint nội bộ để lấy Telegram ID của bot grammY; nếu ID trùng, dịch vụ từ chối đăng ký webhook.

## 2. Bản đồ mã nguồn upstream

Repository upstream: https://github.com/aiogram/aiogram

- Nhánh được kiểm tra: dev-3.x
- Bản phát hành được pin khi tích hợp: 3.31.0
- Telegram Bot API được hỗ trợ tại bản phát hành: 10.3
- Python yêu cầu: >=3.11
- Giấy phép: MIT
- Thư mục nguồn chính: aiogram/client, dispatcher, filters, fsm, handlers, methods, types, utils, webhook.

Một phần lớn các module trong methods/, types/ và enums/ là lớp API Telegram được sinh tự động từ schema Bot API. HENDY không cần sao chép hay fork các lớp này; package chính thức được cài bằng PyPI.

## 3. Đánh giá các module cốt lõi

| Module upstream | Vai trò trong aiogram | Quyết định cho HENDY |
|---|---|---|
| dispatcher/dispatcher.py | Dispatcher gốc, FSM middleware, nhận update, polling và webhook feed | Dùng Dispatcher riêng cho dịch vụ Python |
| dispatcher/router.py | Cây router, observer theo event, dừng propagation khi handler đầu tiên khớp | Dùng router tách common/customer/support |
| dispatcher/event/handler.py | Đăng ký handler, filter chain và chuẩn bị tham số gọi | Dùng dependency injection tự động của aiogram |
| filters/command.py | Lọc lệnh và mention bot | Dùng cho /start, /customer, /balance, /services, /support |
| Magic Filters | Lọc trường dữ liệu Telegram | Dùng F.chat.type, F.text, F.data |
| fsm/ | Trạng thái hội thoại và wizard | Dùng cho biểu mẫu ticket hai bước |
| fsm/storage/base.py | Hợp đồng BaseStorage và StorageKey | Viết PostgresFSMStorage riêng |
| fsm/storage/redis.py | RedisStorage và RedisEventIsolation | Không dùng vì HENDY đã có PostgreSQL và chưa cần Redis |
| dispatcher/middlewares/base.py | Middleware ngoài/trong handler | Dùng đồng bộ hồ sơ khách hàng trước handler |
| webhook/aiohttp_server.py | SimpleRequestHandler và TokenBasedRequestHandler | Dùng SimpleRequestHandler, secret header, không đặt token trong URL |
| utils/i18n/ | GNU Gettext/Babel và middleware ngôn ngữ | Để giai đoạn sau; mặc định bot HENDY là tiếng Việt |
| fsm/scene.py | Scene/wizard phức tạp | Chưa cần; FSM đơn giản đủ cho ticket |
| methods/, types/, enums/ | Telegram Bot API models/methods | Dùng package, không sao chép source |

### Vì sao không dùng MemoryStorage mặc định?

Dispatcher mặc định có thể dùng MemoryStorage nếu không truyền storage. Source upstream cảnh báo MemoryStorage mất toàn bộ state khi process khởi động lại. Đây là rủi ro cho quy trình hỗ trợ đang nhập dở.

HENDY triển khai:
- PostgresFSMStorage: lưu state và JSON data với TTL tối đa 7 ngày.
- PostgresEventIsolation: advisory lock theo bot/chat/user để hai update cùng người dùng không ghi đè dữ liệu FSM.
- Migration 004_aiogram_fsm.sql: tạo bảng bot_fsm_states.
- Tác vụ cleanup hiện có xóa FSM record hết hạn.

### Vì sao dùng SimpleRequestHandler?

Source aiogram xác minh X-Telegram-Bot-Api-Secret-Token bằng constant-time compare khi cấu hình secret. TokenBasedRequestHandler đưa bot token vào URL và chính source cảnh báo token có thể bị ghi vào access log. HENDY dùng SimpleRequestHandler với URL webhook cố định và secret header.

## 4. Module được triển khai trong HENDY

    services/
    └── aiogram_bot/
        ├── main.py                 # aiohttp + webhook + lifecycle
        ├── config.py               # biến môi trường và kiểm tra HTTPS
        ├── api_client.py           # gọi Node API qua service key
        ├── fsm_storage.py          # PostgreSQL BaseStorage + event isolation
        ├── middlewares.py          # đồng bộ hồ sơ Telegram
        ├── routers/
        │   ├── common.py           # /start, /help, /cancel
        │   ├── customer.py         # /customer, /balance, /services
        │   ├── support.py          # FSM tạo ticket hai bước
        │   └── errors.py           # xử lý lỗi toàn cục
        ├── requirements.txt
        └── tests/

### Luồng dữ liệu

1. Telegram gửi update tới webhook của dịch vụ aiogram.
2. SimpleRequestHandler xác minh webhook secret.
3. Middleware đồng bộ hồ sơ khách hàng qua Node API.
4. Router/Filter chọn handler phù hợp.
5. Handler gọi Node API qua HENDY_BOT_SERVICE_KEY để đọc số dư, danh mục hoặc tạo ticket.
6. FSM ghi trạng thái hội thoại vào PostgreSQL; không lưu state quan trọng trong RAM.
7. Node API vẫn là nơi duy nhất xử lý số dư, ticket, audit log và dữ liệu nghiệp vụ.

API nội bộ mới:
- GET /internal/bot/primary-identity
- POST /internal/bot/users/upsert
- GET /internal/bot/users/:telegramId/balance
- POST /internal/bot/tickets

Tất cả yêu cầu nội bộ phải có header X-Hendy-Service-Key. Khóa được sinh trong biến môi trường của API, không đưa vào trình duyệt hoặc chat. Ticket tạo từ bot có giới hạn tối đa 5 ticket/người dùng/giờ.

## 5. Những module chủ động chưa tích hợp

- RedisStorage: PostgreSQL hiện có đã đủ cho quy mô ban đầu; thêm Redis chỉ khi cần tách tải FSM/session hoặc cần latency thấp hơn.
- Scenes: ticket chỉ có hai bước nên StatesGroup dễ kiểm thử và ít phụ thuộc hơn.
- i18n/Babel: ngôn ngữ mặc định hiện tại là tiếng Việt; thêm Gettext khi có yêu cầu đa ngôn ngữ thực tế.
- Payments / Telegram Stars / pre-checkout: chưa bật vì HENDY chưa nối nhà cung cấp thanh toán thật, chưa có idempotency và đối soát.
- Business messages, inline mode, paid media, games: không thuộc luồng CSKH hiện tại.
- Polling: dịch vụ Render được thiết kế webhook để không chạy hai consumer polling/webhook trên cùng token.

## 6. Bảo mật và vận hành

- AIOGRAM_BOT_TOKEN phải là token của bot thử nghiệm riêng, không phải BOT_TOKEN của grammY chính.
- Khi khởi động, aiogram gọi Node API để so sánh Telegram bot ID. Nếu cùng bot, startup bị từ chối trước khi gọi setWebhook.
- AIOGRAM_WEBHOOK_SECRET được băm thành chuỗi URL-safe; SimpleRequestHandler so sánh secret theo constant time.
- BOT_SERVICE_KEY tối thiểu 32 ký tự và chỉ được lưu ở Render Environment của hai dịch vụ server.
- DATABASE_URL chỉ nằm trong môi trường server. Static Site không nhận database URL.
- Legacy URL không chấp nhận query token/password/API key cố định.
- Dữ liệu FSM có TTL, được dọn tự động. Nội dung ticket thật được lưu ở bảng tickets, không lưu trong FSM sau khi hoàn tất.

## 7. Deploy Render

Blueprint render.yaml khai báo Web Service Python thứ ba: hendydichvumang-aiogram-bot.

Cần cung cấp AIOGRAM_BOT_TOKEN của bot thử nghiệm. Các biến HENDY API, service key và PostgreSQL được liên kết từ Web Service API bằng fromService; PUBLIC_APP_URL cần trỏ về Static Site.

Gói Free chỉ thích hợp để chạy pilot: Web Service có thể sleep khi không nhận traffic, vì vậy webhook có thể khởi động chậm. Dịch vụ bot cần chạy ổn định 24/7 nên dùng gói Always-On phù hợp. Không bật song song polling cho token này.

## 8. Giai đoạn tiếp theo

1. Deploy API Node.js và database trước; xác nhận /healthz là ok.
2. Cấu hình token bot thử nghiệm riêng và deploy aiogram service.
3. Thử các lệnh /start, /customer, /balance, /services.
4. Tạo ticket thử nghiệm, kiểm tra ticket trong Admin và xác nhận state được dọn đúng hạn.
5. Chạy soak test/restart service; xác nhận FSM được phục hồi từ PostgreSQL.
6. Chỉ sau khi đủ kiểm thử mới cân nhắc chuyển một bot production sang aiogram. Không thay grammY chính ở giai đoạn pilot.
