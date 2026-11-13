<?php
declare(strict_types=1);

namespace Hendy;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

final class ApiController
{
    private const CATEGORIES = ['minigame', 'tools', 'codes', 'otp', 'proxy', 'vip'];
    private const MINIGAME_BRANDS = ['QQ88', 'HI88', '78WIN', 'RR88', 'MM88', 'GG88', 'ABCVIP', 'XX8', 'U888', 'J88', 'ABC8', '88CLB', 'AD88', 'SC88', 'C168', 'CM88', 'F8BET'];
    private const ORDER_STATUSES = ['pending', 'processing', 'completed', 'rejected', 'cancelled', 'refunded'];
    private const SETTINGS = [
        'homepage_title', 'announcement', 'website_url', 'VIETQR_BANK_ID', 'VIETQR_ACCOUNT_NO',
        'VIETQR_ACCOUNT_NAME', 'GROUP_URL', 'CHANNEL_URL', 'ABCVIP_CONNECT_URL',
        'referral_commission_percent', 'support_message', 'vip_checkin_points', 'replay_live_url',
    ];
    private const MENU_ACTIONS = BotMenuDefinition::ACTIONS;

    private array $query;
    private array $body;
    private array $headers;
    private ?array $user = null;
    private string $botKey = 'primary';

    public function __construct(array $query, array $body, array $headers)
    {
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
    }

    private function publicBotUrl(?string $botKey = null): string
    {
        $key = $botKey ?? $this->botKey;
        $variable = $key === 'secondary' ? 'BOT2_PUBLIC_URL' : 'BOT1_PUBLIC_URL';
        return TelegramLink::normalize((string) Env::get($variable, ''));
    }

    public function uploadModule(array $file, string $module): array
    {
        try { $this->user = TelegramAuth::authenticateRequest(); } catch (Throwable $error) { throw new HttpException(401, 'Yêu cầu xác thực Telegram.'); }
        $this->requireAdmin();
        try { $artifact = ModuleRegistry::stage($file, $module, (string) $this->user['id']); }
        catch (Throwable $error) { throw new HttpException(400, $error->getMessage()); }
        $this->audit('module.upload', (string) ($artifact['id'] ?? ''), ['module' => $module, 'status' => 'pending_review']);
        return self::response(['artifact' => $artifact, 'message' => 'Tệp đã vào vùng chờ kiểm duyệt; chưa được thực thi hoặc phát hành.'], 201);
    }

    public function uploadAiCoverVoice(array $input, array $files): array
    {
        try {
            $this->user = TelegramAuth::authenticateRequest();
        } catch (Throwable $error) {
            throw new HttpException(401, 'Yêu cầu xác thực Telegram hoặc phiên Admin hợp lệ.');
        }
        $this->requireAdmin();
        try {
            $voice = AiCoverService::saveVoice($input, $files, (string) $this->user['id']);
        } catch (Throwable $error) {
            throw new HttpException(400, $error->getMessage() ?: 'Không thể lưu preset AI Cover.');
        }
        $this->audit('ai_cover.voice.save', (string) ($voice['id'] ?? ''), [
            'enabled' => (bool) ($voice['enabled'] ?? false),
            'available' => (bool) ($voice['available'] ?? false),
        ]);
        return self::response([
            'voice' => $voice,
            'message' => 'Đã lưu preset và hồ sơ cấp phép. Hãy xác minh model bằng worker trước khi bật cho thành viên.',
        ], 201);
    }

    public function handle(string $method, string $path): array
    {
        if ($method === 'GET' && $path === '/api/health') {
            return self::response([
                'service' => 'hendy-bot-tele-php',
                'status' => Database::check() ? 'ok' : 'degraded',
                'database' => 'ok',
                'telegram' => [
                    'primaryConfigured' => TelegramAuth::token('primary') !== '',
                    'secondaryConfigured' => TelegramAuth::token('secondary') !== '',
                ],
                'timestamp' => gmdate(DATE_ATOM),
            ]);
        }

        if ($method === 'POST' && $path === '/api/legacy/session/exchange') {
            return $this->exchangeLegacySession();
        }

        if (!str_starts_with($path, '/api/')) {
            throw new HttpException(404, 'Không tìm thấy API.');
        }

        try {
            $this->user = TelegramAuth::authenticateRequest();
            $this->botKey = (string) ($this->user['_bot_key'] ?? 'primary');
        } catch (Throwable $error) {
            throw new HttpException(401, $error->getMessage() ?: 'Yêu cầu xác thực Telegram.');
        }

        $userId = (string) $this->user['id'];

        $accountAccess = Database::one('SELECT is_blocked, blocked_reason FROM users WHERE user_id = ?', [$userId]);
        if ($accountAccess !== null && in_array($accountAccess['is_blocked'], [true, 1, '1', 't', 'true'], true) && !$this->isAdmin()) {
            $reason = trim((string) ($accountAccess['blocked_reason'] ?? ''));
            throw new HttpException(403, 'Tài khoản HENDY đang tạm khóa.' . ($reason !== '' ? ' Lý do: ' . $reason : '') . ' Vui lòng liên hệ CSKH.');
        }

        $operation = OperationsService::forBot($this->botKey);
        if ($operation['active'] && !$this->isAdmin() && strtoupper($method) !== 'GET') {
            $modeLabel = $operation['mode'] === 'upgrade' ? 'nâng cấp' : 'bảo trì';
            throw new HttpException(503, 'HENDY đang ' . $modeLabel . '. ' . $operation['message']);
        }
        $serviceBlock = ProviderRegistryService::blockMessageForPath($path, $this->isAdmin());
        if ($serviceBlock !== null) {
            throw new HttpException(503, $serviceBlock);
        }
        if (WebServiceKernel::handles($path)) {
            try {
                $result = WebServiceKernel::dispatch($method, $path, $this->body, [
                    'userId' => $userId,
                    'botKey' => $this->botKey,
                    'isAdmin' => $this->isAdmin(),
                ]);
                return self::response($result);
            } catch (HttpException $error) {
                throw $error;
            } catch (Throwable $error) {
                throw new HttpException(400, $error->getMessage() ?: 'Không thể xử lý Web Service.');
            }
        }

        if ($method === 'GET' && $path === '/api/bootstrap') {
            $user = $this->userRow();
            $referrals = (int) Database::value('SELECT COUNT(*) FROM users WHERE ref_by = ?', [$userId]);
            $publicSettings = $this->settings(self::SETTINGS, true);
            return self::response([
                'user' => $user,
                'isAdmin' => $this->isAdmin(),
                'botKey' => $this->botKey,
                'products' => $this->listProducts(null, false),
                'settings' => $publicSettings,
                'referralCount' => $referrals,
                'botUrl' => $this->publicBotUrl(),
                'operations' => $operation,
            ]);
        }

        if ($method === 'GET' && $path === '/api/products') {
            $category = $this->text($this->query['category'] ?? '', 32);
            return self::response(['products' => $this->listProducts($category !== '' ? $category : null, false)]);
        }

        if ($method === 'GET' && $path === '/api/me') {
            return self::response(['user' => $this->userRow(), 'isAdmin' => $this->isAdmin()]);
        }

        if ($method === 'GET' && $path === '/api/customer-center') {
            $profile = $this->userRow();
            $stats = Database::one(
                "SELECT COUNT(*) AS total_orders,
                        COUNT(*) FILTER (WHERE status = 'processing') AS processing_orders,
                        COUNT(*) FILTER (WHERE status = 'completed') AS completed_orders,
                        COUNT(*) FILTER (WHERE status = 'cancelled') AS cancelled_orders,
                        COUNT(*) FILTER (
                            WHERE status = 'refunded'
                               OR EXISTS (
                                   SELECT 1 FROM transactions tx
                                   WHERE tx.user_id = orders.user_id
                                     AND tx.reference_code = orders.order_code
                                     AND tx.type = 'ORDER_REFUND'
                               )
                        ) AS refunded_orders
                 FROM orders WHERE user_id = ?",
                [$userId]
            ) ?? [];
            $ticketCounts = Database::one(
                "SELECT COUNT(*) AS total,
                        COUNT(*) FILTER (WHERE ticket_type = 'dinh_lam') AS abuse,
                        COUNT(*) FILTER (WHERE ticket_type = 'support') AS support
                 FROM support_tickets WHERE user_id = ?",
                [$userId]
            ) ?? [];
            return self::response([
                'user' => $profile,
                'statistics' => [
                    'totalOrders' => (int) ($stats['total_orders'] ?? 0),
                    'processingOrders' => (int) ($stats['processing_orders'] ?? 0),
                    'completedOrders' => (int) ($stats['completed_orders'] ?? 0),
                    'cancelledOrders' => (int) ($stats['cancelled_orders'] ?? 0),
                    'refundedOrders' => (int) ($stats['refunded_orders'] ?? 0),
                ],
                'tickets' => [
                    'total' => (int) ($ticketCounts['total'] ?? 0),
                    'dinhLam' => (int) ($ticketCounts['abuse'] ?? 0),
                    'support' => (int) ($ticketCounts['support'] ?? 0),
                ],
                'recentOrders' => Database::all(
                    'SELECT id, order_code, product_name, amount, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5',
                    [$userId]
                ),
                'recentTransactions' => Database::all(
                    'SELECT id, amount, type, reference_code, status, wallet_type, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10',
                    [$userId]
                ),
                'walletHistory' => Database::all(
                    'SELECT id, amount, transaction_type, wallet_type, description, reference_code, created_at FROM wallet_ledger WHERE user_id = ? ORDER BY created_at DESC LIMIT 10',
                    [$userId]
                ),
            ]);
        }

        if ($path === '/api/linked-accounts' && $method === 'GET') {
            return self::response(['accounts' => Database::all(
                'SELECT id, brand, username, created_at FROM linked_accounts WHERE user_id = ? ORDER BY created_at DESC',
                [$userId]
            )]);
        }
        if ($path === '/api/linked-accounts' && $method === 'POST') {
            $brand = strtoupper($this->text($this->body['brand'] ?? '', 16));
            $username = $this->text($this->body['username'] ?? '', 64);
            if (!in_array($brand, ['QQ88', 'HI88', '78WIN', 'RR88', 'MM88', 'GG88', 'ABCVIP', 'XX8', 'U888', 'J88', 'ABC8', '88CLB', 'AD88', 'SC88', 'C168', 'CM88', 'F8BET'], true)) {
                throw new HttpException(400, 'Thương hiệu không nằm trong danh sách hỗ trợ.');
            }
            if (mb_strlen($username) < 3 || preg_match('/[\x00-\x1F]/', $username)) {
                throw new HttpException(400, 'Tên đăng nhập phải từ 3 đến 64 ký tự.');
            }
            $row = Database::one(
                'INSERT INTO linked_accounts (user_id, brand, username) VALUES (?, ?, ?) ' .
                'ON CONFLICT (user_id, brand, username) DO NOTHING RETURNING id, brand, username, created_at',
                [$userId, $brand, $username]
            );
            if ($row === null) {
                throw new HttpException(409, 'Tài khoản này đã được liên kết.');
            }
            return self::response(['account' => $row], 201);
        }
        if (preg_match('#^/api/linked-accounts/(\d{1,18})$#', $path, $match) && $method === 'DELETE') {
            $deleted = Database::run('DELETE FROM linked_accounts WHERE id = ? AND user_id = ? RETURNING id', [$match[1], $userId]);
            if ($deleted->rowCount() === 0) {
                throw new HttpException(404, 'Không tìm thấy tài khoản liên kết.');
            }
            return self::response(['success' => true]);
        }

        if ($path === '/api/orders' && $method === 'GET') {
            return self::response(['orders' => Database::all(
                "SELECT id, order_code, product_name, category, amount, status, admin_note, " .
                "CASE WHEN status = 'completed' THEN delivery_text ELSE '' END AS delivery_text, created_at, updated_at " .
                'FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 100',
                [$userId]
            )]);
        }
        if ($path === '/api/orders' && $method === 'POST') {
            $productId = (string) ($this->body['productId'] ?? '');
            if (!preg_match('/^\d{1,18}$/', $productId)) {
                throw new HttpException(400, 'Sản phẩm không hợp lệ.');
            }
            $order = Database::transaction(function () use ($userId, $productId): array {
                $product = Database::one('SELECT * FROM products WHERE id = ? AND is_active = TRUE AND ' . ProductReadinessService::sqlPredicate() . ' FOR UPDATE', [$productId]);
                if ($product === null) throw new HttpException(404, 'Sản phẩm hiện không còn bán.');
                if ($product['stock'] !== null && (int) $product['stock'] <= 0) throw new HttpException(409, 'Sản phẩm đã hết hàng.');
                Database::run(
                    "INSERT INTO users (user_id, name, username) VALUES (?, ?, ?) ON CONFLICT (user_id) DO NOTHING",
                    [$userId, $this->text($this->user['first_name'] ?? '', 120), $this->text($this->user['username'] ?? '', 64)]
                );
                $member = Database::one('SELECT balance, nyc_wallet, vip_level FROM users WHERE user_id = ? FOR UPDATE', [$userId]);
                if ($member === null) throw new HttpException(404, 'Không tìm thấy hồ sơ thành viên.');
                if ((int) ($product['vip_level'] ?? 0) > (int) ($member['vip_level'] ?? 0)) {
                    throw new HttpException(403, 'Sản phẩm yêu cầu VIP ' . (int) $product['vip_level'] . '.');
                }
                $walletType = in_array((string) $product['category'], ['otp', 'proxy'], true) ? 'NYC' : 'MAIN';
                $walletColumn = $walletType === 'NYC' ? 'nyc_wallet' : 'balance';
                $price = (int) $product['price'];
                if ((int) $member[$walletColumn] < $price) {
                    throw new HttpException(402, 'Số dư Ví ' . $walletType . ' không đủ. Vui lòng nạp/chuyển ví.');
                }
                $orderCode = self::orderCode();
                Database::run("UPDATE users SET {$walletColumn} = {$walletColumn} - ? WHERE user_id = ?", [$price, $userId]);
                $created = Database::one(
                    "INSERT INTO orders (order_code, user_id, product_id, product_name, category, amount, status) " .
                    "VALUES (?, ?, ?, ?, ?, ?, 'pending') RETURNING id, order_code, product_name, category, amount, status, created_at",
                    [$orderCode, $userId, $product['id'], $product['name'], $product['category'], $price]
                );
                Database::run(
                    "INSERT INTO wallet_ledger (reference_code, user_id, amount, transaction_type, description, wallet_type) VALUES (?, ?, ?, 'PURCHASE', ?, ?)",
                    ['order:' . $orderCode, $userId, -$price, 'Thanh toán đơn ' . $orderCode, $walletType]
                );
                Database::run(
                    "INSERT INTO transactions (user_id, amount, type, reference_code, status, wallet_type) VALUES (?, ?, 'PURCHASE', ?, 'paid', ?)",
                    [$userId, $price, $orderCode, $walletType]
                );
                if ($product['stock'] !== null) Database::run('UPDATE products SET stock = stock - 1, updated_at = NOW() WHERE id = ?', [$product['id']]);
                $created['balance'] = (int) Database::value("SELECT {$walletColumn} FROM users WHERE user_id = ?", [$userId]);
                $created['walletType'] = $walletType;
                return $created;
            });
            $this->publishUserDataChanged('order');
            return self::response(['order' => $order, 'message' => 'Đơn hàng đã được tạo và trừ đúng loại ví thành công.'], 201);
        }

        if ($path === '/api/wallet' && $method === 'GET') {
            $user = $this->userRow();
            $history = Database::all(
                'SELECT id, amount, transaction_type, description, reference_code, created_at FROM wallet_ledger WHERE user_id = ? ORDER BY created_at DESC LIMIT 60',
                [$userId]
            );
            return self::response(['balance' => $user['balance'], 'history' => $history]);
        }

        if ($path === '/api/wallet/payment-requests' && $method === 'GET') {
            return self::response(['requests' => PaymentService::forUser($userId, 20)]);
        }
        if ($path === '/api/wallet/payment-requests' && $method === 'POST') {
            $amount = $this->parseMoney($this->body['amount'] ?? null, false);
            if ($amount < 10000 || $amount > 500000000) {
                throw new HttpException(400, 'Số tiền nạp phải từ 10.000 đến 500.000.000 VNĐ.');
            }
            $settings = $this->settings(['VIETQR_BANK_ID', 'VIETQR_ACCOUNT_NO', 'VIETQR_ACCOUNT_NAME'], false);
            $bank = $settings['VIETQR_BANK_ID'] ?: (string) Env::get('VIETQR_BANK_ID', '');
            $account = $settings['VIETQR_ACCOUNT_NO'] ?: (string) Env::get('VIETQR_ACCOUNT_NO', '');
            $holder = $settings['VIETQR_ACCOUNT_NAME'] ?: (string) Env::get('VIETQR_ACCOUNT_NAME', '');
            if ($bank === '' || $account === '' || $holder === '') {
                throw new HttpException(409, 'Quản trị viên chưa cấu hình tài khoản VietQR.');
            }
            try {
                $request = PaymentService::create($userId, $amount, $this->botKey, $bank, $account, $holder);
            } catch (RuntimeException $error) {
                throw new HttpException(400, $error->getMessage());
            }
            $params = http_build_query([
                'amount' => $amount,
                'addInfo' => $request['transfer_content'],
                'accountName' => $holder,
            ]);
            $qrUrl = 'https://img.vietqr.io/image/' . rawurlencode($bank) . '-' . rawurlencode($account) . '-compact2.png?' . $params;
            $this->notifyAdmins(
                '🔔 YÊU CẦU NẠP MỚI ' . $request['payment_id'] . ' · ' . number_format($amount, 0, ',', '.') .
                ' VNĐ · User ' . $userId . ' · Nội dung CK: ' . $request['transfer_content'] . '. Cần đối soát sao kê trước khi duyệt.'
            );
            $this->publishUserDataChanged('payment-request');
            Realtime::publish('admin.payment.created', ['paymentId' => $request['payment_id'], 'botKey' => $this->botKey], 'admin');
            return self::response(['request' => $request, 'qrUrl' => $qrUrl, 'message' => 'Yêu cầu đã tạo và đang chờ Admin đối soát.'], 201);
        }
        if ($path === '/api/wallet/payment-info' && $method === 'GET') {
            $amount = $this->parseMoney($this->query['amount'] ?? 50000, false);
            if ($amount < 10000 || $amount > 500000000) {
                throw new HttpException(400, 'Số tiền nạp phải từ 10.000 đến 500.000.000 VNĐ.');
            }
            $settings = $this->settings(['VIETQR_BANK_ID', 'VIETQR_ACCOUNT_NO', 'VIETQR_ACCOUNT_NAME'], false);
            $bank = $settings['VIETQR_BANK_ID'] ?: (string) Env::get('VIETQR_BANK_ID', '');
            $account = $settings['VIETQR_ACCOUNT_NO'] ?: (string) Env::get('VIETQR_ACCOUNT_NO', '');
            $accountName = $settings['VIETQR_ACCOUNT_NAME'] ?: (string) Env::get('VIETQR_ACCOUNT_NAME', '');
            if ($bank === '' || $account === '' || $accountName === '') {
                throw new HttpException(409, 'Quản trị viên chưa cấu hình tài khoản VietQR.');
            }
            $params = http_build_query([
                'amount' => $amount,
                'addInfo' => 'NAP ' . $userId,
                'accountName' => $accountName,
            ]);
            return self::response([
                'amount' => $amount,
                'accountName' => $accountName,
                'accountNo' => $account,
                'bankId' => $bank,
                'transferContent' => 'NAP ' . $userId,
                'qrUrl' => 'https://img.vietqr.io/image/' . rawurlencode($bank) . '-' . rawurlencode($account) . '-compact2.png?' . $params,
            ]);
        }

        if ($path === '/api/referrals' && $method === 'GET') {
            $count = (int) Database::value('SELECT COUNT(*) FROM users WHERE ref_by = ?', [$userId]);
            $reward = (int) Database::value(
                "SELECT COALESCE(SUM(amount), 0) FROM wallet_ledger WHERE user_id = ? AND transaction_type = 'REFERRAL_REWARD'",
                [$userId]
            );
            $commission = $this->settings(['referral_commission_percent'], true);
            $botUrl = $this->publicBotUrl();
            $link = $botUrl !== '' ? $botUrl . '?start=ref_' . rawurlencode($userId) : '';
            return self::response([
                'totalInvited' => $count,
                'totalReward' => $reward,
                'commissionPercent' => (float) ($commission['referral_commission_percent'] ?? 0),
                'referralLink' => $link,
            ]);
        }

        if ($path === '/api/giftcodes/redeem' && $method === 'POST') {
            $code = strtoupper($this->text($this->body['code'] ?? '', 64));
            if (!preg_match('/^[A-Z0-9_-]{4,64}$/', $code)) {
                throw new HttpException(400, 'Mã giftcode không hợp lệ.');
            }
            $reward = Database::transaction(function () use ($code, $userId): int {
                $gift = Database::one('SELECT * FROM giftcodes WHERE code = ? FOR UPDATE', [$code]);
                if ($gift === null) {
                    throw new HttpException(404, 'Giftcode không tồn tại.');
                }
                if (!$this->toBool($gift['is_active']) || ($gift['expires_at'] && strtotime($gift['expires_at']) < time())) {
                    throw new HttpException(410, 'Giftcode đã hết hạn hoặc bị khóa.');
                }
                if ((int) $gift['redeemed_count'] >= (int) $gift['max_redemptions']) {
                    throw new HttpException(409, 'Giftcode đã được sử dụng hết lượt.');
                }
                $inserted = Database::run(
                    'INSERT INTO giftcode_redemptions (code, user_id, reward_amount) VALUES (?, ?, ?) ON CONFLICT DO NOTHING RETURNING code',
                    [$code, $userId, $gift['reward_amount']]
                );
                if ($inserted->rowCount() === 0) {
                    throw new HttpException(409, 'Bạn đã sử dụng giftcode này rồi.');
                }
                $reward = (int) $gift['reward_amount'];
                if ($reward > 0) {
                    Database::run('UPDATE users SET balance = balance + ? WHERE user_id = ?', [$reward, $userId]);
                    Database::run(
                        "INSERT INTO wallet_ledger (reference_code, user_id, amount, transaction_type, description) VALUES (?, ?, ?, 'GIFTCODE', ?)",
                        ['giftcode:' . $code . ':' . $userId, $userId, $reward, 'Nhận thưởng giftcode ' . $code]
                    );
                    Database::run(
                        "INSERT INTO transactions (user_id, amount, type, reference_code, status) VALUES (?, ?, 'GIFTCODE', ?, 'paid')",
                        [$userId, $reward, $code]
                    );
                }
                Database::run('UPDATE giftcodes SET redeemed_count = redeemed_count + 1 WHERE code = ?', [$code]);
                return $reward;
            });
            return self::response([
                'success' => true,
                'reward' => $reward,
                'balance' => $this->userRow()['balance'],
                'message' => $reward > 0 ? 'Đã cộng thưởng vào ví của bạn.' : 'Giftcode đã được ghi nhận.',
            ]);
        }

        if ($path === '/api/support/tickets' && $method === 'GET') {
            return self::response(['tickets' => Database::all(
                'SELECT id, ticket_type, subject, message, status, priority, admin_reply, created_at, updated_at FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 50',
                [$userId]
            )]);
        }
        if ($path === '/api/support/tickets' && $method === 'POST') {
            $ticketType = $this->text($this->body['ticketType'] ?? 'support', 24);
            if (!in_array($ticketType, ['dinh_lam', 'support'], true)) {
                throw new HttpException(400, 'Loại yêu cầu hỗ trợ không hợp lệ.');
            }
            $subject = $this->text($this->body['subject'] ?? '', 120);
            $message = $this->text($this->body['message'] ?? '', 3000);
            if (mb_strlen($subject) < 3 || mb_strlen($message) < 5) {
                throw new HttpException(400, 'Vui lòng nhập tiêu đề và nội dung yêu cầu.');
            }
            $ticket = Database::one(
                'INSERT INTO support_tickets (user_id, ticket_type, subject, message) VALUES (?, ?, ?, ?) RETURNING id, ticket_type, subject, message, status, created_at',
                [$userId, $ticketType, $subject, $message]
            );
            $this->publishUserDataChanged('support-ticket');
            Realtime::publish('admin.support.created', ['ticketId' => (int) $ticket['id'], 'type' => $ticketType], 'admin');
            return self::response(['ticket' => $ticket, 'message' => 'Đã gửi yêu cầu hỗ trợ.'], 201);
        }

        if ($path === '/api/vouchers' && $method === 'GET') {
            return self::response([
                'eligibleOrders' => VoucherService::eligibleOrders($userId),
                'requests' => VoucherService::forUser($userId),
            ]);
        }
        if ($path === '/api/vouchers' && $method === 'POST') {
            $orderId = filter_var($this->body['orderId'] ?? null, FILTER_VALIDATE_INT);
            $reason = $this->text($this->body['reason'] ?? '', 1000);
            if ($orderId === false || $orderId < 1 || mb_strlen($reason) < 5) {
                throw new HttpException(400, 'Chọn đơn hàng và mô tả lý do yêu cầu voucher (ít nhất 5 ký tự).');
            }
            try {
                $request = VoucherService::create($userId, (int) $orderId, $reason);
                $this->publishUserDataChanged('voucher-request');
                Realtime::publish('admin.voucher.created', ['voucherId' => (int) $request['id'], 'orderId' => (int) $orderId], 'admin');
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            $this->notifyAdmins('🎟️ YÊU CẦU VOUCHER 72H #' . $request['id'] . ' · Đơn ' . $request['order_code'] . ' · User ' . $userId . '. Vui lòng kiểm tra lịch sử đơn trước khi duyệt.');
            return self::response(['request' => $request, 'message' => 'Đã gửi yêu cầu voucher để Admin kiểm tra.'], 201);
        }

        if ($path === '/api/vip' && $method === 'GET') {
            return self::response([
                ...VipService::status($userId),
                'products' => $this->listProducts('vip', false),
            ]);
        }

        if ($path === '/api/vip/checkin' && $method === 'POST') {
            try {
                $result = VipService::checkin($userId);
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            return self::response([
                'checkin' => $result,
                'message' => $result['alreadyCheckedIn']
                    ? 'Bạn đã điểm danh hôm nay.'
                    : 'Điểm danh thành công; điểm đã được cộng vào điểm thành viên.',
            ]);
        }

        if ($path === '/api/matchmaking/status' && $method === 'GET') {
            return self::response(MatchmakingService::status($userId));
        }
        if ($path === '/api/matchmaking/history' && $method === 'GET') {
            return self::response(['matches' => MatchmakingService::history($userId)]);
        }
        if ($path === '/api/matchmaking/leaderboard' && $method === 'GET') {
            return self::response(['players' => MatchmakingService::leaderboard(20)]);
        }
        if ($path === '/api/matchmaking/queue' && $method === 'POST') {
            try {
                $result = MatchmakingService::joinQueue($userId, (string) ($this->body['mode'] ?? 'quick'));
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            Realtime::publish('matchmaking.queue.changed', ['userId' => $userId, 'state' => $result['state'] ?? 'unknown'], 'admin');
            if (($result['state'] ?? '') === 'matched' && isset($result['match']['opponentId'])) {
                $this->notify((string) $result['match']['opponentId'], '⚔️ HENDY đã tìm được đối thủ. Hãy mở Bot và xác nhận trận ghép đôi trong 3 phút.');
            }
            return self::response($result, ($result['state'] ?? '') === 'matched' ? 201 : 200);
        }
        if ($path === '/api/matchmaking/queue/cancel' && $method === 'POST') {
            return self::response(['cancelled' => MatchmakingService::cancelQueue($userId)]);
        }
        if (preg_match('#^/api/matchmaking/matches/(\\d{1,18})/(accept|decline)$#', $path, $match) && $method === 'POST') {
            try {
                $result = $match[2] === 'accept'
                    ? MatchmakingService::acceptMatch($userId, (int) $match[1])
                    : MatchmakingService::declineMatch($userId, (int) $match[1]);
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            if (($result['state'] ?? '') === 'active') {
                $this->notify((string) $result['match']['opponentId'], '✅ Cả hai người đã xác nhận. Trận HENDY #' . (int) $match[1] . ' bắt đầu!');
            } elseif (($result['state'] ?? '') === 'cancelled' && !empty($result['opponentId'])) {
                $this->notify((string) $result['opponentId'], 'ℹ️ Đối thủ đã từ chối trận ghép đôi. Bạn có thể vào hàng chờ lại.');
            }
            return self::response($result);
        }
        if (preg_match('#^/api/matchmaking/matches/(\\d{1,18})/result$#', $path, $match) && $method === 'POST') {
            $scoreFor = filter_var($this->body['scoreFor'] ?? null, FILTER_VALIDATE_INT);
            $scoreAgainst = filter_var($this->body['scoreAgainst'] ?? null, FILTER_VALIDATE_INT);
            if ($scoreFor === false || $scoreAgainst === false) throw new HttpException(400, 'Điểm số phải là số nguyên.');
            try {
                $result = MatchmakingService::submitResult($userId, (int) $match[1], $scoreFor, $scoreAgainst);
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            if (($result['state'] ?? '') === 'disputed') {
                $this->notify((string) $result['opponentId'], '⚠️ Hai báo cáo kết quả trận #' . (int) $match[1] . ' không khớp. Admin sẽ kiểm tra trước khi cộng điểm.');
                Realtime::publish('admin.matchmaking.disputed', ['matchId' => (int) $match[1]], 'admin');
            } elseif (($result['state'] ?? '') === 'pending' && !empty($result['opponentId'])) {
                $this->notify((string) $result['opponentId'], '📊 Đối thủ đã gửi kết quả trận #' . (int) $match[1] . '. Hãy gửi kết quả theo góc nhìn của bạn để xác nhận.');
            } elseif (($result['state'] ?? '') === 'confirmed') {
                $this->notify((string) $result['playerOne'], '🏁 Trận #' . (int) $match[1] . ' đã xác nhận. Điểm thưởng: ' . (int) $result['pointsAwardedOne'] . '. Elo: ' . (int) $result['ratingDeltaOne'] . '.');
                $this->notify((string) $result['playerTwo'], '🏁 Trận #' . (int) $match[1] . ' đã xác nhận. Điểm thưởng: ' . (int) $result['pointsAwardedTwo'] . '. Elo: ' . (int) $result['ratingDeltaTwo'] . '.');
            }
            return self::response($result);
        }

        if ($path === '/api/proxy/homeproxy/status' && $method === 'GET') return self::response(ProxyService::userStatus($userId));
        if ($path === '/api/proxy/homeproxy/connect' && $method === 'POST') {
            try { $status=ProxyService::connect($userId,(string)($this->body['apiKey']??'')); }
            catch (RuntimeException $error) { throw new HttpException(409,$error->getMessage()); }
            return self::response(['status'=>$status,'message'=>'Đã lưu kết nối HomeProxy an toàn. API Key được mã hóa ở máy chủ.']);
        }
        if ($path === '/api/proxy/homeproxy/connection' && $method === 'DELETE') return self::response(['status'=>ProxyService::disconnect($userId),'message'=>'Đã ngắt kết nối HomeProxy.']);
        if (in_array($path,['/api/proxy/homeproxy/check','/api/proxy/homeproxy/rotate'],true) && $method === 'POST') {
            try { $result=$path==='/api/proxy/homeproxy/check'?ProxyService::check($userId):ProxyService::rotate($userId); }
            catch (RuntimeException $error) { throw new HttpException(409,$error->getMessage()); }
            return self::response(['result'=>$result]);
        }

        if (str_starts_with($path, '/api/admin/')) {
            $this->requireAdmin();
            return $this->handleAdmin($method, $path);
        }

        throw new HttpException(404, 'Không tìm thấy API endpoint.');
    }

    private function handleAdmin(string $method, string $path): array
    {
        if ($method === 'GET' && $path === '/api/admin/service-readiness') {
            return self::response(ServiceReadinessService::snapshot());
        }
        if ($method === 'GET' && $path === '/api/admin/provider-registry') {
            return self::response(ProviderRegistryService::snapshot());
        }
        if ($method === 'POST' && $path === '/api/admin/provider-registry/providers') {
            try {
                $provider = ProviderRegistryService::saveProvider($this->body, (string) $this->user['id']);
            } catch (RuntimeException $error) {
                throw new HttpException(400, $error->getMessage());
            } catch (Throwable $error) {
                throw new HttpException(409, 'Không thể lưu nhà cung cấp. Hãy kiểm tra mã nhà cung cấp có bị trùng không.');
            }
            $this->audit('provider_registry.provider.create', (string) $provider['id'], [
                'serviceKey' => $provider['serviceKey'],
                'providerKey' => $provider['providerKey'],
                'adapterKey' => $provider['adapterKey'],
                'enabled' => $provider['enabled'],
            ]);
            return self::response(['provider' => $provider, 'message' => 'Đã lưu thông tin nhà cung cấp. Cấu hình chưa đồng nghĩa với API đã xác minh.'], 201);
        }
        if (preg_match('#^/api/admin/provider-registry/providers/(\\d+)/check$#', $path, $providerMatch) && $method === 'POST') {
            try {
                $provider = ProviderRegistryService::checkProvider((int) $providerMatch[1]);
            } catch (RuntimeException $error) {
                throw new HttpException(404, $error->getMessage());
            }
            $this->audit('provider_registry.provider.check', (string) $provider['id'], [
                'status' => $provider['lastCheckStatus'],
                'serviceKey' => $provider['serviceKey'],
                'providerKey' => $provider['providerKey'],
            ]);
            return self::response(['provider' => $provider, 'message' => $provider['lastCheckMessage'], 'isLiveApiTest' => false]);
        }
        if (preg_match('#^/api/admin/provider-registry/providers/(\\d+)$#', $path, $providerMatch) && $method === 'PATCH') {
            try {
                $provider = ProviderRegistryService::saveProvider($this->body, (string) $this->user['id'], (int) $providerMatch[1]);
            } catch (RuntimeException $error) {
                throw new HttpException(400, $error->getMessage());
            } catch (Throwable $error) {
                throw new HttpException(409, 'Không thể cập nhật nhà cung cấp. Hãy kiểm tra mã nhà cung cấp có bị trùng không.');
            }
            $this->audit('provider_registry.provider.update', (string) $provider['id'], [
                'serviceKey' => $provider['serviceKey'],
                'providerKey' => $provider['providerKey'],
                'adapterKey' => $provider['adapterKey'],
                'enabled' => $provider['enabled'],
            ]);
            return self::response(['provider' => $provider, 'message' => 'Đã cập nhật nhà cung cấp.']);
        }
        if (preg_match('#^/api/admin/provider-registry/providers/(\\d+)$#', $path, $providerMatch) && $method === 'DELETE') {
            try {
                ProviderRegistryService::deleteProvider((int) $providerMatch[1]);
            } catch (RuntimeException $error) {
                throw new HttpException(404, $error->getMessage());
            }
            $this->audit('provider_registry.provider.delete', $providerMatch[1], []);
            return self::response(['success' => true, 'message' => 'Đã xóa cấu hình nhà cung cấp khỏi Registry.']);
        }
        if (preg_match('#^/api/admin/provider-registry/services/([a-z][a-z0-9_]{1,63})$#', $path, $serviceMatch) && $method === 'PATCH') {
            try {
                $service = ProviderRegistryService::saveService($serviceMatch[1], $this->body, (string) $this->user['id']);
            } catch (RuntimeException $error) {
                throw new HttpException(400, $error->getMessage());
            }
            $this->audit('provider_registry.service.update', $serviceMatch[1], [
                'enabled' => $service['enabled'],
                'maintenanceMode' => $service['maintenanceMode'],
            ]);
            return self::response(['service' => $service, 'message' => 'Đã cập nhật cờ quản lý dịch vụ.']);
        }
        if ($method === 'GET' && $path === '/api/admin/ai-media') {
            return self::response(AiMediaService::adminSnapshot());
        }
        if (preg_match('#^/api/admin/ai-media/jobs/([0-9a-f-]{36})/retry$#i', $path, $jobMatch) && $method === 'POST') {
            try {
                $job = AiMediaService::retryJob($jobMatch[1]);
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            $this->audit('ai_media.job.retry', (string) $job['id'], ['jobType' => $job['job_type'], 'userId' => $job['user_id']]);
            return self::response(['job' => $job, 'message' => 'Đã đưa tác vụ AI trở lại hàng chờ.']);
        }
        if ($method === 'PATCH' && $path === '/api/admin/ai-media') {
            try {
                $result = AiMediaService::setFeatures($this->body);
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            $this->audit('ai_media.settings.update', 'ai-studio', [
                'enabled' => (bool) ($this->body['enabled'] ?? false),
                'musicEnabled' => (bool) ($this->body['musicEnabled'] ?? false),
                'imageEnabled' => (bool) ($this->body['imageEnabled'] ?? false),
            ]);
            return self::response(['aiMedia' => $result, 'message' => 'Đã lưu cấu hình AI Studio.']);
        }
        if ($method === 'GET' && $path === '/api/admin/ai-cover') {
            return self::response(AiCoverService::adminSnapshot());
        }
        if ($method === 'PATCH' && $path === '/api/admin/ai-cover/settings') {
            $enabled = $this->toBool($this->body['enabled'] ?? false);
            try {
                $result = AiCoverService::setServiceEnabled($enabled, (string) $this->user['id']);
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            $this->audit('ai_cover.service.toggle', 'ai-cover', ['enabled' => $enabled]);
            return self::response(['aiCover' => $result, 'message' => $enabled ? 'Đã bật dịch vụ AI Cover.' : 'Đã tạm tắt dịch vụ AI Cover.']);
        }
        if (preg_match('#^/api/admin/ai-cover/voices/([a-z0-9][a-z0-9_-]{1,47})$#', $path, $voiceMatch) && $method === 'PATCH') {
            $enabled = $this->toBool($this->body['enabled'] ?? false);
            try {
                AiCoverService::setVoiceEnabled($voiceMatch[1], $enabled, (string) $this->user['id']);
            } catch (RuntimeException $error) {
                throw new HttpException(400, $error->getMessage());
            }
            $this->audit('ai_cover.voice.toggle', $voiceMatch[1], ['enabled' => $enabled]);
            return self::response([
                'success' => true,
                'message' => $enabled ? 'Đã bật trạng thái bán preset. Hệ thống vẫn yêu cầu kiểm tra khả dụng gần đây.' : 'Đã tắt preset AI Cover.',
            ]);
        }
        if (preg_match('#^/api/admin/ai-cover/voices/([a-z0-9][a-z0-9_-]{1,47})/verify$#', $path, $voiceMatch) && $method === 'POST') {
            try {
                $result = AiCoverService::verifyVoice($voiceMatch[1]);
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            $this->audit('ai_cover.voice.verify', $voiceMatch[1], ['available' => (bool) ($result['available'] ?? false)]);
            return self::response(['voice' => $result, 'message' => $result['message'] ?? 'Đã kiểm tra preset.']);
        }
        if (preg_match('#^/api/admin/ai-cover/jobs/([0-9a-f-]{36})/retry$#i', $path, $jobMatch) && $method === 'POST') {
            try {
                $job = AiCoverService::retryJob($jobMatch[1], (string) $this->user['id']);
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            $this->notify((string) $job['telegram_user_id'], 'Tác vụ AI Cover ' . substr((string) $job['id'], 0, 8) . ' đã được đưa lại vào hàng chờ.');
            $this->audit('ai_cover.job.retry', $jobMatch[1], []);
            return self::response(['job' => $job, 'message' => 'Đã đưa tác vụ AI Cover trở lại hàng chờ.']);
        }
        if ($method === 'GET' && $path === '/api/admin/code-telegram') {
            $sources = Database::all(
                "SELECT s.*,
                    (SELECT COUNT(*) FROM code_telegram_posts p WHERE p.source_id = s.id) AS post_count,
                    (SELECT COUNT(*) FROM code_telegram_codes c WHERE c.source_id = s.id AND c.status = 'active') AS active_codes,
                    (SELECT COUNT(*) FROM code_telegram_codes c WHERE c.source_id = s.id AND c.status = 'pending_review') AS pending_codes
                 FROM code_telegram_sources s ORDER BY s.created_at DESC LIMIT 200"
            );
            $sources = array_map(static function (array $row): array {
                $row['is_active'] = filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN);
                $row['auto_publish'] = filter_var($row['auto_publish'], FILTER_VALIDATE_BOOLEAN);
                $row['post_count'] = (int) $row['post_count'];
                $row['active_codes'] = (int) $row['active_codes'];
                $row['pending_codes'] = (int) $row['pending_codes'];
                return $row;
            }, $sources);
            $codes = Database::all(
                "SELECT c.*, s.title AS source_title, p.source_link, p.media_kind
                 FROM code_telegram_codes c
                 JOIN code_telegram_sources s ON s.id = c.source_id
                 JOIN code_telegram_posts p ON p.id = c.post_id
                 ORDER BY CASE c.status WHEN 'pending_review' THEN 0 ELSE 1 END, c.created_at DESC
                 LIMIT 100"
            );
            $posts = Database::all(
                "SELECT p.id, p.telegram_message_id, p.media_kind, p.status, p.extracted_count,
                        p.error_message, p.source_link, p.created_at, s.title AS source_title
                 FROM code_telegram_posts p JOIN code_telegram_sources s ON s.id = p.source_id
                 ORDER BY p.created_at DESC LIMIT 50"
            );
            $requests = Database::all(
                "SELECT a.id AS account_id, a.request_id, a.username, a.status AS account_status,
                        a.code_value, a.charged_amount, a.result_note, a.created_at,
                        r.user_id, r.service_name_snapshot, r.brand_snapshot, r.code_type_snapshot,
                        r.price_min_snapshot, r.price_max_snapshot, r.status AS request_status
                 FROM code_telegram_request_accounts a
                 JOIN code_telegram_requests r ON r.id = a.request_id
                 ORDER BY CASE a.status WHEN 'queued' THEN 0 WHEN 'processing' THEN 1 ELSE 2 END,
                          a.created_at DESC
                 LIMIT 200"
            );
            return self::response([
                'enabled' => CodeTelegramService::enabled(),
                'sources' => $sources,
                'codes' => $codes,
                'posts' => $posts,
                'requests' => $requests,
                'stats' => [
                    'sources' => (int) Database::value('SELECT COUNT(*) FROM code_telegram_sources'),
                    'activeSources' => (int) Database::value('SELECT COUNT(*) FROM code_telegram_sources WHERE is_active = TRUE'),
                    'queuedPosts' => (int) Database::value("SELECT COUNT(*) FROM code_telegram_posts WHERE status = 'queued'"),
                    'failedPosts' => (int) Database::value("SELECT COUNT(*) FROM code_telegram_posts WHERE status = 'failed'"),
                    'pendingCodes' => (int) Database::value("SELECT COUNT(*) FROM code_telegram_codes WHERE status = 'pending_review'"),
                    'activeCodes' => (int) Database::value("SELECT COUNT(*) FROM code_telegram_codes WHERE status = 'active'"),
                ],
            ]);
        }

        if ($method === 'POST' && $path === '/api/admin/code-telegram/sources') {
            $sourceInput = $this->text($this->body['chat'] ?? '', 100);
            if ($sourceInput === '') throw new HttpException(400, 'Nhập @username hoặc channel ID.');
            if (!preg_match('/^(?:@[A-Za-z0-9_]{5,32}|-100[0-9]{5,18})$/', $sourceInput)) {
                throw new HttpException(400, 'Nguồn phải là @username kênh công khai hoặc channel ID dạng -100...');
            }

            try {
                $primaryToken = TelegramAuth::token('primary');
                if ($primaryToken === '') throw new RuntimeException('Bot chính chưa cấu hình token.');
                $telegram = new TelegramApi($primaryToken);
                $chat = $telegram->call('getChat', ['chat_id' => $sourceInput], 15, 5);
                if (($chat['type'] ?? '') !== 'channel') throw new RuntimeException('Nguồn được chọn không phải Telegram channel.');
                $bot = $telegram->call('getMe', [], 15, 5);
                $member = $telegram->call('getChatMember', [
                    'chat_id' => (string) $chat['id'],
                    'user_id' => (int) $bot['id'],
                ], 15, 5);
                if (!in_array((string) ($member['status'] ?? ''), ['administrator', 'creator'], true)) {
                    throw new RuntimeException('Hãy thêm Bot HENDY làm quản trị viên của kênh trước khi kết nối.');
                }
            } catch (Throwable $error) {
                throw new HttpException(409, $error->getMessage());
            }

            $chatId = (string) $chat['id'];
            $username = trim((string) ($chat['username'] ?? ''));
            $title = $this->text($this->body['title'] ?? ($chat['title'] ?? $sourceInput), 160);
            if ($title === '') $title = (string) ($chat['title'] ?? $sourceInput);
            $autoPublish = $this->toBool($this->body['autoPublish'] ?? false);
            $source = Database::one(
                'INSERT INTO code_telegram_sources (chat_id, username, title, added_by, auto_publish) VALUES (?, ?, ?, ?, ?) ' .
                'ON CONFLICT (chat_id) DO UPDATE SET username = EXCLUDED.username, title = EXCLUDED.title, is_active = TRUE, ' .
                'auto_publish = EXCLUDED.auto_publish, updated_at = NOW() RETURNING id, chat_id, username, title, is_active, auto_publish',
                [$chatId, $username, $title, (string) $this->user['id'], $autoPublish]
            );
            $this->audit('code_telegram.source.add', $chatId, ['title' => $title, 'autoPublish' => $autoPublish]);
            Realtime::publish('admin.code_telegram.changed', ['sourceId' => (int) ($source['id'] ?? 0)], 'admin');
            return self::response(['source' => $source, 'message' => 'Đã kết nối kênh. Bot đã xác minh quyền quản trị kênh.'], 201);
        }

        if (preg_match('#^/api/admin/code-telegram/sources/([0-9]+)$#', $path, $sourceMatch) && $method === 'PATCH') {
            $sourceId = (int) $sourceMatch[1];
            $active = array_key_exists('isActive', $this->body) ? $this->toBool($this->body['isActive']) : null;
            $autoPublish = array_key_exists('autoPublish', $this->body) ? $this->toBool($this->body['autoPublish']) : null;
            if ($active === null && $autoPublish === null) throw new HttpException(400, 'Không có trường cấu hình để cập nhật.');
            $source = Database::one(
                'UPDATE code_telegram_sources SET is_active = COALESCE(?, is_active), auto_publish = COALESCE(?, auto_publish), updated_at = NOW() WHERE id = ? RETURNING id, chat_id, title, is_active, auto_publish',
                [$active, $autoPublish, $sourceId]
            );
            if ($source === null) throw new HttpException(404, 'Không tìm thấy kênh nguồn.');
            $this->audit('code_telegram.source.update', (string) $sourceId, ['isActive' => $active, 'autoPublish' => $autoPublish]);
            return self::response(['source' => $source, 'message' => 'Đã cập nhật kênh nguồn.']);
        }

        if (preg_match('#^/api/admin/code-telegram/codes/([0-9]+)$#', $path, $codeMatch) && $method === 'PATCH') {
            $status = $this->text($this->body['status'] ?? '', 24);
            if (!in_array($status, ['active', 'used', 'rejected', 'expired'], true)) {
                throw new HttpException(400, 'Trạng thái mã không hợp lệ.');
            }
            $code = Database::one(
                'UPDATE code_telegram_codes SET status = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ? RETURNING id, code_value, category, status',
                [$status, (string) $this->user['id'], (int) $codeMatch[1]]
            );
            if ($code === null) throw new HttpException(404, 'Không tìm thấy mã Code Telegram.');
            $this->audit('code_telegram.code.' . $status, (string) $codeMatch[1], ['code' => (string) $code['code_value']]);
            Realtime::publish('admin.code_telegram.changed', ['codeId' => (int) $codeMatch[1]], 'admin');
            return self::response(['code' => $code, 'message' => 'Đã cập nhật trạng thái mã.']);
        }

        if (preg_match('#^/api/admin/code-telegram/accounts/([0-9]+)$#', $path, $accountMatch) && $method === 'PATCH') {
            $accountId = (int) $accountMatch[1];
            $status = $this->text($this->body['status'] ?? '', 24);
            if (!in_array($status, ['processing', 'failed', 'completed'], true)) {
                throw new HttpException(400, 'Trạng thái tài khoản không hợp lệ.');
            }

            if ($status !== 'completed') {
                $row = Database::one(
                    "UPDATE code_telegram_request_accounts
                     SET status = ?, result_note = ?, processed_by = ?, updated_at = NOW(),
                         processed_at = CASE WHEN ? = 'failed' THEN NOW() ELSE NULL END
                     WHERE id = ? AND status <> 'completed'
                     RETURNING id, request_id, username, status",
                    [$status, $this->text($this->body['note'] ?? '', 500), (string) $this->user['id'], $status, $accountId]
                );
                if ($row === null) throw new HttpException(409, 'Tài khoản không tồn tại hoặc đã được xử lý thành công.');
                Database::run(
                    "UPDATE code_telegram_requests r
                     SET status = CASE
                         WHEN EXISTS (SELECT 1 FROM code_telegram_request_accounts a WHERE a.request_id = r.id AND a.status = 'processing') THEN 'processing'
                         WHEN EXISTS (SELECT 1 FROM code_telegram_request_accounts a WHERE a.request_id = r.id AND a.status = 'queued') THEN 'queued'
                         ELSE 'partial' END,
                         updated_at = NOW()
                     WHERE r.id = ?",
                    [(int) $row['request_id']]
                );
                $this->audit('code_telegram.account.' . $status, (string) $accountId, ['username' => (string) $row['username']]);
                return self::response(['account' => $row, 'message' => 'Đã cập nhật trạng thái tài khoản.']);
            }

            $codeValue = strtoupper(trim($this->text($this->body['codeValue'] ?? '', 64)));
            $chargeAmount = $this->parseMoney($this->body['chargeAmount'] ?? null, false);
            if (preg_match('/^[A-Z0-9][A-Z0-9_-]{4,63}$/', $codeValue) !== 1) {
                throw new HttpException(400, 'Mã xác nhận thành công không hợp lệ.');
            }

            try {
                $result = Database::transaction(function () use ($accountId, $codeValue, $chargeAmount): array {
                    $account = Database::one(
                        "SELECT a.id, a.request_id, a.username, a.status,
                                r.user_id, r.service_id, r.service_name_snapshot,
                                r.price_min_snapshot, r.price_max_snapshot, r.status AS request_status
                         FROM code_telegram_request_accounts a
                         JOIN code_telegram_requests r ON r.id = a.request_id
                         WHERE a.id = ? FOR UPDATE OF a, r",
                        [$accountId]
                    );
                    if ($account === null) throw new HttpException(404, 'Không tìm thấy tài khoản yêu cầu.');
                    if ($account['status'] === 'completed') throw new HttpException(409, 'Tài khoản này đã được tính phí và hoàn tất.');
                    if ($chargeAmount < (int) $account['price_min_snapshot'] || $chargeAmount > (int) $account['price_max_snapshot']) {
                        throw new HttpException(400, 'Phí thành công phải nằm trong khoảng giá của dịch vụ.');
                    }

                    $member = Database::one('SELECT balance FROM users WHERE user_id = ? FOR UPDATE', [(string) $account['user_id']]);
                    if ($member === null) throw new HttpException(404, 'Không tìm thấy thành viên.');
                    if ((int) $member['balance'] < $chargeAmount) {
                        throw new HttpException(402, 'Ví Chính của thành viên không đủ để thu phí thành công.');
                    }

                    $reference = 'code-telegram:account:' . $accountId;
                    Database::run('UPDATE users SET balance = balance - ? WHERE user_id = ?', [$chargeAmount, (string) $account['user_id']]);
                    Database::run(
                        "INSERT INTO wallet_ledger (reference_code, user_id, amount, transaction_type, description, wallet_type)
                         VALUES (?, ?, ?, 'PURCHASE', ?, 'MAIN')",
                        [$reference, (string) $account['user_id'], -$chargeAmount, 'Code Telegram thành công · ' . $account['service_name_snapshot']]
                    );
                    Database::run(
                        "INSERT INTO transactions (user_id, amount, type, reference_code, status, wallet_type)
                         VALUES (?, ?, 'PURCHASE', ?, 'paid', 'MAIN')",
                        [(string) $account['user_id'], $chargeAmount, $reference]
                    );
                    Database::run(
                        "UPDATE code_telegram_request_accounts
                         SET status = 'completed', code_value = ?, charged_amount = ?, result_note = ?,
                             processed_by = ?, processed_at = NOW(), updated_at = NOW()
                         WHERE id = ?",
                        [
                            $codeValue, $chargeAmount,
                            $this->text($this->body['note'] ?? 'Đã xử lý mã thành công.', 500),
                            (string) $this->user['id'], $accountId,
                        ]
                    );
                    Database::run(
                        "UPDATE code_telegram_requests r
                         SET status = CASE
                             WHEN NOT EXISTS (SELECT 1 FROM code_telegram_request_accounts a WHERE a.request_id = r.id AND a.status <> 'completed') THEN 'completed'
                             WHEN EXISTS (SELECT 1 FROM code_telegram_request_accounts a WHERE a.request_id = r.id AND a.status = 'completed') THEN 'partial'
                             ELSE 'queued' END,
                             updated_at = NOW()
                         WHERE r.id = ?",
                        [(int) $account['request_id']]
                    );
                    $balance = (int) Database::value('SELECT balance FROM users WHERE user_id = ?', [(string) $account['user_id']]);
                    return [
                        'accountId' => $accountId,
                        'requestId' => (int) $account['request_id'],
                        'userId' => (string) $account['user_id'],
                        'username' => (string) $account['username'],
                        'service' => (string) $account['service_name_snapshot'],
                        'code' => $codeValue,
                        'chargedAmount' => $chargeAmount,
                        'balance' => $balance,
                    ];
                });
            } catch (HttpException $error) {
                throw $error;
            } catch (Throwable $error) {
                throw new HttpException(409, 'Không thể hoàn tất và thu phí: ' . $error->getMessage());
            }

            $this->audit('code_telegram.account.completed', (string) $accountId, [
                'username' => $result['username'], 'chargedAmount' => $chargeAmount,
            ]);
            $this->notify(
                $result['userId'],
                '✅ <b>CODE TELEGRAM ĐÃ XỬ LÝ THÀNH CÔNG</b>' . "\n" .
                'Dịch vụ: <b>' . htmlspecialchars($result['service'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>' . "\n" .
                'Tài khoản: <code>' . htmlspecialchars($result['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>' . "\n" .
                'Mã: <code>' . htmlspecialchars($result['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>' . "\n" .
                'Phí đã trừ: ' . number_format($chargeAmount, 0, ',', '.') . ' VNĐ · Ví Chính còn ' .
                number_format($result['balance'], 0, ',', '.') . ' VNĐ.'
            );
            $this->publishUserDataChanged('wallet');
            return self::response(['account' => $result, 'message' => 'Đã xác nhận thành công và thu đúng phí.']);
        }

        if (preg_match('#^/api/admin/code-telegram/posts/([0-9]+)/retry$#', $path, $postMatch) && $method === 'POST') {
            $post = Database::one(
                "UPDATE code_telegram_posts SET status = 'queued', attempts = 0, error_message = '', updated_at = NOW() WHERE id = ? AND status = 'failed' RETURNING id",
                [(int) $postMatch[1]]
            );
            if ($post === null) throw new HttpException(409, 'Bài đăng không ở trạng thái lỗi hoặc không tồn tại.');
            $this->audit('code_telegram.post.retry', (string) $postMatch[1], []);
            return self::response(['post' => $post, 'message' => 'Đã đưa bài đăng trở lại hàng chờ OCR.']);
        }

        if ($method === 'GET' && $path === '/api/admin/payments') {
            return self::response(['payments' => PaymentService::pending(100), 'stats' => PaymentService::stats()]);
        }
        if (preg_match('#^/api/admin/payments/([A-Z0-9]{6,24})/(approve|reject)$#', $path, $paymentMatch) && $method === 'POST') {
            $paymentId = $paymentMatch[1];
            try {
                if ($paymentMatch[2] === 'approve') {
                    $result = PaymentService::approve($paymentId, (string) $this->user['id']);
                    $this->notify((string) $result['telegramId'],
                        '🎉 Yêu cầu nạp ' . $paymentId . ' đã được Admin duyệt. Cộng +' .
                        number_format($result['amountVnd'], 0, ',', '.') . ' VNĐ. Số dư mới: ' .
                        number_format($result['newBalance'], 0, ',', '.') . ' VNĐ.',
                        (string) ($result['botKey'] ?? $this->botKey));
                } else {
                    $result = PaymentService::reject($paymentId, (string) $this->user['id'], $this->text($this->body['note'] ?? 'Admin từ chối sau khi đối soát.', 500));
                    $this->notify((string) $result['telegramId'], 'Yêu cầu nạp ' . $paymentId . ' chưa được duyệt. Vui lòng liên hệ CSKH nếu cần hỗ trợ.', (string) ($result['botKey'] ?? $this->botKey));
                }
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            $this->audit('payment.' . $paymentMatch[2], $paymentId, ['amount' => $result['amountVnd']]);
            $this->publishUserDataChanged('payment-status');
            return self::response(['payment' => $result, 'message' => $paymentMatch[2] === 'approve' ? 'Đã duyệt và cập nhật ví.' : 'Đã từ chối; ví không thay đổi.']);
        }
        if ($method === 'GET' && $path === '/api/admin/video-jobs') {
            return self::response(['jobs' => VideoJobService::recent(100), 'stats' => VideoJobService::stats()]);
        }
        if (preg_match('#^/api/admin/video-jobs/([0-9a-f-]{36})/retry$#i', $path, $jobMatch) && $method === 'POST') {
            try {
                $job = VideoJobService::retry($jobMatch[1]);
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            $this->audit('video_job.retry', $jobMatch[1], []);
            $this->notify((string) $job['user_id'], 'Video job ' . substr((string) $job['id'], 0, 8) . ' đã được đưa lại vào hàng chờ.', (string) ($job['bot_key'] ?? $this->botKey));
            return self::response(['job' => $job, 'message' => 'Đã đưa video job vào hàng chờ.']);
        }

        if (preg_match('#^/api/admin/modules/(\\d+)/download$#', $path, $moduleDownload) && $method === 'GET') {
            $this->requireAdmin();
            $artifact = Database::one('SELECT stored_name FROM module_artifacts WHERE id = ?', [(int) $moduleDownload[1]]);
            if ($artifact === null) throw new HttpException(404, 'Không tìm thấy tệp module.');
            if (!str_starts_with((string) $artifact['stored_name'], 'modules/')) {
                throw new HttpException(409, 'Tệp cũ đang nằm trong vùng lưu trữ cục bộ, không có URL Neon Storage.');
            }
            return self::response([
                'url' => NeonStorage::presignedDownloadUrl((string) $artifact['stored_name'], 300),
                'expiresInSeconds' => 300,
            ]);
        }

        if ($method === 'GET' && $path === '/api/admin/modules') {
            return self::response(['artifacts' => Database::all('SELECT id, module_key, original_name, mime_type, sha256, status, created_at FROM module_artifacts ORDER BY created_at DESC LIMIT 100')]);
        }
        if ($method === 'POST' && $path === '/api/realtime-ticket') {
            $audience = $this->isAdmin() ? 'admin' : 'users';
            $ticket = Realtime::issueTicket($userId, $audience);
            $url = trim((string) Env::get('WEBSOCKET_URL', ''));
            if ($url === '' || !preg_match('#^wss://#i', $url)) throw new HttpException(503, 'WebSocket WSS chưa được cấu hình.');
            return self::response(['ticket' => $ticket, 'url' => $url, 'audience' => $audience, 'expiresInSeconds' => 60]);
        }
        if ($method === 'POST' && $path === '/api/admin/realtime-ticket') {
            $ticket = Realtime::issueAdminTicket($userId);
            $url = trim((string) Env::get('WEBSOCKET_URL', ''));
            if ($url === '' || !preg_match('#^wss://#i', $url)) throw new HttpException(503, 'WebSocket WSS chưa được cấu hình.');
            return self::response(['ticket' => $ticket, 'url' => $url, 'audience' => 'admin', 'expiresInSeconds' => 60]);
        }
        if ($method === 'GET' && $path === '/api/admin/audit-log') {
            $limit = max(10, min(100, (int) ($this->query['limit'] ?? 50)));
            return self::response(['events' => Database::all(
                'SELECT id, admin_user_id, action, target, details, created_at FROM admin_audit ORDER BY created_at DESC LIMIT ?',
                [$limit]
            )]);
        }
        if ($method === 'GET' && $path === '/api/admin/overview') {
            $paymentStats = PaymentService::stats();
            $videoStats = VideoJobService::stats();
            return self::response([
                'users' => (int) Database::value('SELECT COUNT(*) FROM users'),
                'activeProducts' => (int) Database::value('SELECT COUNT(*) FROM products WHERE is_active = TRUE'),
                'ordersLast30Days' => (int) Database::value("SELECT COUNT(*) FROM orders WHERE created_at >= NOW() - INTERVAL '30 days'"),
                'completedOrderValue' => (int) Database::value("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status = 'completed'"),
                'openTickets' => (int) Database::value("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open', 'processing')"),
                'pendingPayments' => $paymentStats['pending'],
                'queuedVideoJobs' => $videoStats['queued'],
                'activeVideoJobs' => $videoStats['processing'],
            ]);
        }
        if ($method === 'GET' && $path === '/api/admin/runtime') {
            $primary = TelegramAuth::token('primary') !== '';
            return self::response([
                'bots' => [
                    ['key' => 'primary', 'status' => $primary ? 'webhook-configured' : 'missing-token', 'configured' => $primary],
                ],
                'timestamp' => gmdate(DATE_ATOM),
            ]);
        }
        if ($method === 'GET' && $path === '/api/admin/operations') return self::response(OperationsService::all());
        if ($method === 'PATCH' && $path === '/api/admin/operations') {
            try { $result=OperationsService::update($this->body); }
            catch (RuntimeException $error) { throw new HttpException(400,$error->getMessage()); }
            $modes=[];
            foreach (($this->body['bots']??[]) as $botKey=>$settings) if(is_array($settings)) $modes[$botKey]=['mode'=>$settings['mode']??'off','until'=>$settings['until']??null];
            $this->audit('operations.update','telegram-bots',$modes);
            return self::response(['operations'=>$result,'message'=>'Đã cập nhật chế độ vận hành bot.']);
        }
        if ($method === 'GET' && $path === '/api/admin/proxy') return self::response(ProxyService::adminStatus());
        if ($method === 'PATCH' && $path === '/api/admin/proxy') {
            $enabled=$this->toBool($this->body['enabled']??false);
            $result=ProxyService::setEnabled($enabled);
            $this->audit('proxy.homeproxy.toggle','homeproxy',['enabled'=>$enabled]);
            return self::response(['proxy'=>$result,'message'=>$enabled?'Đã bật tích hợp HomeProxy.':'Đã tạm tắt tích hợp HomeProxy.']);
        }

        if ($method === 'GET' && $path === '/api/admin/products') {
            $category = $this->text($this->query['category'] ?? '', 32);
            return self::response(['products' => $this->listProducts($category !== '' ? $category : null, true)]);
        }
        if ($method === 'POST' && $path === '/api/admin/products') {
            if (!ProductReadinessService::schemaReady()) {
                throw new HttpException(409, 'Database chưa áp migration nhà cung cấp/quyền nguồn. Hãy chạy telegram-bot/database/migrations/20261113_product_supplier_readiness.sql bằng database owner trước khi tạo hoặc chỉnh sửa sản phẩm.');
            }
            $name = $this->text($this->body['name'] ?? '', 120);
            $category = $this->text($this->body['category'] ?? '', 32);
            $description = $this->text($this->body['description'] ?? '', 1500);
            $brand = strtoupper($this->text($this->body['brand'] ?? '', 24));
            $price = $this->parseMoney($this->body['price'] ?? null, true);
            $days = (int) ($this->body['durationDays'] ?? 0);
            $stock = ($this->body['stock'] ?? null) === '' || ($this->body['stock'] ?? null) === null ? null : (int) $this->body['stock'];
            $vip = (int) ($this->body['vipLevel'] ?? 0);
            if (mb_strlen($name) < 2 || !in_array($category, self::CATEGORIES, true)) {
                throw new HttpException(400, 'Tên sản phẩm hoặc danh mục không hợp lệ.');
            }
            if ($category === 'minigame' && !in_array($brand, self::MINIGAME_BRANDS, true)) {
                throw new HttpException(400, 'Sản phẩm MiniGame phải chọn nhà cái hợp lệ.');
            }
            if ($days < 0 || $days > 3650 || $stock !== null && ($stock < 0 || $stock > 10000000) || $vip < 0 || $vip > 3) {
                throw new HttpException(400, 'Thời hạn, tồn kho hoặc cấp VIP không hợp lệ.');
            }
            $slug = self::slug($this->body['slug'] ?? $name);
            $image = $this->text($this->body['imageUrl'] ?? '', 500);
            self::validateHttps($image, 'Ảnh sản phẩm phải dùng HTTPS.');
            $metadata = $brand !== '' ? ['brand' => $brand] : [];
            try {
                $provider = $this->productProviderInput([]);
            } catch (RuntimeException $error) {
                throw new HttpException(400, $error->getMessage());
            }
            $product = Database::one(
                'INSERT INTO products (slug, name, category, description, price, duration_days, stock, vip_level, image_url, metadata, is_active, supplier_name, supplier_url, supplier_product_ref, source_url, source_permission_status, supplier_access_status, fulfillment_mode, credential_env_key, verified_by, verified_at) ' .
                'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, TRUE, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::timestamptz) RETURNING *',
                [$slug, $name, $category, $description, $price, $days, $stock, $vip, $image ?: null, json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $provider['supplier_name'], $provider['supplier_url'], $provider['supplier_product_ref'], $provider['source_url'],
                    $provider['source_permission_status'], $provider['supplier_access_status'], $provider['fulfillment_mode'],
                    $provider['credential_env_key'], $provider['verified_by'], $provider['verified_at']]
            );
            $this->audit('product.create', $slug, ['category' => $category, 'price' => $price, 'sourcePermissionStatus' => $provider['source_permission_status'], 'supplierAccessStatus' => $provider['supplier_access_status']]);
            return self::response(['product' => self::adminProduct($product)], 201);
        }

        if (preg_match('#^/api/admin/products/(\d{1,18})$#', $path, $match)) {
            $id = $match[1];
            if ($method === 'PATCH') {
                if (!ProductReadinessService::schemaReady()) {
                    throw new HttpException(409, 'Database chưa áp migration nhà cung cấp/quyền nguồn. Hãy chạy telegram-bot/database/migrations/20261113_product_supplier_readiness.sql bằng database owner trước khi tạo hoặc chỉnh sửa sản phẩm.');
                }
                $current = Database::one('SELECT * FROM products WHERE id = ?', [$id]);
                if ($current === null) {
                    throw new HttpException(404, 'Không tìm thấy sản phẩm.');
                }
                $name = array_key_exists('name', $this->body) ? $this->text($this->body['name'], 120) : $current['name'];
                $category = array_key_exists('category', $this->body) ? $this->text($this->body['category'], 32) : $current['category'];
                $description = array_key_exists('description', $this->body) ? $this->text($this->body['description'], 1500) : $current['description'];
                $price = array_key_exists('price', $this->body) ? $this->parseMoney($this->body['price'], true) : (int) $current['price'];
                $days = array_key_exists('durationDays', $this->body) ? (int) $this->body['durationDays'] : (int) $current['duration_days'];
                $stock = array_key_exists('stock', $this->body)
                    ? (($this->body['stock'] === '' || $this->body['stock'] === null) ? null : (int) $this->body['stock'])
                    : ($current['stock'] === null ? null : (int) $current['stock']);
                $vip = array_key_exists('vipLevel', $this->body) ? (int) $this->body['vipLevel'] : (int) $current['vip_level'];
                $active = array_key_exists('isActive', $this->body) ? $this->toBool($this->body['isActive']) : $this->toBool($current['is_active']);
                $image = array_key_exists('imageUrl', $this->body) ? $this->text($this->body['imageUrl'], 500) : (string) ($current['image_url'] ?? '');
                $metadata = self::jsonArray($current['metadata'] ?? []);
                $brand = array_key_exists('brand', $this->body)
                    ? strtoupper($this->text($this->body['brand'], 24))
                    : strtoupper((string) ($metadata['brand'] ?? ''));
                if ($category === 'minigame' && !in_array($brand, self::MINIGAME_BRANDS, true)) {
                    throw new HttpException(400, 'Sản phẩm MiniGame phải chọn nhà cái hợp lệ.');
                }
                if ($category === 'minigame') {
                    $metadata['brand'] = $brand;
                } elseif (array_key_exists('brand', $this->body)) {
                    unset($metadata['brand']);
                }
                if (mb_strlen($name) < 2 || !in_array($category, self::CATEGORIES, true) || $days < 0 || $days > 3650 || $stock !== null && $stock < 0 || $vip < 0 || $vip > 3) {
                    throw new HttpException(400, 'Thông tin sản phẩm không hợp lệ.');
                }
                self::validateHttps($image, 'Ảnh sản phẩm phải dùng HTTPS.');
                try {
                    $provider = $this->productProviderInput($current);
                } catch (RuntimeException $error) {
                    throw new HttpException(400, $error->getMessage());
                }
                $product = Database::one(
                    'UPDATE products SET name = ?, category = ?, description = ?, price = ?, duration_days = ?, stock = ?, vip_level = ?, image_url = ?, metadata = ?::jsonb, is_active = ?, supplier_name = ?, supplier_url = ?, supplier_product_ref = ?, source_url = ?, source_permission_status = ?, supplier_access_status = ?, fulfillment_mode = ?, credential_env_key = ?, verified_by = ?, verified_at = ?::timestamptz, updated_at = NOW() WHERE id = ? RETURNING *',
                    [$name, $category, $description, $price, $days, $stock, $vip, $image ?: null, json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $active,
                        $provider['supplier_name'], $provider['supplier_url'], $provider['supplier_product_ref'], $provider['source_url'],
                        $provider['source_permission_status'], $provider['supplier_access_status'], $provider['fulfillment_mode'],
                        $provider['credential_env_key'], $provider['verified_by'], $provider['verified_at'], $id]
                );
                $this->audit('product.update', $id, ['isActive' => $active, 'category' => $category, 'price' => $price, 'sourcePermissionStatus' => $provider['source_permission_status'], 'supplierAccessStatus' => $provider['supplier_access_status'], 'fulfillmentMode' => $provider['fulfillment_mode']]);
                return self::response(['product' => self::adminProduct($product)]);
            }
            if ($method === 'DELETE') {
                $deleted = Database::run('UPDATE products SET is_active = FALSE, updated_at = NOW() WHERE id = ? RETURNING id', [$id]);
                if ($deleted->rowCount() === 0) {
                    throw new HttpException(404, 'Không tìm thấy sản phẩm.');
                }
                $this->audit('product.disable', $id, []);
                return self::response(['success' => true]);
            }
        }

        if ($method === 'GET' && $path === '/api/admin/orders') {
            return self::response(['orders' => Database::all(
                'SELECT id, order_code, user_id, product_name, category, amount, status, admin_note, delivery_text, created_at, updated_at FROM orders ORDER BY created_at DESC LIMIT 200'
            )]);
        }
        if (preg_match('#^/api/admin/orders/(\d{1,18})$#', $path, $match) && $method === 'PATCH') {
            return $this->updateOrder($match[1]);
        }

        if ($method === 'GET' && $path === '/api/admin/users') {
            $search = $this->text($this->query['search'] ?? '', 80);
            $users = Database::all(
                "SELECT user_id, name, username, balance, total_deposited, vip_level, ref_by, is_blocked, blocked_reason, created_at FROM users " .
                "WHERE (? = '' OR user_id ILIKE ? OR name ILIKE ? OR username ILIKE ?) ORDER BY created_at DESC LIMIT 100",
                [$search, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%']
            );
            foreach ($users as &$user) {
                $user['balance'] = (int) $user['balance'];
                $user['total_deposited'] = (int) $user['total_deposited'];
                $user['vip_level'] = (int) $user['vip_level'];
                $user['is_blocked'] = in_array($user['is_blocked'] ?? false, [true, 1, '1', 't', 'true'], true);
            }
            unset($user);
            return self::response(['users' => $users]);
        }
        if (preg_match('#^/api/admin/users/(\d{5,20})/access$#', $path, $match) && $method === 'PATCH') {
            $targetId = $match[1];
            if (TelegramAuth::isAdmin($targetId)) {
                throw new HttpException(409, 'Không thể khóa tài khoản Admin thuộc allowlist hệ thống.');
            }
            $isBlocked = $this->toBool($this->body['isBlocked'] ?? false);
            $reason = $this->text($this->body['reason'] ?? '', 300);
            if ($isBlocked && $reason === '') {
                throw new HttpException(400, 'Cần nhập lý do khi khóa tài khoản.');
            }
            $updated = Database::one(
                'UPDATE users SET is_blocked = ?, blocked_reason = ?, blocked_at = CASE WHEN ? THEN NOW() ELSE NULL END, blocked_by = ? WHERE user_id = ? RETURNING user_id, is_blocked, blocked_reason',
                [$isBlocked, $isBlocked ? $reason : '', $isBlocked, (string) $this->user['id'], $targetId]
            );
            if ($updated === null) throw new HttpException(404, 'Không tìm thấy thành viên.');
            Database::run('DELETE FROM bot_pending_inputs WHERE user_id = ?', [$targetId]);
            if ($isBlocked) {
                Database::run('DELETE FROM legacy_login_tickets WHERE user_id = ?', [$targetId]);
            }
            $this->audit('user.access', $targetId, ['isBlocked' => $isBlocked, 'reason' => $reason]);
            $this->notify($targetId, $isBlocked
                ? '⛔ Tài khoản HENDY đã tạm khóa quyền sử dụng. Lý do: ' . $reason . '. Liên hệ CSKH để được hỗ trợ.'
                : '✅ Tài khoản HENDY đã được mở khóa. Bạn có thể sử dụng Bot trở lại.');
            $updated['is_blocked'] = in_array($updated['is_blocked'] ?? false, [true, 1, '1', 't', 'true'], true);
            return self::response(['user' => $updated, 'message' => $isBlocked ? 'Đã khóa tài khoản; dữ liệu ví và đơn hàng vẫn được giữ.' : 'Đã mở khóa tài khoản.']);
        }

        if (preg_match('#^/api/admin/users/(\d{5,20})/vip$#', $path, $match) && $method === 'PATCH') {
            $targetId = $match[1];
            $vipLevel = filter_var($this->body['vipLevel'] ?? null, FILTER_VALIDATE_INT);
            if ($vipLevel === false || $vipLevel < 0 || $vipLevel > 10) {
                throw new HttpException(400, 'Cấp VIP phải là số nguyên từ 0 đến 10.');
            }
            $updated = Database::one(
                'UPDATE users SET vip_level = ? WHERE user_id = ? RETURNING user_id, vip_level',
                [$vipLevel, $targetId]
            );
            if ($updated === null) throw new HttpException(404, 'Không tìm thấy thành viên.');
            $this->audit('user.vip', $targetId, ['vipLevel' => $vipLevel]);
            $this->notify($targetId, '👑 Cấp VIP tài khoản HENDY được cập nhật thành VIP ' . $vipLevel . '.');
            $updated['vip_level'] = (int) $updated['vip_level'];
            return self::response(['user' => $updated, 'message' => 'Đã cập nhật cấp VIP thành ' . $vipLevel . '.']);
        }

        if (preg_match('#^/api/admin/users/(\d{5,20})/adjust-balance$#', $path, $match) && $method === 'POST') {
            $amount = $this->parseMoney($this->body['amount'] ?? null, false);
            $direction = $this->text($this->body['direction'] ?? '', 12);
            $description = $this->text($this->body['description'] ?? '', 300);
            if (!in_array($direction, ['credit', 'debit'], true)) {
                throw new HttpException(400, 'Loại điều chỉnh không hợp lệ.');
            }
            $userId = $match[1];
            $signed = $direction === 'credit' ? $amount : -$amount;
            $balance = Database::transaction(function () use ($amount, $signed, $userId, $description): int {
                $updated = Database::one(
                    'UPDATE users SET balance = balance + ? WHERE user_id = ? AND balance + ? >= 0 RETURNING balance',
                    [$signed, $userId, $signed]
                );
                if ($updated === null) {
                    throw new HttpException(409, 'Không tìm thấy tài khoản hoặc số dư không đủ.');
                }
                $reference = 'admin:' . bin2hex(random_bytes(12));
                Database::run(
                    "INSERT INTO wallet_ledger (reference_code, user_id, amount, transaction_type, description) VALUES (?, ?, ?, 'ADMIN_ADJUSTMENT', ?)",
                    [$reference, $userId, $signed, $description !== '' ? $description : 'Điều chỉnh số dư bởi quản trị viên']
                );
                Database::run(
                    "INSERT INTO transactions (user_id, amount, type, reference_code, status) VALUES (?, ?, 'ADMIN_ADJUSTMENT', ?, 'paid')",
                    [$userId, $amount, $reference]
                );
                return (int) $updated['balance'];
            });
            $this->audit('user.balance', $userId, ['amount' => $signed]);
            $this->notify($userId, 'Số dư của bạn vừa được điều chỉnh: ' . ($signed > 0 ? '+' : '') . self::moneyFormat($signed) . '.');
            return self::response(['success' => true, 'balance' => $balance]);
        }

        if ($method === 'GET' && $path === '/api/admin/vouchers') {
            return self::response(['requests' => VoucherService::pending(200)]);
        }
        if (preg_match('#^/api/admin/vouchers/(\\d+)/(approve|reject)$#', $path, $match) && $method === 'POST') {
            $action = $match[2];
            $reward = $action === 'approve' ? $this->parseMoney($this->body['rewardAmount'] ?? null, false) : 0;
            $note = $this->text($this->body['adminNote'] ?? '', 1000);
            try {
                $result = VoucherService::review((int) $match[1], $action, (string) $this->user['id'], $reward, $note);
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            $this->audit('voucher.' . $action, (string) $match[1], ['rewardAmount' => $reward]);
            $message = $action === 'approve'
                ? '✅ Yêu cầu voucher cho đơn ' . $result['request']['order_code'] . ' đã được duyệt. Ví Voucher được cộng ' . self::moneyFormat($reward) . '.'
                : 'Yêu cầu voucher cho đơn ' . $result['request']['order_code'] . ' đã bị từ chối. Ghi chú: ' . ($note !== '' ? $note : 'Không có.');
            $this->notify($result['userId'], $message);
            return self::response(['request' => $result['request'], 'message' => $action === 'approve' ? 'Đã duyệt và cộng ví Voucher.' : 'Đã từ chối yêu cầu.']);
        }

        if ($method === 'GET' && $path === '/api/admin/tickets') {
            return self::response(['tickets' => Database::all(
                "SELECT * FROM support_tickets ORDER BY CASE WHEN status = 'open' THEN 0 ELSE 1 END, created_at DESC LIMIT 200"
            )]);
        }
        if (preg_match('#^/api/admin/tickets/(\d{1,18})$#', $path, $match) && $method === 'PATCH') {
            $status = $this->text($this->body['status'] ?? '', 24);
            $reply = $this->text($this->body['reply'] ?? '', 3000);
            if (!in_array($status, ['open', 'processing', 'resolved', 'closed'], true)) {
                throw new HttpException(400, 'Trạng thái hỗ trợ không hợp lệ.');
            }
            $ticket = Database::one(
                'UPDATE support_tickets SET status = ?, admin_reply = ?, updated_at = NOW() WHERE id = ? RETURNING *',
                [$status, $reply, $match[1]]
            );
            if ($ticket === null) {
                throw new HttpException(404, 'Không tìm thấy yêu cầu hỗ trợ.');
            }
            $this->audit('support.reply', $match[1], ['status' => $status]);
            if ($reply !== '') {
                $this->notify((string) $ticket['user_id'], "🎧 Phản hồi từ Trung tâm khách hàng:\n" . htmlspecialchars($reply, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            }
            return self::response(['ticket' => $ticket]);
        }

        if ($method === 'GET' && $path === '/api/admin/settings') {
            return self::response(['settings' => $this->settings(self::SETTINGS, false)]);
        }
        if ($method === 'PATCH' && $path === '/api/admin/settings') {
            $keys = array_keys($this->body);
            if ($keys === [] || array_diff($keys, self::SETTINGS) !== []) {
                throw new HttpException(400, 'Có khóa cấu hình không được phép cập nhật. Secret hệ thống chỉ quản lý trong Environment.');
            }
            $values = [];
            foreach ($this->body as $key => $value) {
                $text = $this->text($value, 500);
                if ($key === 'vip_checkin_points') {
                    $points = filter_var($text, FILTER_VALIDATE_INT);
                    if ($points === false || $points < 0 || $points > 1000) {
                        throw new HttpException(400, 'Điểm danh VIP phải là số nguyên từ 0 đến 1.000 điểm.');
                    }
                    $text = (string) $points;
                }
                if ($key === 'referral_commission_percent') {
                    $percent = (float) $text;
                    if (!is_finite($percent) || $percent < 0 || $percent > 30) {
                        throw new HttpException(400, 'Tỷ lệ hoa hồng phải từ 0 đến 30%.');
                    }
                }
                if (in_array($key, ['website_url', 'GROUP_URL', 'CHANNEL_URL', 'ABCVIP_CONNECT_URL'], true)) {
                    self::validateHttps($text, 'Liên kết website/nhóm/kênh phải sử dụng HTTPS.');
                    if ($text !== '' && filter_var($text, FILTER_VALIDATE_URL) === false) {
                        throw new HttpException(400, 'Liên kết website phải là URL hợp lệ.');
                    }
                }
                $values[$key] = $text;
            }
            Database::transaction(function () use ($values): void {
                foreach ($values as $key => $value) {
                    $isPublic = !in_array($key, ['VIETQR_BANK_ID', 'VIETQR_ACCOUNT_NO', 'VIETQR_ACCOUNT_NAME'], true);
                    Database::run(
                        'INSERT INTO app_settings (key, value, is_public, updated_at) VALUES (?, ?, ?, NOW()) ' .
                        'ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, is_public = EXCLUDED.is_public, updated_at = NOW()',
                        [$key, $value, $isPublic]
                    );
                }
            });
            $this->audit('settings.update', 'public-settings', ['keys' => $keys]);
            return self::response(['success' => true, 'settings' => $this->settings(self::SETTINGS, false)]);
        }

        if (preg_match('#^/api/admin/giftcodes/([A-Z0-9_-]{4,64})$#i', $path, $match) && $method === 'PATCH') {
            $code = strtoupper($match[1]);
            $active = $this->toBool($this->body['isActive'] ?? false);
            $gift = Database::one('UPDATE giftcodes SET is_active = ? WHERE code = ? RETURNING code, is_active', [$active, $code]);
            if ($gift === null) {
                throw new HttpException(404, 'Không tìm thấy giftcode.');
            }
            $this->audit('giftcode.toggle', $code, ['isActive' => $active]);
            return self::response(['giftcode' => $gift]);
        }

        if ($method === 'GET' && $path === '/api/admin/bot-menus') {
            $menus = Database::all('SELECT * FROM bot_menu_configs ORDER BY bot_key');
            return self::response(['menus' => array_map(fn (array $row): array => $this->publicMenu($row), $menus)]);
        }
        if (preg_match('#^/api/admin/bot-menus/(primary|secondary)$#', $path, $match) && $method === 'PATCH') {
            return $this->saveBotMenu($match[1]);
        }

        if ($method === 'GET' && $path === '/api/admin/matches') {
            try {
                $matches = MatchmakingService::adminMatches((string) ($this->query['status'] ?? ''), (int) ($this->query['limit'] ?? 100));
            } catch (RuntimeException $error) {
                throw new HttpException(400, $error->getMessage());
            }
            return self::response(['matches' => $matches]);
        }
        if (preg_match('#^/api/admin/matches/(\\d{1,18})/resolve$#', $path, $match) && $method === 'POST') {
            $scoreOne = filter_var($this->body['scoreOne'] ?? null, FILTER_VALIDATE_INT);
            $scoreTwo = filter_var($this->body['scoreTwo'] ?? null, FILTER_VALIDATE_INT);
            if ($scoreOne === false || $scoreTwo === false) throw new HttpException(400, 'Điểm số phải là số nguyên.');
            try {
                $result = MatchmakingService::resolveByAdmin((int) $match[1], $scoreOne, $scoreTwo, (string) $this->user['id'], (string) ($this->body['note'] ?? ''));
            } catch (RuntimeException $error) {
                throw new HttpException(409, $error->getMessage());
            }
            $this->audit('matchmaking.result.resolve', (string) $match[1], ['scoreOne' => $scoreOne, 'scoreTwo' => $scoreTwo, 'note' => (string) ($this->body['note'] ?? '')]);
            $this->notify((string) $result['playerOne'], '🏁 Admin đã xác nhận kết quả trận #' . (int) $match[1] . ': ' . $scoreOne . '-' . $scoreTwo . '.');
            $this->notify((string) $result['playerTwo'], '🏁 Admin đã xác nhận kết quả trận #' . (int) $match[1] . ': ' . $scoreOne . '-' . $scoreTwo . '.');
            return self::response(['result' => $result, 'message' => 'Đã xác nhận kết quả và cập nhật bảng xếp hạng.']);
        }
        if ($method === 'GET' && $path === '/api/admin/giftcodes') {
            return self::response(['giftcodes' => Database::all('SELECT * FROM giftcodes ORDER BY created_at DESC LIMIT 200')]);
        }
        if ($method === 'POST' && $path === '/api/admin/giftcodes') {
            $code = strtoupper($this->text($this->body['code'] ?? bin2hex(random_bytes(5)), 64));
            $reward = $this->parseMoney($this->body['rewardAmount'] ?? 0, true);
            $max = (int) ($this->body['maxRedemptions'] ?? 1);
            $expires = $this->body['expiresAt'] ?? null;
            if (!preg_match('/^[A-Z0-9_-]{4,64}$/', $code)) {
                throw new HttpException(400, 'Mã giftcode chỉ gồm A-Z, số, _ hoặc -.');
            }
            if ($max < 1 || $max > 100000) {
                throw new HttpException(400, 'Số lượt sử dụng không hợp lệ.');
            }
            if ($expires !== null && $expires !== '') {
                try {
                    $expires = (new DateTimeImmutable((string) $expires))->format(DATE_ATOM);
                } catch (Throwable) {
                    throw new HttpException(400, 'Ngày hết hạn không hợp lệ.');
                }
            } else {
                $expires = null;
            }
            $gift = Database::one(
                'INSERT INTO giftcodes (code, reward_amount, max_redemptions, expires_at) VALUES (?, ?, ?, ?) RETURNING *',
                [$code, $reward, $max, $expires]
            );
            $this->audit('giftcode.create', $code, ['reward' => $reward, 'maxRedemptions' => $max]);
            return self::response(['giftcode' => $gift], 201);
        }

        throw new HttpException(404, 'Không tìm thấy API quản trị.');
    }

    private function updateOrder(string $id): array
    {
        $status = $this->text($this->body['status'] ?? '', 24);
        $note = $this->text($this->body['adminNote'] ?? '', 1000);
        $delivery = $this->text($this->body['deliveryText'] ?? '', 5000);
        if (!in_array($status, self::ORDER_STATUSES, true)) {
            throw new HttpException(400, 'Trạng thái đơn hàng không hợp lệ.');
        }

        [$order, $refunded] = Database::transaction(function () use ($id, $status, $note, $delivery): array {
            $current = Database::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$id]);
            if ($current === null) {
                throw new HttpException(404, 'Không tìm thấy đơn hàng.');
            }
            $terminal = ['completed', 'rejected', 'cancelled', 'refunded'];
            if (in_array($current['status'], $terminal, true) && $current['status'] !== $status) {
                throw new HttpException(409, 'Đơn hàng đã kết thúc và không thể thay đổi trạng thái.');
            }

            $order = Database::one(
                'UPDATE orders SET status = ?, admin_note = ?, delivery_text = ?, updated_at = NOW() WHERE id = ? RETURNING *',
                [$status, $note, $delivery, $id]
            );
            $refunded = false;

            if (in_array($status, ['rejected', 'cancelled', 'refunded'], true) && !in_array($current['status'], $terminal, true)) {
                $amount = (int) $current['amount'];
                if ($amount > 0) {
                    $credit = Database::run(
                        "INSERT INTO wallet_ledger (reference_code, user_id, amount, transaction_type, description) VALUES (?, ?, ?, 'ORDER_REFUND', ?) ON CONFLICT (reference_code) DO NOTHING RETURNING id",
                        ['refund:' . $current['order_code'], $current['user_id'], $amount, 'Hoàn tiền đơn ' . $current['order_code']]
                    );
                    if ($credit->rowCount() > 0) {
                        Database::run('UPDATE users SET balance = balance + ? WHERE user_id = ?', [$amount, $current['user_id']]);
                        Database::run(
                            "INSERT INTO transactions (user_id, amount, type, reference_code, status) VALUES (?, ?, 'ORDER_REFUND', ?, 'paid')",
                            [$current['user_id'], $amount, $current['order_code']]
                        );
                        $refunded = true;
                    }
                }
                if ($current['product_id'] !== null) {
                    Database::run('UPDATE products SET stock = stock + 1, updated_at = NOW() WHERE id = ? AND stock IS NOT NULL', [$current['product_id']]);
                }
            }

            if ($status === 'completed' && $current['status'] !== 'completed' && $current['product_id'] !== null) {
                $product = Database::one('SELECT vip_level FROM products WHERE id = ?', [$current['product_id']]);
                if ($product !== null && (int) $product['vip_level'] > 0) {
                    Database::run('UPDATE users SET vip_level = GREATEST(vip_level, ?) WHERE user_id = ?', [(int) $product['vip_level'], $current['user_id']]);
                }
            }

            return [$order, $refunded];
        });

        $this->audit('order.update', (string) $order['order_code'], ['status' => $status, 'refunded' => $refunded]);
        if ($status === 'completed') {
            $message = 'Đơn hàng ' . $order['order_code'] . ' đã hoàn tất.' . ($delivery !== '' ? "\nThông tin bàn giao:\n" . htmlspecialchars($delivery, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '');
        } elseif ($refunded) {
            $message = 'Đơn hàng ' . $order['order_code'] . ' đã bị hủy/từ chối. Số tiền ' . self::moneyFormat($order['amount']) . ' đã được hoàn vào ví.';
        } else {
            $message = 'Đơn hàng ' . $order['order_code'] . ' đã cập nhật trạng thái: ' . $status;
        }
        $this->notify((string) $order['user_id'], $message);
        return self::response(['order' => $order, 'refunded' => $refunded]);
    }

    private function saveBotMenu(string $botKey): array
    {
        if ($botKey === 'secondary' && TelegramAuth::token('secondary') === '') {
            throw new HttpException(409, 'BOT2_TOKEN chưa được cấu hình trong Environment.');
        }
        // Always normalize saved menu configuration to Inline Keyboard.
        $mode = 'inline_keyboard';
        $columns = (int) ($this->body['menuColumns'] ?? 0);
        if ($columns < 1 || $columns > 3) throw new HttpException(400, 'Số cột menu phải từ 1 đến 3.');
        $labels = $this->validateMap($this->body['buttonLabels'] ?? [], 'Tên nút', 60);
        $descriptions = $this->validateMap($this->body['buttonDescriptions'] ?? [], 'Mô tả nút', 160);
        $styles = $this->validateMap($this->body['buttonStyles'] ?? [], 'Màu nút', 12, ['primary', 'success', 'danger']);
        $icons = $this->validateMap($this->body['buttonIcons'] ?? [], 'Custom Emoji ID', 32);
        foreach ($icons as $iconId) {
            if (!preg_match('/^[0-9]{5,32}$/', (string) $iconId)) {
                throw new HttpException(400, 'Custom Emoji ID phải là chuỗi số từ 5 đến 32 ký tự.');
            }
        }
        $intro = $this->text($this->body['introText'] ?? '', 600);
        $persistent = array_key_exists('persistentKeyboard', $this->body) ? $this->toBool($this->body['persistentKeyboard']) : true;
        $resize = array_key_exists('resizeKeyboard', $this->body) ? $this->toBool($this->body['resizeKeyboard']) : true;
        $saved = Database::one(
            'INSERT INTO bot_menu_configs (bot_key, menu_mode, intro_text, menu_columns, persistent_keyboard, resize_keyboard, button_labels, button_descriptions, button_styles, button_icons, updated_at) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, ?::jsonb, ?::jsonb, NOW()) ' .
            'ON CONFLICT (bot_key) DO UPDATE SET menu_mode = EXCLUDED.menu_mode, intro_text = EXCLUDED.intro_text, menu_columns = EXCLUDED.menu_columns, ' .
            'persistent_keyboard = EXCLUDED.persistent_keyboard, resize_keyboard = EXCLUDED.resize_keyboard, button_labels = EXCLUDED.button_labels, ' .
            'button_descriptions = EXCLUDED.button_descriptions, button_styles = EXCLUDED.button_styles, button_icons = EXCLUDED.button_icons, updated_at = NOW() RETURNING *',
            [$botKey, $mode, $intro, $columns, $persistent, $resize, json_encode($labels, JSON_UNESCAPED_UNICODE), json_encode($descriptions, JSON_UNESCAPED_UNICODE), json_encode($styles, JSON_UNESCAPED_UNICODE), json_encode($icons, JSON_UNESCAPED_UNICODE)]
        );
        $this->audit('bot-menu.update', $botKey, ['menuMode' => $mode, 'menuColumns' => $columns, 'customEmojiCount' => count($icons)]);
        return self::response([
            'success' => true,
            'menu' => $this->publicMenu($saved),
            'message' => 'Đã lưu cấu hình riêng của bot. Custom Emoji ID chỉ hoạt động khi tài khoản sở hữu bot đủ điều kiện Telegram Premium.',
        ]);
    }

    private function exchangeLegacySession(): array
    {
        $ticket = $this->text($this->body['ticket'] ?? '', 128);
        $scope = $this->text($this->body['scope'] ?? 'customer', 16);
        if (!preg_match('/^[a-f0-9]{64}$/i', $ticket) || !in_array($scope, ['customer', 'admin'], true)) {
            throw new HttpException(400, 'Login ticket hoặc phạm vi đăng nhập không hợp lệ.');
        }

        // Claim the one-time ticket with one atomic UPDATE. This is concurrency-safe
        // and avoids leaving a pooled PostgreSQL connection inside an aborted transaction.
        $hash = hash('sha256', $ticket);
        $identity = Database::one(
            "UPDATE legacy_login_tickets
                SET consumed_at = NOW()
              WHERE token_hash = ?
                AND bot_key = 'primary'
                AND consumed_at IS NULL
                AND expires_at > NOW()
          RETURNING user_id, bot_key",
            [$hash]
        );
        if ($identity === null) {
            throw new HttpException(401, 'Link đăng nhập đã hết hạn hoặc đã được sử dụng. Hãy quay lại HENDY BOT và tạo link mới.');
        }

        $user = Database::one(
            'SELECT user_id, name, username, vip_level, balance, is_blocked, blocked_reason FROM users WHERE user_id = ?',
            [$identity['user_id']]
        );
        if ($user === null) {
            throw new HttpException(404, 'Không tìm thấy hồ sơ thành viên. Hãy tạo lại phiên đăng nhập từ HENDY BOT.');
        }
        if (in_array($user['is_blocked'] ?? false, [true, 1, '1', 't', 'true'], true) && !TelegramAuth::isAdmin((string) $user['user_id'])) {
            throw new HttpException(403, 'Tài khoản HENDY đang tạm khóa. Vui lòng liên hệ CSKH.');
        }
        if ($scope === 'admin' && !TelegramAuth::isAdmin((string) $user['user_id'])) {
            throw new HttpException(403, 'Tài khoản này không nằm trong danh sách Admin được phép.');
        }

        $botKey = 'primary';
        $secret = TelegramAuth::websiteSessionSecret($botKey);
        $now = time();
        $header = self::base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $sessionTtl = max(900, min(43200, Env::int('WEBSITE_SESSION_TTL_SECONDS', 43200)));
        $payload = self::base64Url(json_encode([
            'sub' => (string) $user['user_id'],
            'name' => (string) $user['name'],
            'username' => (string) $user['username'],
            'bot' => $botKey,
            'aud' => $scope === 'admin' ? 'hendy-admin-dashboard' : 'hendy-legacy-account',
            'iat' => $now,
            'exp' => $now + $sessionTtl,
        ], JSON_UNESCAPED_UNICODE));
        $unsigned = $header . '.' . $payload;
        $signature = self::base64Url(hash_hmac('sha256', $unsigned, $secret, true));
        $sessionToken = $unsigned . '.' . $signature;

        setcookie('hendy_web_session', $sessionToken, [
            'expires' => $now + $sessionTtl,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        return self::response([
            'authenticated' => true,
            'scope' => $scope,
            'botKey' => $botKey,
            'expiresInSeconds' => $sessionTtl,
            'user' => [
                'id' => (string) $user['user_id'],
                'name' => (string) $user['name'],
                'username' => (string) $user['username'],
                'vipLevel' => (int) $user['vip_level'],
                'balance' => (int) $user['balance'],
            ],
        ]);
    }

    private function userRow(): array
    {
        $userId = (string) $this->user['id'];
        $name = $this->text(trim((string) ($this->user['first_name'] ?? '') . ' ' . (string) ($this->user['last_name'] ?? '')), 120);
        $username = $this->text($this->user['username'] ?? '', 64);
        $row = Database::one(
            "INSERT INTO users (user_id, name, username) VALUES (?, ?, ?) ON CONFLICT (user_id) DO UPDATE SET " .
            "name = CASE WHEN EXCLUDED.name <> '' THEN EXCLUDED.name ELSE users.name END, " .
            "username = CASE WHEN EXCLUDED.username <> '' THEN EXCLUDED.username ELSE users.username END " .
            'RETURNING user_id, name, username, balance, vip_level, total_deposited, nyc_wallet, voucher_wallet, point_old, created_at',
            [$userId, $name, $username]
        ) ?? [];
        return [
            'id' => $userId,
            'name' => $row['name'] ?: ($username ?: 'Thành viên'),
            'username' => $row['username'],
            'balance' => (int) $row['balance'],
            'mainWallet' => (int) $row['balance'],
            'nycWallet' => (int) $row['nyc_wallet'],
            'voucherWallet' => (int) $row['voucher_wallet'],
            'vipLevel' => (int) $row['vip_level'],
            'totalDeposited' => (int) $row['total_deposited'],
            'totalRecharge' => (int) $row['total_deposited'],
            'main_wallet' => (int) $row['balance'],
            'total_recharge' => (int) $row['total_deposited'],
            'pointOld' => (int) $row['point_old'],
            'createdAt' => $row['created_at'],
        ];
    }

    private function listProducts(?string $category, bool $includeInactive): array
    {
        $where = [];
        $parameters = [];
        if ($category !== null && $category !== '') {
            if (!in_array($category, self::CATEGORIES, true)) {
                throw new HttpException(400, 'Danh mục không hợp lệ.');
            }
            $where[] = 'category = ?';
            $parameters[] = $category;
        }
        if (!$includeInactive) {
            $where[] = 'is_active = TRUE';
            $where[] = ProductReadinessService::sqlPredicate();
            $where[] = '(stock IS NULL OR stock > 0)';
        }
        $sql = 'SELECT * FROM products' . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY category, created_at DESC LIMIT 250';
        return array_map(
            $includeInactive ? self::adminProduct(...) : self::publicProduct(...),
            Database::all($sql, $parameters)
        );
    }

    private static function adminProduct(array $row): array
    {
        return [...self::publicProduct($row), ...ProductReadinessService::adminFields($row)];
    }

    private function productProviderInput(array $current): array
    {
        $get = function (string $input, string $column, int $limit = 500) use ($current): string {
            return array_key_exists($input, $this->body)
                ? $this->text($this->body[$input], $limit)
                : (string) ($current[$column] ?? '');
        };
        $supplierName = $get('supplierName', 'supplier_name', 120);
        $supplierUrl = ProductReadinessService::validateHttpsUrl($get('supplierUrl', 'supplier_url'), 'URL nhà cung cấp');
        $supplierRef = $get('supplierProductRef', 'supplier_product_ref', 160);
        $sourceUrl = ProductReadinessService::validateHttpsUrl($get('sourceUrl', 'source_url'), 'URL nguồn / hồ sơ quyền sử dụng');
        $permission = strtolower($get('sourcePermissionStatus', 'source_permission_status', 16) ?: 'pending');
        $access = strtolower($get('supplierAccessStatus', 'supplier_access_status', 16) ?: 'pending');
        $fulfillment = strtolower($get('fulfillmentMode', 'fulfillment_mode', 16) ?: 'manual');
        $envKey = ProductReadinessService::validateEnvKey($get('credentialEnvKey', 'credential_env_key', 64));
        if (!in_array($permission, ['pending','authorized','rejected'], true)) {
            throw new RuntimeException('Trạng thái quyền nguồn không hợp lệ.');
        }
        if (!in_array($access, ['pending','verified','revoked'], true)) {
            throw new RuntimeException('Trạng thái nhà cung cấp không hợp lệ.');
        }
        if (!in_array($fulfillment, ['manual','api'], true)) {
            throw new RuntimeException('Phương thức bàn giao không hợp lệ.');
        }

        $sourceChanged = $current !== [] && $sourceUrl !== (string) ($current['source_url'] ?? '');
        $supplierChanged = $current !== [] && (
            $supplierName !== (string) ($current['supplier_name'] ?? '')
            || $supplierUrl !== (string) ($current['supplier_url'] ?? '')
            || $supplierRef !== (string) ($current['supplier_product_ref'] ?? '')
        );
        // Changing proof or supplier identity invalidates the old attestation.
        if ($sourceChanged) $permission = 'pending';
        if ($supplierChanged) $access = 'pending';

        $fullyVerified = $permission === 'authorized' && $access === 'verified';
        $wasVerified = (string) ($current['source_permission_status'] ?? '') === 'authorized'
            && (string) ($current['supplier_access_status'] ?? '') === 'verified';
        $verifiedBy = '';
        $verifiedAt = null;
        if ($fullyVerified) {
            $verifiedBy = $wasVerified && !$sourceChanged && !$supplierChanged
                ? (string) ($current['verified_by'] ?? '')
                : (string) ($this->user['id'] ?? '');
            $verifiedAt = $wasVerified && !$sourceChanged && !$supplierChanged
                ? ($current['verified_at'] ?? null)
                : gmdate('c');
        }
        return [
            'supplier_name' => $supplierName,
            'supplier_url' => $supplierUrl,
            'supplier_product_ref' => $supplierRef,
            'source_url' => $sourceUrl,
            'source_permission_status' => $permission,
            'supplier_access_status' => $access,
            'fulfillment_mode' => $fulfillment,
            'credential_env_key' => $envKey,
            'verified_by' => $verifiedBy,
            'verified_at' => $verifiedAt,
        ];
    }

    private function settings(array $keys, bool $publicOnly): array
    {
        if ($keys === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $sql = "SELECT key, value FROM app_settings WHERE key IN ($placeholders)" . ($publicOnly ? ' AND is_public = TRUE' : '');
        $result = [];
        foreach (Database::all($sql, $keys) as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }

    private function publishUserDataChanged(string $kind): void
    {
        $allowed = ['order', 'payment-request', 'payment-status', 'support-ticket', 'voucher-request', 'wallet', 'giftcode', 'video-job'];
        if (in_array($kind, $allowed, true)) {
            Realtime::publish('user.data.changed', ['kind' => $kind], 'users');
        }
    }

    private function audit(string $action, string $target, array $details): void
    {
        Realtime::publish('admin.' . $action, ['target' => $target, 'details' => $details]);
        Database::run(
            'INSERT INTO admin_audit (admin_user_id, action, target, details) VALUES (?, ?, ?, ?::jsonb)',
            [(string) $this->user['id'], $action, $target, json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
        Realtime::publish('user.data.changed', ['resource' => $action], 'users');
    }

    private function notify(string $userId, string $message, ?string $botKey = null): void
    {
        $token = TelegramAuth::token($botKey ?? $this->botKey);
        if ($token === '') {
            return;
        }
        try {
            (new TelegramApi($token))->sendMessage($userId, $message);
        } catch (Throwable $error) {
            error_log('Không gửi được thông báo Telegram: ' . $error->getMessage());
        }
    }

    private function notifyAdmins(string $message): void
    {
        foreach (TelegramAuth::adminIds() as $adminId) {
            $this->notify($adminId, $message);
        }
    }

    private function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            throw new HttpException(403, 'Bạn không có quyền quản trị.');
        }
    }

    private function isAdmin(): bool
    {
        return (bool) ($this->user['_is_admin'] ?? false);
    }

    private function publicMenu(array $row): array
    {
        return [
            'botKey' => $row['bot_key'],
            'configured' => TelegramAuth::token($row['bot_key']) !== '',
            'menuMode' => $row['menu_mode'],
            'introText' => $row['intro_text'],
            'menuColumns' => (int) $row['menu_columns'],
            'persistentKeyboard' => $this->toBool($row['persistent_keyboard']),
            'resizeKeyboard' => $this->toBool($row['resize_keyboard']),
            'buttonLabels' => self::jsonArray($row['button_labels']),
            'buttonDescriptions' => self::jsonArray($row['button_descriptions']),
            'buttonStyles' => self::jsonArray($row['button_styles']),
            'buttonIcons' => self::jsonArray($row['button_icons'] ?? []),
            'updatedAt' => $row['updated_at'],
        ];
    }

    private function validateMap(mixed $value, string $label, int $maximum, ?array $allowed = null): array
    {
        if ($value === null) {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                throw new HttpException(400, $label . ' phải là JSON hợp lệ.');
            }
            $value = $decoded;
        }
        if (!is_array($value) || array_is_list($value) && $value !== []) {
            throw new HttpException(400, $label . ' phải là đối tượng JSON.');
        }
        $result = [];
        foreach ($value as $key => $raw) {
            if (!in_array((string) $key, self::MENU_ACTIONS, true)) {
                throw new HttpException(400, 'Khóa nút không hợp lệ: ' . $key);
            }
            $text = trim((string) $raw);
            if (mb_strlen($text) > $maximum) {
                throw new HttpException(400, $label . ' có giá trị quá dài.');
            }
            if ($allowed !== null && $text !== '' && !in_array($text, $allowed, true)) {
                throw new HttpException(400, 'Màu nút không hợp lệ.');
            }
            if ($text !== '') {
                $result[(string) $key] = $text;
            }
        }
        return $result;
    }

    private function text(mixed $value, int $maximum): string
    {
        if (!is_scalar($value) && $value !== null) {
            throw new HttpException(400, 'Dữ liệu văn bản không hợp lệ.');
        }
        return mb_substr(trim((string) ($value ?? '')), 0, $maximum);
    }

    private function parseMoney(mixed $value, bool $allowZero): int
    {
        if (!is_numeric($value) || (string) $value === '') {
            throw new HttpException(400, 'Số tiền không hợp lệ.');
        }
        $amount = filter_var($value, FILTER_VALIDATE_INT);
        if ($amount === false || $amount < ($allowZero ? 0 : 1) || $amount > 9000000000000000) {
            throw new HttpException(400, 'Số tiền không hợp lệ.');
        }
        return (int) $amount;
    }

    private function toBool(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 't', 'true', 'yes', 'on'], true);
    }

    private static function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function publicProduct(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'slug' => $row['slug'],
            'name' => $row['name'],
            'category' => $row['category'],
            'description' => $row['description'],
            'price' => (int) $row['price'],
            'durationDays' => (int) $row['duration_days'],
            'stock' => $row['stock'] === null ? null : (int) $row['stock'],
            'vipLevel' => (int) $row['vip_level'],
            'imageUrl' => $row['image_url'] ?? '',
            'isActive' => filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN),
            'isSellable' => ProductReadinessService::isSellable($row),
            'metadata' => self::jsonArray($row['metadata'] ?? []),
        ];
    }

    private static function slug(mixed $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', (string) ($normalized ?: $value)), '-');
        return mb_substr($slug !== '' ? $slug : bin2hex(random_bytes(4)), 0, 70);
    }

    private static function validateHttps(string $url, string $message): void
    {
        if ($url !== '' && !preg_match('/^https:\/\/\S+$/i', $url)) {
            throw new HttpException(400, $message);
        }
    }

    private static function orderCode(): string
    {
        return 'HD' . strtoupper(base_convert((string) time(), 10, 36)) . strtoupper(bin2hex(random_bytes(3)));
    }

    private static function moneyFormat(mixed $amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . ' VNĐ';
    }


    private static function base64Url(string|false $value): string
    {
        return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
    }

    private static function response(array $body, int $status = 200): array
    {
        return ['status' => $status, 'body' => $body];
    }
}
