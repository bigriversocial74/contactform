<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$root = rtrim((string) ($argv[1] ?? dirname(__DIR__)), '/');
$score = 0;
$checks = [];

function ccp3_source(string $root, string $path): string
{
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        throw new RuntimeException('Missing required file: ' . $path);
    }
    return $content;
}

function ccp3_check(bool $ok, string $label, int $points = 10): void
{
    global $score, $checks;
    $checks[] = ['label' => $label, 'ok' => $ok, 'points' => $ok ? $points : 0];
    if ($ok) $score += $points;
}

$required = [
    'api/creator/campaigns.php',
    'api/merchant/creator-campaign-participation.php',
    'assets/css/creator-campaign-participation.css',
    'assets/js/creator-campaign-participation.js',
    'assets/js/merchant-creator-campaign-participation.js',
    'creator-campaigns.php',
    'merchant-creator-participation.php',
    'database/20260721_creator_campaign_participation_v3.sql',
    'includes/creator-campaigns/participation-definitions.php',
    'includes/creator-campaigns/participation-context.php',
    'includes/creator-campaigns/participation-repository.php',
    'includes/creator-campaigns/application-service.php',
    'includes/creator-campaigns/invitation-service.php',
    'includes/creator-campaigns/participant-service.php',
    'includes/creator-campaigns/participation-query.php',
];
ccp3_check(array_reduce($required, static fn(bool $carry, string $path): bool => $carry && is_file($root . '/' . $path), true), 'Complete Phase 3 file set');

$sql = ccp3_source($root, 'database/20260721_creator_campaign_participation_v3.sql');
ccp3_check(
    str_contains($sql, 'creator_campaign_applications')
    && str_contains($sql, 'creator_campaign_application_answers')
    && str_contains($sql, 'creator_campaign_invitations')
    && str_contains($sql, 'creator_campaign_participants')
    && str_contains($sql, 'creator_campaign_participation_events'),
    'Normalized participation schema'
);
ccp3_check(
    str_contains($sql, 'SET automatic_acceptance=0')
    && !str_contains($sql, 'creator_campaign_agreement_versions')
    && !str_contains($sql, 'creator_campaign_deliverables')
    && !str_contains($sql, 'creator_campaign_payouts'),
    'Human approval and later-phase schema boundary'
);

$application = ccp3_source($root, 'includes/creator-campaigns/application-creator.php')
    . ccp3_source($root, 'includes/creator-campaigns/application-merchant.php');
ccp3_check(
    str_contains($application, "human_approval_required")
    && str_contains($application, "'submitted'")
    && str_contains($application, "'approve' => 'approved'")
    && str_contains($application, 'participant_upsert_pending'),
    'Human-reviewed application lifecycle'
);

$invitation = ccp3_source($root, 'includes/creator-campaigns/invitation-merchant.php')
    . ccp3_source($root, 'includes/creator-campaigns/invitation-creator.php')
    . ccp3_source($root, 'includes/creator-campaigns/invitation-directory.php');
ccp3_check(
    str_contains($invitation, 'idempotency_hash')
    && str_contains($invitation, 'response_deadline_at')
    && str_contains($invitation, 'invitation.expired')
    && str_contains($invitation, 'participant_upsert_pending'),
    'Invitation, expiration, and acceptance lifecycle'
);

$participant = ccp3_source($root, 'includes/creator-campaigns/participant-service.php');
ccp3_check(
    str_contains($participant, 'agreement_pending')
    && str_contains($participant, 'FOR UPDATE')
    && str_contains($participant, 'maximum_approved_creators')
    && str_contains($participant, 'Phase 4'),
    'Atomic participant capacity and agreement gate'
);

$context = ccp3_source($root, 'includes/creator-campaigns/participation-context.php')
    . ccp3_source($root, 'includes/creator-campaigns/participation-repository.php');
ccp3_check(
    str_contains($context, 'workspace_id')
    && str_contains($context, 'creator_user_id')
    && str_contains($context, 'creator_profile_id')
    && str_contains($context, 'expected_lock_version'),
    'Workspace, Creator ownership, and optimistic locking'
);

$creatorApi = ccp3_source($root, 'api/creator/campaigns.php');
$merchantApi = ccp3_source($root, 'api/merchant/creator-campaign-participation.php');
ccp3_check(
    str_contains($creatorApi, 'mg_require_csrf_for_write($input)')
    && str_contains($merchantApi, 'mg_require_csrf_for_write($input)')
    && str_contains($creatorApi, 'respond_invitation')
    && str_contains($merchantApi, 'review_application'),
    'Authenticated Creator and merchant APIs'
);

$creatorUi = ccp3_source($root, 'includes/creator-campaigns-participation-view.php')
    . ccp3_source($root, 'assets/js/creator-campaign-participation.js');
$merchantUi = ccp3_source($root, 'includes/merchant-creator-campaign-participation-view.php')
    . ccp3_source($root, 'assets/js/merchant-creator-campaign-participation.js');
ccp3_check(
    str_contains($creatorUi, 'Discover')
    && str_contains($creatorUi, 'submit_application')
    && str_contains($merchantUi, 'Creator Directory')
    && str_contains($merchantUi, 'review_application'),
    'Operational Creator and merchant workspaces'
);

$manifest = ccp3_source($root, 'config/migrations.php');
$workflow = ccp3_source($root, '.github/workflows/creator-campaign-participation-v3.yml');
ccp3_check(
    str_contains($manifest, '20260721_creator_campaign_participation_v3.sql')
    && str_contains($workflow, "php: ['8.2', '8.3']")
    && str_contains($workflow, 'validate_creator_campaign_participation_v3_mysql.php'),
    'Canonical migration and CI coverage'
);

$result = [
    'ok' => $score === 100,
    'score' => $score,
    'maximum' => 100,
    'checks' => $checks,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
if ($score !== 100) exit(1);
