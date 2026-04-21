<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/includes/bootstrap.php';
admin_required();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * @return array{0: string, 1: string, 2: string} date, time label, maps URL
 */
function export_date_time_maps(array $r): array
{
    $ts = $r['created_at'];
    try {
        $dt = new DateTimeImmutable($ts, new DateTimeZone(defined('DB_DATETIME_ZONE') ? DB_DATETIME_ZONE : 'UTC'));
        $dt = $dt->setTimezone(new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Karachi'));
    } catch (Exception $e) {
        $dt = null;
    }
    $maps = 'https://maps.google.com/?q=' . $r['lat'] . ',' . $r['lng'];
    return [
        $dt ? $dt->format('Y-m-d') : $ts,
        $dt ? ($dt->format('g:i A') . ' PKT') : '',
        $maps,
    ];
}

/**
 * Local path suitable for Excel embedding (WebP converted to temp JPEG when GD is available).
 *
 * @return array{path: ?string, unlink: list<string>}
 */
function export_prepare_image_path(?string $photoFile): array
{
    $out = ['path' => null, 'unlink' => []];
    if ($photoFile === null || $photoFile === '') {
        return $out;
    }
    $base = basename($photoFile);
    $full = UPLOAD_DIR . $base;
    if (!is_file($full)) {
        return $out;
    }
    $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    if ($ext === 'webp') {
        if (!extension_loaded('gd')) {
            return $out;
        }
        $raw = @file_get_contents($full);
        if ($raw === false) {
            return $out;
        }
        $im = @imagecreatefromstring($raw);
        if ($im === false) {
            return $out;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'attx_') . '.jpg';
        if (!imagejpeg($im, $tmp, 88)) {
            imagedestroy($im);
            return $out;
        }
        imagedestroy($im);
        $out['path'] = $tmp;
        $out['unlink'][] = $tmp;
        return $out;
    }
    $out['path'] = $full;
    return $out;
}

$fUc = isset($_GET['uc']) ? trim((string) $_GET['uc']) : '';
$fTh = isset($_GET['tehsil']) ? trim((string) $_GET['tehsil']) : '';
$fFrom = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
$fTo = isset($_GET['to']) ? trim((string) $_GET['to']) : '';

$sql = 'SELECT id, created_at, uc_no, uc_name, tehsil, secretary_name, lat, lng, accuracy_m, distance_m, photo_file
        FROM attendance WHERE 1=1';
$params = [];
if ($fUc !== '') {
    $sql .= ' AND uc_no = ?';
    $params[] = (int) $fUc;
}
if ($fTh !== '') {
    $sql .= ' AND tehsil = ?';
    $params[] = $fTh;
}
[$boundFrom, $boundTo] = attendance_created_bounds_for_pkt_calendar($fFrom, $fTo);
if ($boundFrom !== null) {
    $sql .= ' AND created_at >= ?';
    $params[] = $boundFrom;
}
if ($boundTo !== null) {
    $sql .= ' AND created_at <= ?';
    $params[] = $boundTo;
}
$sql .= ' ORDER BY id ASC';

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Attendance');

$headers = [
    'ID', 'Date', 'Time', 'UC No', 'UC Name', 'Tehsil', 'Secretary Name',
    'Distance (m)', 'Latitude', 'Longitude', 'Accuracy (m)', 'Maps URL', 'Photo',
];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:M1')->getFont()->setBold(true);

$sheet->getColumnDimension('E')->setWidth(24);
$sheet->getColumnDimension('G')->setWidth(18);
$sheet->getColumnDimension('L')->setWidth(36);
$sheet->getColumnDimension('M')->setWidth(14);

$toUnlink = [];
$rowIdx = 2;

foreach ($rows as $r) {
    [$d, $t, $maps] = export_date_time_maps($r);
    $rowData = [
        $r['id'],
        $d,
        $t,
        $r['uc_no'],
        $r['uc_name'],
        $r['tehsil'],
        $r['secretary_name'],
        $r['distance_m'],
        $r['lat'],
        $r['lng'],
        $r['accuracy_m'],
        $maps,
        '',
    ];
    $sheet->fromArray($rowData, null, 'A' . $rowIdx);
    $sheet->getCell('L' . $rowIdx)->getHyperlink()->setUrl($maps);

    $prep = export_prepare_image_path($r['photo_file'] ?? null);
    foreach ($prep['unlink'] as $u) {
        $toUnlink[] = $u;
    }
    if ($prep['path'] !== null) {
        $drawing = new Drawing();
        $drawing->setName('Row ' . $r['id']);
        $drawing->setDescription('Attendance photo');
        $drawing->setPath($prep['path']);
        $drawing->setHeight(115);
        $drawing->setCoordinates('M' . $rowIdx);
        $drawing->setOffsetX(3);
        $drawing->setOffsetY(3);
        $drawing->setWorksheet($sheet);
        $sheet->getRowDimension($rowIdx)->setRowHeight(92);
    }

    $rowIdx++;
}

$fn = 'Vehari_UC_Attendance_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fn . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

foreach ($toUnlink as $u) {
    if (is_file($u)) {
        @unlink($u);
    }
}

$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
exit;
