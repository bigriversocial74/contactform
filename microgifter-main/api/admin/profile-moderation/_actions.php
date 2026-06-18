<?php
declare(strict_types=1);

require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/_case.php';

function mg_profile_moderation_previous_restore_status(PDO $pdo, int $caseId, array $profile): string
{
    $stmt = $pdo->prepare(
        "SELECT previous_profile_status
         FROM profile_moderation_actions
         WHERE case_id=? AND action_type IN ('hide','suspend') AND previous_profile_status IS NOT NULL
         ORDER BY created_at DESC,id DESC LIMIT 1"
    );
    $stmt->execute([$caseId]);
    $status = (string)($stmt->fetchColumn() ?: 'draft');
    if (!in_array($status, ['active', 'draft', 'hidden'], true)) $status = 'draft';
    if ($status === 'active') {
        $readiness = mg_profile_readiness($profile, mg_profile_links((int)$profile['id'], false), mg_profile_sections((int)$profile['id'], false));
        if (!$readiness['required_complete']) $status = 'draft';
    }
    return $status;
}

function mg_profile_moderation_apply_action(PDO $pdo, array $user, array $input): array
{
    if (!mg_profile_moderation_access($user)['manage']) throw new RuntimeException('Permission denied.');
    $casePublicId = mg_profile_moderation_text($input['case_id'] ?? '', 40, true);
    $actionType = mg_profile_moderation_enum($input['action'] ?? null, mg_profile_moderation_actions());
    $reasonCode = mg_profile_moderation_enum($input['reason_code'] ?? null, mg_profile_moderation_reason_codes(), 'other');
    $reasonText = mg_profile_moderation_text($input['reason'] ?? '', 5000, $actionType !== 'claim');

    $pdo->beginTransaction();
    try {
        $case = mg_profile_moderation_case($pdo, $casePublicId, true);
        $profileStmt = $pdo->prepare('SELECT * FROM public_profiles WHERE id=? LIMIT 1 FOR UPDATE');
        $profileStmt->execute([(int)$case['profile_id']]);
        $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
        if (!$profile) throw new RuntimeException('Profile not found.');

        $caseStatus = (string)$case['status'];
        $profileBefore = (string)$profile['status'];
        $profileAfter = $profileBefore;
        $caseAfter = $caseStatus;
        $assigned = $case['assigned_user_id'] !== null ? (int)$case['assigned_user_id'] : null;
        $resolvedAt = $case['resolved_at'];
        $reviewedAt = $case['reviewed_at'];
        $priority = (string)$case['priority'];
        $appealId = null;

        if ($actionType === 'claim') {
            if (in_array($caseStatus, ['resolved', 'dismissed'], true)) throw new DomainException('Closed cases cannot be claimed.');
            $assigned = (int)$user['id'];
            $caseAfter = $caseStatus === 'appealed' ? 'appealed' : 'in_review';
            $reviewedAt = gmdate('Y-m-d H:i:s');
        } elseif ($actionType === 'note') {
            if ($assigned === null) $assigned = (int)$user['id'];
            if ($caseStatus === 'open') $caseAfter = 'in_review';
            $reviewedAt = $reviewedAt ?: gmdate('Y-m-d H:i:s');
        } elseif ($actionType === 'warn') {
            if (in_array($caseStatus, ['resolved', 'dismissed'], true)) throw new DomainException('Closed cases cannot be actioned.');
            $caseAfter = 'actioned';
            $assigned = (int)$user['id'];
            $reviewedAt = $reviewedAt ?: gmdate('Y-m-d H:i:s');
        } elseif ($actionType === 'hide') {
            if ($profileBefore === 'suspended') throw new DomainException('A suspended profile cannot be downgraded to hidden.');
            $profileAfter = 'hidden';
            $caseAfter = 'actioned';
            $assigned = (int)$user['id'];
            $reviewedAt = $reviewedAt ?: gmdate('Y-m-d H:i:s');
        } elseif ($actionType === 'suspend') {
            $profileAfter = 'suspended';
            $caseAfter = 'actioned';
            $assigned = (int)$user['id'];
            $reviewedAt = $reviewedAt ?: gmdate('Y-m-d H:i:s');
        } elseif ($actionType === 'restore') {
            $requested = strtolower(trim((string)($input['restore_status'] ?? '')));
            $profileAfter = $requested !== ''
                ? (string)mg_profile_moderation_enum($requested, ['active', 'draft', 'hidden'])
                : mg_profile_moderation_previous_restore_status($pdo, (int)$case['id'], $profile);
            if ($profileAfter === 'active') {
                $readiness = mg_profile_readiness($profile, mg_profile_links((int)$profile['id'], false), mg_profile_sections((int)$profile['id'], false));
                if (!$readiness['required_complete']) throw new DomainException('The profile is not ready to restore as active.');
            }
            $caseAfter = 'resolved';
            $assigned = (int)$user['id'];
            $resolvedAt = gmdate('Y-m-d H:i:s');
            $reviewedAt = $reviewedAt ?: $resolvedAt;
        } elseif ($actionType === 'dismiss') {
            $caseAfter = 'dismissed';
            $assigned = (int)$user['id'];
            $resolvedAt = gmdate('Y-m-d H:i:s');
            $reviewedAt = $reviewedAt ?: $resolvedAt;
        } elseif ($actionType === 'escalate') {
            $priorityOrder = ['low', 'normal', 'high', 'urgent'];
            $current = array_search($priority, $priorityOrder, true);
            $requested = strtolower(trim((string)($input['priority'] ?? '')));
            $priority = $requested !== ''
                ? (string)mg_profile_moderation_enum($requested, $priorityOrder)
                : $priorityOrder[min(count($priorityOrder) - 1, ((int)$current) + 1)];
            $assigned = (int)$user['id'];
            if ($caseStatus === 'open') $caseAfter = 'in_review';
            $reviewedAt = $reviewedAt ?: gmdate('Y-m-d H:i:s');
        } elseif (in_array($actionType, ['appeal_accept', 'appeal_deny'], true)) {
            $appealStmt = $pdo->prepare(
                "SELECT * FROM profile_moderation_appeals
                 WHERE case_id=? AND status IN ('submitted','in_review')
                 ORDER BY submitted_at DESC,id DESC LIMIT 1 FOR UPDATE"
            );
            $appealStmt->execute([(int)$case['id']]);
            $appeal = $appealStmt->fetch(PDO::FETCH_ASSOC);
            if (!$appeal) throw new DomainException('No active appeal is available for review.');
            $appealId = (int)$appeal['id'];

            if ($actionType === 'appeal_accept') {
                $requested = strtolower(trim((string)($input['restore_status'] ?? '')));
                $profileAfter = $requested !== ''
                    ? (string)mg_profile_moderation_enum($requested, ['active', 'draft', 'hidden'])
                    : mg_profile_moderation_previous_restore_status($pdo, (int)$case['id'], $profile);
                if ($profileAfter === 'active') {
                    $readiness = mg_profile_readiness($profile, mg_profile_links((int)$profile['id'], false), mg_profile_sections((int)$profile['id'], false));
                    if (!$readiness['required_complete']) $profileAfter = 'draft';
                }
                $appealStatus = 'accepted';
            } else {
                $appealStatus = 'denied';
            }
            $appealUpdate = $pdo->prepare('UPDATE profile_moderation_appeals SET status=?,decision_reason=?,reviewed_by_user_id=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?');
            $appealUpdate->execute([$appealStatus, $reasonText, (int)$user['id'], $appealId]);
            $caseAfter = 'resolved';
            $assigned = (int)$user['id'];
            $resolvedAt = gmdate('Y-m-d H:i:s');
            $reviewedAt = $reviewedAt ?: $resolvedAt;
        }

        if ($profileAfter !== $profileBefore) {
            $profileUpdate = $pdo->prepare('UPDATE public_profiles SET status=?,updated_at=NOW() WHERE id=?');
            $profileUpdate->execute([$profileAfter, (int)$profile['id']]);
        }

        $caseUpdate = $pdo->prepare('UPDATE profile_moderation_cases SET assigned_user_id=?,status=?,priority=?,reviewed_at=?,resolved_at=?,updated_at=NOW() WHERE id=?');
        $caseUpdate->execute([$assigned, $caseAfter, $priority, $reviewedAt, $resolvedAt, (int)$case['id']]);

        $metadata = json_encode(['appeal_id' => $appealId, 'priority' => $priority], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $actionInsert = $pdo->prepare(
            "INSERT INTO profile_moderation_actions
             (public_id,case_id,profile_id,actor_user_id,actor_type,action_type,reason_code,reason_text,previous_profile_status,resulting_profile_status,metadata_json,created_at)
             VALUES (?,?,?,?, 'moderator',?,?,?,?,?,?,NOW())"
        );
        $actionInsert->execute([
            mg_profile_moderation_public_id('pma'), (int)$case['id'], (int)$profile['id'], (int)$user['id'],
            $actionType, $reasonCode, $reasonText !== '' ? $reasonText : null, $profileBefore, $profileAfter, $metadata,
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    mg_audit('profile.moderation.' . $actionType, 'public_profile', [
        'case_id' => $casePublicId,
        'profile_id' => (string)$profile['public_id'],
        'previous_status' => $profileBefore,
        'resulting_status' => $profileAfter,
        'reason_code' => $reasonCode,
    ], (int)$user['id']);
    mg_event('profile.moderation.action', [
        'case_id' => $casePublicId,
        'profile_id' => (string)$profile['public_id'],
        'action' => $actionType,
        'resulting_status' => $profileAfter,
    ], (int)$user['id']);

    return mg_profile_moderation_detail($pdo, $user, $casePublicId);
}
