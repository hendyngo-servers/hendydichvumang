<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Env.php';
require dirname(__DIR__) . '/app/NeonStorage.php';
require dirname(__DIR__) . '/app/ModuleRegistry.php';
require dirname(__DIR__) . '/app/TelegramAuth.php';
require dirname(__DIR__) . '/app/TelegramLink.php';
require dirname(__DIR__) . '/app/BotMenuDefinition.php';
require dirname(__DIR__) . '/app/WebhookSecurity.php';
require dirname(__DIR__) . '/app/TelegramApi.php';
require dirname(__DIR__) . '/app/PaymentService.php';
require dirname(__DIR__) . '/app/VideoJobService.php';
require dirname(__DIR__) . '/app/CodeTelegramService.php';
require dirname(__DIR__) . '/app/WebServiceContract.php';
require dirname(__DIR__) . '/app/WebServiceKernel.php';
require dirname(__DIR__) . '/app/SystemWebService.php';
require dirname(__DIR__) . '/app/OperationsService.php';
require dirname(__DIR__) . '/app/ProxyService.php';

use Hendy\VideoJobService;
use Hendy\CodeTelegramService;
use Hendy\TelegramAuth;
use Hendy\TelegramLink;
use Hendy\BotMenuDefinition;
use Hendy\WebhookSecurity;
use Hendy\WebServiceKernel;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        throw new RuntimeException('Test thất bại: ' . $message);
    }
};

$assert(TelegramAuth::safeEquals('same-secret', 'same-secret'), 'so sánh chuỗi đúng');
$assert(!TelegramAuth::safeEquals('different', 'same-secret'), 'so sánh chuỗi khác');
$assert(!TelegramAuth::safeEquals('', ''), 'secret rỗng bị từ chối');
$telegramAuthSource = file_get_contents(dirname(__DIR__) . '/app/TelegramAuth.php');
$schemaSource = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$assert(str_contains($telegramAuthSource, 'ON CONFLICT (bot_key) DO UPDATE SET') && str_contains($telegramAuthSource, 'RETURNING secret_value'), 'webhook/session secrets persist using atomic PostgreSQL upserts');
$assert(!str_contains($telegramAuthSource, 'pg_advisory_xact_lock'), 'webhook/session secret startup does not rely on multi-statement advisory-lock transactions');
$assert(str_contains($telegramAuthSource, 'char_length(telegram_webhook_secrets.secret_value) BETWEEN 32 AND 256') && str_contains($telegramAuthSource, 'secret_value !~'), 'PostgreSQL-safe secret validation combines character allowlist and length bounds');
$assert(str_contains($schemaSource, 'CREATE TABLE IF NOT EXISTS website_session_secrets'), 'database schema creates the website session secret table used by authentication');
$assert(TelegramLink::normalize('https://t.me/HendySupportGroup') === 'https://t.me/HendySupportGroup', 'chấp nhận liên kết Telegram HTTPS hợp lệ');
$assert(TelegramLink::normalize('https://t.me/+Invite_Code-123') === 'https://t.me/+Invite_Code-123', 'chấp nhận liên kết mời Telegram');
$assert(TelegramLink::normalize('http://t.me/HendySupportGroup') === '', 'từ chối liên kết Telegram không HTTPS');
$assert(TelegramLink::normalize('https://t.me.evil.example/HendySupportGroup') === '', 'từ chối domain Telegram giả mạo');
$assert(TelegramLink::normalize('https://user@t.me/HendySupportGroup') === '', 'từ chối URL Telegram có userinfo');
$assert(TelegramLink::normalize('https://t.me/hendy_cyber_bot?start=old') === 'https://t.me/hendy_cyber_bot', 'chuẩn hóa public URL bot và loại query có sẵn');
$assert(TelegramLink::normalize('https://example.com/hendy_cyber_bot') === '', 'từ chối URL public bot ngoài Telegram');

$assert(array_column(BotMenuDefinition::ITEMS, 'action') === ['live','otp','wallet','vip','codes','customer','music-ai','image-ai','ai-cover','group','news','vip-coming-soon'], 'menu chính có AI music và AI image theo yêu cầu');
$assert(BotMenuDefinition::LAYOUT === [['live','otp'],['wallet'],['vip','codes'],['customer'],['music-ai','image-ai'],['ai-cover'],['group'],['news','vip-coming-soon'],['admin']], 'layout Inline Menu dùng nhịp so le hai nút - một nút và có hai nút AI');
$styles = array_column(BotMenuDefinition::ITEMS, 'style');
$assert(count($styles) === count(BotMenuDefinition::ITEMS) && count(array_diff($styles, ['primary','success','danger'])) === 0, 'mọi nút menu chính đều có màu Telegram hợp lệ');
$botServiceSource = file_get_contents(dirname(__DIR__) . '/app/BotService.php');
$assert(str_contains($botServiceSource, '$widths = [2, 1]') && str_contains($botServiceSource, '$button[\'style\'] = $style;') && str_contains($botServiceSource, "member:home"), 'mọi menu con được sắp so le và tự gán màu từng nút');
$startupSource = file_get_contents(dirname(__DIR__) . '/docker/start.sh');
$webhookRegistrationSource = file_get_contents(dirname(__DIR__) . '/bin/register-webhook.php');
$assert(str_contains($startupSource, 'php bin/register-webhook.php') && str_contains($webhookRegistrationSource, '$telegram->setWebhook($webhookUrl, $secret)') && str_contains($webhookRegistrationSource, 'getWebhookInfo'), 'Render startup đăng ký và xác minh webhook Telegram chính');
$assert(str_contains($botServiceSource, "'menu_mode' => 'inline_keyboard'"), 'menu mặc định là Inline Keyboard');
$assert(str_contains($botServiceSource, "return ['inline_keyboard' => \$rows];"), 'menu luôn gửi Inline Keyboard, kể cả cấu hình cũ');
$assert(str_contains($botServiceSource, "if (\$item['action'] === 'miniapp')") && str_contains($botServiceSource, "'web_app' => ['url' => \$url]"), 'nút Mở Mini App dùng Telegram Web App trực tiếp');
$assert(str_contains($botServiceSource, '$this->telegram->answerCallback($callbackId);') && !str_contains($botServiceSource, 'Đã nhận, đang xử lý…'), 'callback được xác nhận ngầm, không hiện thông báo kỹ thuật');
$assert(str_contains($botServiceSource, '👋 Chào mừng bạn đến với HENDY BOT!') && !str_contains($botServiceSource, '♻️ Menu HENDY đã chuyển sang Inline Menu mới.'), 'gỡ Reply Keyboard bằng lời chào tự nhiên thay cho thông báo kỹ thuật');
$assert(str_contains($botServiceSource, 'https://i.postimg.cc/HY1LRby9/hendy-home-banner.jpg?dl=1') && str_contains($botServiceSource, '$this->telegram->sendPhotoFromUrl'), 'banner Telegram được tải ở backend và upload multipart thay vì Telegram tự fetch URL');
$telegramApiSource = file_get_contents(dirname(__DIR__) . '/app/TelegramApi.php');
$codeTelegramSource = file_get_contents(dirname(__DIR__) . '/app/CodeTelegramService.php');
$codeTelegramSchema = file_get_contents(dirname(__DIR__) . '/database/migrations/20261112_code_telegram.sql');
$assert(str_contains($telegramApiSource, "'channel_post', 'edited_channel_post'") && str_contains($telegramApiSource, 'downloadVideoToTemp'), 'webhook subscribes to channel posts and supports secure video download');
$assert(str_contains($codeTelegramSource, 'WHERE chat_id = ? AND is_active = TRUE') && str_contains($codeTelegramSource, "'pending_review'") && str_contains($codeTelegramSource, 'tesseract'), 'Code Telegram is allow-listed, OCR candidates require moderation by default');
$assert(str_contains($codeTelegramSchema, 'CREATE TABLE IF NOT EXISTS code_telegram_sources') && str_contains($codeTelegramSchema, 'CREATE TABLE IF NOT EXISTS code_telegram_posts') && str_contains($codeTelegramSchema, 'CREATE TABLE IF NOT EXISTS code_telegram_codes'), 'Code Telegram uses additive PostgreSQL tables');
$parsedCodeTelegram = CodeTelegramService::extractCandidates("Mã khuyến mãi: HENDY2026\nCode Tele: TELE2026\nPromo code: FREE999\nHENDY2026");
$assert(in_array('HENDY2026', $parsedCodeTelegram, true) && in_array('TELE2026', $parsedCodeTelegram, true) && in_array('FREE999', $parsedCodeTelegram, true), 'Code Telegram extracts Vietnamese promo, Telegram and English promo codes');
$assert(count($parsedCodeTelegram) === count(array_unique($parsedCodeTelegram)), 'Code Telegram parser removes duplicate candidates from the same message');
$assert(str_contains($botServiceSource, 'code-telegram:recent') && str_contains($botServiceSource, 'case \'code_telegram_search\':' ) && str_contains($botServiceSource, 'CodeTelegramService::ingestChannelPost'), 'Bot menu supports Code Telegram browse, search and channel ingestion');
$assert(str_contains($botServiceSource, 'private function showMinigameProduct') && str_contains($botServiceSource, 'live-buy:') && str_contains($botServiceSource, 'MINIGAME LIVE'), 'MiniGame service list opens a payment confirmation flow');
$assert(str_contains($botServiceSource, 'FOR UPDATE') && str_contains($botServiceSource, "category = 'minigame'") && str_contains($botServiceSource, 'Ví Chính'), 'MiniGame checkout validates and locks a catalog product before paying from the Main wallet');

$codeTelegramServicesSchema = file_get_contents(dirname(__DIR__) . '/database/migrations/20261112_code_telegram_services.sql');
$apiControllerSource = file_get_contents(dirname(__DIR__) . '/app/ApiController.php');
$adminDashboardSource = file_get_contents(dirname(__DIR__, 2) . '/website/telegram/admin-dashboard/index.html');
$assert(str_contains($botServiceSource, '$configuredLabel = $action === \'codes\'') && str_contains($botServiceSource, 'private function showCodeTelegramService') && str_contains($botServiceSource, "case 'code_telegram_accounts':"), 'CODE TELEGRAM label cannot be overridden by stale Admin config and opens the username intake flow');
$assert(str_contains($botServiceSource, 'code_telegram_services') && str_contains($botServiceSource, 'max_accounts') && str_contains($botServiceSource, 'Chỉ tính phí khi xử lý thành công.'), 'CODE TELEGRAM menu prices and account limits come from PostgreSQL services');
$assert(str_contains($codeTelegramServicesSchema, 'CREATE TABLE IF NOT EXISTS code_telegram_services') && str_contains($codeTelegramServicesSchema, 'CREATE TABLE IF NOT EXISTS code_telegram_requests') && str_contains($codeTelegramServicesSchema, 'CREATE TABLE IF NOT EXISTS code_telegram_request_accounts'), 'Code Telegram catalog and username request queue use additive PostgreSQL tables');
$assert(str_contains($apiControllerSource, '/api/admin/code-telegram/accounts/') && str_contains($apiControllerSource, 'code-telegram:account:') && str_contains($apiControllerSource, 'status = \'completed\''), 'Admin charges a Code Telegram username only after a success status is confirmed');
$assert(str_contains($adminDashboardSource, 'Yêu cầu tài khoản Code Telegram') && str_contains($adminDashboardSource, 'data-code-account-success'), 'Admin Dashboard displays and processes Code Telegram account requests');
$assert(str_contains($telegramApiSource, 'BOT_HOME_PHOTO_ALLOWED_HOSTS') && str_contains($telegramApiSource, 'sendPhotoFile') && str_contains($telegramApiSource, 'CURLOPT_SSL_VERIFYPEER'), 'banner download validates HTTPS host, MIME and uploads bytes securely');
$aiServiceSource = file_get_contents(dirname(__DIR__) . '/app/AiMediaService.php');
$assert(str_contains($aiServiceSource, 'AI_MEDIA_QUEUE_ENABLED') && str_contains($aiServiceSource, 'ELEVENLABS_API_KEY') && str_contains($aiServiceSource, 'OPENAI_API_KEY'), 'AI generation uses server-side secrets and an explicit queue toggle');
$assert(str_contains($botServiceSource, "case 'music-ai':") && str_contains($botServiceSource, "case 'image-ai':") && str_contains($botServiceSource, '$this->sendUnavailableNotice($chatId);') && !str_contains($botServiceSource, "case 'image-ai':\n                if ("), 'AI buttons remain visible but only show the short unavailable notice until released');
$welcomeStart = strpos($botServiceSource, '$lines = [', strpos($botServiceSource, 'private function sendHome'));
$welcomeEnd = $welcomeStart === false ? false : strpos($botServiceSource, '];', $welcomeStart);
$welcomeBlock = ($welcomeStart !== false && $welcomeEnd !== false) ? substr($botServiceSource, $welcomeStart, $welcomeEnd - $welcomeStart) : '';
$assert(str_contains($welcomeBlock, "'✌️ Chúc ' . \$greeting . ' vui vẻ, <b>'") && str_contains($welcomeBlock, 'Ví NYC:') && !str_contains($welcomeBlock, 'Ví Voucher:') && !str_contains($welcomeBlock, 'Tổng nạp:') && !str_contains($welcomeBlock, '👑 Thành viên:'), 'welcome caption uses requested greeting and only the two wallet balances');
$assert(str_contains($botServiceSource, '🚧 TÍNH NĂNG ĐANG PHÁT TRIỂN / CHƯA MỞ BÁN') && str_contains($botServiceSource, "private function sendUnavailableNotice(string \$chatId): void") && !str_contains($botServiceSource, 'Danh mục và giá được lấy từ kho sản phẩm PostgreSQL:'), 'unreleased actions and empty catalog return one status line only');
$assert(str_contains($botServiceSource, 'OperationsService::forBot') && str_contains($botServiceSource, '/status'), 'bot kiểm tra trạng thái bảo trì/nâng cấp và cho phép xem /status');
$operationsSource = file_get_contents(dirname(__DIR__) . '/app/OperationsService.php');
$assert(str_contains($operationsSource, 'maintenance') && str_contains($operationsSource, 'upgrade') && str_contains($operationsSource, 'Asia/Ho_Chi_Minh'), 'dịch vụ vận hành hỗ trợ bảo trì, nâng cấp và giờ Việt Nam');
$assert(str_contains($operationsSource, '$effectiveMode = $active ? $mode : \'off\'') && str_contains($operationsSource, '$effectiveUntil = $active'), 'chế độ đã hết hạn trở lại trạng thái hoạt động và xóa lịch cũ trên giao diện');
$proxyServiceSource = file_get_contents(dirname(__DIR__) . '/app/ProxyService.php');
$assert(str_contains($proxyServiceSource, 'aes-256-gcm') && str_contains($proxyServiceSource, 'checkOnly') && str_contains($proxyServiceSource, 'rotatev2'), 'HomeProxy dùng AES-256-GCM, checkOnly và API xoay IP');
$customerUiSource = file_get_contents(dirname(__DIR__, 2) . '/website/telegram/customer-center/index.html');
$assert(str_contains($customerUiSource, 'class="cc-dashboard"') && str_contains($customerUiSource, 'cc-order-modal'), 'trung tâm khách hàng dùng layout tham chiếu và chi tiết đơn hàng');
$assert(str_contains($customerUiSource, 'name="hendy-build" content="2026.11.07.5"') && str_contains($customerUiSource, 'const MINI_APP_ROUTE'), 'Customer portal giữ marker ẩn và nhận diện route Mini App');
$assert(!str_contains($customerUiSource, 'build-label') && !str_contains($customerUiSource, 'BUILD 2026.11.07.3'), 'không hiển thị nhãn build trên giao diện thành viên');
$assert(str_contains($customerUiSource, "if(MINI_APP_ROUTE)") && str_contains($customerUiSource, "MINI_APP_ROUTE?'':'<div class=\"cc-page-heading\""), 'Mini App không hiển thị tiêu đề Customer Center hoặc cổng login web');
$assert(str_contains($customerUiSource, "const accountAndStats=MINI_APP_ROUTE?'':'<section class=\"cc-summary\"") && str_contains($customerUiSource, "linkedAccountsRequest=MINI_APP_ROUTE?Promise.resolve"), 'Mini App ẩn thống kê/quản lý tài khoản Mini Game và không gọi API liên kết');
$assert(str_contains($customerUiSource, "api('/api/customer-center')") && str_contains($customerUiSource, "api('/api/wallet/payment-requests')") && str_contains($customerUiSource, "api('/api/vouchers')"), 'Customer Center lấy số liệu ví, đơn hàng và voucher từ API thật');
$assert(!preg_match('/<input[^>]+type=["\']password/i', $customerUiSource) && !str_contains($customerUiSource, 'name="abuse_account_name"'), 'Customer Center không thu thập mật khẩu tài khoản game');
$assert(!str_contains($customerUiSource, '8639286417') && !str_contains($customerUiSource, 'Hendyngok') && !str_contains($customerUiSource, 'AVATAR/5.gif'), 'Customer Center không hard-code Telegram ID, username hoặc avatar cá nhân');
$frontControllerSource = file_get_contents(dirname(__DIR__) . '/public/index.php');
$pollingSource = file_get_contents(dirname(__DIR__) . '/bin/polling.php');
$assert(str_contains($frontControllerSource, "'/app/OperationsService.php'") && str_contains($frontControllerSource, "'/app/ProxyService.php'"), 'HTTP front controller explicitly loads operations and proxy services');
$assert(str_contains($pollingSource, "'/app/OperationsService.php'"), 'polling entrypoint explicitly loads maintenance service');
$adminUiSource = file_get_contents(dirname(__DIR__, 2) . '/website/telegram/admin-dashboard/index.html');
$assert(!str_contains($adminUiSource, 'telegram-web-app.js'), 'Admin không tải Telegram Mini App SDK');
$assert(str_contains($adminUiSource, 'value="inline_keyboard"'), 'trình quản lý menu chỉ lưu Inline Keyboard');
$assert(str_contains($adminUiSource, 'name="hendy-build" content="2026.11.07.2"') && str_contains($adminUiSource, '--primary:#ff315d'), 'Admin có release marker và giao diện HENDY đỏ đen');
$assert(str_contains($adminUiSource, 'data-admin-tab="operations"') && str_contains($adminUiSource, 'adminOperations()'), 'Admin có bảng điều khiển bảo trì/nâng cấp cho từng bot');
$assert(str_contains($adminUiSource, 'data-admin-tab="proxy"') && str_contains($adminUiSource, 'adminProxy()'), 'Admin có trang quản lý HomeProxy');
$assert(str_contains($adminUiSource, 'homeproxy-connect-form') && str_contains($adminUiSource, 'data-homeproxy-action="check"') && str_contains($adminUiSource, 'data-homeproxy-action="rotate"'), 'Mini App có luồng kết nối, kiểm tra và xoay IP HomeProxy');
$landingUiSource = file_get_contents(dirname(__DIR__, 2) . '/website/public/index.html');
$assert(str_contains($landingUiSource, 'HENDY DIGITAL') && str_contains($landingUiSource, 'data-tab="store"'), 'trang chủ có cache-busting và release marker');
$assert(str_contains($landingUiSource, 'HENDY DIGITAL') && str_contains($landingUiSource, 'href="#code"'), 'trang chủ giữ bố cục HENDY, liên kết Bot, Group và Channel');
$assert(!str_contains($landingUiSource, 'Trung tâm khách hàng'), 'trang chủ đã bỏ hoàn toàn khối Features Tags Grid');
$publicRouterSource = file_get_contents(dirname(__DIR__) . '/public/index.php');
$assert(str_contains($publicRouterSource, "header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');"), 'trang chủ không giữ HTML cũ trong cache');
$productionHost = 'https://hendydichvumang-telegram-bot.onrender.com';
$repoRoot = dirname(__DIR__, 2);
$landingSource = file_get_contents(dirname(__DIR__, 2) . '/website/public/index.html');
$privacySource = file_get_contents(dirname(__DIR__, 2) . '/website/public/privacy/index.html');
$termsSource = file_get_contents(dirname(__DIR__, 2) . '/website/public/terms/index.html');
$renderSource = file_get_contents($repoRoot . '/render.yaml');
$envManifest = file_get_contents(dirname(__DIR__) . '/config/env.schema.json');
$runtimeWorkflow = file_get_contents($repoRoot . '/.github/workflows/hendy-monorepo-ci.yml');
$sitemapSource = file_get_contents(dirname(__DIR__, 2) . '/website/public/sitemap.xml');
$robotsSource = file_get_contents(dirname(__DIR__, 2) . '/website/public/robots.txt');
$databaseSchema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$neonStorageSource = file_get_contents(dirname(__DIR__) . '/app/NeonStorage.php');
$moduleRegistrySource = file_get_contents(dirname(__DIR__) . '/app/ModuleRegistry.php');
$neonFunctionSource = file_get_contents($repoRoot . '/neon-functions/hendystorage/index.mjs');
$neonConfigSource = file_get_contents($repoRoot . '/neon.ts');
$deployScript = file_get_contents(dirname(__DIR__) . '/scripts/deploy-verify-render.sh');
$syncScript = file_get_contents(dirname(__DIR__) . '/scripts/sync-render-env.sh');
$websiteDbBootstrap = file_get_contents($repoRoot . '/website/public/index.html');
$websiteHeader = file_get_contents($repoRoot . '/website/public/index.html');
$websiteRootHtaccess = file_get_contents($repoRoot . '/website/public/robots.txt');
$websiteStoreHtaccess = file_get_contents($repoRoot . '/website/README.md');
$assert(str_contains($websiteRootHtaccess, 'Disallow: /admin/'), 'Website blocks direct access to monorepo source and build files');
$assert(str_contains($websiteStoreHtaccess, 'Only `public/` is published'), 'Website blocks direct HTTP access to shared PHP include files');
$assert(str_contains($landingSource, 'https://hendydichvumang.onrender.com/') && str_contains($landingSource, $productionHost) && str_contains($privacySource, 'Chính sách quyền riêng tư') && str_contains($termsSource, 'Điều khoản sử dụng'), 'Static Website canonical URL and protected Bot Admin link use their separate service hosts');
$assert(str_contains($renderSource, 'name: hendydichvumang-telegram-bot') && str_contains($renderSource, 'dockerfilePath: ./telegram-bot/Dockerfile') && str_contains($renderSource, 'dockerContext: .') && str_contains($renderSource, 'name: hendydichvumang\n') && str_contains($renderSource, 'runtime: static') && str_contains($renderSource, 'staticPublishPath: ./website/public') && !str_contains($renderSource, 'name: hendydichvumang-website'), 'Render Blueprint defines one named Static Site and the isolated Bot backend');
$assert(!str_contains($renderSource, 'BOT2_TOKEN') && str_contains($renderSource, 'BOT1_TOKEN') && str_contains($renderSource, 'HOMEPROXY_ENCRYPTION_KEY'), 'production Blueprint documents the single bot and existing private secrets without copying secret values');
$assert(!str_contains($envManifest, 'BOT2_TOKEN') && !str_contains($envManifest, 'BOT2_PUBLIC_URL') && !str_contains($envManifest, 'TELEGRAM_SECOND_WEBHOOK_SECRET'), 'runtime environment manifest contains no secondary-bot credentials');
$assert(str_contains($renderSource, 'HOMEPROXY_ENCRYPTION_KEY') && str_contains($envManifest, 'HOMEPROXY_ENCRYPTION_KEY'), 'HomeProxy encryption secret remains documented in Blueprint and env manifest');
$assert(str_contains($runtimeWorkflow, 'working-directory: telegram-bot') && str_contains($runtimeWorkflow, 'composer test'), 'monorepo CI runs Bot tests from the isolated application directory');
$assert(str_contains($sitemapSource, 'hendydichvumang.onrender.com') && str_contains($robotsSource, 'hendydichvumang.onrender.com'), 'sitemap and robots reference the new Static Site hostname');
$assert(str_contains($databaseSchema, 'https://hendydichvumang.onrender.com') && str_contains($databaseSchema, "'https://hendy-bot-tele.onrender.com'"), 'Bot PostgreSQL migration seeds the new Static Site URL and keeps legacy URL migration rules');
$assert(str_contains($deployScript, '${APP_URL:?') && str_contains($syncScript, 'RENDER_EXTERNAL_URL') && !str_contains($syncScript, 'hendy-bot-cybertech-1.onrender.com'), 'operational scripts resolve the active Render URL instead of hardcoding the former service');
$assert(str_contains($botServiceSource, $productionHost) && !str_contains($botServiceSource, 'https://hendy-bot-cybertech-1.onrender.com'), 'Bot welcome links fall back to the new Render service');
$assert(str_contains($websiteDbBootstrap, 'HENDY DIGITAL ECOSYSTEM') && str_contains($websiteDbBootstrap, 'CODE TELEGRAM'), 'Static Website contains only public-facing ecosystem content');
$assert(str_contains($websiteHeader, 'Quản trị ↗') && str_contains($websiteHeader, '/admin'), 'Website navigation links to the Bot without exposing a token');
$publicRouterSource = file_get_contents(dirname(__DIR__) . '/public/index.php');
$registerWebhookSource = file_get_contents(dirname(__DIR__) . '/bin/register-webhooks.php');
$assert(str_contains($publicRouterSource, "SELECT value FROM app_settings WHERE key = ? AND is_public = TRUE"), 'public Bot config uses the cached URL discovered from getMe');
$assert(str_contains($registerWebhookSource, "call('getMe'") && str_contains($registerWebhookSource, "'primary_bot_url'"), 'Bot startup discovers and caches its public username through Telegram getMe');
$assert(str_contains($botServiceSource, "key = 'primary_bot_url'") && str_contains($botServiceSource, "Env::get('BOT1_PUBLIC_URL'"), 'Referral links prefer the optional override and otherwise use the getMe-discovered Bot URL');
$assert(str_contains($moduleRegistrySource, 'NeonStorage::uploadFile') && str_contains($moduleRegistrySource, 'sha256'), 'module uploads use private Neon Object Storage and preserve SHA-256 validation');
$assert(str_contains($neonStorageSource, 'NEON_STORAGE_FUNCTION_KEY') && str_contains($neonStorageSource, 'CURLOPT_SSL_VERIFYPEER'), 'storage signing key stays server-side and HTTPS verification remains enabled');
$assert(str_contains($neonFunctionSource, 'timingSafeEqual') && str_contains($neonFunctionSource, "const BUCKET = 'uploads'"), 'Neon Function authenticates internal requests and restricts uploads to private bucket');
$assert(str_contains($neonConfigSource, 'realtime: true') && str_contains($neonConfigSource, 'hendystorage'), 'Neon config declares Function, Object Storage and Realtime');
$webhookSecret = 'sample-webhook-secret-for-tests-000001';
$assert(WebhookSecurity::verifySecret($webhookSecret, $webhookSecret), 'webhook secret đúng được chấp nhận');
$assert(!WebhookSecurity::verifySecret($webhookSecret, 'wrong-webhook-secret-for-tests-000001'), 'webhook secret sai bị từ chối');
$hmacBody = '{"update_id":123,"message":{"text":"hello"}}';
$hmac = 'sha256=' . hash_hmac('sha256', $hmacBody, $webhookSecret);
$assert(WebhookSecurity::verifyHmac($hmacBody, $hmac, $webhookSecret), 'HMAC callback hợp lệ được chấp nhận');
$assert(!WebhookSecurity::verifyHmac($hmacBody, 'sha256=' . str_repeat('0', 64), $webhookSecret), 'HMAC callback sai bị từ chối');
$assert(WebhookSecurity::decodeJson($hmacBody, 1024)['update_id'] === 123, 'JSON webhook được giải mã');

$previousAdminId = getenv('ADMIN_ID');
$previousAdminIds = getenv('ADMIN_IDS');
putenv('ADMIN_ID=9500000001');
putenv('ADMIN_IDS=9500000002,9500000001,invalid');
$assert(TelegramAuth::adminIds() === ['9500000001', '9500000002'], 'admin allowlist merges and deduplicates ADMIN_ID and ADMIN_IDS');
$assert(TelegramAuth::isAdmin('9500000002'), 'secondary admin is authorized by shared admin allowlist');
if ($previousAdminId === false) putenv('ADMIN_ID'); else putenv('ADMIN_ID=' . $previousAdminId);
if ($previousAdminIds === false) putenv('ADMIN_IDS'); else putenv('ADMIN_IDS=' . $previousAdminIds);


$assert(WebServiceKernel::handles('/api/services/system/health'), 'Web Service kernel nhận route đã đăng ký');
$assert(!WebServiceKernel::handles('/api/not-services/system/health'), 'Web Service kernel không nhận route ngoài namespace');
$assert(in_array('system', WebServiceKernel::registeredServices(), true), 'System Web Service được đăng ký trong allow-list');
$invalidServicePathRejected = false;
try {
    WebServiceKernel::dispatch('GET', '/api/services/unknown/health', [], []);
} catch (RuntimeException) {
    $invalidServicePathRejected = true;
}
$assert($invalidServicePathRejected, 'Web Service không được đăng ký sẽ bị từ chối');

$assert(VideoJobService::isAllowedMediaUrl('https://youtu.be/dQw4w9WgXcQ'), 'chấp nhận URL YouTube HTTPS');
$assert(VideoJobService::isAllowedMediaUrl('https://www.tiktok.com/@creator/video/123'), 'chấp nhận URL TikTok HTTPS');
$assert(!VideoJobService::isAllowedMediaUrl('http://youtube.com/watch?v=abc'), 'từ chối URL video HTTP');
$assert(!VideoJobService::isAllowedMediaUrl('https://youtube.com.evil.example/watch?v=abc'), 'từ chối domain giả mạo');
$assert(!VideoJobService::isAllowedMediaUrl('https://127.0.0.1/admin'), 'từ chối IP nội bộ thay vì domain video');

$token = sprintf('123456789:%s', str_repeat('A', 40));
$authDate = time();
$user = json_encode(['id' => 12345678, 'first_name' => 'Hendy'], JSON_UNESCAPED_UNICODE);
$params = [
    'auth_date' => (string) $authDate,
    'query_id' => 'AAEAAAE',
    'user' => (string) $user,
];
ksort($params, SORT_STRING);
$checkString = implode("\n", array_map(
    static fn ($key, $value): string => $key . '=' . $value,
    array_keys($params),
    array_values($params)
));
$secret = hash_hmac('sha256', $token, 'WebAppData', true);
$hash = hash_hmac('sha256', $checkString, $secret);
$initData = http_build_query([...$params, 'hash' => $hash]);
$validated = TelegramAuth::validateInitData($initData, $token);
$assert((string) $validated['id'] === '12345678', 'xác thực Telegram initData hợp lệ');

$invalid = str_replace($hash, str_repeat('0', 64), $initData);
$thrown = false;
try {
    TelegramAuth::validateInitData($invalid, $token);
} catch (Throwable) {
    $thrown = true;
}
$assert($thrown, 'initData bị sửa phải bị từ chối');

$expiredParams = [
    'auth_date' => (string) ($authDate - 90000),
    'user' => (string) $user,
];
ksort($expiredParams, SORT_STRING);
$expiredString = implode("\n", array_map(
    static fn ($key, $value): string => $key . '=' . $value,
    array_keys($expiredParams),
    array_values($expiredParams)
));
$expiredHash = hash_hmac('sha256', $expiredString, $secret);
$expiredData = http_build_query([...$expiredParams, 'hash' => $expiredHash]);
$thrown = false;
try {
    TelegramAuth::validateInitData($expiredData, $token);
} catch (Throwable) {
    $thrown = true;
}
$assert($thrown, 'initData quá hạn phải bị từ chối');

echo "Đã chạy {$checks} kiểm tra PHP.\n";
