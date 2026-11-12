<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Method not allowed'); }
verify_csrf();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
 $p=session_get_cookie_params();
 setcookie(session_name(),'', ['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Lax']);
}
session_destroy();
redirect('index.php');
