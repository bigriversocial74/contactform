<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/includes/creator-campaigns.php';

function ccp3_mysql_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function ccp3_mysql_uuid(): string
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
        . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
}

function ccp3_mysql_user(PDO $pdo, string $suffix, string $name): int
{
    $stmt = $pdo->prepare(
        "INSERT INTO users(email,password_hash,full_name,display_name,status,created_at,updated_at)
         VALUES(?,?,?,?,'active',NOW(),NOW())"
    );
    $stmt->execute([
        "ccp3-{$suffix}@example.test",
        password_hash('CreatorParticipation!42', PASSWORD_DEFAULT),
        $name,
        $name,
    ]);
    return (int) $pdo->lastInsertId();
}

function ccp3_mysql_creator(PDO $pdo, string $suffix, string $name): array
{
    $userId = ccp3_mysql_user($pdo, $suffix, $name);
    $modelId = (int) $pdo->query("SELECT id FROM user_models WHERE code='creator' LIMIT 1")->fetchColumn();
    ccp3_mysql_assert($modelId > 0, 'Creator user model missing.');
    $pdo->prepare(
        "INSERT INTO user_model_assignments
         (public_id,user_id,user_model_id,status,requested_at,enabled_at,approved_at,reason,created_at,updated_at)
         VALUES(?,?,?,'active',NOW(),NOW(),NOW(),'Phase 3 MySQL lifecycle',NOW(),NOW())"
    )->execute([ccp3_mysql_uuid(), $userId, $modelId]);
    $profilePublicId = 'cp_' . bin2hex(random_bytes(12));
    $pdo->prepare(
        "INSERT INTO creator_profiles
         (public_id,user_id,display_name,slug,bio,status,metadata_json,created_at,updated_at)
         VALUES(?,?,?,?,?,'active',JSON_OBJECT('validation',true),NOW(),NOW())"
    )->execute([$profilePublicId, $userId, $name, "ccp3-{$suffix}", 'Creator participation lifecycle profile.']);
    return [
        'id' => $userId,
        'roles' => ['admin'],
        'permissions' => [],
        'creator_profile_public_id' => $profilePublicId,
    ];
}

$pdo = mg_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$suffix = substr(bin2hex(random_bytes(8)), 0, 12);

$requiredTables = [
    'creator_campaign_applications',
    'creator_campaign_application_answers',
    'creator_campaign_invitations',
    'creator_campaign_participants',
    'creator_campaign_participation_events',
];
foreach ($requiredTables as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]);
    ccp3_mysql_assert((int) $stmt->fetchColumn() === 1, "Missing Phase 3 table: {$table}");
}

$merchantId = ccp3_mysql_user($pdo, "merchant-{$suffix}", 'CCP3 Merchant');
$merchantUser = ['id' => $merchantId, 'roles' => ['admin'], 'permissions' => []];
$pdo->prepare(
    "INSERT INTO merchant_workspaces
     (public_id,merchant_user_id,display_name,default_currency,timezone,status,eligibility_status,onboarding_percent,created_at,updated_at)
     VALUES(?,?,?,'USD','UTC','active','eligible',100,NOW(),NOW())"
)->execute([ccp3_mysql_uuid(), $merchantId, 'CCP3 Merchant Workspace']);

$creatorOne = ccp3_mysql_creator($pdo, "creator-one-{$suffix}", 'CCP3 Creator One');
$creatorTwo = ccp3_mysql_creator($pdo, "creator-two-{$suffix}", 'CCP3 Creator Two');
$creatorThree = ccp3_mysql_creator($pdo, "creator-three-{$suffix}", 'CCP3 Creator Three');

$created = mg_creator_campaign_create_draft($pdo, $merchantUser, [
    'idempotency_key' => "ccp3-campaign-{$suffix}",
    'internal_reference' => "CCP3-{$suffix}",
    'title' => 'Creator Participation Lifecycle',
    'description' => 'Human-reviewed creator participation validation campaign.',
    'objective' => 'Content creation',
    'category' => 'Hospitality',
    'access_mode' => 'hybrid',
    'timezone' => 'UTC',
]);
$campaign = $created['campaign'];
$campaignId = (int) $campaign['id'];
$campaignPublicId = (string) $campaign['public_id'];
$deadline = gmdate('Y-m-d H:i:s', time() + 86400 * 7);
$starts = gmdate('Y-m-d H:i:s', time() + 86400 * 8);
$ends = gmdate('Y-m-d H:i:s', time() + 86400 * 30);
$pdo->prepare(
    "UPDATE creator_campaigns
     SET status='scheduled',access_mode='hybrid',maximum_approved_creators=2,maximum_applications=10,
         application_deadline_at=?,starts_at=?,ends_at=?,automatic_acceptance=1,updated_at=NOW()
     WHERE id=?"
)->execute([$deadline, $starts, $ends, $campaignId]);

$application = mg_creator_campaign_application_save_creator(
    $pdo,
    $creatorOne,
    $campaignPublicId,
    [
        'cover_note' => 'A strong local Creator fit.',
        'portfolio_url' => 'https://example.test/portfolio',
        'answers' => [],
    ],
    true
);
ccp3_mysql_assert($application['status'] === 'submitted', 'Application submission was not held for human review.');
$stmt = $pdo->prepare('SELECT COUNT(*) FROM creator_campaign_participants WHERE campaign_id=? AND creator_user_id=?');
$stmt->execute([$campaignId, (int) $creatorOne['id']]);
ccp3_mysql_assert((int) $stmt->fetchColumn() === 0, 'Automatic acceptance created a participant before human approval.');

$reviewed = mg_creator_campaign_application_review_merchant(
    $pdo,
    $merchantUser,
    (string) $application['public_id'],
    'approve',
    [
        'expected_lock_version' => (int) $application['lock_version'],
        'reason' => 'Human merchant approval for lifecycle validation.',
        'internal_note' => 'Approved by the MySQL lifecycle fixture.',
        'idempotency_key' => "ccp3-approve-{$suffix}",
    ]
);
ccp3_mysql_assert($reviewed['status'] === 'approved', 'Human merchant approval failed.');
ccp3_mysql_assert(($reviewed['participant']['status'] ?? null) === 'agreement_pending', 'Approved participant did not stop at agreement_pending.');

$activationBlocked = false;
try {
    mg_creator_campaign_participant_transition_merchant(
        $pdo,
        $merchantUser,
        (string) $reviewed['participant']['public_id'],
        'active',
        [
            'expected_lock_version' => (int) $reviewed['participant']['lock_version'],
            'reason' => 'This must be blocked before Phase 4.',
            'idempotency_key' => "ccp3-active-block-{$suffix}",
        ]
    );
} catch (DomainException) {
    $activationBlocked = true;
}
ccp3_mysql_assert($activationBlocked, 'Participant activation was not blocked before Phase 4.');

$invitation = mg_creator_campaign_invitation_create_merchant(
    $pdo,
    $merchantUser,
    $campaignPublicId,
    [
        'creator_profile_id' => (string) $creatorTwo['creator_profile_public_id'],
        'invitation_message' => 'Join the lifecycle validation campaign.',
        'response_deadline_at' => gmdate('Y-m-d H:i:s', time() + 86400 * 3),
        'idempotency_key' => "ccp3-invite-two-{$suffix}",
    ]
);
$invitationReplay = mg_creator_campaign_invitation_create_merchant(
    $pdo,
    $merchantUser,
    $campaignPublicId,
    [
        'creator_profile_id' => (string) $creatorTwo['creator_profile_public_id'],
        'invitation_message' => 'Join the lifecycle validation campaign.',
        'response_deadline_at' => gmdate('Y-m-d H:i:s', time() + 86400 * 3),
        'idempotency_key' => "ccp3-invite-two-{$suffix}",
    ]
);
ccp3_mysql_assert($invitationReplay['public_id'] === $invitation['public_id'], 'Invitation idempotency failed.');

$accepted = mg_creator_campaign_invitation_respond_creator(
    $pdo,
    $creatorTwo,
    (string) $invitation['public_id'],
    'accepted',
    [
        'expected_lock_version' => (int) $invitation['lock_version'],
        'reason' => 'Creator accepted the merchant invitation.',
        'idempotency_key' => "ccp3-accept-two-{$suffix}",
    ]
);
ccp3_mysql_assert(($accepted['participant']['status'] ?? null) === 'agreement_pending', 'Invitation acceptance did not stop at agreement_pending.');

$capacityBlocked = false;
try {
    mg_creator_campaign_invitation_create_merchant(
        $pdo,
        $merchantUser,
        $campaignPublicId,
        [
            'creator_profile_id' => (string) $creatorThree['creator_profile_public_id'],
            'invitation_message' => 'Capacity should block this invitation.',
            'response_deadline_at' => gmdate('Y-m-d H:i:s', time() + 86400 * 3),
            'idempotency_key' => "ccp3-invite-three-{$suffix}",
        ]
    );
} catch (DomainException) {
    $capacityBlocked = true;
}
ccp3_mysql_assert($capacityBlocked, 'Campaign participant capacity was not enforced.');

$eventCount = (int) $pdo->query('SELECT COUNT(*) FROM creator_campaign_participation_events WHERE campaign_id=' . $campaignId)->fetchColumn();
ccp3_mysql_assert($eventCount >= 4, 'Participation lifecycle events were not recorded.');

$result = [
    'ok' => true,
    'campaign_id' => $campaignPublicId,
    'application_status' => $reviewed['status'],
    'application_participant_status' => $reviewed['participant']['status'],
    'invitation_status' => $accepted['status'],
    'invitation_participant_status' => $accepted['participant']['status'],
    'automatic_acceptance_ignored' => true,
    'activation_blocked_until_phase4' => $activationBlocked,
    'capacity_enforced' => $capacityBlocked,
    'event_count' => $eventCount,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
