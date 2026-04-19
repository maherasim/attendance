<?php
/**
 * Diagnostics — open https://yoursite/health.php
 * Remove or protect this file after the site works.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

echo "Attendance — health check\n";
echo str_repeat('=', 40) . "\n";
echo 'PHP: ' . PHP_VERSION . " (7.4+ recommended)\n\n";

$configFile = __DIR__ . '/config.php';
if (!is_readable($configFile)) {
    echo "FAIL: config.php missing or not readable.\n";
    echo "Fix: Upload config.php (copy from config.example.php) with DB_HOST, DB_NAME, DB_USER, DB_PASS.\n";
    exit;
}
echo "OK: config.php readable\n";

require $configFile;

foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'] as $c) {
    if (!defined($c)) {
        echo "FAIL: constant $c not defined in config.php\n";
        exit;
    }
}
echo 'OK: DB constants set (host=' . DB_HOST . ', db=' . DB_NAME . ")\n\n";

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "OK: MySQL connection opened\n";
} catch (Throwable $e) {
    echo 'FAIL: ' . $e->getMessage() . "\n";
    echo "\nTips:\n";
    echo "- In hosting panel, copy the exact MySQL hostname (may not be 'localhost').\n";
    echo "- Confirm database name, user, and password match phpMyAdmin.\n";
    echo "- User must be allowed to connect from this server.\n";
    exit;
}

try {
    $n = (int) $pdo->query('SELECT COUNT(*) FROM uc_offices')->fetchColumn();
    echo "OK: table uc_offices exists ($n rows)\n";
} catch (Throwable $e) {
    echo 'WARN: uc_offices — ' . $e->getMessage() . "\n";
    echo "Import schema.sql into database " . DB_NAME . "\n";
}

try {
    $pdo->query('SELECT 1 FROM attendance LIMIT 1');
    echo "OK: table attendance exists\n";
} catch (Throwable $e) {
    echo 'WARN: attendance — ' . $e->getMessage() . "\n";
}

echo "\nDone. If all OK, index.php should load. Delete health.php when finished.\n";
