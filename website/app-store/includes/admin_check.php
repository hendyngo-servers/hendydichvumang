<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
if (!current_user()) {
    flash('Vui lòng đăng nhập để tiếp tục.', 'error');
    redirect('/login.php');
}
if (!is_admin()) {
    http_response_code(403);
    exit('Bạn không có quyền truy cập khu vực quản trị.');
}
