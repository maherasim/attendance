<?php
/**
 * Production — kommodoro.de
 * Site: http://kommodoro.de/
 * If MySQL fails, check hosting panel for DB host (sometimes not "localhost").
 */
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'kommode_vehari_uc_attendance');
define('DB_USER', 'kommode');
define('DB_PASS', 'Daniela972757');
define('DB_CHARSET', 'utf8mb4');

define('GEOFENCE_M', 500);

/** Production: false. true = skip 500m geofence (testing only). */
define('BYPASS_GEOFENCE', false);

define('ADMIN_PASSWORD_HASH', '$2y$10$2G0s5Pyq0fojA0mzn1tULO5pIRsYq9/2or6Pr.fij5qG.UDfLLmGu');
define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024);
define('SESSION_ADMIN_KEY', 'vehari_uc_admin');
