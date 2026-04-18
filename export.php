<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
admin_required();

$fUc = isset($_GET['uc']) ? trim((string) $_GET['uc']) : '';
$fTh = isset($_GET['tehsil']) ? trim((string) $_GET['tehsil']) : '';
$fFrom = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
$fTo = isset($_GET['to']) ? trim((string) $_GET['to']) : '';

$sql = 'SELECT id, created_at, uc_no, uc_name, tehsil, secretary_name, lat, lng, accuracy_m, distance_m
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
if ($fFrom !== '') {
    $sql .= ' AND DATE(created_at) >= ?';
    $params[] = $fFrom;
}
if ($fTo !== '') {
    $sql .= ' AND DATE(created_at) <= ?';
    $params[] = $fTo;
}
$sql .= ' ORDER BY id ASC';

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$fn = 'Vehari_UC_Attendance_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fn . '"');
$out = fopen('php://output', 'w');
fputcsv($out, ['ID', 'Date', 'Time', 'UC No', 'UC Name', 'Tehsil', 'Secretary Name', 'Distance (m)', 'Latitude', 'Longitude', 'Accuracy (m)', 'Maps URL']);

foreach ($rows as $r) {
    $ts = $r['created_at'];
    $dt = new DateTime($ts);
    $maps = 'https://maps.google.com/?q=' . $r['lat'] . ',' . $r['lng'];
    fputcsv($out, [
        $r['id'],
        $dt->format('Y-m-d'),
        $dt->format('g:i A'),
        $r['uc_no'],
        $r['uc_name'],
        $r['tehsil'],
        $r['secretary_name'],
        $r['distance_m'],
        $r['lat'],
        $r['lng'],
        $r['accuracy_m'],
        $maps,
    ]);
}
fclose($out);
exit;
