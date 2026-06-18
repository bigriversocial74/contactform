<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/includes/profiles.php';
require_once dirname(__DIR__) . '/api/profiles/_public_profile.php';
require_once dirname(__DIR__) . '/api/admin/profile-moderation/_queue.php';
require_once dirname(__DIR__) . '/api/admin/profile-moderation/_actions.php';
require_once dirname(__DIR__) . '/api/admin/profile-moderation/_owner.php';
require_once dirname(__DIR__) . '/tests/integration/MicrogiftBehaviorFixture.php';

function mg_pm_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = mg_db();
$runId = 'profilemoderation' . bin2hex(random_bytes(5));
$ownerId = null;
$moderatorId = null;
$exitCode = 0;
$auditStart = (int)($pdo->query('SELECT COALESCE(MAX(id),0) FROM audit_logs')->fetchColumn() ?: 0);
$eventStart = (int)($pdo->query('SELECT COALESCE(MAX(id),0) FROM events')->fetchColumn() ?: 0);
$result = array_fill_keys([
    'case_opened', 'duplicate_blocked', 'queue_visible', 'case_detail_visible',
    'suspension_atomic', 'public_access_blocked', 'owner_cannot_escape',
    'appeal_submitted', 'appeal_accepted', 'history_durable', 'audit_durable', 'cleanup_complete',
], false);

try {
    $ownerId = mg_it_user($pdo, $runId . '-owner@example.test', 'Moderation Owner');
    $moderatorId = mg_it_user($pdo, $runId . '-moderator@example.test', 'Moderation Admin');
    $moderator = ['id' => $moderatorId, 'roles' => ['super_admin'], 'permissions' => []];

    $profile = mg_profile_update($ownerId, [
        'display_name' => 'Moderation Test Profile',
        'slug' => $runId,
        'headline' => 'Complete profile for moderation validation',
        'bio' => 'This profile verifies cases, restrictions, appeals, restoration, and durable history.',
        'location_label' => 'Phoenix, AZ',
        'website_url' => 'https://example.test/moderation',
        'profile_type' => 'creator',
        'visibility' => 'public',
        'status' => 'active',
    ]);
    mg_pm_assert((string)$profile['status'] === 'active', 'Profile was not active before moderation.');

    $opened = mg_profile_moderation_open_case($pdo, $moderator, [
        'profile_ref' => (string)$profile['slug'],
        'category' => 'impersonation',
        'priority' => 'high',
        'summary' => 'Identity authenticity requires review',
        'details' => 'Behavior validation case with structured evidence.',
        'evidence' => ['source' => 'behavior_test', 'reference' => $runId],
    ]);
    $caseId = (string)$opened['case']['id'];
    mg_pm_assert(str_starts_with($caseId, 'pmc_'), 'Moderation case was not created.');
    $result['case_opened'] = true;

    try {
        mg_profile_moderation_open_case($pdo, $moderator, [
            'profile_ref' => (string)$profile['public_id'],
            'category' => 'impersonation',
            'priority' => 'normal',
            'summary' => 'Duplicate active case',
        ]);
        throw new RuntimeException('Duplicate active moderation case was accepted.');
    } catch (DomainException $expected) {
        mg_pm_assert(str_contains($expected->getMessage(), 'active case'), 'Unexpected duplicate-case error.');
    }
    $result['duplicate_blocked'] = true;

    $queue = mg_profile_moderation_queue($pdo, $moderator, ['status' => 'active', 'category' => 'impersonation', 'q' => $runId]);
    mg_pm_assert(count($queue['cases']) === 1 && (string)$queue['cases'][0]['id'] === $caseId, 'Created case was not visible in the filtered queue.');
    mg_pm_assert((int)$queue['summary']['open'] >= 1, 'Queue summary did not include the open case.');
    $result['queue_visible'] = true;

    $detail = mg_profile_moderation_detail($pdo, $moderator, $caseId);
    mg_pm_assert((string)$detail['profile']['id'] === (string)$profile['public_id'], 'Case detail returned the wrong profile.');
    mg_pm_assert(($detail['case']['evidence']['reference'] ?? null) === $runId, 'Case evidence was not preserved.');
    $result['case_detail_visible'] = true;

    $suspended = mg_profile_moderation_apply_action($pdo, $moderator, [
        'case_id' => $caseId,
        'action' => 'suspend',
        'reason_code' => 'impersonation',
        'reason' => 'Profile suspended while identity authenticity is reviewed.',
    ]);
    mg_pm_assert((string)$suspended['profile']['status'] === 'suspended', 'Suspend action did not update the profile atomically.');
    mg_pm_assert((string)$suspended['case']['status'] === 'actioned', 'Suspend action did not update the case.');
    $result['suspension_atomic'] = true;

    $publicProfile = mg_public_profile_load($pdo, (string)$profile['slug']);
    try {
        mg_public_profile_assert_access($pdo, $publicProfile, null, false);
        throw new RuntimeException('Suspended profile remained publicly accessible.');
    } catch (RuntimeException $expected) {
        mg_pm_assert($expected->getMessage() === 'Profile not found.', 'Unexpected suspended public-access result.');
    }
    $result['public_access_blocked'] = true;

    $ownerAttempt = mg_profile_update($ownerId, ['status' => 'active']);
    mg_pm_assert((string)$ownerAttempt['status'] === 'suspended', 'Owner escaped moderation suspension through the profile editor authority.');
    $result['owner_cannot_escape'] = true;

    $ownerStatus = mg_profile_moderation_owner_status($pdo, $ownerId);
    mg_pm_assert($ownerStatus['can_appeal'] === true && (string)$ownerStatus['case']['id'] === $caseId, 'Owner did not receive an appealable moderation state.');
    $appealed = mg_profile_moderation_submit_appeal($pdo, $ownerId, [
        'case_id' => $caseId,
        'statement' => 'I have supplied corrected identity details and request another review of this profile restriction.',
    ]);
    mg_pm_assert((string)$appealed['appeal']['status'] === 'submitted' && $appealed['can_appeal'] === false, 'Appeal was not submitted exactly once.');
    $result['appeal_submitted'] = true;

    $accepted = mg_profile_moderation_apply_action($pdo, $moderator, [
        'case_id' => $caseId,
        'action' => 'appeal_accept',
        'reason_code' => 'appeal_upheld',
        'reason' => 'Owner remediation and supplied identity details satisfy the review.',
        'restore_status' => 'active',
    ]);
    mg_pm_assert((string)$accepted['profile']['status'] === 'active', 'Accepted appeal did not restore the profile.');
    mg_pm_assert((string)$accepted['case']['status'] === 'resolved', 'Accepted appeal did not resolve the case.');
    mg_pm_assert((string)$accepted['appeals'][0]['status'] === 'accepted', 'Appeal decision was not durable.');
    $result['appeal_accepted'] = true;

    $actionStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM profile_moderation_actions ma
         INNER JOIN profile_moderation_cases mc ON mc.id=ma.case_id
         WHERE mc.public_id=?'
    );
    $actionStmt->execute([$caseId]);
    mg_pm_assert((int)$actionStmt->fetchColumn() >= 4, 'Moderation action history was incomplete.');
    $result['history_durable'] = true;

    $auditStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE id>? AND action LIKE 'profile.moderation.%'");
    $auditStmt->execute([$auditStart]);
    $eventStmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE id>? AND event_type LIKE 'profile.moderation.%'");
    $eventStmt->execute([$eventStart]);
    mg_pm_assert((int)$auditStmt->fetchColumn() >= 3 && (int)$eventStmt->fetchColumn() >= 3, 'Moderation audit or event records were not written.');
    $result['audit_durable'] = true;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    try {
        $pdo->prepare('DELETE FROM audit_logs WHERE id>?')->execute([$auditStart]);
        $pdo->prepare('DELETE FROM events WHERE id>?')->execute([$eventStart]);
        if ($moderatorId !== null) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$moderatorId]);
        if ($ownerId !== null) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$ownerId]);
        $remaining = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id IN (?,?)');
        $remaining->execute([(int)$ownerId, (int)$moderatorId]);
        $result['cleanup_complete'] = (int)$remaining->fetchColumn() === 0;
    } catch (Throwable $cleanupError) {
        fwrite(STDERR, 'Cleanup failed: ' . $cleanupError->getMessage() . PHP_EOL);
        $exitCode = 1;
    }
}

if ($exitCode !== 0) exit(1);
echo json_encode($result + ['suite' => 'profile_moderation_ui_foundation'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
