<?php
declare(strict_types=1);
require_once __DIR__ . '/_public.php';
require_once dirname(__DIR__, 2) . '/distribution/_developer_webhooks.php';

function mg_account_link_redirect(string $url, array $params): void
{
    $separator = str_contains($url, '?') ? '&' : '?';
    header('Cache-Control: no-store, private');
    header('Location: ' . $url . $separator . http_build_query($params), true, 302);
    exit;
}

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);
$code = trim((string)($input['code'] ?? ''));
$action = trim((string)($input['action'] ?? 'approve'));
if ($code === '' || !in_array($action, ['approve','cancel'], true)) mg_fail('Invalid account link request.', 422);

$pdo = mg_db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT dalr.*,mda.public_id AS app_public_id,mda.name AS app_name,mda.status AS app_status FROM developer_app_link_requests dalr INNER JOIN merchant_developer_apps mda ON mda.id=dalr.app_id WHERE dalr.link_code_hash=? LIMIT 1 FOR UPDATE");
    $stmt->execute([hash('sha256', $code)]);
    $request = $stmt->fetch();
    if (!$request) mg_fail('Account link request not found.', 404);
    if ((string)$request['status'] !== 'pending') mg_fail('Account link request has already been completed.', 409);
    if (!empty($request['expires_at']) && strtotime((string)$request['expires_at']) < time()) {
        $pdo->prepare("UPDATE developer_app_link_requests SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$request['id']]);
        mg_dev_webhook_event($pdo,(int)$request['app_id'],(int)$request['merchant_user_id'],'account_link.expired',['link_request_id'=>(string)$request['public_id'],'external_user_id'=>(string)$request['external_user_id']],null,'account_link',(string)$request['public_id']);
        $pdo->commit();
        mg_account_link_redirect((string)$request['return_url'], ['status'=>'expired','external_user_id'=>(string)$request['external_user_id'],'state'=>(string)($request['state'] ?? '')]);
    }
    if ((string)$request['app_status'] !== 'active') mg_fail('Developer app is not active.', 409);

    if ($action === 'cancel') {
        $pdo->prepare("UPDATE developer_app_link_requests SET status='cancelled',completed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$request['id']]);
        mg_dev_webhook_event($pdo,(int)$request['app_id'],(int)$request['merchant_user_id'],'account_link.cancelled',['link_request_id'=>(string)$request['public_id'],'external_user_id'=>(string)$request['external_user_id']],null,'account_link',(string)$request['public_id']);
        $pdo->commit();
        mg_account_link_redirect((string)$request['return_url'], ['status'=>'cancelled','external_user_id'=>(string)$request['external_user_id'],'state'=>(string)($request['state'] ?? '')]);
    }

    $linkedId = mg_distribution_uuid();
    $consent = ['approved_at'=>gmdate('c'),'app_id'=>(string)$request['app_public_id'],'app_name'=>(string)$request['app_name'],'source'=>'account_link_page'];
    $pdo->prepare("INSERT INTO developer_app_user_links (public_id,app_id,merchant_user_id,microgifter_user_id,external_user_id,external_user_hash,status,consent_json,linked_at,updated_at) VALUES (?,?,?,?,?,?,'active',?,NOW(),NOW()) ON DUPLICATE KEY UPDATE public_id=VALUES(public_id),microgifter_user_id=VALUES(microgifter_user_id),status='active',consent_json=VALUES(consent_json),linked_at=NOW(),revoked_at=NULL,updated_at=NOW()")
        ->execute([$linkedId,(int)$request['app_id'],(int)$request['merchant_user_id'],(int)$user['id'],(string)$request['external_user_id'],(string)$request['external_user_hash'],mg_distribution_json($consent)]);
    $fetchLink = $pdo->prepare("SELECT public_id FROM developer_app_user_links WHERE app_id=? AND external_user_hash=? AND status='active' LIMIT 1");
    $fetchLink->execute([(int)$request['app_id'],(string)$request['external_user_hash']]);
    $linkedPublicId = (string)($fetchLink->fetchColumn() ?: $linkedId);
    $pdo->prepare("UPDATE developer_app_link_requests SET status='approved',approved_user_id=?,linked_account_public_id=?,completed_at=NOW(),updated_at=NOW() WHERE id=?")
        ->execute([(int)$user['id'],$linkedPublicId,(int)$request['id']]);
    mg_dev_webhook_event($pdo,(int)$request['app_id'],(int)$request['merchant_user_id'],'account_link.approved',['link_request_id'=>(string)$request['public_id'],'linked_account_id'=>$linkedPublicId,'external_user_id'=>(string)$request['external_user_id']],null,'account_link',$linkedPublicId);
    $pdo->commit();
    mg_audit('developer_app_user_linked', 'developer_app_user_link', ['app_id'=>(string)$request['app_public_id'],'linked_account_id'=>$linkedPublicId], (int)$user['id']);
    mg_account_link_redirect((string)$request['return_url'], ['status'=>'linked','linked_account_id'=>$linkedPublicId,'external_user_id'=>(string)$request['external_user_id'],'state'=>(string)($request['state'] ?? '')]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to complete account link.', 500);
}
