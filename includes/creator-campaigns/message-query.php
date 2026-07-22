<?php
declare(strict_types=1);

function mg_creator_campaign_message_list(PDO $pdo, string $scopeColumn, int $scopeId, int $viewerUserId): array
{
    if (!in_array($scopeColumn, ['mc.merchant_workspace_id','mc.creator_user_id'], true)) throw new InvalidArgumentException('Invalid message query scope.');
    $sql = "SELECT mc.public_id context_public_id,mc.status,mc.lock_version,mc.updated_at,
                   mt.public_id thread_public_id,mt.subject,mt.updated_at thread_updated_at,
                   c.public_id campaign_public_id,c.title campaign_name,
                   p.public_id participant_public_id,p.status participant_status,
                   COALESCE(NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email) creator_name,
                   (SELECT m.body FROM messages m WHERE m.thread_id=mt.id AND COALESCE(m.moderation_status,'clear') NOT IN('hidden','removed') ORDER BY m.id DESC LIMIT 1) last_message,
                   (SELECT m.created_at FROM messages m WHERE m.thread_id=mt.id AND COALESCE(m.moderation_status,'clear') NOT IN('hidden','removed') ORDER BY m.id DESC LIMIT 1) last_message_at,
                   (SELECT COUNT(*) FROM messages m LEFT JOIN message_thread_participants vp ON vp.thread_id=m.thread_id AND vp.user_id=? WHERE m.thread_id=mt.id AND m.sender_user_id<>? AND (vp.last_read_at IS NULL OR m.created_at>vp.last_read_at) AND COALESCE(m.moderation_status,'clear') NOT IN('hidden','removed')) unread_count
            FROM creator_campaign_message_contexts mc
            INNER JOIN message_threads mt ON mt.id=mc.thread_id
            INNER JOIN creator_campaigns c ON c.id=mc.campaign_id
            INNER JOIN creator_campaign_participants p ON p.id=mc.participant_id
            INNER JOIN users u ON u.id=mc.creator_user_id
            WHERE {$scopeColumn}=?
            ORDER BY COALESCE(mt.updated_at,mc.updated_at) DESC,mc.id DESC
            LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$viewerUserId,$viewerUserId,$scopeId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mg_creator_campaign_message_notes(PDO $pdo, int $workspaceId, ?string $participantPublicId = null): array
{
    $sql = "SELECT n.public_id,n.context_type,n.context_public_id,n.body,n.created_at,n.updated_at,
                   p.public_id participant_public_id,c.public_id campaign_public_id,c.title campaign_name,
                   COALESCE(NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email) author_name
            FROM creator_campaign_internal_notes n
            INNER JOIN creator_campaigns c ON c.id=n.campaign_id
            LEFT JOIN creator_campaign_participants p ON p.id=n.participant_id
            INNER JOIN users u ON u.id=n.author_user_id
            WHERE n.merchant_workspace_id=? AND n.moderation_status='clear'";
    $args = [$workspaceId];
    if ($participantPublicId !== null && $participantPublicId !== '') {
        $sql .= ' AND p.public_id=?';
        $args[] = $participantPublicId;
    }
    $sql .= ' ORDER BY n.created_at DESC,n.id DESC LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mg_creator_campaign_message_summary(PDO $pdo, string $scopeColumn, int $scopeId, int $viewerUserId): array
{
    if (!in_array($scopeColumn, ['merchant_workspace_id','creator_user_id'], true)) throw new InvalidArgumentException('Invalid summary scope.');
    $stmt = $pdo->prepare("SELECT COUNT(*) threads,SUM(status='open') open_threads,SUM(status='closed') closed_threads FROM creator_campaign_message_contexts WHERE {$scopeColumn}=?");
    $stmt->execute([$scopeId]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['threads'=>0,'open_threads'=>0,'closed_threads'=>0];
    $unread = $pdo->prepare("SELECT COUNT(*) FROM messages m INNER JOIN creator_campaign_message_contexts mc ON mc.thread_id=m.thread_id LEFT JOIN message_thread_participants vp ON vp.thread_id=m.thread_id AND vp.user_id=? WHERE mc.{$scopeColumn}=? AND m.sender_user_id<>? AND (vp.last_read_at IS NULL OR m.created_at>vp.last_read_at) AND COALESCE(m.moderation_status,'clear') NOT IN('hidden','removed')");
    $unread->execute([$viewerUserId,$scopeId,$viewerUserId]);
    $summary['unread_messages'] = (int)$unread->fetchColumn();
    return $summary;
}
