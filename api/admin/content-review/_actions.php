<?php
declare(strict_types=1);

require_once __DIR__ . '/_subjects.php';
require_once dirname(__DIR__, 2) . '/communications/_communications.php';

function mg_content_review_target_is_super_admin(PDO $pdo, int $userId): bool
{
    if ($userId < 1) return false;
    $stmt = $pdo->prepare(
        "SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id
         WHERE ur.user_id=? AND r.slug='super_admin' LIMIT 1"
    );
    $stmt->execute([$userId]);
    return (bool)$stmt->fetchColumn();
}

function mg_content_review_record_action(
    PDO $pdo,
    int $reportId,
    int $actorId,
    string $action,
    ?string $reason,
    ?string $previousState,
    ?string $resultingState,
    array $metadata = []
): string {
    $publicId = mg_public_uuid();
    $pdo->prepare(
        'INSERT INTO content_moderation_actions
         (public_id,report_id,actor_user_id,action_type,reason,previous_state,resulting_state,metadata_json,created_at)
         VALUES (?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        $publicId,
        $reportId,
        $actorId,
        $action,
        $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 5000) : null,
        $previousState,
        $resultingState,
        $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
    ]);
    return $publicId;
}

function mg_content_review_content_state(PDO $pdo, array $report): ?string
{
    $type = (string)$report['subject_type'];
    if ($type === 'post' && !empty($report['feed_post_id'])) {
        $stmt=$pdo->prepare('SELECT moderation_status FROM feed_posts WHERE id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([(int)$report['feed_post_id']]);
        return $stmt->fetchColumn() ?: null;
    }
    if ($type === 'comment' && !empty($report['comment_id'])) {
        $stmt=$pdo->prepare('SELECT status FROM feed_post_comments WHERE id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([(int)$report['comment_id']]);
        return $stmt->fetchColumn() ?: null;
    }
    if ($type === 'media' && !empty($report['asset_id'])) {
        $stmt=$pdo->prepare('SELECT moderation_status FROM catalog_assets WHERE id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([(int)$report['asset_id']]);
        return $stmt->fetchColumn() ?: null;
    }
    if ($type === 'message' && !empty($report['message_id'])) {
        $stmt=$pdo->prepare('SELECT moderation_status FROM messages WHERE id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([(int)$report['message_id']]);
        return $stmt->fetchColumn() ?: null;
    }
    if (($type === 'profile' || $type === 'user') && !empty($report['subject_user_id'])) {
        $stmt=$pdo->prepare('SELECT status FROM public_profiles WHERE user_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([(int)$report['subject_user_id']]);
        return $stmt->fetchColumn() ?: null;
    }
    return null;
}

function mg_content_review_set_content_state(PDO $pdo, array $report, string $action): array
{
    $type = (string)$report['subject_type'];
    $previous = mg_content_review_content_state($pdo, $report);
    $result = $previous;

    if ($type === 'post' && !empty($report['feed_post_id'])) {
        $result = $action === 'restore_content' ? 'clear' : 'hidden';
        $pdo->prepare('UPDATE feed_posts SET moderation_status=?,updated_at=NOW() WHERE id=?')
            ->execute([$result,(int)$report['feed_post_id']]);
    } elseif ($type === 'comment' && !empty($report['comment_id'])) {
        $result = $action === 'restore_content' ? 'visible' : 'hidden';
        $pdo->prepare('UPDATE feed_post_comments SET status=?,updated_at=NOW() WHERE id=?')
            ->execute([$result,(int)$report['comment_id']]);
    } elseif ($type === 'media' && !empty($report['asset_id'])) {
        $result = $action === 'restore_content' ? 'clear' : 'quarantined';
        $pdo->prepare('UPDATE catalog_assets SET moderation_status=?,updated_at=NOW() WHERE id=?')
            ->execute([$result,(int)$report['asset_id']]);
    } elseif ($type === 'message' && !empty($report['message_id'])) {
        $result = $action === 'restore_content' ? 'clear' : 'hidden';
        $pdo->prepare('UPDATE messages SET moderation_status=?,updated_at=NOW() WHERE id=?')
            ->execute([$result,(int)$report['message_id']]);
    } elseif (($type === 'profile' || $type === 'user') && !empty($report['subject_user_id'])) {
        $result = $action === 'restore_content' ? 'active' : 'hidden';
        $pdo->prepare('UPDATE public_profiles SET status=?,updated_at=NOW() WHERE user_id=?')
            ->execute([$result,(int)$report['subject_user_id']]);
    } else {
        throw new RuntimeException('This report has no actionable content subject.');
    }
    return ['previous'=>$previous,'result'=>$result];
}

function mg_content_review_clear_flag_if_resolved(PDO $pdo, array $report): void
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM social_reports
         WHERE id<>? AND subject_type=? AND subject_reference=? AND status IN ('open','reviewing')"
    );
    $stmt->execute([(int)$report['id'],(string)$report['subject_type'],(string)$report['subject_reference']]);
    if ((int)$stmt->fetchColumn() > 0) return;

    $type = (string)$report['subject_type'];
    if ($type === 'post' && !empty($report['feed_post_id'])) {
        $pdo->prepare("UPDATE feed_posts SET moderation_status='clear',updated_at=NOW() WHERE id=? AND moderation_status='flagged'")
            ->execute([(int)$report['feed_post_id']]);
    } elseif ($type === 'comment' && !empty($report['comment_id'])) {
        $pdo->prepare("UPDATE feed_post_comments SET status='visible',updated_at=NOW() WHERE id=? AND status='flagged'")
            ->execute([(int)$report['comment_id']]);
    } elseif ($type === 'media' && !empty($report['asset_id'])) {
        $pdo->prepare("UPDATE catalog_assets SET moderation_status='clear',updated_at=NOW() WHERE id=? AND moderation_status='flagged'")
            ->execute([(int)$report['asset_id']]);
    } elseif ($type === 'message' && !empty($report['message_id'])) {
        $pdo->prepare("UPDATE messages SET moderation_status='clear',updated_at=NOW() WHERE id=? AND moderation_status='flagged'")
            ->execute([(int)$report['message_id']]);
    }
}

function mg_content_review_warn_user(PDO $pdo, array $report, int $actorId, string $reason): string
{
    $target = (int)($report['subject_user_id'] ?? 0);
    if ($target < 1) throw new RuntimeException('The reported content has no linked account.');
    return mg_create_notification(
        $pdo,
        $target,
        'system',
        'Account warning',
        mb_substr($reason !== '' ? $reason : 'Your recent activity was reviewed by the Microgifter safety team.', 0, 500),
        '/notifications.php',
        [
            'actor_user_id'=>$actorId,
            'event_key'=>'review.warning.' . strtolower((string)$report['public_id']),
            'report_id'=>(string)$report['public_id'],
        ]
    );
}

function mg_content_review_restrict_posting(PDO $pdo, array $report, int $actorId, string $reason): string
{
    $target = (int)($report['subject_user_id'] ?? 0);
    if ($target < 1) throw new RuntimeException('The reported content has no linked account.');
    $existing = $pdo->prepare(
        "SELECT public_id FROM user_moderation_restrictions
         WHERE user_id=? AND restriction_type IN ('posting','all') AND status='active'
           AND (ends_at IS NULL OR ends_at>NOW()) LIMIT 1 FOR UPDATE"
    );
    $existing->execute([$target]);
    $publicId = (string)($existing->fetchColumn() ?: '');
    if ($publicId !== '') return $publicId;
    $publicId = mg_public_uuid();
    $pdo->prepare(
        "INSERT INTO user_moderation_restrictions
         (public_id,user_id,restriction_type,status,reason,starts_at,created_by_user_id,created_at,updated_at)
         VALUES (?,?,'posting','active',?,NOW(),?,NOW(),NOW())"
    )->execute([$publicId,$target,mb_substr($reason,0,1000),$actorId]);
    return $publicId;
}

function mg_content_review_set_user_status(PDO $pdo, array $report, string $action, int $actorId): array
{
    $target = (int)($report['subject_user_id'] ?? 0);
    if ($target < 1) throw new RuntimeException('The reported content has no linked account.');
    if (mg_content_review_target_is_super_admin($pdo, $target)) {
        throw new RuntimeException('Super administrator accounts cannot be changed from the review center.');
    }
    $stmt = $pdo->prepare('SELECT status FROM users WHERE id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$target]);
    $previous = (string)($stmt->fetchColumn() ?: '');
    if ($previous === '') throw new RuntimeException('Account not found.');
    $result = $action === 'suspend_user' ? 'disabled' : 'active';
    $pdo->prepare('UPDATE users SET status=?,updated_at=NOW() WHERE id=?')->execute([$result,$target]);
    if ($action === 'suspend_user') {
        $pdo->prepare("UPDATE public_profiles SET status='suspended',updated_at=NOW() WHERE user_id=? AND status<>'suspended'")
            ->execute([$target]);
    } else {
        $pdo->prepare("UPDATE public_profiles SET status='active',updated_at=NOW() WHERE user_id=? AND status='suspended'")
            ->execute([$target]);
        $pdo->prepare(
            "UPDATE user_moderation_restrictions
             SET status='lifted',lifted_by_user_id=?,lifted_at=NOW(),updated_at=NOW()
             WHERE user_id=? AND status='active'"
        )->execute([$actorId,$target]);
    }
    return ['previous'=>$previous,'result'=>$result,'user_id'=>$target];
}
