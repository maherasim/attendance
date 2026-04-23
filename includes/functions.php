<?php
declare(strict_types=1);

function distance_meters(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $R = 6371000.0;
    $toRad = function (float $d): float {
        return $d * M_PI / 180.0;
    };
    $dLat = $toRad($lat2 - $lat1);
    $dLng = $toRad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ensure_upload_dir(): void
{
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
}

/** Format MySQL created_at for Pakistan (PKT). */
function attendance_format_time(?string $mysqlDatetime): string
{
    if ($mysqlDatetime === null || $mysqlDatetime === '') {
        return '';
    }
    $fromZone = defined('DB_DATETIME_ZONE') ? DB_DATETIME_ZONE : 'UTC';
    $toZone = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Karachi';
    try {
        $dt = new DateTimeImmutable($mysqlDatetime, new DateTimeZone($fromZone));
    } catch (Exception $e) {
        return $mysqlDatetime;
    }
    return $dt->setTimezone(new DateTimeZone($toZone))->format('d M Y, g:i A') . ' PKT';
}

/**
 * Short relative label vs now (APP_TIMEZONE), e.g. "just now", "4 h ago", "3 d ago".
 */
function attendance_relative_time(?string $mysqlDatetime): string
{
    if ($mysqlDatetime === null || $mysqlDatetime === '') {
        return '';
    }
    $dbZoneName = defined('DB_DATETIME_ZONE') ? DB_DATETIME_ZONE : 'UTC';
    $appZoneName = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Karachi';
    try {
        $then = (new DateTimeImmutable($mysqlDatetime, new DateTimeZone($dbZoneName)))
            ->setTimezone(new DateTimeZone($appZoneName));
        $now = new DateTimeImmutable('now', new DateTimeZone($appZoneName));
    } catch (Exception $e) {
        return '';
    }
    $secs = $now->getTimestamp() - $then->getTimestamp();
    if ($secs < 0) {
        return 'just now';
    }
    if ($secs < 60) {
        return 'just now';
    }
    if ($secs < 3600) {
        $m = intdiv($secs, 60);

        return $m === 1 ? '1 min ago' : $m . ' min ago';
    }
    if ($secs < 86400) {
        $h = intdiv($secs, 3600);

        return $h === 1 ? '1 h ago' : $h . ' h ago';
    }
    if ($secs < 604800) {
        $d = intdiv($secs, 86400);

        return $d === 1 ? '1 d ago' : $d . ' d ago';
    }
    if ($secs < 2592000) {
        $w = intdiv($secs, 604800);

        return $w === 1 ? '1 wk ago' : $w . ' wk ago';
    }

    return $then->format('d M Y');
}

/**
 * SQL expression for calendar date in Pakistan (Asia/Karachi) from created_at.
 * Prefer attendance_created_bounds_for_pkt_calendar() for WHERE clauses: CONVERT_TZ with
 * named zones returns NULL when MySQL time zone tables are not loaded (common on shared hosting).
 */
function attendance_sql_calendar_date(string $column = 'created_at'): string
{
    $fromZone = defined('DB_DATETIME_ZONE') ? DB_DATETIME_ZONE : 'UTC';
    if ($fromZone === 'Asia/Karachi') {
        return "DATE($column)";
    }
    if ($fromZone === 'UTC') {
        return "DATE(CONVERT_TZ($column, '+00:00', '+05:00'))";
    }
    $esc = str_replace("'", "''", $fromZone);
    return "DATE(CONVERT_TZ($column, '$esc', 'Asia/Karachi'))";
}

/**
 * Inclusive Pakistan (PKT) calendar-day range as naive DATETIME strings in DB_DATETIME_ZONE
 * for comparing against created_at. Use when filtering admin/export by date pickers.
 *
 * @return array{0: ?string, 1: ?string} [lower created_at bound, upper bound] or null entries if no filter
 */
function attendance_created_bounds_for_pkt_calendar(string $fromYmd, string $toYmd): array
{
    $dbZoneName = defined('DB_DATETIME_ZONE') ? DB_DATETIME_ZONE : 'UTC';
    $dbZone = new DateTimeZone($dbZoneName);
    $pkZone = new DateTimeZone('Asia/Karachi');
    $lower = null;
    $upper = null;
    if ($fromYmd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromYmd)) {
        $lower = (new DateTimeImmutable($fromYmd . ' 00:00:00', $pkZone))
            ->setTimezone($dbZone)->format('Y-m-d H:i:s');
    }
    if ($toYmd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toYmd)) {
        $upper = (new DateTimeImmutable($toYmd . ' 23:59:59', $pkZone))
            ->setTimezone($dbZone)->format('Y-m-d H:i:s');
    }
    return [$lower, $upper];
}
