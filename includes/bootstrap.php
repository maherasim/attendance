<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/functions.php';

date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Karachi');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function admin_required(): void
{
    if (empty($_SESSION[SESSION_ADMIN_KEY])) {
        header('Location: admin.php');
        exit;
    }
}

$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isApi = strncmp($script, 'api_', 4) === 0;

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log('Attendance PDO: ' . $e->getMessage());
    if ($isApi) {
        json_out(['success' => false, 'error' => 'Database unavailable. Run health.php on the server.'], 503);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $health = ($base === '' ? '' : $base) . '/health.php';
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Database</title></head><body>';
    echo '<h1>Database connection failed</h1>';
    echo '<p>Open <a href="' . htmlspecialchars($health) . '">health.php</a> for diagnostics.</p>';
    echo '</body></html>';
    exit;
}
