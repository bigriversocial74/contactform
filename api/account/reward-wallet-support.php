<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
mg_require_api_user();

if ($method === 'GET') {
    mg_ok([
        'support_cases'=>[],
        'deprecated_wallet_support'=>true,
        'redirect_url'=>'/inbox.php',
    ], 'Reward support is managed from the Microgift in your Inbox.');
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
mg_fail('Standalone wallet support is retired. Open the Microgift in Inbox and use its PPPM actions or contact support.', 410);
