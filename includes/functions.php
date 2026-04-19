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
