<?php
/**
 * Copy this file to config.php and edit credentials.
 * Default admin password (change after first login): Vehari@2026
 */
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'vehari_uc_attendance');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('GEOFENCE_M', 500);

/** Production: false. true = skip 500m geofence (testing only). */
define('BYPASS_GEOFENCE', false);

/** Bcrypt hash — generate with: php -r "echo password_hash('YourPassword', PASSWORD_DEFAULT);" */
define('ADMIN_PASSWORD_HASH', '$2y$10$2G0s5Pyq0fojA0mzn1tULO5pIRsYq9/2or6Pr.fij5qG.UDfLLmGu');

define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024);
define('SESSION_ADMIN_KEY', 'vehari_uc_admin');
