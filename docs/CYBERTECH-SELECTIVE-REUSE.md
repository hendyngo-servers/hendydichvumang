# Đánh giá tích hợp chọn lọc HENDY-BOT-CYBERTECH

## Kết luận kiến trúc

Không thay toàn bộ repository bằng archive PHP. Repository đích đang chạy Node.js 22 + Express + grammY, có Aiogram pilot riêng, PostgreSQL làm nguồn dữ liệu và Static Site độc lập. Archive là PHP 8.3 + Apache + PostgreSQL + Ratchet; chép toàn bộ sẽ tạo hai API, hai bot dispatcher và nguy cơ trùng bảng nghiệp vụ.

Giữ nguyên nền tảng hiện hữu và port các capability độc lập, có hợp đồng API rõ ràng.

## Đã tích hợp vào nhánh này

1. Module artifact review: upload metadata/content dạng BYTEA, SHA-256, allowlist loại tệp, giới hạn 1 MB, trạng thái pending_review/approved/rejected, audit log.
2. Không thực thi tệp: PHP, JS, HTML, SVG, archive và MIME không cho phép bị từ chối. Approve không đồng nghĩa deploy.
3. PostgreSQL authoritative: dùng migration 006, không tạo database nghiệp vụ thứ hai.
4. CI: test validation module và compile source bằng Node test runner.
5. Quyền Admin: mọi endpoint module yêu cầu session role admin ở backend.

## Có thể tích hợp tiếp theo

| Capability từ archive | Hướng tích hợp | Điều kiện |
|---|---|---|
| PostgreSQL NOTIFY + realtime event log | Port sang Node pg + WebSocket service độc lập | Thêm event ACL, one-time ticket, origin allowlist, heartbeat, load test |
| Feature flags | Bảng PostgreSQL + evaluator phía API/bot | Quy định key allowlist, default-off và audit |
| Queue retry/DLQ | PostgreSQL job table, FOR UPDATE SKIP LOCKED, exponential backoff | Idempotency, max attempts, dead-letter review |
| Central runtime config | Admin-managed non-secret config; secret chỉ ở Render/GitHub secret store | RBAC, audit, versioning, rollback; không đọc/ghi secret từ browser |
| Frontend React/Vite | Port từng component theo API contract hiện có | Không thay frontend hiện hành toàn bộ một lần |

## Không nhập trực tiếp

- PHP BotService.php / ApiController.php hoặc PHP runtime vào Node service.
- Schema ví, đơn hàng, giao dịch và người dùng của hệ thống khác.
- D1/R2/Workers như bản sao của PostgreSQL hoặc tự động dual-write.
- App Store/Google Play/Microsoft Store publisher actions khi adapter mới chỉ là skeleton hoặc chưa có credential.
- Bank/payment/Vietsub providers chưa có hợp đồng API và xác minh chữ ký thực tế.

## Bảo mật archive

Archive .env.example có các trường token chứa chuỗi giống Bot Token thật. Các giá trị đó không được sao chép vào repository, tài liệu, log hay CI. Nếu token thuộc bot đang hoạt động, revoke qua BotFather và tạo token mới. Kiểm tra Git history và các log/build artifact từng chia sẻ file.

## Triển khai

Nhánh này chỉ thêm migration additive và endpoint Admin; không sửa cấu trúc ví, ticket, session, grammY, Aiogram hay URL production. Trước khi merge:
- Chạy npm ci && npm run verify.
- Kiểm tra migration 005 trên PostgreSQL test.
- Thử upload các tệp allowlist và xác nhận PHP/JS/HTML/SVG/ZIP bị từ chối.
- Xác nhận user session nhận 403, Admin session nhận 201.
- Thử approve/reject, kiểm tra audit log.
- Chỉ sau khi CI xanh mới merge và để Render auto-deploy.