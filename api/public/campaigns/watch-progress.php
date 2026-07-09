<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/rewards/_zero_value_bridge.php';
require_once dirname(__DIR__, 3) . '/includes/merchant-crm.php';
require_once dirname(__DIR__, 3) . '/includes/campaign-types.php';
require_once __DIR__ . '/_limits.php';

function mg_watch_reward_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
    $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
function mg_watch_reward_rules(mixed $json): array
{
    $decoded = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? $decoded : [];
}
function mg_watch_reward_find_user(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");
    $stmt->execute([$email]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}
function mg_watch_reward_expiry(array $template): ?string
{
    $rule = (string)($template['expiration_rule'] ?? 'none');
    if (($rule === 'fixed_date' || $rule === 'event_date') && !empty($template['expires_at'])) return (string)$template['expires_at'];
    if ($rule === 'after_issue' && !empty($template['expiration_days'])) return date('Y-m-d H:i:s', time() + ((int)$template['expiration_days'] * 86400));
    return null;
}
function mg_watch_reward_event(PDO $pdo, array $campaign, ?int $walletItemId, ?int $contactId, string $type, array $context): void
{
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([mg_watch_reward_uuid(), (int)$campaign['merchant_user_id'], (int)$campaign['id'], $walletItemId, $contactId, $type, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}
function mg_watch_reward_bridge(PDO $pdo, array $campaign, array $template, array $contact, int $walletDbId, string $walletPublicId, ?int $userId, ?string $expiresAt, int $percent): ?array
{
    if (!$userId) return null;
    return mg_zero_reward_issue_from_wallet($pdo, ['merchant_user_id'=>(int)$campaign['merchant_user_id'],'recipient_user_id'=>$userId,'recipient_external_id'=>(string)($contact['public_id'] ?? ''),'wallet_item_db_id'=>$walletDbId,'wallet_item_public_id'=>$walletPublicId,'campaign_public_id'=>(string)$campaign['public_id'],'reward_template_public_id'=>(string)$template['public_id'],'source_type'=>'watch_video_reward','source_reference'=>$walletPublicId,'source_line_reference'=>(string)($contact['public_id'] ?? $walletPublicId),'title'=>(string)$template['title'],'description'=>$template['description'] ?? $campaign['description'] ?? null,'currency'=>(string)($template['currency'] ?? 'USD'),'display_value_cents'=>(int)($template['value_amount_cents'] ?? 0),'expires_at'=>$expiresAt,'redemption_instructions'=>$template['redemption_instructions'] ?? null,'terms'=>['campaign_type'=>'watch_video_reward','milestone_percent'=>$percent]]);
}
function mg_watch_reward_template(PDO $pdo, int $merchantId, int $fallbackTemplateId, string $publicId): array
{
    if ($publicId !== '' && strlen($publicId) === 36 && preg_match('/^[a-f0-9-]{36}$/', $publicId) === 1) {
        $stmt = $pdo->prepare("SELECT * FROM reward_templates WHERE public_id=? AND merchant_user_id=? AND status='active' LIMIT 1 FOR UPDATE");
        $stmt->execute([$publicId, $merchantId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($template) return $template;
    }
    $stmt = $pdo->prepare("SELECT * FROM reward_templates WHERE id=? AND merchant_user_id=? AND status='active' LIMIT 1 FOR UPDATE");
    $stmt->execute([$fallbackTemplateId, $merchantId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$template) mg_fail('Milestone reward is unavailable.', 409);
    return $template;
}
function mg_watch_reward_milestones(array $rules): array
{
    $milestones = is_array($rules['milestones'] ?? null) ? $rules['milestones'] : [];
    if (!$milestones) $milestones = [['percent' => max(1, min(100, (int)($rules['required_percent'] ?? 80))), 'reward_template_id' => '', 'label' => 'Video completion gift']];
    $out = [];
    foreach ($milestones as $item) {
        $percent = max(1, min(100, (int)($item['percent'] ?? 0)));
        if ($percent < 1) continue;
        $out[$percent] = ['percent' => $percent, 'reward_template_id' => strtolower(trim((string)($item['reward_template_id'] ?? ''))), 'label' => trim((string)($item['label'] ?? '')) ?: ($percent . '% watched gift')];
    }
    ksort($out);
    return array_values($out);
}
function mg_watch_reward_already_issued(PDO $pdo, int $campaignId, int $contactId, int $percent): bool
{
    $stmt = $pdo->prepare("SELECT id FROM wallet_items WHERE campaign_id=? AND contact_id=? AND source_type='watch_video_reward' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.milestone_percent'))=? AND status<>'cancelled' LIMIT 1");
    $stmt->execute([$campaignId, $contactId, (string)$percent]);
    return (bool)$stmt->fetchColumn();
}
function mg_watch_reward_issue(PDO $pdo, array $campaign, array $contact, ?int $userId, array $template, int $percent, float $watchPercent, array $watchContext): array
{
    if ($template['quantity_limit'] !== null && (int)$template['issued_count'] >= (int)$template['quantity_limit']) mg_fail('A milestone reward has reached its inventory limit.', 409);
    $expiresAt = mg_watch_reward_expiry($template);
    $walletPublicId = mg_watch_reward_uuid();
    $stampLedger = mg_public_campaign_debit_reward_stamp($pdo, $campaign + ['reward_template_public_id' => (string)$template['public_id']], $walletPublicId, 'watch_video_reward', ['contact_id'=>(string)$contact['public_id'],'milestone_percent'=>$percent,'watch_percent'=>$watchPercent,'video_id'=>$watchContext['video_id'] ?? '']);
    $metadata = ['campaign_type'=>'watch_video_reward','reward_template_id'=>(string)$template['public_id'],'milestone_percent'=>$percent,'watch_percent'=>$watchPercent,'video_id'=>$watchContext['video_id'] ?? '','duration_seconds'=>$watchContext['duration_seconds'] ?? 0,'current_time_seconds'=>$watchContext['current_time_seconds'] ?? 0,'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id'] ?? null];
    $stmt = $pdo->prepare('INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())');
    $stmt->execute([$walletPublicId,$userId,(int)$contact['id'],(int)$campaign['merchant_user_id'],(int)$template['id'],(int)$campaign['id'],'watch_video_reward',(string)$contact['public_id'],'issued',(int)($template['value_amount_cents'] ?? 0),(string)($template['currency'] ?? 'USD'),(string)$template['title'],json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),$expiresAt]);
    $walletDbId = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE campaigns SET issued_count=issued_count+1, updated_at=NOW() WHERE id=?')->execute([(int)$campaign['id']]);
    $pdo->prepare('UPDATE reward_templates SET issued_count=issued_count+1, updated_at=NOW() WHERE id=?')->execute([(int)$template['id']]);
    $bridge = mg_watch_reward_bridge($pdo, $campaign, $template, $contact, $walletDbId, $walletPublicId, $userId, $expiresAt, $percent);
    mg_watch_reward_event($pdo, $campaign, $walletDbId, (int)$contact['id'], 'watch_reward.issued', ['wallet_item_id'=>$walletPublicId,'milestone_percent'=>$percent,'reward_template_id'=>(string)$template['public_id'],'watch_percent'=>$watchPercent,'pppm_bridge'=>$bridge,'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id'] ?? null]);
    return ['wallet_item_id'=>$walletPublicId,'reward_title'=>(string)$template['title'],'percent'=>$percent,'expires_at'=>$expiresAt,'pppm_bridge'=>$bridge];
}

mg_require_method('POST');
$input = mg_input();
$pdo = mg_db();
$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? '')));
$email = strtolower(trim((string)($input['email'] ?? '')));
$name = trim((string)($input['name'] ?? ''));
$phone = trim((string)($input['phone'] ?? ''));
$progress = max(0, min(100, (float)($input['progress_percent'] ?? 0)));
$videoId = trim((string)($input['video_id'] ?? ''));
$duration = max(0, (int)($input['duration_seconds'] ?? 0));
$currentTime = max(0, (int)($input['current_time_seconds'] ?? 0));
if ($campaignRef === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255 || mb_strlen($name) > 180 || mb_strlen($phone) > 60) mg_fail('Invalid watch reward progress.', 422);
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT c.*, rt.id reward_template_db_id, rt.public_id reward_template_public_id FROM campaigns c INNER JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.status='active' AND c.campaign_type='watch_video_reward' AND (c.public_id=? OR c.public_slug=?) LIMIT 1 FOR UPDATE");
    $stmt->execute([$campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) { $pdo->rollBack(); mg_fail('Watch Video Reward campaign is not available.', 404); }
    $rules = mg_watch_reward_rules($campaign['rules_json'] ?? null);
    $expectedVideoId = trim((string)($rules['youtube_video_id'] ?? ''));
    if ($expectedVideoId !== '' && $videoId !== '' && $videoId !== $expectedVideoId) { $pdo->rollBack(); mg_fail('Video does not match this campaign.', 422); }
    $now = time();
    if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) { $pdo->rollBack(); mg_fail('Campaign has not started yet.', 409); }
    if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) { $pdo->rollBack(); mg_fail('Campaign has ended.', 409); }
    if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) { $pdo->rollBack(); mg_fail('Campaign reward limit has been reached.', 409); }
    $merchantId = (int)$campaign['merchant_user_id']; $campaignId = (int)$campaign['id']; $userId = mg_watch_reward_find_user($pdo, $email);
    $existingContactStmt = $pdo->prepare('SELECT id, public_id FROM campaign_contacts WHERE campaign_id=? AND email=? LIMIT 1 FOR UPDATE');
    $existingContactStmt->execute([$campaignId, $email]);
    $existing = $existingContactStmt->fetch(PDO::FETCH_ASSOC); $publicId = $existing ? (string)$existing['public_id'] : mg_watch_reward_uuid();
    $metadata = ['campaign_type'=>'watch_video_reward','video_id'=>$videoId ?: $expectedVideoId,'max_progress_percent'=>$progress,'duration_seconds'=>$duration,'current_time_seconds'=>$currentTime,'user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255),'ip'=>mg_client_ip()];
    $contactStmt = $pdo->prepare("INSERT INTO campaign_contacts (public_id,merchant_user_id,campaign_id,user_id,email,phone,name,source,opt_in_status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), phone=VALUES(phone), name=VALUES(name), source=VALUES(source), metadata_json=JSON_MERGE_PATCH(COALESCE(metadata_json, JSON_OBJECT()), VALUES(metadata_json)), updated_at=NOW()");
    $contactStmt->execute([$publicId,$merchantId,$campaignId,$userId,$email,$phone!==''?$phone:null,$name!==''?$name:null,'watch_video_reward','opted_in',json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    $contactLookup = $pdo->prepare('SELECT id, public_id FROM campaign_contacts WHERE campaign_id=? AND email=? LIMIT 1 FOR UPDATE');
    $contactLookup->execute([$campaignId, $email]);
    $contact = $contactLookup->fetch(PDO::FETCH_ASSOC);
    if (!$contact) { $pdo->rollBack(); mg_fail('Watch reward contact could not be prepared.', 500); }
    mg_merchant_crm_record_event($pdo, ['merchant_user_id'=>$merchantId,'campaign_id'=>$campaignId,'campaign_type'=>'watch_video_reward','event_type'=>'watch_reward.progress','source_type'=>'watch_video_reward','source_public_id'=>(string)$contact['public_id'],'user_id'=>$userId,'email'=>$email,'phone'=>$phone,'name'=>$name,'metadata'=>$metadata]);
    mg_watch_reward_event($pdo, $campaign, null, (int)$contact['id'], $progress <= 1 ? 'watch_reward.started' : 'watch_reward.progress', $metadata + ['progress_percent'=>$progress]);
    $issued = [];
    foreach (mg_watch_reward_milestones($rules) as $milestone) {
        $percent = (int)$milestone['percent'];
        if ($progress + 0.0001 < $percent) continue;
        if (mg_watch_reward_already_issued($pdo, $campaignId, (int)$contact['id'], $percent)) continue;
        $template = mg_watch_reward_template($pdo, $merchantId, (int)$campaign['reward_template_db_id'], (string)$milestone['reward_template_id']);
        $issued[] = mg_watch_reward_issue($pdo, $campaign, $contact, $userId, $template, $percent, $progress, $metadata);
    }
    $pdo->commit();
    mg_ok(['campaign_id'=>(string)$campaign['public_id'],'contact_id'=>(string)$contact['public_id'],'progress_percent'=>$progress,'issued_rewards'=>$issued,'milestones'=>mg_watch_reward_milestones($rules)], $issued ? 'Video reward unlocked.' : 'Watch progress recorded.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'watch_reward.progress.failed', 'Unable to record watch reward progress.', ['exception_class'=>$error::class,'message'=>$error->getMessage()]);
    mg_fail('Unable to record watch reward progress.', 500);
}
