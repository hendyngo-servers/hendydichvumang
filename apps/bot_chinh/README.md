# Bot chính HENDY — grammY

Đây là bot production chính của HENDY, hiện được vận hành bằng grammY.

- Telegram username: @HendyBot_Bot
- Logic bot: `bot.js`
- Cấu hình và nghiệp vụ dùng chung: `../../src/`
- Token lấy từ biến môi trường `BOT1_TOKEN`; không lưu token trong source.
- Webhook nhận tại Node.js API; không bật polling đồng thời.
- Tên biến chuẩn: `BOT1_TOKEN`; `BOT_TOKEN` cũ chỉ là bí danh chuyển tiếp.

Aiogram là bot phụ độc lập trong `apps/bot_phu/` và phải sử dụng token khác.
