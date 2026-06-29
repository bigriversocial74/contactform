<?php
require_once __DIR__ . '/../../includes/training-lab-db.php';

header('Content-Type: application/json; charset=utf-8');

$status = training_lab_db_status();

echo json_encode([
    'ok' => true,
    'data' => $status,
    'mode' => $status['db_configured'] ? 'database' : 'demo-fallback',
], JSON_PRETTY_PRINT);
