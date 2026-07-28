<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_intelligence.php';

$user = mg_require_api_user();
$merchantId = (int)$user['id'];
$pdo = mg_db();
if (!mg_homeserver_operational_tables_ready($pdo)) mg_fail('HomeServer campaign authority schema is not installed.', 503);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = $method === 'GET' ? $_GET : mg_input();
$devicePublicId = strtolower(trim((string)($input['device_id'] ?? '')));
if (!mg_homeserver_is_uuid($devicePublicId)) mg_fail('HomeServer device identity is invalid.', 422);

$deviceStmt = $pdo->prepare('SELECT * FROM homeserver_devices WHERE public_id=? AND owner_user_id=? LIMIT 1');
$deviceStmt->execute([$devicePublicId, $merchantId]);
$device = $deviceStmt->fetch(PDO::FETCH_ASSOC);
if (!$device) mg_fail('HomeServer device not found.', 404);

$allowedCampaignTypes = ['customer_refund','referral_reward','newsletter_signup','contest_giveaway','qr_reward_drop','birthday_vip','agent_offer','customer_review'];
$allowedAuthorityLevels = ['analyze','draft','approval_required','authorized_execution'];
$allowedChannels = ['microgifter_inbox','email'];

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM homeserver_campaign_authorizations WHERE device_id=? AND merchant_user_id=? ORDER BY campaign_type');
    $stmt->execute([(int)$device['id'], $merchantId]);
    $authorizations = array_map(static function (array $row): array {
        return [
            'authorization_id' => (string)$row['public_id'],
            'campaign_type' => (string)$row['campaign_type'],
            'authorization_state' => (string)$row['authorization_state'],
            'authority_level' => (string)$row['authority_level'],
            'allowed_campaign_ids' => json_decode((string)($row['allowed_campaign_ids_json'] ?? 'null'), true),
            'allowed_product_ids' => json_decode((string)($row['allowed_product_ids_json'] ?? 'null'), true),
            'allowed_channels' => json_decode((string)$row['allowed_channels_json'], true) ?: [],
            'allowed_audience_rules' => json_decode((string)$row['allowed_audience_rules_json'], true) ?: [],
            'maximum_value_cents' => $row['maximum_value_cents'] === null ? null : (int)$row['maximum_value_cents'],
            'maximum_daily_value_cents' => $row['maximum_daily_value_cents'] === null ? null : (int)$row['maximum_daily_value_cents'],
            'maximum_total_value_cents' => $row['maximum_total_value_cents'] === null ? null : (int)$row['maximum_total_value_cents'],
            'maximum_recipients' => $row['maximum_recipients'] === null ? null : (int)$row['maximum_recipients'],
            'approval_threshold_cents' => $row['approval_threshold_cents'] === null ? null : (int)$row['approval_threshold_cents'],
            'duplicate_window_days' => (int)$row['duplicate_window_days'],
            'require_consent' => (bool)$row['require_consent'],
            'require_evidence' => (bool)$row['require_evidence'],
            'allowed_send_start' => $row['allowed_send_start'],
            'allowed_send_end' => $row['allowed_send_end'],
            'timezone_name' => (string)$row['timezone_name'],
            'approved_at' => $row['approved_at'],
            'expires_at' => $row['expires_at'],
            'policy_hash' => (string)$row['policy_hash'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    mg_ok([
        'device' => mg_homeserver_device_payload($device),
        'campaign_types' => $allowedCampaignTypes,
        'authority_levels' => $allowedAuthorityLevels,
        'channels' => $allowedChannels,
        'authorizations' => $authorizations,
    ]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
mg_require_csrf_for_write($input);

$campaignType = strtolower(trim((string)($input['campaign_type'] ?? '')));
$state = strtolower(trim((string)($input['authorization_state'] ?? 'enabled')));
$authorityLevel = strtolower(trim((string)($input['authority_level'] ?? 'draft')));
if (!in_array($campaignType, $allowedCampaignTypes, true)) mg_fail('Unsupported campaign type.', 422);
if (!in_array($state, ['enabled','paused','revoked'], true)) mg_fail('Campaign authorization state is invalid.', 422);
if (!in_array($authorityLevel, $allowedAuthorityLevels, true)) mg_fail('Campaign authority level is invalid.', 422);

$uuidList = static function ($value, string $label): ?array {
    if ($value === null || $value === '') return null;
    if (!is_array($value)) mg_fail($label . ' must be a list.', 422);
    $result = [];
    foreach ($value as $item) {
        $item = strtolower(trim((string)$item));
        if (!mg_homeserver_is_uuid($item)) mg_fail($label . ' contains an invalid identity.', 422);
        $result[] = $item;
    }
    return array_values(array_unique($result));
};
$campaignIds = $uuidList($input['allowed_campaign_ids'] ?? null, 'Allowed campaigns');
$productIds = $uuidList($input['allowed_product_ids'] ?? null, 'Allowed products');
$channels = is_array($input['allowed_channels'] ?? null) ? array_values(array_unique(array_map(static fn($value): string => strtolower(trim((string)$value)), $input['allowed_channels']))) : ['microgifter_inbox'];
if ($channels === [] || array_diff($channels, $allowedChannels)) mg_fail('One or more campaign channels are not supported.', 422);
$audienceRules = is_array($input['allowed_audience_rules'] ?? null) ? $input['allowed_audience_rules'] : ['merchant_owned_contacts' => true];

$nullableMoney = static function ($value): ?int {
    if ($value === null || $value === '') return null;
    return max(0, min(100000000, (int)$value));
};
$maximumValue = $nullableMoney($input['maximum_value_cents'] ?? null);
$maximumDaily = $nullableMoney($input['maximum_daily_value_cents'] ?? null);
$maximumTotal = $nullableMoney($input['maximum_total_value_cents'] ?? null);
$approvalThreshold = $nullableMoney($input['approval_threshold_cents'] ?? null);
$maximumRecipients = isset($input['maximum_recipients']) && $input['maximum_recipients'] !== '' ? max(1, min(100000, (int)$input['maximum_recipients'])) : null;
$duplicateWindowDays = max(0, min(3650, (int)($input['duplicate_window_days'] ?? 90)));
$requireConsent = !array_key_exists('require_consent', $input) || !empty($input['require_consent']);
$requireEvidence = !array_key_exists('require_evidence', $input) || !empty($input['require_evidence']);
$timezone = trim((string)($input['timezone_name'] ?? 'America/Phoenix'));
try { new DateTimeZone($timezone); } catch (Throwable) { mg_fail('Campaign authorization timezone is invalid.', 422); }
$validateTime = static function ($value): ?string {
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) !== 1) mg_fail('Campaign send time is invalid.', 422);
    return strlen($value) === 5 ? $value . ':00' : $value;
};
$sendStart = $validateTime($input['allowed_send_start'] ?? null);
$sendEnd = $validateTime($input['allowed_send_end'] ?? null);
$expiresAt = trim((string)($input['expires_at'] ?? '')) ?: null;
if ($expiresAt !== null && strtotime($expiresAt) === false) mg_fail('Campaign authorization expiration is invalid.', 422);

$policy = [
    'device_id' => $devicePublicId,
    'merchant_user_id' => $merchantId,
    'campaign_type' => $campaignType,
    'authorization_state' => $state,
    'authority_level' => $authorityLevel,
    'allowed_campaign_ids' => $campaignIds,
    'allowed_product_ids' => $productIds,
    'allowed_channels' => $channels,
    'allowed_audience_rules' => $audienceRules,
    'maximum_value_cents' => $maximumValue,
    'maximum_daily_value_cents' => $maximumDaily,
    'maximum_total_value_cents' => $maximumTotal,
    'maximum_recipients' => $maximumRecipients,
    'approval_threshold_cents' => $approvalThreshold,
    'duplicate_window_days' => $duplicateWindowDays,
    'require_consent' => $requireConsent,
    'require_evidence' => $requireEvidence,
    'allowed_send_start' => $sendStart,
    'allowed_send_end' => $sendEnd,
    'timezone_name' => $timezone,
    'expires_at' => $expiresAt,
];
$policyHash = hash('sha256', mg_homeserver_json($policy));

$stmt = $pdo->prepare("INSERT INTO homeserver_campaign_authorizations
    (public_id,device_id,merchant_user_id,campaign_type,authorization_state,authority_level,allowed_campaign_ids_json,allowed_product_ids_json,allowed_channels_json,allowed_audience_rules_json,maximum_value_cents,maximum_daily_value_cents,maximum_total_value_cents,maximum_recipients,approval_threshold_cents,duplicate_window_days,require_consent,require_evidence,allowed_send_start,allowed_send_end,timezone_name,approved_by_user_id,approved_at,expires_at,revoked_at,policy_hash,created_at,updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())
    ON DUPLICATE KEY UPDATE
      authorization_state=VALUES(authorization_state),authority_level=VALUES(authority_level),allowed_campaign_ids_json=VALUES(allowed_campaign_ids_json),allowed_product_ids_json=VALUES(allowed_product_ids_json),allowed_channels_json=VALUES(allowed_channels_json),allowed_audience_rules_json=VALUES(allowed_audience_rules_json),maximum_value_cents=VALUES(maximum_value_cents),maximum_daily_value_cents=VALUES(maximum_daily_value_cents),maximum_total_value_cents=VALUES(maximum_total_value_cents),maximum_recipients=VALUES(maximum_recipients),approval_threshold_cents=VALUES(approval_threshold_cents),duplicate_window_days=VALUES(duplicate_window_days),require_consent=VALUES(require_consent),require_evidence=VALUES(require_evidence),allowed_send_start=VALUES(allowed_send_start),allowed_send_end=VALUES(allowed_send_end),timezone_name=VALUES(timezone_name),approved_by_user_id=VALUES(approved_by_user_id),approved_at=UTC_TIMESTAMP(),expires_at=VALUES(expires_at),revoked_at=VALUES(revoked_at),policy_hash=VALUES(policy_hash),updated_at=UTC_TIMESTAMP()");
$stmt->execute([
    mg_homeserver_public_uuid(), (int)$device['id'], $merchantId, $campaignType, $state, $authorityLevel,
    $campaignIds === null ? null : mg_homeserver_json($campaignIds),
    $productIds === null ? null : mg_homeserver_json($productIds),
    mg_homeserver_json($channels), mg_homeserver_json($audienceRules),
    $maximumValue, $maximumDaily, $maximumTotal, $maximumRecipients, $approvalThreshold,
    $duplicateWindowDays, $requireConsent ? 1 : 0, $requireEvidence ? 1 : 0,
    $sendStart, $sendEnd, $timezone, $merchantId,
    $expiresAt === null ? null : gmdate('Y-m-d H:i:s', strtotime($expiresAt)),
    $state === 'revoked' ? gmdate('Y-m-d H:i:s') : null,
    $policyHash,
]);

mg_security_log('info', 'homeserver.campaign_authorization.updated', 'HomeServer campaign authorization updated.', $policy + ['policy_hash' => $policyHash], $merchantId);
mg_ok($policy + ['policy_hash' => $policyHash], 'HomeServer campaign authorization saved.');
