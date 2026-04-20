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
 * SQL expression for calendar date in Pakistan (Asia/Karachi) from created_at.
 * UTC: offset form works without MySQL time_zone tables.
 * Europe/Berlin (German hosting): named zones need MySQL tables (most hosts have them).
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
