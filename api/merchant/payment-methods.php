<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';

$user = mg_require_api_user();
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);

function mg_merchant_payment_methods_payload(PDO $pdo, int $workspaceId): array
{
    $stmt = $pdo->prepare('SELECT state_json FROM merchant_payment_readiness WHERE workspace_id=? LIMIT 1');
    $stmt->execute([$workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $state = json_decode((string)($row['state_json'] ?? ''), true);
    if (!is_array($state)) $state = [];
    $methods = is_array($state['payment_methods'] ?? null) ? $state['payment_methods'] : [];
    return [
        'payment_methods' => [
            'cash' => [
                'enabled' => !empty($methods['cash']['enabled']),
                'mode' => 'test',
                'label' => 'Pay with cash',
                'description' => 'Manual cash collection for local testing. No Stripe charge is created.',
            ],
        ],
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    mg_ok(mg_merchant_payment_methods_payload($pdo, (int)$workspace['id']));
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$cashEnabled = !empty($input['cash_enabled']);

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
    $state['payment_methods']['cash'] = [
        'enabled' => $cashEnabled,
        'mode' => 'test',
        'updated_by_user_id' => (int)$user['id'],
        'updated_at' => gmdate('c'),
    ];
    $pdo->prepare('UPDATE merchant_payment_readiness SET state_json=?,updated_at=NOW() WHERE id=?')
        ->execute([json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int)$row['id']]);
    $pdo->commit();
    mg_audit('merchant.payment_methods_updated', 'merchant_payment_readiness', ['cash_enabled' => $cashEnabled], (int)$user['id']);
    mg_ok(mg_merchant_payment_methods_payload($pdo, (int)$workspace['id']), $cashEnabled ? 'Cash payments enabled for testing.' : 'Cash payments disabled.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to save payment method settings.', 500);
}
