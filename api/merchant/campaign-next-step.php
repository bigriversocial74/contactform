<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_next_step_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
$campaignId = strtolower(trim((string) ($input['campaign_id'] ?? '')));
$stepType = strtolower(trim((string) ($input['step_type'] ?? 'review')));
$allowed = ['review','share','complete','copy','template','refresh'];
if ($stepType === '' || !in_array($stepType, $allowed, true)) mg_fail('Invalid step.', 422);

try {
    $campaignDbId = null;
    if ($campaignId !== '') {
        if (strlen($campaignId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $campaignId)) mg_fail('Invalid campaign.', 422);
        $stmt = $pdo->prepare('SELECT id FROM campaigns WHERE public_id = ? AND merchant_user_id = ? LIMIT 1');
        $stmt->execute([$campaignId, $merchantId]);
        $campaignDbId = $stmt->fetchColumn();
        if (!$campaignDbId) mg_fail('Campaign not found.', 404);
    }
    $context = ['step_type'=>$stepType,'campaign_id'=>$campaignId ?: null,'label'=>(string)($input['label'] ?? '')];
    $event = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $event->execute([mg_next_step_uuid(), $merchantId, $campaignDbId ? (int) $campaignDbId : null, null, null, 'merchant.next_step.' . $stepType, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    mg_ok(['logged'=>true,'step_type'=>$stepType]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.next_step.unavailable', 'Merchant next step unavailable.', ['exception_class'=>$error::class], $merchantId);
    mg_ok(['logged'=>false], 'Merchant next step unavailable until the Stage 12 schema is installed.');
}
