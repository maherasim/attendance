<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!is_readable($configFile)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Setup required</title></head><body>';
    echo '<h1>Configuration missing</h1>';
    echo '<p>Copy <code>config.example.php</code> to <code>config.php</code> on the server and set your database credentials.</p>';
    echo '<p><a href="health.php">Run health.php</a> for diagnostics.</p>';
    echo '</body></html>';
    exit;
}
require_once $configFile;

if (!defined('GEOFENCE_M')) {
    define('GEOFENCE_M', 500);
}
if (!defined('BYPASS_GEOFENCE')) {
    define('BYPASS_GEOFENCE', false);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"/>
<meta name="theme-color" content="#0d5c3a"/>
<title>Vehari UC Attendance</title>
<link rel="stylesheet" href="assets/css/app.css"/>
</head>
<body>
<div id="root">
  <div class="header">
    <svg width="36" height="36" viewBox="0 0 40 40" aria-hidden="true">
      <circle cx="20" cy="20" r="19" stroke="#c9a84c" stroke-width="1.8" fill="none"/>
      <path d="M20 8L23 16L32 16L25 21L28 30L20 25L12 30L15 21L8 16L17 16Z" fill="#c9a84c"/>
    </svg>
    <div class="header-inner">
      <div class="h-title">District Vehari — Local Government</div>
      <div class="h-sub">Secretary UC Attendance · Geo-Verified · 105 UCs</div>
    </div>
    <span class="status-dot" title="Server mode"></span>
  </div>

  <div class="tab-bar">
    <span class="tab tab-on">📋 Mark Attendance</span>
    <a class="tab tab-link" href="admin.php">🛡️ Admin</a>
  </div>

  <div id="gps-banner" class="gps-banner" hidden></div>
  <?php if (defined('BYPASS_GEOFENCE') && BYPASS_GEOFENCE): ?>
  <div class="gps-banner" style="background:#e8f4fc;border-color:#2980b9;color:#1a5276;">
    <strong>TEST MODE:</strong> 500m geofence is off — set <code>BYPASS_GEOFENCE</code> to <code>false</code> in <code>config.php</code> for production.
  </div>
  <?php endif; ?>

  <main class="main">
    <div id="form-screen" class="card">
      <h1 class="card-title">Secretary UC — Daily Attendance</h1>
      <p class="card-sub">All fields mandatory · Location verified within <?= (int) GEOFENCE_M ?>m of UC office</p>

      <div class="fld">
        <label class="lbl" for="uc_no">UC Number (1–105) *</label>
        <input class="inp" id="uc_no" type="number" min="1" max="105" placeholder="e.g. 55"/>
        <div id="err-uc_no" class="err" hidden></div>
      </div>

      <div class="fld">
        <label class="lbl" for="uc_name">UC Name *</label>
        <div class="rel">
          <input class="inp" id="uc_name" type="text" placeholder="Auto-fills, or type to search" autocomplete="off"/>
          <div id="sugg" class="dropdown" hidden></div>
        </div>
        <div id="tehsil-chip" class="tehsil-chip" hidden></div>
        <div id="err-uc_name" class="err" hidden></div>
      </div>

      <div class="fld">
        <label class="lbl" for="sec">Secretary UC Name *</label>
        <input class="inp" id="sec" type="text" placeholder="Full name of Secretary UC"/>
        <div id="err-sec" class="err" hidden></div>
      </div>

      <div class="fld">
        <label class="lbl">GPS Verification (<?= (int) GEOFENCE_M ?>m radius) *</label>
        <div id="office-info" class="office-info" hidden></div>
        <div id="perm-hint" class="geo-box geo-warn" hidden></div>
        <button type="button" id="btn-loc" class="action-btn">📍 Verify My Location</button>
        <div id="geo-box"></div>
        <div id="err-loc" class="err" hidden></div>
      </div>

      <div class="fld">
        <label class="lbl">Selfie / Photo *</label>
        <label class="action-btn photo-label" id="photo-label">
          <input id="photo" type="file" accept="image/*" capture="user" hidden/>
          <span id="photo-lbl">📸 Take Selfie / Upload Photo</span>
        </label>
        <div id="photo-preview-wrap" class="photo-wrap" hidden>
          <img id="photo-preview" class="photo-img" alt="Preview"/>
          <button type="button" id="photo-clear" class="retake-btn">🔄 Retake</button>
        </div>
        <div id="err-photo" class="err" hidden></div>
      </div>

      <button type="button" id="btn-submit" class="submit-btn">✅ Submit Attendance</button>
    </div>

    <div id="ok-saved" class="success-box" hidden>
      <div class="emoji-huge">✅</div>
      <div class="suc-title">Attendance Submitted!</div>
      <div class="suc-sub">Saved to the server successfully.</div>
    </div>
    <div id="ok-fail" class="success-box" hidden>
      <div class="emoji-huge">❌</div>
      <div class="suc-title err-title">Could not save</div>
      <div id="fail-msg" class="suc-sub"></div>
    </div>
  </main>

  <div class="footer">District Local Government · Vehari © <?= (int) date('Y') ?> · Geo-Fenced <?= (int) GEOFENCE_M ?>m · <a href="admin.php">Admin</a></div>
</div>

<script>
window.APP = {
  GEOFENCE_M: <?= (int) GEOFENCE_M ?>,
  BYPASS_GEOFENCE: <?= (defined('BYPASS_GEOFENCE') && BYPASS_GEOFENCE) ? 'true' : 'false' ?>,
  API_UCS: 'api_ucs.php',
  API_SUBMIT: 'api_submit.php'
};
</script>
<script src="assets/js/staff.js" defer></script>
</body>
</html>
