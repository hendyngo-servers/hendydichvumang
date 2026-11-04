# Chính sách bảo mật

## Báo cáo lỗ hổng

Không đăng Bot Token, API key, webhook secret, ảnh chụp màn hình có thông tin xác thực hoặc file .env vào GitHub Issues, Pull Requests hay cuộc trò chuyện công khai. Khi phát hiện lỗ hổng, hãy liên hệ chủ repository qua kênh riêng và mô tả đường dẫn, tác động, bước tái hiện tối thiểu.

## Xử lý khi credential bị lộ

1. Thu hồi Bot Token đã lộ bằng BotFather và tạo token mới.
2. Thu hồi Cloudflare API Token đã lộ trong Cloudflare Dashboard và tạo token mới với quyền tối thiểu.
3. Thay các khóa webhook, quản trị và dịch vụ bên ngoài liên quan.
4. Cập nhật GitHub Secrets và Cloudflare Worker Secrets; không đưa giá trị mới vào file mẫu hoặc commit.
5. Kiểm tra Git history, GitHub Actions logs, deployment logs và các máy chủ cũ có thể đang giữ credential.

Xóa một giá trị khỏi commit mới không làm credential cũ mất hiệu lực và không xóa nó khỏi lịch sử Git. Luôn thu hồi credential trước, sau đó mới cân nhắc làm sạch lịch sử repository.

## Quy tắc mã nguồn

- File .env.example chỉ chứa placeholder trống; .env thực tế không được commit.
- Production secrets chỉ được cấp qua GitHub Secrets hoặc Cloudflare Worker Secrets.
- Không ghi Bot Token, Authorization header, webhook secret hay URL Bot API có token vào log.
- Chạy npm run security:check trước khi push.
