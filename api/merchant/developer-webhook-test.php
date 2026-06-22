<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/distribution/_developer_webhooks.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.developer_webhooks.test');
$input = mg_input();
mg_require_csrf_for_write($input);
$appPublicId = trim((string)($input['app_id'] ?? ''));
if ($appPublicId === '') mg_fail('Developer app is required.', 422);

$pdo = mg_db();
$stmt = $pdo->prepare('SELECT * FROM merchant_developer_apps WHERE public_id=? AND merchant_user_id=? LIMIT 1');
$stmt->execute([$appPublicId,(int)$user['id']]);
$app = $stmt->fetch();
if (!$app) mg_fail('Developer app not found.', 404);

$eventId = mg_dev_webhook_event($pdo,(int)$app['id'],(int)$user['id'],'webhook.test',[
    'app_id'=>$appPublicId,
    'message'=>'Microgifter webhook test event.',
    'queued_at'=>gmdate('c'),
],null,'webhook_test',$appPublicId);

mg_audit('developer_webhooks.test_queued','merchant_developer_app',['app_id'=>$appPublicId,'event_id'=>$eventId],(int)$user['id']);
mg_ok(['event_id'=>$eventId,'status'=>$eventId ? 'queued_or_skipped' : 'not_queued'], 'Webhook test event queued.');
