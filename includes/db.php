<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE){$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax']);session_start();}
function envv(string $k,?string $d=null):?string{$v=getenv($k);return $v===false||$v===''?$d:$v;}
try{$pdo=new PDO('mysql:host='.envv('DB_HOST','127.0.0.1').';port='.envv('DB_PORT','3306').';dbname='.envv('DB_NAME','hendydichvumang').';charset=utf8mb4',envv('DB_USER','root'),envv('DB_PASSWORD',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}catch(PDOException $e){http_response_code(500);exit('Không thể kết nối cơ sở dữ liệu. Kiểm tra cấu hình DB_* trong môi trường triển khai.');}
function e(?string $s):string{return htmlspecialchars($s??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function redirect(string $u):never{header('Location: '.$u);exit;}
function csrf_token():string{if(empty($_SESSION['_csrf']))$_SESSION['_csrf']=bin2hex(random_bytes(32));return $_SESSION['_csrf'];}
function verify_csrf():void{$v=$_POST['_csrf']??'';if(!is_string($v)||!hash_equals(csrf_token(),$v)){http_response_code(419);exit('Yêu cầu hết hạn hoặc không hợp lệ. Hãy tải lại trang.');}}
function flash(string $m,string $t='success'):void{$_SESSION['_flash']=['message'=>$m,'type'=>$t];}
function current_user():?array{return $_SESSION['user']??null;}
function is_admin():bool{return(current_user()['role']??'')==='admin';}
function store_screenshot(array $f):?string{if(($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;if(($f['error']??0)!==UPLOAD_ERR_OK)throw new RuntimeException('Tải ảnh lên thất bại.');if(($f['size']??0)>5242880)throw new RuntimeException('Mỗi ảnh tối đa 5 MB.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];if(!isset($types[$mime])||@getimagesize($f['tmp_name'])===false)throw new RuntimeException('Chỉ nhận ảnh JPG, PNG, WEBP hoặc GIF hợp lệ.');$dir=__DIR__.'/../uploads/screenshots';if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Không tạo được thư mục ảnh.');$n=bin2hex(random_bytes(16)).'.'.$types[$mime];if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$n))throw new RuntimeException('Không lưu được ảnh.');return'uploads/screenshots/'.$n;}
function delete_local_image(?string $p):void{if(!$p||!str_starts_with($p,'uploads/screenshots/'))return;$b=realpath(__DIR__.'/../uploads/screenshots');$f=realpath(__DIR__.'/../'.$p);if($b&&$f&&str_starts_with($f,$b.DIRECTORY_SEPARATOR)&&is_file($f))@unlink($f);}
$ae=envv('ADMIN_EMAIL');$ap=envv('ADMIN_PASSWORD');if($ae&&$ap&&filter_var($ae,FILTER_VALIDATE_EMAIL)&&(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()===0){$s=$pdo->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'admin')");$s->execute(['HENDY Administrator',strtolower($ae),password_hash($ap,PASSWORD_DEFAULT)]);}

function telegram_bot_url():?string{
    $url=trim((string)envv('TELEGRAM_BOT_URL',''));
    if($url==='')return null;
    $parts=parse_url($url);
    if(!is_array($parts)
        || strtolower((string)($parts['scheme']??''))!=='https'
        || !in_array(strtolower((string)($parts['host']??'')),['t.me','telegram.me'],true)
        || isset($parts['user'])||isset($parts['pass'])
        || empty($parts['path'])||trim((string)$parts['path'],'/')==='')return null;
    return $url;
}
