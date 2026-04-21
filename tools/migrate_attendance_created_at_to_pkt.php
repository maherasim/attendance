<?php

declare(strict_types=1);

/**
 * One-time: reinterpret naive created_at as --from-zone wall clock, rewrite as Asia/Karachi naive
 * (same instant). Use for rows written under EU server time before switching to PKT storage.
 *
 * Examples:
 *   php tools/migrate_attendance_created_at_to_pkt.php --max-id=500 --dry-run
 *   php tools/migrate_attendance_created_at_to_pkt.php --max-id=500 --from=Europe/Berlin
 */
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require dirname(__DIR__) . '/config.php';

$fromZoneName = 'Europe/Berlin';
$maxId = null;
$minId = 1;
$dry = false;
foreach ($argv as $arg) {
    if ($arg === '--dry-run') {
        $dry = true;
    } elseif (str_starts_with($arg, '--from=')) {
        $fromZoneName = substr($arg, 7);
    } elseif (str_starts_with($arg, '--max-id=')) {
        $maxId = (int) substr($arg, 9);
    } elseif (str_starts_with($arg, '--min-id=')) {
        $minId = max(1, (int) substr($arg, 9));
    }
}

if ($maxId === null || $maxId < 1) {
    fwrite(STDERR, "Usage: php tools/migrate_attendance_created_at_to_pkt.php --max-id=N [options]\n");
    fwrite(STDERR, "Options: --min-id=M (default 1)  --from=Europe/Berlin  --dry-run\n");
    exit(1);
}

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$fromZ = new DateTimeZone($fromZoneName);
$toZ = new DateTimeZone('Asia/Karachi');

$st = $pdo->prepare('SELECT id, created_at FROM attendance WHERE id >= ? AND id <= ? ORDER BY id');
$st->execute([$minId, $maxId]);
$rows = $st->fetchAll();

$changed = 0;
$preview = 0;
$previewLimit = 15;

foreach ($rows as $r) {
    $id = (int) $r['id'];
    $old = (string) $r['created_at'];
    try {
        $dt = new DateTimeImmutable($old, $fromZ);
    } catch (Exception $e) {
        fwrite(STDERR, "Skip id={$id} invalid created_at: {$old}\n");
        continue;
    }
    $new = $dt->setTimezone($toZ)->format('Y-m-d H:i:s');
    if ($old === $new) {
        continue;
    }
    if ($dry) {
        if ($preview < $previewLimit) {
            echo "{$id}: {$old} -> {$new}\n";
            $preview++;
        }
        $changed++;
        continue;
    }
    $u = $pdo->prepare('UPDATE attendance SET created_at = ? WHERE id = ?');
    $u->execute([$new, $id]);
    $changed++;
}

if ($dry && $changed > $previewLimit) {
    echo "... and " . ($changed - $previewLimit) . " more (use without --dry-run to apply).\n";
}
echo $dry ? "Dry-run: {$changed} row(s) would change.\n" : "Updated {$changed} row(s).\n";
echo "Set DB_DATETIME_ZONE to Asia/Karachi in config.php after verifying.\n";
