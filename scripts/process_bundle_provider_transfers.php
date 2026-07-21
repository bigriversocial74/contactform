<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/bundles/_provider_reconciliation.php';
require_once dirname(__DIR__) . '/api/bundles/_release_readiness.php';

$pdo = mg_db();
$limit = max(1, min(25, (int)($argv[1] ?? 10)));
mg_bundle_provider_assert_execution_allowed();
mg_bundle_release_assert_runtime_allowed($pdo, 'transfer');

$processed = 0;
$succeeded = 0;
$failed = 0;

while ($processed < $limit) {
    $transfer = null;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->query("SELECT * FROM gift_bundle_settlement_transfers
            WHERE transfer_status='created'
              AND (next_dispatch_at IS NULL OR next_dispatch_at<=NOW())
              AND (dispatch_locked_at IS NULL OR dispatch_locked_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE))
            ORDER BY created_at ASC
            LIMIT 1 FOR UPDATE");
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$transfer) {
            $pdo->commit();
            break;
        }

        $lockToken = mg_public_uuid();
        $pdo->prepare("UPDATE gift_bundle_settlement_transfers
            SET transfer_status='submitted',dispatch_attempt_count=dispatch_attempt_count+1,dispatch_locked_at=NOW(),dispatch_lock_token=?,submitted_at=COALESCE(submitted_at,NOW()),updated_at=NOW()
            WHERE id=?")
            ->execute([$lockToken, (int)$transfer['id']]);
        $pdo->commit();

        $provider = mg_stripe_api_request(
            $pdo,
            'POST',
            '/v1/transfers',
            mg_bundle_provider_transfer_payload($transfer),
            'bundle-transfer-' . (string)$transfer['public_id']
        );

        $pdo->beginTransaction();
        mg_bundle_provider_mark_succeeded($pdo, $transfer, $provider);
        $pdo->commit();
        $succeeded++;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_array($transfer)) {
            $pdo->beginTransaction();
            mg_bundle_provider_mark_failed($pdo, $transfer, $error);
            $pdo->commit();
        }
        fwrite(STDERR, '[bundle-transfer] ' . $error->getMessage() . PHP_EOL);
        $failed++;
    }
    $processed++;
}

fwrite(STDOUT, json_encode([
    'processed' => $processed,
    'succeeded' => $succeeded,
    'failed' => $failed,
    'payment_mode' => mg_payment_mode(),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
