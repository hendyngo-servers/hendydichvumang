# Tích hợp chọn lọc từ Hendy Server PRO

Tài liệu này ghi lại phạm vi đã đối chiếu giữa:

- Nguồn tham khảo: https://github.com/ngogiaidy56-eng/Hendy-Server-PRO
- Repository đích: https://github.com/hendyngo-servers/hendydichvumang

## Đã tích hợp trong nhánh này

- Cơ chế idempotency cho Telegram webhook bằng D1: mỗi update_id chỉ được nhận xử lý khi chưa hoàn tất; bản gửi lặp đã xử lý sẽ được ACK mà không gửi lại tin nhắn.
- Khóa xử lý có thời hạn 60 giây để tránh hai lần Telegram delivery chạy song song. Bản xử lý lỗi hoặc lease hết hạn có thể được thử lại.
- Chỉ lưu mã lỗi đã chuẩn hóa, trạng thái và metadata tối thiểu; không lưu nội dung message hoặc callback payload.
- Thêm migration D1 riêng, không sửa hoặc ghi đè migration dữ liệu hiện hữu.
- Làm sạch .env.example: chỉ giữ placeholder trống, không giữ credential.

## Không chép nguyên trạng

- Không chép src/bot.js hoặc schema thanh toán của dự án tham khảo vì cấu trúc bảng khác với D1 hiện tại (đích dùng wallet_accounts, topup_orders, wallet_ledger).
- Không chép Admin Dashboard tham khảo vì nó nhận token qua query string và lưu token trong localStorage; cần thiết kế lại phiên quản trị an toàn trước khi tích hợp.
- Không chép .dev.vars.example tham khảo do file đó có các giá trị giống credential thật.
- Chưa bật Queues, Durable Objects hoặc R2 từ dự án tham khảo vì workflow hiện tại chưa bootstrap các tài nguyên đó. Bật binding trước khi có migration hạ tầng tương ứng sẽ làm deployment thất bại.

## Giới hạn xử lý

Idempotency ngăn xử lý trùng khi update đã được ghi nhận hoàn tất và hạn chế cạnh tranh khi Telegram retry. Nó không biến side effect bên ngoài (ví dụ Telegram sendMessage) thành giao dịch exactly-once: nếu request bên ngoài thành công nhưng Worker chết trước khi ghi trạng thái hoàn tất, update có thể được thử lại sau khi lease hết hạn.
