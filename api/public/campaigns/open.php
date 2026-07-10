<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/store/_canvas.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();
$userId = (int)$user['id'];
$campaignRef = strtolower(trim((string)($input['campaign'] ?? $input['campaign_id'] ?? $input['slug'] ?? '')));
$token = trim((string)($input['token'] ?? $input['qr_token'] ?? ''));

if ($campaignRef === '' && $token === '') mg_fail('Campaign not found.', 404);
mg_rate_limit('public.campaign.open', 'user:' . $userId, 60, 300);

try {
    $stmt = $pdo->prepare("SELECT c.id,c.public_id,c.public_slug,c.qr_code_token,c.merchant_user_id,c.status,c.starts_at,c.ends_at
        FROM campaigns c
        WHERE c.status='active'
          AND ((?<>'' AND (c.public_id=? OR c.public_slug=?)) OR (?<>'' AND c.qr_code_token=?))
        LIMIT 1");
    $stmt->execute([$campaignRef,$campaignRef,$campaignRef,$token,$token]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Campaign not found.', 404);
    if ((int)$campaign['merchant_user_id'] === $userId) mg_ok(['recorded'=>false,'reason'=>'merchant_owner'], 'Merchant preview is not recorded as customer activity.');
    $now = time();
    if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) mg_fail('Campaign has not started yet.', 409);
    if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) mg_fail('Campaign has ended.', 409);

    $session = mg_store_active_session_for_customer($pdo,$userId);
    if (!$session || (int)$session['merchant_user_id'] !== (int)$campaign['merchant_user_id']) {
        mg_ok(['recorded'=>false,'reason'=>'no_matching_active_store_session'], 'Campaign open was not connected to an active Store Canvas session.');
    }

    $duplicate = $pdo->prepare("SELECT 1 FROM campaign_events WHERE merchant_user_id=? AND campaign_id=? AND event_type='campaign.opened' AND created_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE) AND JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.user_id'))=? LIMIT 1");
    $duplicate->execute([(int)$campaign['merchant_user_id'],(int)$campaign['id'],(string)$userId]);
    if ($duplicate->fetchColumn()) {
        mg_ok(['recorded'=>false,'reason'=>'duplicate_window','session_id'=>(string)$session['public_id']], 'Campaign open already recorded recently.');
    }

    $contactStmt = $pdo->prepare('SELECT id,public_id FROM campaign_contacts WHERE merchant_user_id=? AND campaign_id=? AND user_id=? ORDER BY id DESC LIMIT 1');
    $contactStmt->execute([(int)$campaign['merchant_user_id'],(int)$campaign['id'],$userId]);
    $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);
    $eventId = mg_public_uuid();
    $context = [
        'user_id'=>$userId,
        'store_session_id'=>(string)$session['public_id'],
        'campaign_public_id'=>(string)$campaign['public_id'],
        'contact_public_id'=>(string)($contact['public_id'] ?? ''),
        'source_system'=>'public_campaign_page',
        'server_authoritative'=>true,
        'browser_overlap_used'=>false,
        'reward_issued'=>false,
    ];
    $event = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $event->execute([
        $eventId,(int)$campaign['merchant_user_id'],(int)$campaign['id'],null,$contact ? (int)$contact['id'] : null,
        'campaign.opened',json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
    ]);
    mg_store_log_event($pdo,$session,'campaign_opened','Opened campaign',[
        'campaign_id'=>(string)$campaign['public_id'],'campaign_event_id'=>$eventId,'source_system'=>'public_campaign_page','reward_issued'=>false,
    ]);
    mg_ok(['recorded'=>true,'event_id'=>$eventId,'session_id'=>(string)$session['public_id']], 'Campaign open recorded.');
} catch (Throwable $error) {
    mg_security_log('warning','public.campaign_open_event_failed','Unable to record campaign-open activity.',[
        'exception_class'=>$error::class,'message'=>$error->getMessage(),
    ],$userId);
    mg_fail('Unable to record campaign activity.', 500);
}
