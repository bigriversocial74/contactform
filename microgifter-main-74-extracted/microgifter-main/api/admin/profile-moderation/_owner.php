<?php
declare(strict_types=1);

require_once __DIR__ . '/_base.php';

function mg_profile_moderation_owner_status(PDO $pdo, int $userId): array
{
    $profile = mg_profile_ensure_for_user($userId);
    $stmt = $pdo->prepare(
        "SELECT c.public_id,c.category,c.priority,c.status,c.summary,c.opened_at,c.updated_at,
                (SELECT ma.action_type FROM profile_moderation_actions ma WHERE ma.case_id=c.id AND ma.action_type IN ('warn','hide','suspend','restore','appeal_accept','appeal_deny') ORDER BY ma.created_at DESC,ma.id DESC LIMIT 1) AS latest_action,
                (SELECT ma.reason_code FROM profile_moderation_actions ma WHERE ma.case_id=c.id AND ma.action_type IN ('warn','hide','suspend','restore','appeal_accept','appeal_deny') ORDER BY ma.created_at DESC,ma.id DESC LIMIT 1) AS latest_reason_code,
                (SELECT ma.reason_text FROM profile_moderation_actions ma WHERE ma.case_id=c.id AND ma.action_type IN ('warn','hide','suspend','restore','appeal_accept','appeal_deny') ORDER BY ma.created_at DESC,ma.id DESC LIMIT 1) AS latest_reason,
                (SELECT pa.public_id FROM profile_moderation_appeals pa WHERE pa.case_id=c.id ORDER BY pa.submitted_at DESC,pa.id DESC LIMIT 1) AS appeal_public_id,
                (SELECT pa.status FROM profile_moderation_appeals pa WHERE pa.case_id=c.id ORDER BY pa.submitted_at DESC,pa.id DESC LIMIT 1) AS appeal_status,
                (SELECT pa.statement FROM profile_moderation_appeals pa WHERE pa.case_id=c.id ORDER BY pa.submitted_at DESC,pa.id DESC LIMIT 1) AS appeal_statement,
                (SELECT pa.decision_reason FROM profile_moderation_appeals pa WHERE pa.case_id=c.id ORDER BY pa.submitted_at DESC,pa.id DESC LIMIT 1) AS appeal_decision_reason
         FROM profile_moderation_cases c
         WHERE c.profile_id=? AND c.status IN ('actioned','appealed','resolved')
         ORDER BY c.updated_at DESC,c.id DESC LIMIT 1"
    );
    $stmt->execute([(int)$profile['id']]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$case) {
        return ['profile_status' => (string)$profile['status'], 'case' => null, 'appeal' => null, 'can_appeal' => false];
    }

    $restricted = in_array((string)$profile['status'], ['hidden', 'suspended'], true);
    $hasAppeal = $case['appeal_public_id'] !== null;
    return [
        'profile_status' => (string)$profile['status'],
        'case' => [
            'id' => (string)$case['public_id'],
            'category' => (string)$case['category'],
            'priority' => (string)$case['priority'],
            'status' => (string)$case['status'],
            'summary' => (string)$case['summary'],
            'latest_action' => $case['latest_action'] !== null ? (string)$case['latest_action'] : null,
            'reason_code' => $case['latest_reason_code'] !== null ? (string)$case['latest_reason_code'] : null,
            'reason' => $case['latest_reason'] !== null ? (string)$case['latest_reason'] : null,
            'opened_at' => (string)$case['opened_at'],
            'updated_at' => (string)$case['updated_at'],
        ],
        'appeal' => $hasAppeal ? [
            'id' => (string)$case['appeal_public_id'],
            'status' => (string)$case['appeal_status'],
            'statement' => (string)$case['appeal_statement'],
            'decision_reason' => $case['appeal_decision_reason'] !== null ? (string)$case['appeal_decision_reason'] : null,
        ] : null,
        'can_appeal' => $restricted && !$hasAppeal && in_array((string)$case['latest_action'], ['hide', 'suspend'], true),
    ];
}

function mg_profile_moderation_submit_appeal(PDO $pdo, int $userId, array $input): array
{
    $statement = mg_profile_moderation_text($input['statement'] ?? '', 5000, true);
    if (mb_strlen($statement) < 20) throw new InvalidArgumentException('Appeal statement must be at least 20 characters.');
    $profile = mg_profile_ensure_for_user($userId);
    if (!in_array((string)$profile['status'], ['hidden', 'suspended'], true)) throw new DomainException('This profile is not currently eligible for appeal.');

    $pdo->beginTransaction();
    try {
        $caseReference = trim((string)($input['case_id'] ?? ''));
        if ($caseReference !== '') {
            $caseStmt = $pdo->prepare("SELECT * FROM profile_moderation_cases WHERE public_id=? AND profile_id=? AND status IN ('actioned','resolved') LIMIT 1 FOR UPDATE");
            $caseStmt->execute([$caseReference, (int)$profile['id']]);
        } else {
            $caseStmt = $pdo->prepare("SELECT * FROM profile_moderation_cases WHERE profile_id=? AND status IN ('actioned','resolved') ORDER BY updated_at DESC,id DESC LIMIT 1 FOR UPDATE");
            $caseStmt->execute([(int)$profile['id']]);
        }
        $case = $caseStmt->fetch(PDO::FETCH_ASSOC);
        if (!$case) throw new DomainException('No appealable moderation case was found.');

        $restricting = $pdo->prepare("SELECT action_type FROM profile_moderation_actions WHERE case_id=? AND action_type IN ('hide','suspend') ORDER BY created_at DESC,id DESC LIMIT 1");
        $restricting->execute([(int)$case['id']]);
        if (!$restricting->fetchColumn()) throw new DomainException('This case does not contain an appealable profile restriction.');

        $existing = $pdo->prepare('SELECT public_id FROM profile_moderation_appeals WHERE case_id=? LIMIT 1 FOR UPDATE');
        $existing->execute([(int)$case['id']]);
        if ($existing->fetchColumn()) throw new DomainException('An appeal has already been submitted for this case.');

        $appealPublicId = mg_profile_moderation_public_id('pmp');
        $insert = $pdo->prepare(
            "INSERT INTO profile_moderation_appeals
             (public_id,case_id,profile_id,appellant_user_id,status,statement,submitted_at,created_at,updated_at)
             VALUES (?,?,?,?,'submitted',?,NOW(),NOW(),NOW())"
        );
        $insert->execute([$appealPublicId, (int)$case['id'], (int)$profile['id'], $userId, $statement]);

        $caseUpdate = $pdo->prepare("UPDATE profile_moderation_cases SET status='appealed',updated_at=NOW() WHERE id=?");
        $caseUpdate->execute([(int)$case['id']]);

        $action = $pdo->prepare(
            "INSERT INTO profile_moderation_actions
             (public_id,case_id,profile_id,actor_user_id,actor_type,action_type,reason_code,reason_text,previous_profile_status,resulting_profile_status,created_at)
             VALUES (?,?,?,?, 'owner','appeal_submitted','other',?,?,?,NOW())"
        );
        $action->execute([
            mg_profile_moderation_public_id('pma'), (int)$case['id'], (int)$profile['id'], $userId,
            $statement, (string)$profile['status'], (string)$profile['status'],
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    mg_audit('profile.moderation.appeal_submitted', 'public_profile', [
        'case_id' => (string)$case['public_id'],
        'appeal_id' => $appealPublicId,
        'profile_id' => (string)$profile['public_id'],
    ], $userId);
    mg_event('profile.moderation.appeal_submitted', ['case_id' => (string)$case['public_id'], 'appeal_id' => $appealPublicId], $userId);
    return mg_profile_moderation_owner_status($pdo, $userId);
}
