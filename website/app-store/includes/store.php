<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function app_store_valid_https_url(string $url, bool $emptyAllowed = false): bool
{
    $url = trim($url);
    if ($url === '') return $emptyAllowed;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($url);
    return is_array($parts)
        && strtolower((string)($parts['scheme'] ?? '')) === 'https'
        && !empty($parts['host'])
        && !isset($parts['user'])
        && !isset($parts['pass']);
}

function app_store_store_screenshot(array $file): ?string
{
    $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
    if($error===UPLOAD_ERR_NO_FILE)return null;
    if($error!==UPLOAD_ERR_OK)throw new RuntimeException('Tải ảnh lên thất bại.');
    if((int)($file['size']??0)<1||(int)($file['size']??0)>5242880)throw new RuntimeException('Mỗi ảnh tối đa 5 MB.');
    $tmp=(string)($file['tmp_name']??'');
    if($tmp===''||!is_uploaded_file($tmp))throw new RuntimeException('Nguồn ảnh không hợp lệ.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if(!isset($types[$mime])||@getimagesize($tmp)===false)throw new RuntimeException('Chỉ nhận ảnh JPG, PNG, WEBP hoặc GIF hợp lệ.');
    if(neon_storage_configured())return store_screenshot($file);
    $dir=__DIR__.'/../uploads/screenshots';
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Không tạo được thư mục ảnh App Store.');
    $name=bin2hex(random_bytes(16)).'.'.$types[$mime];
    if(!move_uploaded_file($tmp,$dir.'/'.$name))throw new RuntimeException('Không lưu được ảnh App Store.');
    return 'app_store/uploads/screenshots/'.$name;
}

function app_store_image_url(?string $path): string
{
    if(!$path)return '';
    if(str_starts_with($path,'app_store/uploads/screenshots/'))return '/'.$path;
    if(str_starts_with($path,'uploads/screenshots/'))return '/'.$path;
    if(str_starts_with($path,'website/screenshots/'))return screenshot_url($path);
    return $path;
}

function app_store_delete_image(string $path): void
{
    if(str_starts_with($path,'app_store/uploads/screenshots/')){
        $base=realpath(__DIR__.'/../uploads/screenshots');
        $file=$base ? realpath($base.'/'.basename($path)) : false;
        if($base&&$file&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)&&is_file($file))@unlink($file);
        return;
    }
    delete_local_image($path);
}

function app_store_upload_screenshots(PDO $pdo, int $appId): void
{
    $files = $_FILES['screenshots'] ?? null;
    if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) return;
    if (count($files['name']) > 8) throw new RuntimeException('Mỗi lần chỉ được tải lên tối đa 8 ảnh.');
    $insert = $pdo->prepare('INSERT INTO app_screenshots(app_id,image_url,sort_order) VALUES(?,?,?)');
    foreach ($files['name'] as $i => $name) {
        $error = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        $path = app_store_store_screenshot([
            'name'=>(string)$name,
            'tmp_name'=>(string)($files['tmp_name'][$i] ?? ''),
            'error'=>$error,
            'size'=>(int)($files['size'][$i] ?? 0)
        ]);
        if ($path !== null) $insert->execute([$appId,$path,$i]);
    }
}

function app_store_delete_app(PDO $pdo, int $id): void
{
    $s=$pdo->prepare('SELECT image_url FROM app_screenshots WHERE app_id=?');
    $s->execute([$id]);
    $images=$s->fetchAll(PDO::FETCH_COLUMN);
    $d=$pdo->prepare('DELETE FROM apps WHERE id=?');
    $d->execute([$id]);
    foreach ($images as $image) app_store_delete_image((string)$image);
}
