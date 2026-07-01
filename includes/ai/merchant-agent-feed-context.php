<?php
declare(strict_types=1);

function mg_ai_chat_feed_posts_context(PDO $pdo, int $merchantId, int $limit = 8): array
{
    $limit = max(1, min(12, $limit));
    if (!function_exists('mg_agent_table_exists') || !mg_agent_table_exists($pdo, 'feed_posts')) {
        return ['available' => false, 'items' => []];
    }
    try {
        $stmt = $pdo->prepare("SELECT public_id,post_type,headline,visibility,status,created_at,updated_at,reaction_count,comment_count,share_count,save_count FROM feed_posts WHERE created_by_user_id=? AND status IN ('draft','published') AND moderation_status NOT IN ('hidden','removed') ORDER BY updated_at DESC,id DESC LIMIT {$limit}");
        $stmt->execute([$merchantId]);
        $items = [];
        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $items[] = [
                'id' => (string)($row['public_id'] ?? ''),
                'type' => (string)($row['post_type'] ?? 'simple'),
                'status' => (string)($row['status'] ?? ''),
                'visibility' => (string)($row['visibility'] ?? ''),
                'headline' => mg_ai_chat_clean($row['headline'] ?? '', 220),
                'engagement' => [
                    'reactions' => (int)($row['reaction_count'] ?? 0),
                    'comments' => (int)($row['comment_count'] ?? 0),
                    'shares' => (int)($row['share_count'] ?? 0),
                    'saves' => (int)($row['save_count'] ?? 0),
                ],
                'created_at' => (string)($row['created_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];
        }
        return ['available' => true, 'items' => $items];
    } catch (Throwable $error) {
        return ['available' => false, 'items' => [], 'error' => $error->getMessage()];
    }
}
