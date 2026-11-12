<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    $pdo->query('SELECT 1')->fetchColumn();
    echo json_encode(['status'=>'ok','service'=>'hendy-website'], JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('Website health check failed: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['status'=>'unavailable','service'=>'hendy-website']);
}
