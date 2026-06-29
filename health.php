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

$uploadDir = __DIR__ . '/uploads/';
$phpUser = '?';
if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
    $pw = @posix_getpwuid(posix_geteuid());
    $phpUser = $pw ? $pw['name'] : (string) posix_geteuid();
}
echo "INFO: PHP running as user: $phpUser\n";
echo "INFO: open_basedir = " . (ini_get('open_basedir') ?: 'none (unrestricted)') . "\n";
echo "INFO: upload_tmp_dir = " . (ini_get('upload_tmp_dir') ?: sys_get_temp_dir()) . "\n\n";

if (!is_dir($uploadDir)) {
    echo "WARN: uploads/ directory missing\n";
    if (@mkdir($uploadDir, 0755, true)) {
        echo "OK: uploads/ created by health check\n";
    } else {
        echo "FAIL: Could not create uploads/ — check open_basedir or parent directory permissions\n";
    }
}
if (is_dir($uploadDir)) {
    $perms = substr(sprintf('%o', fileperms($uploadDir)), -4);
    $owner = function_exists('posix_getpwuid') ? (@posix_getpwuid(fileowner($uploadDir))['name'] ?? fileowner($uploadDir)) : fileowner($uploadDir);
    echo "INFO: uploads/ owner=$owner perms=$perms\n";
    $testFile = $uploadDir . '.write_test_' . time();
    if (@file_put_contents($testFile, 'ok') !== false) {
        @unlink($testFile);
        echo "OK: uploads/ is writable (actual write test passed)\n";
    } else {
        echo "FAIL: uploads/ write test failed — run on server: chmod 777 " . realpath($uploadDir) . "\n";
        if (ini_get('open_basedir')) {
            echo "NOTE: open_basedir is set — uploads path must be inside: " . ini_get('open_basedir') . "\n";
        }
    }
}

echo "\nDone. If all OK, index.php should load. Delete health.php when finished.\n";
