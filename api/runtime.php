<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'GET') {
    mg_fail('Method not allowed.', 405);
}

mg_ok(['runtime' => mg_runtime_public_payload()], 'Runtime profile loaded.');
