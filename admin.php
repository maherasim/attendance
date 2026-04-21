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
$page = $logged ? max(1, (int) ($_GET['page'] ?? 1)) : 1;

$records = [];
$stats = ['total' => 0, 'today' => 0, 'ucs' => 0, 'filtered' => 0];
$tehsils = [];
$todayStr = (new DateTime('today'))->format('Y-m-d');
$perPage = 15;
$totalFiltered = 0;
$totalPages = 1;
$showingFrom = 0;
$showingTo = 0;

if ($logged) {
    $tehsils = $pdo->query('SELECT DISTINCT tehsil FROM uc_offices ORDER BY tehsil')->fetchAll(PDO::FETCH_COLUMN);

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

    $stCount = $pdo->prepare('SELECT COUNT(*)' . $where);
    $stCount->execute($params);
    $totalFiltered = (int) $stCount->fetchColumn();

    $totalPages = max(1, (int) ceil($totalFiltered / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $sql = 'SELECT id, created_at, uc_no, uc_name, tehsil, secretary_name, lat, lng, accuracy_m, distance_m'
        . $where
        . ' ORDER BY id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $records = $st->fetchAll();

    if ($totalFiltered > 0) {
        $showingFrom = $offset + 1;
        $showingTo = min($offset + $perPage, $totalFiltered);
    }

    $allCount = (int) $pdo->query('SELECT COUNT(*) FROM attendance')->fetchColumn();
    [$todayLo, $todayHi] = attendance_created_bounds_for_pkt_calendar($todayStr, $todayStr);
    $todayCount = 0;
    if ($todayLo !== null && $todayHi !== null) {
        $stT = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE created_at >= ? AND created_at <= ?');
        $stT->execute([$todayLo, $todayHi]);
        $todayCount = (int) $stT->fetchColumn();
    }
    $ucDistinct = (int) $pdo->query('SELECT COUNT(DISTINCT uc_no) FROM attendance')->fetchColumn();

    $stats = [
        'total' => $allCount,
        'today' => $todayCount,
        'ucs' => $ucDistinct,
        'filtered' => $totalFiltered,
    ];
}

$filterQs = array_filter([
    'uc' => $fUc,
    'tehsil' => $fTh,
    'from' => $fFrom,
    'to' => $fTo,
], static function ($v) {
    return $v !== '' && $v !== null;
});
$exportQs = http_build_query($filterQs);

function admin_pager_url(array $base, int $p): string
{
    $q = array_merge($base, ['page' => $p]);
    return 'admin.php?' . http_build_query($q);
}
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
          <a class="btn-export" href="export.php?<?= h($exportQs) ?>">📊 Export Excel</a>
        </div>
      </form>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
        <h2 class="card-title" style="margin-bottom:0;">Records</h2>
        <?php if ($totalFiltered > 0): ?>
        <span class="rec-range"><?= (int) $showingFrom ?>–<?= (int) $showingTo ?> of <?= (int) $totalFiltered ?> · Page <?= (int) $page ?>/<?= (int) $totalPages ?></span>
        <?php endif; ?>
      </div>
      <?php if (count($records) === 0): ?>
      <p class="empty">No records match.</p>
      <?php else: ?>
      <div class="rec-list">
        <?php foreach ($records as $r): ?>
        <?php
          $maps = 'https://maps.google.com/?q=' . urlencode((string) $r['lat'] . ',' . (string) $r['lng']);
        ?>
        <div class="rec-card">
          <div class="rec-top">
            <div class="rec-head">
              <span class="uc-badge">UC <?= (int) $r['uc_no'] ?></span>
              <span class="rec-name"><?= h($r['uc_name']) ?></span>
              <?php if ($r['tehsil']): ?><span class="t-badge"><?= h($r['tehsil']) ?></span><?php endif; ?>
            </div>
            <span class="rec-time"><?= h(attendance_format_time($r['created_at'])) ?></span>
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
      <?php if ($totalPages > 1): ?>
      <nav class="pager" aria-label="Pagination">
        <?php if ($page > 1): ?>
        <a class="pager-btn" href="<?= h(admin_pager_url($filterQs, $page - 1)) ?>">← Prev</a>
        <?php else: ?>
        <span class="pager-btn pager-disabled">← Prev</span>
        <?php endif; ?>
        <span class="pager-info"><?= (int) $page ?> / <?= (int) $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
        <a class="pager-btn" href="<?= h(admin_pager_url($filterQs, $page + 1)) ?>">Next →</a>
        <?php else: ?>
        <span class="pager-btn pager-disabled">Next →</span>
        <?php endif; ?>
      </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <p class="footer"><a href="index.php">Staff attendance</a></p>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
