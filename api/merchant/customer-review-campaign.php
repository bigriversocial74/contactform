<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_customer_review_campaign_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_customer_review_campaign_datetime(mixed $value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    $raw = str_replace('T', ' ', $raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw) === 1) $raw .= ':00';
    $timestamp = strtotime($raw);
    if ($timestamp === false) mg_fail('Invalid campaign date.', 422);
    return date('Y-m-d H:i:s', $timestamp);
}

mg_require_method('POST');
$user = mg_merchant_require_permission('merchant.campaigns.manage');
$merchantId = (int)($user['id'] ?? 0);
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

$input = mg_input();
mg_require_csrf_for_write($input);

$campaignId = strtolower(trim((string)($input['campaign_id'] ?? '')));
$title = trim((string)($input['title'] ?? ''));
$status = strtolower(trim((string)($input['status'] ?? 'draft')));
$description = trim((string)($input['description'] ?? '')) ?: null;
$formHeadline = trim((string)($input['form_headline'] ?? '')) ?: null;
$formDescription = trim((string)($input['form_description'] ?? '')) ?: null;
$successMessage = trim((string)($input['success_message'] ?? '')) ?: null;
$rewardPublicId = strtolower(trim((string)($input['reward_template_id'] ?? '')));
$startsAt = mg_customer_review_campaign_datetime($input['starts_at'] ?? '');
$endsAt = mg_customer_review_campaign_datetime($input['ends_at'] ?? '');
$quantityRaw = trim((string)($input['quantity_limit'] ?? ''));
$quantityLimit = $quantityRaw === '' ? null : max(1, (int)$quantityRaw);
$maxReviews = max(1, min(1000, (int)($input['review_max_per_period'] ?? 1)));
$period = strtolower(trim((string)($input['review_limit_period'] ?? 'month')));
$prompt = trim((string)($input['review_prompt'] ?? $formDescription ?? 'Tell us about your experience.'));

if (!in_array($period, ['day', 'week', 'month', 'quarter', 'year'], true)) $period = 'month';
if ($campaignId !== '' && preg_match('/^[a-f0-9-]{36}$/', $campaignId) !== 1) mg_fail('Invalid campaign.', 422);
if ($title === '' || mb_strlen($title) > 180) mg_fail('Campaign title is required and must be 180 characters or fewer.', 422);
if (!in_array($status, ['draft', 'active', 'paused', 'ended', 'archived'], true)) mg_fail('Invalid campaign status.', 422);
if ($startsAt !== null && $endsAt !== null && strtotime($startsAt) >= strtotime($endsAt)) {
    mg_fail('Campaign end date must be after the start date.', 422);
}
if ($description !== null && mb_strlen($description) > 5000) mg_fail('Campaign description is too long.', 422);
if ($formHeadline !== null && mb_strlen($formHeadline) > 255) mg_fail('Form headline is too long.', 422);
if ($formDescription !== null && mb_strlen($formDescription) > 2000) mg_fail('Form description is too long.', 422);
if ($successMessage !== null && mb_strlen($successMessage) > 500) mg_fail('Success message is too long.', 422);

$rewardTemplateId = null;
$reward = null;
if ($rewardPublicId !== '') {
    if (preg_match('/^[a-f0-9-]{36}$/', $rewardPublicId) !== 1) mg_fail('Invalid reward template.', 422);
    $rewardStmt = $pdo->prepare("SELECT id,public_id,title,status FROM reward_templates WHERE public_id=? AND merchant_user_id=? AND status<>'archived' LIMIT 1");
    $rewardStmt->execute([$rewardPublicId, $merchantId]);
    $reward = $rewardStmt->fetch(PDO::FETCH_ASSOC);
    if (!$reward) mg_fail('Reward template not found.', 404);
    if ($status === 'active' && (string)$reward['status'] !== 'active') {
        mg_fail('Active Customer Review campaigns require an active reward template.', 422);
    }
    $rewardTemplateId = (int)$reward['id'];
}
if ($status === 'active' && $rewardTemplateId === null) {
    mg_fail('Choose an active reward template before activating this Customer Review campaign.', 422);
}

if ($status === 'active') {
    $usageStmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND status='active' AND public_id<>?");
    $usageStmt->execute([$merchantId, $campaignId]);
    mg_package_require_limit_available(
        $pdo,
        $user,
        'max_active_campaigns',
        (int)$usageStmt->fetchColumn(),
        'Active campaign limit reached.'
    );
}

$rules = [
    'campaign_type' => 'customer_review',
    'version' => 1,
    'registry' => 'customer_review_profile_module_v1',
    'mode' => 'profile_customer_review',
    'prompt' => mb_substr($prompt !== '' ? $prompt : 'Tell us about your experience.', 0, 500),
    'max_reviews_per_period' => $maxReviews,
    'limit_period' => $period,
    'rating_scale' => 5,
    'written_review_required' => true,
    'entry_reward_enabled' => true,
    'reward_destination' => 'wallet_pppm_inbox',
];
$rulesJson = json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

try {
    $pdo->beginTransaction();
    $isNew = $campaignId === '';
    if ($isNew) {
        $campaignId = mg_customer_review_campaign_uuid();
        $stmt = $pdo->prepare(
            "INSERT INTO campaigns
            (public_id,merchant_user_id,reward_template_id,campaign_type,title,description,form_headline,form_description,success_message,status,starts_at,ends_at,quantity_limit,per_user_limit,agent_discoverable,public_slug,qr_code_token,rules_json,created_at,updated_at)
            VALUES (?,?,?,'customer_review',?,?,?,?,?,?,?,?,?,?,?,0,NULL,NULL,?,NOW(),NOW())"
        );
        $stmt->execute([
            $campaignId,
            $merchantId,
            $rewardTemplateId,
            $title,
            $description,
            $formHeadline,
            $formDescription,
            $successMessage,
            $status,
            $startsAt,
            $endsAt,
            $quantityLimit,
            $maxReviews,
            $rulesJson,
        ]);
        $dbId = (int)$pdo->lastInsertId();
        $message = 'Customer Review campaign created.';
    } else {
        $lookup = $pdo->prepare("SELECT id,campaign_type FROM campaigns WHERE public_id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE");
        $lookup->execute([$campaignId, $merchantId]);
        $existing = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$existing) mg_fail('Campaign not found.', 404);
        if ((string)$existing['campaign_type'] !== 'customer_review') {
            mg_fail('This campaign is not a Customer Review campaign.', 409);
        }
        $dbId = (int)$existing['id'];
        $stmt = $pdo->prepare(
            "UPDATE campaigns SET reward_template_id=?,title=?,description=?,form_headline=?,form_description=?,success_message=?,status=?,starts_at=?,ends_at=?,quantity_limit=?,per_user_limit=?,agent_discoverable=0,public_slug=NULL,qr_code_token=NULL,rules_json=?,updated_at=NOW()
             WHERE id=? AND merchant_user_id=?"
        );
        $stmt->execute([
            $rewardTemplateId,
            $title,
            $description,
            $formHeadline,
            $formDescription,
            $successMessage,
            $status,
            $startsAt,
            $endsAt,
            $quantityLimit,
            $maxReviews,
            $rulesJson,
            $dbId,
            $merchantId,
        ]);
        $message = 'Customer Review campaign updated.';
    }

    $pdo->commit();

    mg_audit('merchant.customer_review_campaign_saved', 'campaign', [
        'campaign_id' => $campaignId,
        'campaign_type' => 'customer_review',
        'status' => $status,
        'reward_template_id' => $rewardPublicId ?: null,
        'max_reviews_per_period' => $maxReviews,
        'limit_period' => $period,
    ], $merchantId);

    mg_ok([
        'campaign' => [
            'id' => $campaignId,
            'campaign_type' => 'customer_review',
            'campaign_type_label' => 'CUSTOMER REVIEW',
            'title' => $title,
            'description' => $description,
            'form_headline' => $formHeadline,
            'form_description' => $formDescription,
            'success_message' => $successMessage,
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'quantity_limit' => $quantityLimit,
            'per_user_limit' => $maxReviews,
            'reward_template_id' => $rewardPublicId ?: null,
            'reward_template_title' => $reward['title'] ?? null,
            'rules' => $rules,
            'public_url' => null,
        ],
        'schema_ready' => true,
    ], $message, 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.customer_review_campaign_save_failed', 'Unable to save Customer Review campaign.', [
        'exception_class' => $error::class,
        'message' => $error->getMessage(),
    ], $merchantId);
    mg_fail('Unable to save Customer Review campaign.', 500);
}
