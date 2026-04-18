<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    $pw = (string) $_POST['admin_password'];
    if (password_verify($pw, ADMIN_PASSWORD_HASH)) {
        $_SESSION[SESSION_ADMIN_KEY] = true;
        header('Location: admin.php');
        exit;
    }
    $err = 'Incorrect password.';
}

$logged = !empty($_SESSION[SESSION_ADMIN_KEY]);

$fUc = $logged && isset($_GET['uc']) ? trim((string) $_GET['uc']) : '';
$fTh = $logged && isset($_GET['tehsil']) ? trim((string) $_GET['tehsil']) : '';
$fFrom = $logged && isset($_GET['from']) ? trim((string) $_GET['from']) : '';
$fTo = $logged && isset($_GET['to']) ? trim((string) $_GET['to']) : '';

$records = [];
$stats = ['total' => 0, 'today' => 0, 'ucs' => 0, 'filtered' => 0];
$tehsils = [];
$todayStr = (new DateTime('today'))->format('Y-m-d');

if ($logged) {
    $tehsils = $pdo->query('SELECT DISTINCT tehsil FROM uc_offices ORDER BY tehsil')->fetchAll(PDO::FETCH_COLUMN);

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
    $sql .= ' ORDER BY id DESC LIMIT 5000';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $records = $st->fetchAll();

    $allCount = (int) $pdo->query('SELECT COUNT(*) FROM attendance')->fetchColumn();
    $stT = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE DATE(created_at) = ?');
    $stT->execute([$todayStr]);
    $todayCount = (int) $stT->fetchColumn();
    $ucDistinct = (int) $pdo->query('SELECT COUNT(DISTINCT uc_no) FROM attendance')->fetchColumn();

    $stats = [
        'total' => $allCount,
        'today' => $todayCount,
        'ucs' => $ucDistinct,
        'filtered' => count($records),
    ];
}

$exportQs = http_build_query([
    'uc' => $fUc,
    'tehsil' => $fTh,
    'from' => $fFrom,
    'to' => $fTo,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin — Vehari UC Attendance</title>
<link rel="stylesheet" href="assets/css/app.css"/>
</head>
<body>
<div id="root">
  <div class="header">
    <div class="header-inner">
      <div class="h-title">District Vehari — Local Government</div>
      <div class="h-sub">Admin · Attendance records</div>
    </div>
    <?php if ($logged): ?>
    <a class="btn-logout" href="logout.php">Logout</a>
    <?php endif; ?>
  </div>

  <main class="main">
    <?php if (!$logged): ?>
    <div class="card card-center">
      <div class="emoji-big">🛡️</div>
      <h1 class="card-title">Admin Access</h1>
      <p class="card-sub">Authorised officers only</p>
      <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
      <form method="post" class="stack">
        <input class="inp" type="password" name="admin_password" placeholder="Password" required autocomplete="current-password"/>
        <button class="submit-btn" type="submit">Login</button>
      </form>
      <p class="hint"><a href="index.php">← Staff attendance</a></p>
    </div>
    <?php else: ?>

    <div class="stats-row">
      <div class="stat-card"><span class="stat-ic">📋</span><span class="stat-n"><?= (int) $stats['total'] ?></span><span class="stat-l">Total</span></div>
      <div class="stat-card"><span class="stat-ic">📅</span><span class="stat-n"><?= (int) $stats['today'] ?></span><span class="stat-l">Today</span></div>
      <div class="stat-card"><span class="stat-ic">🏘️</span><span class="stat-n"><?= (int) $stats['ucs'] ?></span><span class="stat-l">UCs</span></div>
      <div class="stat-card"><span class="stat-ic">🔍</span><span class="stat-n"><?= (int) $stats['filtered'] ?></span><span class="stat-l">Filtered</span></div>
    </div>

    <div class="card">
      <h2 class="card-title">Filter &amp; export</h2>
      <form method="get" class="filter-grid">
        <div class="fld">
          <label class="lbl">UC</label>
          <select class="inp" name="uc">
            <option value="">All UCs</option>
            <?php for ($i = 1; $i <= 105; $i++): ?>
            <option value="<?= $i ?>" <?= $fUc === (string) $i ? 'selected' : '' ?>>UC <?= $i ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="fld">
          <label class="lbl">Tehsil</label>
          <select class="inp" name="tehsil">
            <option value="">All</option>
            <?php foreach ($tehsils as $t): ?>
            <option value="<?= h($t) ?>" <?= $fTh === $t ? 'selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fld">
          <label class="lbl">From</label>
          <input class="inp" type="date" name="from" value="<?= h($fFrom) ?>"/>
        </div>
        <div class="fld">
          <label class="lbl">To</label>
          <input class="inp" type="date" name="to" value="<?= h($fTo) ?>"/>
        </div>
        <div class="fld fld-actions">
          <button class="btn-secondary" type="submit">Apply</button>
          <a class="btn-export" href="export.php?<?= h($exportQs) ?>">📊 Export CSV</a>
        </div>
      </form>
    </div>

    <div class="card">
      <h2 class="card-title">Records</h2>
      <?php if (count($records) === 0): ?>
      <p class="empty">No records match.</p>
      <?php else: ?>
      <div class="rec-list">
        <?php foreach ($records as $r): ?>
        <?php
          $dt = new DateTime($r['created_at']);
          $maps = 'https://maps.google.com/?q=' . urlencode((string) $r['lat'] . ',' . (string) $r['lng']);
        ?>
        <div class="rec-card">
          <div class="rec-top">
            <div class="rec-head">
              <span class="uc-badge">UC <?= (int) $r['uc_no'] ?></span>
              <span class="rec-name"><?= h($r['uc_name']) ?></span>
              <?php if ($r['tehsil']): ?><span class="t-badge"><?= h($r['tehsil']) ?></span><?php endif; ?>
            </div>
            <span class="rec-time"><?= h($dt->format('d M Y, g:i A')) ?></span>
          </div>
          <div class="rec-body">
            <span>👤 <?= h($r['secretary_name']) ?></span>
            <span class="dist">📏 <?= (int) $r['distance_m'] ?>m</span>
            <a class="link" href="<?= h($maps) ?>" target="_blank" rel="noopener">📍 Map</a>
            <a class="link" href="photo.php?id=<?= (int) $r['id'] ?>" target="_blank" rel="noopener">📸 Photo</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <p class="footer"><a href="index.php">Staff attendance</a></p>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
