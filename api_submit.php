<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['success' => false, 'error' => 'Method not allowed'], 405);
}

$ucNo = isset($_POST['uc_no']) ? (int) $_POST['uc_no'] : 0;
$sec = isset($_POST['secretary_name']) ? trim((string) $_POST['secretary_name']) : '';
$lat = isset($_POST['lat']) ? (float) $_POST['lat'] : null;
$lng = isset($_POST['lng']) ? (float) $_POST['lng'] : null;
$acc = isset($_POST['accuracy']) ? (int) round((float) $_POST['accuracy']) : null;

if ($ucNo < 1 || $ucNo > 105) {
    json_out(['success' => false, 'error' => 'Invalid UC number (1–105).'], 400);
}
if ($sec === '') {
    json_out(['success' => false, 'error' => 'Secretary name is required.'], 400);
}
if ($lat === null || $lng === null || !is_finite($lat) || !is_finite($lng)) {
    json_out(['success' => false, 'error' => 'Valid GPS coordinates are required.'], 400);
}

if (empty($_FILES['photo']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
    json_out(['success' => false, 'error' => 'Photo is required.'], 400);
}

$st = $pdo->prepare('SELECT uc_no, name, tehsil, lat, lng FROM uc_offices WHERE uc_no = ? LIMIT 1');
$st->execute([$ucNo]);
$office = $st->fetch();
if (!$office) {
    json_out(['success' => false, 'error' => 'UC not found.'], 400);
}

$oLat = (float) $office['lat'];
$oLng = (float) $office['lng'];
$dist = (int) round(distance_meters($lat, $lng, $oLat, $oLng));

// Distance and accuracy are always stored. Optional strict geofence (off by default — some UC pins are inaccurate).
if (
    defined('STRICT_GEOFENCE') && STRICT_GEOFENCE
    && (!defined('BYPASS_GEOFENCE') || !BYPASS_GEOFENCE)
    && $dist > GEOFENCE_M
) {
    json_out([
        'success' => false,
        'error' => "You are {$dist}m from the office. Must be within " . GEOFENCE_M . 'm.',
        'distance_m' => $dist,
    ], 403);
}

$f = $_FILES['photo'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    json_out(['success' => false, 'error' => 'Photo upload failed.'], 400);
}
if ($f['size'] > UPLOAD_MAX_BYTES) {
    json_out(['success' => false, 'error' => 'Photo is too large (max 2 MB).'], 400);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($f['tmp_name']);
$ext = null;
if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
    $ext = 'jpg';
} elseif ($mime === 'image/png') {
    $ext = 'png';
} elseif ($mime === 'image/webp') {
    $ext = 'webp';
}
if ($ext === null) {
    json_out(['success' => false, 'error' => 'Photo must be JPEG, PNG, or WebP.'], 400);
}

ensure_upload_dir();
$basename = 'att_' . bin2hex(random_bytes(16)) . '.' . $ext;
$dest = UPLOAD_DIR . $basename;
if (!move_uploaded_file($f['tmp_name'], $dest)) {
    json_out(['success' => false, 'error' => 'Could not save photo.'], 500);
}

$ins = $pdo->prepare(
    'INSERT INTO attendance (uc_no, uc_name, tehsil, secretary_name, lat, lng, accuracy_m, distance_m, photo_file)
     VALUES (?,?,?,?,?,?,?,?,?)'
);
$ins->execute([
    $ucNo,
    $office['name'],
    $office['tehsil'],
    $sec,
    $lat,
    $lng,
    $acc,
    $dist,
    $basename,
]);

$id = (int) $pdo->lastInsertId();
json_out(['success' => true, 'id' => $id, 'distance_m' => $dist]);
