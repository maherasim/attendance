<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/includes/bootstrap.php';
admin_required();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

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

/** Full datetime string in APP_TIMEZONE (e.g. Pakistan) for spreadsheets. */
function export_created_pkt_full(?string $mysqlDatetime): string
{
    if ($mysqlDatetime === null || $mysqlDatetime === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable(
            $mysqlDatetime,
            new DateTimeZone(defined('DB_DATETIME_ZONE') ? DB_DATETIME_ZONE : 'UTC')
        );
        $dt = $dt->setTimezone(new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Karachi'));
        return $dt->format('Y-m-d H:i:s') . ' PKT';
    } catch (Exception $e) {
        return $mysqlDatetime;
    }
}

/**
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

$where = ' FROM attendance WHERE 1=1';
$params = [];
if ($fUc !== '') {
    $where .= ' AND uc_no = ?';
    $params[] = (int) $fUc;
}
if ($fTh !== '') {
    $where .= ' AND tehsil = ?';
    $params[] = $fTh;
}
[$boundFrom, $boundTo] = attendance_created_bounds_for_pkt_calendar($fFrom, $fTo);
if ($boundFrom !== null) {
    $where .= ' AND created_at >= ?';
    $params[] = $boundFrom;
}
if ($boundTo !== null) {
    $where .= ' AND created_at <= ?';
    $params[] = $boundTo;
}

$stCount = $pdo->prepare('SELECT uc_no, COUNT(*) AS entry_count' . $where . ' GROUP BY uc_no');
$stCount->execute($params);
$entryCountByUc = [];
foreach ($stCount->fetchAll() as $row) {
    $entryCountByUc[(int) $row['uc_no']] = (int) $row['entry_count'];
}

$ucSql = 'SELECT uc_no, name, tehsil FROM uc_offices WHERE 1=1';
$ucParams = [];
if ($fTh !== '') {
    $ucSql .= ' AND tehsil = ?';
    $ucParams[] = $fTh;
}
if ($fUc !== '') {
    $ucSql .= ' AND uc_no = ?';
    $ucParams[] = (int) $fUc;
}
$ucSql .= ' ORDER BY uc_no ASC';
$stUc = $pdo->prepare($ucSql);
$stUc->execute($ucParams);
$allUcs = $stUc->fetchAll();

$sql = 'SELECT id, created_at, uc_no, uc_name, tehsil, secretary_name, lat, lng, accuracy_m, distance_m, photo_file'
    . $where
    . ' ORDER BY id ASC';
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$periodLabel = 'Report period: ';
if ($fFrom !== '' && $fTo !== '') {
    $periodLabel .= $fFrom . ' → ' . $fTo . ' (PKT calendar)';
} elseif ($fFrom !== '') {
    $periodLabel .= 'from ' . $fFrom . ' onward';
} elseif ($fTo !== '') {
    $periodLabel .= 'through ' . $fTo;
} else {
    $periodLabel .= 'all dates (any matching mark = Present)';
}
if ($fTh !== '') {
    $periodLabel .= ' · Tehsil: ' . $fTh;
}
if ($fUc !== '') {
    $periodLabel .= ' · UC filter: ' . $fUc;
}

$spreadsheet = new Spreadsheet();

// —— Sheet 1: UC summary (all UCs in scope + Present / Absent) ——
$sum = $spreadsheet->getActiveSheet();
$sum->setTitle('UC Summary');

$sum->mergeCells('A1:E1');
$sum->setCellValue('A1', 'District Vehari — Local Government · UC attendance overview');
$sum->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sum->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sum->mergeCells('A2:E2');
$sum->setCellValue('A2', $periodLabel);
$sum->getStyle('A2')->getFont()->setSize(11);
$sum->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$hdrRow = 3;
$sum->fromArray(
    ['UC No', 'UC Name', 'Tehsil', 'Status', 'Marks in period'],
    null,
    'A' . $hdrRow
);
$sum->getStyle('A' . $hdrRow . ':E' . $hdrRow)->getFont()->setBold(true);
$sum->getStyle('A' . $hdrRow . ':E' . $hdrRow)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('0D5C3A');
$sum->getStyle('A' . $hdrRow . ':E' . $hdrRow)->getFont()->getColor()->setRGB('FFFFFF');
$sum->getStyle('A' . $hdrRow . ':E' . $hdrRow)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$dataRow = $hdrRow + 1;
$presentFill = 'C6EFCE';
$absentFill = 'FFC7CE';
$presentCount = 0;
$absentCount = 0;

foreach ($allUcs as $u) {
    $no = (int) $u['uc_no'];
    $cnt = $entryCountByUc[$no] ?? 0;
    $present = $cnt > 0;
    if ($present) {
        $presentCount++;
    } else {
        $absentCount++;
    }
    $sum->fromArray(
        [
            $no,
            $u['name'],
            $u['tehsil'],
            $present ? 'Present' : 'Absent',
            $cnt,
        ],
        null,
        'A' . $dataRow
    );
    $rgb = $present ? $presentFill : $absentFill;
    $sum->getStyle('A' . $dataRow . ':E' . $dataRow)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB($rgb);
    $sum->getStyle('A' . $dataRow . ':E' . $dataRow)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)
        ->getColor()->setRGB('CCCCCC');
    $sum->getStyle('D' . $dataRow)->getFont()->setBold(true);
    if (!$present) {
        $sum->getStyle('D' . $dataRow)->getFont()->getColor()->setRGB('9C0006');
    } else {
        $sum->getStyle('D' . $dataRow)->getFont()->getColor()->setRGB('006100');
    }
    $dataRow++;
}

$totRow = $dataRow;
$sum->setCellValue('A' . $totRow, 'Totals');
$sum->setCellValue('D' . $totRow, 'Present: ' . $presentCount . ' · Absent: ' . $absentCount);
$sum->mergeCells('D' . $totRow . ':E' . $totRow);
$sum->getStyle('A' . $totRow . ':E' . $totRow)->getFont()->setBold(true);
$sum->getStyle('A' . $totRow . ':C' . $totRow)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('E8F0EC');

$sum->getColumnDimension('A')->setWidth(9);
$sum->getColumnDimension('B')->setWidth(28);
$sum->getColumnDimension('C')->setWidth(14);
$sum->getColumnDimension('D')->setWidth(14);
$sum->getColumnDimension('E')->setWidth(16);
$sum->freezePane('A' . ($hdrRow + 1));

// —— Sheet 2: full attendance rows (all DB columns + map link + thumbnail) ——
$det = $spreadsheet->createSheet();
$det->setTitle('Attendance detail');

$detLastCol = 'N';
$det->mergeCells('A1:' . $detLastCol . '1');
$det->setCellValue('A1', 'District Vehari — Local Government · Attendance export (full table data)');
$det->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$det->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$det->mergeCells('A2:' . $detLastCol . '2');
$det->setCellValue('A2', $periodLabel . ' · Rows: ' . count($rows));
$det->getStyle('A2')->getFont()->setSize(10);
$det->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$hdrRowDet = 3;
$headers = [
    'ID',
    'Created at (database)',
    'Recorded (PKT)',
    'UC No',
    'UC Name',
    'Tehsil',
    'Secretary name',
    'Latitude',
    'Longitude',
    'GPS accuracy (m)',
    'Distance from office (m)',
    'Photo file',
    'Google Maps',
    'Photo preview',
];
$det->fromArray($headers, null, 'A' . $hdrRowDet);
$det->getStyle('A' . $hdrRowDet . ':' . $detLastCol . $hdrRowDet)->getFont()->setBold(true);
$det->getStyle('A' . $hdrRowDet . ':' . $detLastCol . $hdrRowDet)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('0D5C3A');
$det->getStyle('A' . $hdrRowDet . ':' . $detLastCol . $hdrRowDet)->getFont()->getColor()->setRGB('FFFFFF');
$det->getStyle('A' . $hdrRowDet . ':' . $detLastCol . $hdrRowDet)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER)
    ->setWrapText(true);
$det->getRowDimension($hdrRowDet)->setRowHeight(28);
$det->setAutoFilter('A' . $hdrRowDet . ':' . 'M' . $hdrRowDet);
$det->freezePane('A' . ($hdrRowDet + 1));

$det->getColumnDimension('A')->setWidth(10);
$det->getColumnDimension('B')->setWidth(20);
$det->getColumnDimension('C')->setWidth(22);
$det->getColumnDimension('D')->setWidth(8);
$det->getColumnDimension('E')->setWidth(26);
$det->getColumnDimension('F')->setWidth(12);
$det->getColumnDimension('G')->setWidth(22);
$det->getColumnDimension('H')->setWidth(12);
$det->getColumnDimension('I')->setWidth(12);
$det->getColumnDimension('J')->setWidth(12);
$det->getColumnDimension('K')->setWidth(14);
$det->getColumnDimension('L')->setWidth(28);
$det->getColumnDimension('M')->setWidth(40);
$det->getColumnDimension('N')->setWidth(14);

$toUnlink = [];
$rowIdx = $hdrRowDet + 1;

foreach ($rows as $r) {
    [, , $maps] = export_date_time_maps($r);
    $acc = $r['accuracy_m'];
    $rowData = [
        $r['id'],
        $r['created_at'],
        export_created_pkt_full($r['created_at']),
        $r['uc_no'],
        $r['uc_name'],
        $r['tehsil'],
        $r['secretary_name'],
        $r['lat'],
        $r['lng'],
        $acc !== null && $acc !== '' ? (int) $acc : '',
        $r['distance_m'],
        $r['photo_file'] ?? '',
        $maps,
        '',
    ];
    $det->fromArray($rowData, null, 'A' . $rowIdx);
    $det->getCell('M' . $rowIdx)->getHyperlink()->setUrl($maps);
    $det->getStyle('A' . $rowIdx . ':M' . $rowIdx)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)
        ->getColor()->setRGB('DDDDDD');
    $det->getStyle('H' . $rowIdx . ':I' . $rowIdx)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

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
        $drawing->setCoordinates('N' . $rowIdx);
        $drawing->setOffsetX(3);
        $drawing->setOffsetY(3);
        $drawing->setWorksheet($det);
        $det->getRowDimension($rowIdx)->setRowHeight(92);
    }

    $rowIdx++;
}

$spreadsheet->setActiveSheetIndex(0);

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
