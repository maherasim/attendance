<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (empty($_SESSION[SESSION_ADMIN_KEY])) {
    http_response_code(403);
    exit('Forbidden');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    http_response_code(400);
    exit('Bad request');
}

$st = $pdo->prepare('SELECT photo_file FROM attendance WHERE id = ? LIMIT 1');
$st->execute([$id]);
$row = $st->fetch();
if (!$row || !$row['photo_file']) {
    http_response_code(404);
    exit('Not found');
}

$path = UPLOAD_DIR . basename($row['photo_file']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if ($ext === 'jpg' || $ext === 'jpeg') {
    $mime = 'image/jpeg';
} elseif ($ext === 'png') {
    $mime = 'image/png';
} elseif ($ext === 'webp') {
    $mime = 'image/webp';
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
readfile($path);
