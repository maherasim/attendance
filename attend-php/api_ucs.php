<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$rows = $pdo->query('SELECT uc_no AS no, name, tehsil, lat, lng FROM uc_offices ORDER BY uc_no ASC')->fetchAll();
json_out(['success' => true, 'ucs' => $rows]);
