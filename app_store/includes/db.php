<?php
declare(strict_types=1);

$forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}

function envv(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : (string) $value;
}

function open_database(): PDO
{
    $url = envv('DATABASE_URL');
    if ($url !== null) {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['postgres', 'postgresql'], true)
            || empty($parts['host']) || empty($parts['path']) || empty($parts['user'])) {
            throw new RuntimeException('DATABASE_URL không phải PostgreSQL URL hợp lệ.');
        }
        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? 5432);
        $database = rawurldecode(ltrim((string) $parts['path'], '/'));
        $username = rawurldecode((string) $parts['user']);
        $password = rawurldecode((string) ($parts['pass'] ?? ''));
        if (!preg_match('/\A[A-Za-z0-9.-]+\z/D', $host) || !preg_match('/\A[A-Za-z0-9_.-]+\z/D', $database)
            || $port < 1 || $port > 65535) {
            throw new RuntimeException('Host, port hoặc database trong DATABASE_URL không hợp lệ.');
        }
        parse_str((string) ($parts['query'] ?? ''), $query);
        $sslMode = filter_var(envv('DATABASE_SSL', 'true'), FILTER_VALIDATE_BOOLEAN)
            ? (string) ($query['sslmode'] ?? 'require') : 'disable';
        if (!in_array($sslMode, ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
            $sslMode = 'require';
        }
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s', $host, $port, $database, $sslMode),
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 10, PDO::ATTR_PERSISTENT => false]
        );
        $pdo->exec("SET TIME ZONE 'UTC'");
        return $pdo;
    }

    // Keep compatibility with existing MySQL hosting configurations.
    return new PDO(
        'mysql:host=' . envv('DB_HOST', '127.0.0.1') . ';port=' . envv('DB_PORT', '3306')
        . ';dbname=' . envv('DB_NAME', 'hendydichvumang') . ';charset=utf8mb4',
        envv('DB_USER', 'root'),
        envv('DB_PASSWORD', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
}

try {
    $pdo = open_database();
} catch (Throwable $error) {
    error_log('Website database connection failed: ' . $error->getMessage());
    http_response_code(503);
    exit('Dịch vụ dữ liệu đang tạm thời không khả dụng. Vui lòng thử lại sau.');
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}
function csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function verify_csrf(): void
{
    $value = $_POST['_csrf'] ?? '';
    if (!is_string($value) || !hash_equals(csrf_token(), $value)) {
        http_response_code(419); exit('Yêu cầu hết hạn hoặc không hợp lệ. Hãy tải lại trang.');
    }
}
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
}
function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}
function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}
function neon_storage_configured(): bool
{
    return trim((string) envv('NEON_STORAGE_FUNCTION_URL', '')) !== ''
        && trim((string) envv('NEON_STORAGE_FUNCTION_KEY', '')) !== '';
}

/** Get a short-lived URL from the private Neon Function. */
function neon_storage_request(string $action, string $key, string $contentType = ''): array
{
    if (!neon_storage_configured()) throw new RuntimeException('Neon Object Storage chưa được cấu hình.');
    if (!preg_match('#\Awebsite/screenshots/[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z#', $key)) {
        throw new RuntimeException('Đường dẫn ảnh lưu trữ không hợp lệ.');
    }
    if (!in_array($action, ['upload', 'download', 'delete'], true)) throw new RuntimeException('Thao tác lưu trữ không hợp lệ.');
    $base = rtrim((string) envv('NEON_STORAGE_FUNCTION_URL'), '/') . '/website/presign';
    $body = json_encode(['action' => $action, 'key' => $key, 'contentType' => $contentType, 'expiresIn' => 300],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $curl = curl_init($base);
    if ($curl === false) throw new RuntimeException('Không khởi tạo được kết nối Neon Function.');
    try {
        curl_setopt_array($curl, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . (string) envv('NEON_STORAGE_FUNCTION_KEY'),
                'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Neon Function không cấp được URL lưu trữ (HTTP ' . $status . ').');
        }
        $data = json_decode((string) $response, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_string($data['url'] ?? null) || !is_string($data['method'] ?? null)) {
            throw new RuntimeException('Phản hồi Neon Function không hợp lệ.');
        }
        $parts = parse_url($data['url']);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !str_contains(strtolower((string) ($parts['host'] ?? '')), '.storage.')
            || !str_ends_with(strtolower((string) ($parts['host'] ?? '')), '.neon.tech')) {
            throw new RuntimeException('Neon Function trả về URL lưu trữ không an toàn.');
        }
        return $data;
    } catch (JsonException $error) {
        throw new RuntimeException('Neon Function trả về JSON không hợp lệ.', 0, $error);
    } finally {
        curl_close($curl);
    }
}

function neon_storage_upload(string $localPath, string $objectKey, string $mime): void
{
    $signed = neon_storage_request('upload', $objectKey, $mime);
    $stream = fopen($localPath, 'rb');
    if ($stream === false) throw new RuntimeException('Không mở được ảnh tải lên.');
    $curl = curl_init($signed['url']);
    if ($curl === false) { fclose($stream); throw new RuntimeException('Không khởi tạo được upload tới Neon.'); }
    try {
        curl_setopt_array($curl, [
            CURLOPT_UPLOAD => true, CURLOPT_INFILE => $stream, CURLOPT_INFILESIZE => filesize($localPath),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: ' . $mime, 'Expect:'],
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 45, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Không lưu được ảnh lên Neon Object Storage (HTTP ' . $status . ').');
        }
    } finally { curl_close($curl); fclose($stream); }
}

function screenshot_url(?string $path): string
{
    if (!$path) return '';
    static $cache = [];
    if (isset($cache[$path])) return $cache[$path];
    if (str_starts_with($path, 'website/screenshots/')) {
        try { return $cache[$path] = (string) neon_storage_request('download', $path)['url']; }
        catch (Throwable $error) { error_log('Neon screenshot URL failed: ' . $error->getMessage()); return ''; }
    }
    // The public home page now lives under /app_store/, while legacy website
    // screenshots remain in the root /uploads directory.
    if (str_starts_with($path, 'uploads/screenshots/')) return $cache[$path] = '/' . $path;
    return $path;
}

function store_screenshot(array $file): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('Tải ảnh lên thất bại.');
    if (($file['size'] ?? 0) < 1 || ($file['size'] ?? 0) > 5242880) throw new RuntimeException('Mỗi ảnh tối đa 5 MB.');
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Nguồn ảnh tải lên không hợp lệ.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $types = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if (!isset($types[$mime]) || @getimagesize($tmp) === false) throw new RuntimeException('Chỉ nhận ảnh JPG, PNG, WEBP hoặc GIF hợp lệ.');
    if (neon_storage_configured()) {
        $key = 'website/screenshots/' . bin2hex(random_bytes(16)) . '.' . $types[$mime];
        neon_storage_upload($tmp, $key, $mime);
        return $key;
    }
    $directory = dirname(__DIR__, 2) . '/uploads/screenshots';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Không tạo được thư mục ảnh.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $types[$mime];
    if (!move_uploaded_file($tmp, $directory . '/' . $name)) throw new RuntimeException('Không lưu được ảnh.');
    return 'uploads/screenshots/' . $name;
}

function delete_local_image(?string $path): void
{
    if (!$path) return;
    if (str_starts_with($path, 'website/screenshots/')) {
        try {
            $signed = neon_storage_request('delete', $path);
            $curl = curl_init($signed['url']);
            if ($curl === false) throw new RuntimeException('Không khởi tạo được yêu cầu xóa ảnh.');
            try {
                curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false]);
                $response = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                if ($response === false || !in_array($status, [200,204,404], true)) {
                    throw new RuntimeException('Neon Storage không xóa được ảnh (HTTP ' . $status . ').');
                }
            } finally { curl_close($curl); }
        } catch (Throwable $error) { error_log('Neon screenshot delete failed: ' . $error->getMessage()); }
        return;
    }
    if (!str_starts_with($path, 'uploads/screenshots/')) return;
    $base = realpath(dirname(__DIR__, 2) . '/uploads/screenshots');
    $file = realpath(dirname(__DIR__, 2) . '/' . $path);
    if ($base && $file && str_starts_with($file, $base . DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
}

function telegram_bot_url(): ?string
{
    $url = trim((string) envv('TELEGRAM_BOT_URL', ''));
    if ($url === '') return null;
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || !in_array(strtolower((string) ($parts['host'] ?? '')), ['t.me','telegram.me'], true)
        || isset($parts['user']) || isset($parts['pass']) || empty($parts['path'])
        || trim((string) $parts['path'], '/') === '') return null;
    return $url;
}

// Seed the initial admin once, only when an operator configured credentials in Render.
$adminEmail = trim((string) envv('ADMIN_EMAIL', ''));
$adminPassword = (string) envv('ADMIN_PASSWORD', '');
if ($adminEmail !== '' && $adminPassword !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if ($count === 0) {
        $statement = $pdo->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'admin')");
        $statement->execute(['HENDY Administrator', strtolower($adminEmail), password_hash($adminPassword, PASSWORD_DEFAULT)]);
    }
}
