<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/loyalty-quest-campaign-type.php';
require_once dirname(__DIR__) . '/public/campaigns/_merchant_notifications.php';

function mg_lq_datetime(mixed $value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    $raw = str_replace('T', ' ', $raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw) === 1) $raw .= ':00';
    $time = strtotime($raw);
    if ($time === false) mg_fail('Invalid campaign date.', 422);
    return date('Y-m-d H:i:s', $time);
}

function mg_lq_slug(string $title): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? ''));
    return substr(trim($slug, '-') ?: 'loyalty-quest', 0, 120);
}

function mg_lq_unique_slug(PDO $pdo, int $merchantId, string $title, string $exclude = ''): string
{
    $base = mg_lq_slug($title);
    $candidate = $base;
    $suffix = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND public_slug=? AND public_id<>?');
    while (true) {
        $stmt->execute([$merchantId, $candidate, $exclude]);
        if ((int)$stmt->fetchColumn() === 0) return $candidate;
        $suffix++;
        $candidate = substr($base, 0, max(1, 120 - strlen((string)$suffix) - 1)) . '-' . $suffix;
    }
}

function mg_lq_reward_template(PDO $pdo, int $merchantId, string $publicId, string $status): ?int
{
    $publicId = strtolower(trim($publicId));
    if ($publicId === '') return null;
    if (strlen($publicId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) mg_fail('Invalid reward template.', 422);
    $stmt = $pdo->prepare("SELECT id,status FROM reward_templates WHERE public_id=? AND merchant_user_id=? AND status<>'archived' LIMIT 1");
    $stmt->execute([$publicId, $merchantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Reward template not found.', 404);
    if ($status === 'active' && (string)$row['status'] !== 'active') mg_fail('Active Loyalty Quests require an active reward template.', 422);
    return (int)$row['id'];
}

function mg_lq_row(array $row): array
{
    $rules = json_decode((string)($row['rules_json'] ?? ''), true);
    if (!is_array($rules)) $rules = [];
    return [
        'id' => (string)$row['public_id'],
        'campaign_type' => 'loyalty_quest',
        'campaign_type_label' => 'Loyalty Quest',
        'campaign_type_category' => 'loyalty_retention',
        'public_enabled' => true,
        'internal_only' => false,
        'title' => (string)$row['title'],
        'description' => (string)($row['description'] ?? ''),
        'form_headline' => (string)($row['form_headline'] ?? ''),
        'form_description' => (string)($row['form_description'] ?? ''),
        'success_message' => (string)($row['success_message'] ?? ''),
        'status' => (string)$row['status'],
        'starts_at' => $row['starts_at'] ?? null,
        'ends_at' => $row['ends_at'] ?? null,
        'quantity_limit' => $row['quantity_limit'] === null ? null : (int)$row['quantity_limit'],
        'issued_count' => (int)($row['issued_count'] ?? 0),
        'per_user_limit' => (int)($row['per_user_limit'] ?? 1),
        'agent_discoverable' => (bool)((int)($row['agent_discoverable'] ?? 0)),
        'public_slug' => (string)($row['public_slug'] ?? ''),
        'qr_code_token' => (string)($row['qr_code_token'] ?? ''),
        'reward_template_id' => $row['reward_template_public_id'] ?? null,
        'reward_template_title' => $row['reward_template_title'] ?? null,
        'reward_template_status' => $row['reward_template_status'] ?? null,
        'reward_attached' => !empty($row['reward_template_public_id']),
        'rules' => $rules,
        'activity' => ['contacts'=>0,'wallet_items'=>(int)($row['issued_count'] ?? 0),'events'=>0,'last_event_at'=>null],
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_lq_status_transition_allowed(?string $previous, string $next): bool
{
    if ($previous === null) return in_array($next, ['draft','active'], true);
    if ($previous === $next) return true;
    $allowed = [
        'draft' => ['active','archived'],
        'active' => ['paused','ended'],
        'paused' => ['active','ended','archived'],
        'ended' => ['archived'],
        'archived' => [],
    ];
    return in_array($next, $allowed[$previous] ?? [], true);
}

$user = mg_merchant_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

$campaignId = strtolower(trim((string)($input['campaign_id'] ?? '')));
$title = trim((string)($input['title'] ?? ''));
$status = strtolower(trim((string)($input['status'] ?? 'draft')));
if ($campaignId !== '' && (strlen($campaignId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $campaignId) !== 1)) mg_fail('Invalid campaign.', 422);
if ($title === '' || mb_strlen($title) > 180) mg_fail('Enter a campaign title up to 180 characters.', 422);
if (!in_array($status, ['draft','active','paused','ended','archived'], true)) mg_fail('Invalid campaign status.', 422);

$startsAt = mg_lq_datetime($input['starts_at'] ?? '');
$endsAt = mg_lq_datetime($input['ends_at'] ?? '');
if ($startsAt && $endsAt && strtotime($startsAt) >= strtotime($endsAt)) mg_fail('Campaign end date must be after the start date.', 422);
if ($status === 'active' && $endsAt !== null && strtotime($endsAt) <= time()) mg_fail('Active Loyalty Quests require a future end date.', 422);

$existingRules = [];
$existingStatus = null;
if ($campaignId !== '') {
    $stmt = $pdo->prepare("SELECT status,rules_json FROM campaigns WHERE public_id=? AND merchant_user_id=? AND campaign_type='loyalty_quest' LIMIT 1");
    $stmt->execute([$campaignId, $merchantId]);
    $existingCampaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existingCampaign) mg_fail('Loyalty Quest not found.', 404);
    $existingStatus = (string)$existingCampaign['status'];
    $existingRules = json_decode((string)($existingCampaign['rules_json'] ?? ''), true);
    if (!is_array($existingRules)) $existingRules = [];
}
if (!mg_lq_status_transition_allowed($existingStatus, $status)) mg_fail('This Loyalty Quest status transition is not allowed.', 409);

$rules = mg_loyalty_quest_normalize_rules($input, $existingRules);
$errors = mg_loyalty_quest_validate_rules($rules, $status);
if ($errors) mg_fail(implode(' ', $errors), 422);

$rewardTemplateId = mg_lq_reward_template($pdo, $merchantId, (string)($input['reward_template_id'] ?? ''), $status);
if ($status === 'active' && $rewardTemplateId === null) mg_fail('Active Loyalty Quests require an attached reward template.', 422);
if ($status === 'active') {
    $usage = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND status='active' AND public_id<>?");
    $usage->execute([$merchantId, $campaignId]);
    mg_package_require_limit_available($pdo, $user, 'max_active_campaigns', (int)$usage->fetchColumn(), 'Active campaign limit reached.');
}

$quantityRaw = trim((string)($input['quantity_limit'] ?? ''));
$quantityLimit = $quantityRaw === '' ? null : max(1, (int)$quantityRaw);
$perUserLimit = max(1, (int)($input['per_user_limit'] ?? 1));
$description = trim((string)($input['description'] ?? '')) ?: null;
$formHeadline = trim((string)($input['form_headline'] ?? '')) ?: null;
$formDescription = trim((string)($input['form_description'] ?? '')) ?: null;
$successMessage = trim((string)($input['success_message'] ?? '')) ?: null;
$agentDiscoverable = !empty($input['agent_discoverable']) ? 1 : 0;
$rules['campaign_type'] = 'loyalty_quest';
$rules['registry'] = 'loyalty_quest_campaign_type_v1';
$rulesJson = json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($rulesJson)) mg_fail('Unable to encode Loyalty Quest rules.', 500);

try {
    $pdo->beginTransaction();
    $isNew = $campaignId === '';
    $previousStatus = $existingStatus;
    if ($isNew) {
        $campaignId = mg_merchant_uuid();
        $slug = mg_lq_unique_slug($pdo, $merchantId, $title);
        $qrToken = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare('INSERT INTO campaigns (public_id,merchant_user_id,reward_template_id,campaign_type,title,description,form_headline,form_description,success_message,status,starts_at,ends_at,quantity_limit,per_user_limit,agent_discoverable,public_slug,qr_code_token,rules_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([$campaignId,$merchantId,$rewardTemplateId,'loyalty_quest',$title,$description,$formHeadline,$formDescription,$successMessage,$status,$startsAt,$endsAt,$quantityLimit,$perUserLimit,$agentDiscoverable,$slug,$qrToken,$rulesJson]);
        $dbId = (int)$pdo->lastInsertId();
        $message = 'Loyalty Quest created.';
    } else {
        $lookup = $pdo->prepare("SELECT id,qr_code_token FROM campaigns WHERE public_id=? AND merchant_user_id=? AND campaign_type='loyalty_quest' LIMIT 1 FOR UPDATE");
        $lookup->execute([$campaignId,$merchantId]);
        $existing = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$existing) mg_fail('Loyalty Quest not found.', 404);
        $dbId = (int)$existing['id'];
        $slug = mg_lq_unique_slug($pdo,$merchantId,$title,$campaignId);
        $qrToken = (string)($existing['qr_code_token'] ?? '') ?: bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("UPDATE campaigns SET reward_template_id=?,title=?,description=?,form_headline=?,form_description=?,success_message=?,status=?,starts_at=?,ends_at=?,quantity_limit=?,per_user_limit=?,agent_discoverable=?,public_slug=?,qr_code_token=?,rules_json=?,updated_at=NOW() WHERE id=? AND public_id=? AND merchant_user_id=? AND campaign_type='loyalty_quest'");
        $stmt->execute([$rewardTemplateId,$title,$description,$formHeadline,$formDescription,$successMessage,$status,$startsAt,$endsAt,$quantityLimit,$perUserLimit,$agentDiscoverable,$slug,$qrToken,$rulesJson,$dbId,$campaignId,$merchantId]);
        $message = 'Loyalty Quest updated.';
    }
    $pdo->commit();

    $select = $pdo->prepare('SELECT c.*,rt.public_id reward_template_public_id,rt.title reward_template_title,rt.status reward_template_status FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.id=? AND c.merchant_user_id=? LIMIT 1');
    $select->execute([$dbId,$merchantId]);
    $row = $select->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Loyalty Quest could not be loaded.', 500);

    $notification = ['created'=>false,'reason'=>'not_required'];
    if ($status === 'active' && ($isNew || $previousStatus !== 'active')) $notification = mg_public_campaign_notify_merchant_lifecycle($pdo,$row,'campaign.launched');
    elseif ($isNew) $notification = mg_public_campaign_notify_merchant_lifecycle($pdo,$row,'campaign.created');

    mg_audit('merchant.loyalty_quest_saved','campaign',['campaign_id'=>$campaignId,'status'=>$status,'previous_status'=>$previousStatus,'rules'=>$rules,'notification'=>$notification],$merchantId);
    mg_ok(['campaign'=>mg_lq_row($row),'notification'=>$notification,'schema_ready'=>true],$message,$isNew ? 201 : 200);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','merchant.loyalty_quest.save_failed','Unable to save Loyalty Quest.',['exception_class'=>$error::class,'message'=>$error->getMessage()],$merchantId);
    mg_fail('Unable to save Loyalty Quest.',500);
}
