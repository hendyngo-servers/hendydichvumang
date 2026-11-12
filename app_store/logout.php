<?php
declare(strict_types=1);
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Method not allowed');}
require dirname(__DIR__) . '/logout.php';
