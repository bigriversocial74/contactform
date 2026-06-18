<?php
declare(strict_types=1);

require_once __DIR__ . '/_engagement.php';

function mg_social_report_severity(string $reason): string
{
    return match ($reason) {
        'immediate_safety', 'exploitation' => 'urgent',
        'harassment', 'fraud', 'privacy', 'impersonation' => 'high',
        'spam', 'copyright', 'unsafe_content' => 'normal',
        default => 'low',
    };
}

function mg_social_report_snapshot(array $values): string
{
    return json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_social_report_subject(PDO $pdo, int $actorId, string $type, string $reference): array
{
    if ($type === 'post') {
        $post = mg_engagement_post($pdo, $reference, $actorId, true);
        if ((int)$post['created_by_user_id'] === $actorId) throw new InvalidArgumentException('You cannot report your own post.');
        return [
            'subject_type' => 'post',
            'subject_reference' => (string)$post['public_id'],
            'subject_user_id' => (int)$post['created_by_user_id'],
            'feed_post_id' => (int)$post['id'],
            'comment_id' => null,
            'asset_id' => null,
            'message_id' => null,
            'snapshot' => mg_social_report_snapshot([
                'title' => $post['title'] ?? null,
                'body' => isset($post['body']) ? mb_substr((string)$post['body'], 0, 2000) : null,
                'status' => $post['status'] ?? null,
                'visibility' => $post['visibility'] ?? null,
                'moderation_status' => $post['moderation_status'] ?? null,
            ]),
        ];
    }

    if ($type === 'comment') {
        $stmt = $pdo->prepare(
            'SELECT c.id,c.public_id,c.user_id,c.body,c.status,fp.public_id post_public_id,fp.id feed_post_id
             FROM feed_post_comments c
             INNER JOIN feed_posts fp ON fp.id=c.feed_post_id
             WHERE c.public_id=? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$reference]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$comment) throw new RuntimeException('Comment not found.');
        if ((int)$comment['user_id'] === $actorId) throw new InvalidArgumentException('You cannot report your own comment.');
        mg_engagement_post($pdo, (string)$comment['post_public_id'], $actorId, false);
        return [
            'subject_type' => 'comment',
            'subject_reference' => (string)$comment['public_id'],
            'subject_user_id' => (int)$comment['user_id'],
            'feed_post_id' => (int)$comment['feed_post_id'],
            'comment_id' => (int)$comment['id'],
            'asset_id' => null,
            'message_id' => null,
            'snapshot' => mg_social_report_snapshot([
                'body' => mb_substr((string)$comment['body'], 0, 2000),
                'status' => (string)$comment['status'],
                'post_id' => (string)$comment['post_public_id'],
            ]),
        ];
    }

    if ($type === 'profile' || $type === 'user') {
        $stmt = $pdo->prepare(
            "SELECT pp.id profile_id,pp.public_id profile_public_id,pp.slug,pp.user_id,pp.display_name,
                    pp.headline,pp.status profile_status,pp.visibility,u.public_id user_public_id,u.status user_status
             FROM public_profiles pp
             INNER JOIN users u ON u.id=pp.user_id
             WHERE (pp.public_id=? OR pp.slug=? OR u.public_id=?)
               AND pp.status='active' AND pp.visibility IN ('public','unlisted') AND u.status='active'
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$reference, strtolower($reference), $reference]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$profile) throw new RuntimeException('Profile not found.');
        if ((int)$profile['user_id'] === $actorId) throw new InvalidArgumentException('You cannot report your own profile.');
        if (mg_social_is_blocked($pdo, $actorId, (int)$profile['user_id'])) throw new RuntimeException('Profile not found.');
        return [
            'subject_type' => $type,
            'subject_reference' => $type === 'user' ? (string)$profile['user_public_id'] : (string)$profile['profile_public_id'],
            'subject_user_id' => (int)$profile['user_id'],
            'feed_post_id' => null,
            'comment_id' => null,
            'asset_id' => null,
            'message_id' => null,
            'snapshot' => mg_social_report_snapshot([
                'profile_id' => (string)$profile['profile_public_id'],
                'slug' => (string)$profile['slug'],
                'display_name' => (string)$profile['display_name'],
                'headline' => $profile['headline'] ?? null,
                'profile_status' => (string)$profile['profile_status'],
                'visibility' => (string)$profile['visibility'],
                'user_status' => (string)$profile['user_status'],
            ]),
        ];
    }

    if ($type === 'media') {
        $stmt = $pdo->prepare(
            "SELECT a.id,a.public_id,a.owner_user_id,a.asset_type,a.original_filename,a.mime_type,a.byte_size,
                    a.moderation_status,fp.id feed_post_id,fp.public_id post_public_id
             FROM catalog_assets a
             INNER JOIN feed_post_assets fpa ON fpa.asset_id=a.id
             INNER JOIN feed_posts fp ON fp.id=fpa.feed_post_id
             WHERE a.public_id=? AND a.status='ready'
             ORDER BY fp.updated_at DESC,fp.id DESC LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([strtolower($reference)]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$asset) throw new RuntimeException('Media not found.');
        if ((int)$asset['owner_user_id'] === $actorId) throw new InvalidArgumentException('You cannot report your own media.');
        mg_engagement_post($pdo, (string)$asset['post_public_id'], $actorId, false);
        return [
            'subject_type' => 'media',
            'subject_reference' => (string)$asset['public_id'],
            'subject_user_id' => (int)$asset['owner_user_id'],
            'feed_post_id' => (int)$asset['feed_post_id'],
            'comment_id' => null,
            'asset_id' => (int)$asset['id'],
            'message_id' => null,
            'snapshot' => mg_social_report_snapshot([
                'asset_type' => (string)$asset['asset_type'],
                'filename' => (string)($asset['original_filename'] ?? ''),
                'mime_type' => (string)($asset['mime_type'] ?? ''),
                'byte_size' => (int)($asset['byte_size'] ?? 0),
                'moderation_status' => (string)$asset['moderation_status'],
                'post_id' => (string)$asset['post_public_id'],
            ]),
        ];
    }

    if ($type === 'message') {
        $stmt = $pdo->prepare(
            'SELECT m.id,m.public_id,m.sender_user_id,m.body,m.moderation_status,m.created_at,mt.public_id thread_public_id
             FROM messages m
             INNER JOIN message_threads mt ON mt.id=m.thread_id
             INNER JOIN message_thread_participants mtp ON mtp.thread_id=m.thread_id AND mtp.user_id=?
             WHERE m.public_id=? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$actorId, strtolower($reference)]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$message) throw new RuntimeException('Message not found.');
        if ((int)$message['sender_user_id'] === $actorId) throw new InvalidArgumentException('You cannot report your own message.');
        return [
            'subject_type' => 'message',
            'subject_reference' => (string)$message['public_id'],
            'subject_user_id' => (int)$message['sender_user_id'],
            'feed_post_id' => null,
            'comment_id' => null,
            'asset_id' => null,
            'message_id' => (int)$message['id'],
            'snapshot' => mg_social_report_snapshot([
                'body' => mb_substr((string)$message['body'], 0, 2000),
                'moderation_status' => (string)$message['moderation_status'],
                'thread_id' => (string)$message['thread_public_id'],
                'created_at' => $message['created_at'] ?? null,
            ]),
        ];
    }

    throw new InvalidArgumentException('Unsupported report subject.');
}

function mg_social_report_flag_subject(PDO $pdo, array $subject): void
{
    if ($subject['subject_type'] === 'post') {
        $pdo->prepare("UPDATE feed_posts SET moderation_status=IF(moderation_status='clear','flagged',moderation_status),updated_at=NOW() WHERE id=?")
            ->execute([(int)$subject['feed_post_id']]);
    } elseif ($subject['subject_type'] === 'comment') {
        $pdo->prepare("UPDATE feed_post_comments SET status=IF(status='visible','flagged',status),updated_at=NOW() WHERE id=?")
            ->execute([(int)$subject['comment_id']]);
    } elseif ($subject['subject_type'] === 'media') {
        $pdo->prepare("UPDATE catalog_assets SET moderation_status=IF(moderation_status='clear','flagged',moderation_status),updated_at=NOW() WHERE id=?")
            ->execute([(int)$subject['asset_id']]);
    } elseif ($subject['subject_type'] === 'message') {
        $pdo->prepare("UPDATE messages SET moderation_status=IF(moderation_status='clear','flagged',moderation_status),updated_at=NOW() WHERE id=?")
            ->execute([(int)$subject['message_id']]);
    }
}
