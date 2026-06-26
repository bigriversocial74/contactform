<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/merchant-state.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/notifications.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
mg_rate_limit('share_market.program.opt_out', 'user:' . (int)$user['id'], 6, 300);

try {
    $pdo = mg_db();
    if (!mg_share_market_sql_schema_available($pdo)) {
        throw new RuntimeException('Share Market SQL schema is not installed.');
    }
    $note = trim((string)($input['note'] ?? 'Merchant opted out from DAVE Share Market Admin.'));
    if ($note === '') $note = 'Merchant opted out from DAVE Share Market Admin.';
    if (mb_strlen($note) > 1000) throw new InvalidArgumentException('Opt-out note cannot exceed 1,000 characters.');

    $closedId = '';
    $pdo->beginTransaction();
    try {
        $row = mg_share_market_sql_fetch_enrollment_by_user($pdo, (int)$user['id'], true);
        if ($row) {
            $oldState = (string)$row['status'];
            $closedId = (string)$row['public_id'];
            if (!in_array($oldState, ['closed','rejected'], true)) {
                $stmt = $pdo->prepare("UPDATE share_market_enrollments SET status='closed', review_note=?, closed_at=NOW() WHERE id=?");
                $stmt->execute([$note, (int)$row['id']]);
                mg_share_market_sql_admin_event($pdo, (int)$user['id'], 'share_market.sql.enrollment_closed_by_merchant', 'enrollment', (string)$row['public_id'], $oldState, 'closed', $note);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $state = mg_share_market_merchant_state($pdo, (int)$user['id']);
    if ($closedId !== '') {
        mg_share_market_notify_admins($pdo, (int)$user['id'], 'DAVE merchant opted out', 'A merchant closed Share Market participation.', 'enrollment_closed.' . strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $closedId)), ['target_type'=>'enrollment','target_public_id'=>$closedId,'share_market_status'=>'closed']);
    }
    mg_ok($state, 'Share Market participation closed.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.program_opt_out_failed', 'Unable to close Share Market participation.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to close Share Market participation.', 500);
}
