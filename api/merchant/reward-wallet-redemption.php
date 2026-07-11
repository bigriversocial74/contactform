<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
mg_merchant_require_permission($method === 'GET' ? 'merchant.claims.view' : 'merchant.claims.manage');

if ($method === 'GET') {
    mg_ok([
        'deprecated_wallet_redemption'=>true,
        'redirect_url'=>'/merchant-wallet-redemptions.php',
        'claim_tokens'=>[],
        'support_cases'=>[],
        'totals'=>['active_codes'=>0,'redeemed'=>0,'expired'=>0,'open_support'=>0],
    ], 'Use the canonical Microgift/PPPM redemption console.');
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
mg_fail('Standalone wallet claim-code redemption is retired. Use the canonical Microgift/PPPM redemption console.', 410);
