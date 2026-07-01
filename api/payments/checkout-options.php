<?php
declare(strict_types=1);

require_once __DIR__ . '/_readiness.php';

mg_require_method('GET');
mg_require_api_user();

$pdo = mg_db();
$mode = mg_payment_mode();
$cashEnabled = mg_payment_cash_enabled($pdo);
$stripeReadiness = mg_payment_readiness($pdo, 'stripe', $mode);
$stripeEnabled = !empty($stripeReadiness['provider']['enabled']);
$stripeReady = $stripeEnabled && !empty($stripeReadiness['ready']);
$stripeBlockers = [];
foreach (($stripeReadiness['checks'] ?? []) as $key => $check) {
    if (is_array($check) && empty($check['ok'])) {
        $stripeBlockers[] = [
            'key' => (string)$key,
            'label' => (string)($check['label'] ?? $key),
            'detail' => (string)($check['detail'] ?? 'Not ready.'),
        ];
    }
}

mg_ok([
    'mode' => $mode,
    'methods' => [
        'cash' => [
            'available' => $cashEnabled,
            'label' => 'Pay with cash',
            'detail' => $cashEnabled ? 'Manual cash checkout is enabled.' : 'Cash checkout is disabled.',
        ],
        'card' => [
            'available' => $stripeReady,
            'label' => 'Pay with card',
            'detail' => $stripeReady ? 'Stripe card checkout is ready.' : 'Stripe card checkout is not ready.',
            'blockers' => $stripeBlockers,
        ],
    ],
]);
