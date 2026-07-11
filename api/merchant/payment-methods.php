<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/payments/_connect.php';

$user = mg_require_api_user();
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantId = (int)$user['id'];

function mg_merchant_payment_methods_payload(PDO $pdo, int $workspaceId, int $merchantUserId): array
{
    $stmt = $pdo->prepare('SELECT state_json FROM merchant_payment_readiness WHERE workspace_id=? LIMIT 1');
    $stmt->execute([$workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $state = json_decode((string)($row['state_json'] ?? ''), true);
    if (!is_array($state)) $state = [];
    $methods = is_array($state['payment_methods'] ?? null) ? $state['payment_methods'] : [];

    $stripeAccount = mg_payment_connect_status($pdo, $merchantUserId, false);
    $stripeMode = !empty($stripeAccount['ready'])
        ? 'ready'
        : (!empty($stripeAccount['connected']) ? 'pending_onboarding' : 'not_connected');

    return [
        'payment_methods' => [
            'cash' => [
                'enabled' => !empty($methods['cash']['enabled']),
                'mode' => 'manual',
                'label' => 'Cash payments',
                'description' => 'Manual cash collection. No Stripe charge is created.',
            ],
            'stripe' => [
                'enabled' => !empty($methods['stripe']['enabled']),
                'mode' => $stripeMode,
                'connected' => !empty($stripeAccount['connected']),
                'ready' => !empty($stripeAccount['ready']),
                'account_hint' => (string)($stripeAccount['account_hint'] ?? ''),
                'label' => 'Stripe payments',
                'description' => !empty($stripeAccount['ready'])
                    ? 'Stripe account connected and ready for card checkout.'
                    : (!empty($stripeAccount['connected'])
                        ? 'Stripe account connected with onboarding or verification still pending.'
                        : 'Connect or create a Stripe account before card checkout can be used.'),
            ],
        ],
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok(mg_merchant_payment_methods_payload($pdo, (int)$workspace['id'], $merchantId));
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$cashEnabled = !empty($input['cash_enabled']);
$stripeEnabled = !empty($input['stripe_enabled']);

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id,state_json FROM merchant_payment_readiness WHERE workspace_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int)$workspace['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->prepare('INSERT INTO merchant_payment_readiness (workspace_id,created_at,updated_at) VALUES (?,NOW(),NOW())')->execute([(int)$workspace['id']]);
        $row = ['id' => (int)$pdo->lastInsertId(), 'state_json' => null];
    }

    $state = json_decode((string)($row['state_json'] ?? ''), true);
    if (!is_array($state)) $state = [];
    if (!isset($state['payment_methods']) || !is_array($state['payment_methods'])) $state['payment_methods'] = [];

    $stripeAccount = mg_payment_connect_status($pdo, $merchantId, false);
    $stripeMode = !empty($stripeAccount['ready'])
        ? 'ready'
        : (!empty($stripeAccount['connected']) ? 'pending_onboarding' : 'not_connected');
    $updatedAt = gmdate('c');
    $methodAudit = [
        'updated_by_user_id' => $merchantId,
        'updated_at' => $updatedAt,
    ];
    $state['payment_methods']['cash'] = $methodAudit + [
        'enabled' => $cashEnabled,
        'mode' => 'manual',
    ];
    $state['payment_methods']['stripe'] = $methodAudit + [
        'enabled' => $stripeEnabled,
        'mode' => $stripeMode,
        'account_id' => (string)($stripeAccount['account_id'] ?? ''),
    ];

    $pdo->prepare('UPDATE merchant_payment_readiness SET state_json=?,updated_at=NOW() WHERE id=?')
        ->execute([json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int)$row['id']]);
    $pdo->commit();

    mg_audit('merchant.payment_methods_updated', 'merchant_payment_readiness', [
        'cash_enabled' => $cashEnabled,
        'stripe_enabled' => $stripeEnabled,
        'stripe_onboarding_connected' => !empty($stripeAccount['connected']),
        'stripe_ready' => !empty($stripeAccount['ready']),
    ], $merchantId);

    header('Cache-Control: private, no-store, max-age=0');
    mg_ok(
        mg_merchant_payment_methods_payload($pdo, (int)$workspace['id'], $merchantId),
        'Payment methods saved.'
    );
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to save payment method settings.', 500);
}
