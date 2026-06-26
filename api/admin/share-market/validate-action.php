<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/admin-actions.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/approval-workflow.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
mg_rate_limit('share_market.admin.validate', 'user:' . (int)$user['id'], 30, 300);

try {
    $preview = mg_share_market_admin_validate_preview($input, $user);
    $preview['validation_token'] = mg_share_market_approval_issue_validation_token($preview['manifest']);
    $preview['approval_queue_enabled'] = true;
    mg_ok($preview, 'Share Market admin action validated. No mutation was performed.');
} catch (DomainException $e) {
    mg_security_log('warning', 'share_market.admin_action_denied', $e->getMessage(), [
        'action' => (string)($input['action'] ?? ''),
        'target_id' => (string)($input['target_id'] ?? ''),
    ], (int)($user['id'] ?? 0));
    mg_fail($e->getMessage(), 403);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.admin_action_preview_failed', 'Share Market admin action preview failed.', [
        'action' => (string)($input['action'] ?? ''),
        'exception_class' => $e::class,
    ], (int)($user['id'] ?? 0));
    mg_fail('Unable to validate the Share Market admin action.', 500);
}
