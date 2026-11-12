<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function website_valid_https_url(string $url, bool $emptyAllowed = true): bool
{
    $url = trim($url);
    if ($url === '') return $emptyAllowed;
    if (strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) return false;
    $parts = parse_url($url);
    return is_array($parts)
        && strtolower((string)($parts['scheme'] ?? '')) === 'https'
        && !empty($parts['host'])
        && !isset($parts['user'])
        && !isset($parts['pass'])
        && (!isset($parts['port']) || (int)$parts['port'] === 443);
}

/**
 * Store a small gallery for the existing Website catalogue's screenshots table.
 * The caller controls the product transaction; this helper deletes any files
 * already written if a later upload/insert in the same batch fails.
 *
 * @return list<string> paths written during this call
 */
function website_upload_product_screenshots(PDO $pdo, int $productId): array
{
    $files = $_FILES['screenshots'] ?? null;
    if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) return [];
    if (count($files['name']) > 8) throw new RuntimeException('Mỗi lần chỉ được tải lên tối đa 8 ảnh.');

    $insert = $pdo->prepare('INSERT INTO screenshots(product_id,image_path,caption,sort_order) VALUES(?,?,?,?)');
    $saved = [];
    try {
        foreach ($files['name'] as $index => $name) {
            $error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            $path = store_screenshot([
                'name'=>(string)$name,
                'tmp_name'=>(string)($files['tmp_name'][$index] ?? ''),
                'error'=>$error,
                'size'=>(int)($files['size'][$index] ?? 0),
            ]);
            if ($path === null) continue;
            $saved[] = $path;
            $caption = trim((string)($_POST['screenshot_caption'][$index] ?? ''));
            $insert->execute([$productId,$path,mb_substr($caption,0,255),$index]);
        }
        return $saved;
    } catch (Throwable $error) {
        foreach ($saved as $path) delete_local_image($path);
        throw $error;
    }
}

function website_product_screenshots(PDO $pdo, int $productId): array
{
    $query = $pdo->prepare('SELECT id,image_path,caption,sort_order FROM screenshots WHERE product_id=? ORDER BY sort_order,id');
    $query->execute([$productId]);
    return $query->fetchAll();
}
