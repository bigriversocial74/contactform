<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';
$pdo = mg_db();
$date = $argv[1] ?? date('Y-m-d', strtotime('-1 day'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    fwrite(STDERR, "Usage: php scripts/aggregate_stage4d_engagement.php YYYY-MM-DD\n");
    exit(1);
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM content_engagement_daily WHERE metric_date = ?')->execute([$date]);
    $pdo->prepare(
        'INSERT INTO content_engagement_daily
         (metric_date, merchant_user_id, feed_post_version_id, event_type,
          event_count, unique_viewers, total_playback_ms, updated_at)
         SELECT DATE(cee.occurred_at), fp.merchant_user_id, cee.feed_post_version_id, cee.event_type,
                COUNT(*), COUNT(DISTINCT COALESCE(CAST(cee.viewer_user_id AS CHAR), cee.anonymous_session_hash)),
                COALESCE(SUM(cee.playback_position_ms), 0), NOW()
         FROM content_engagement_events cee
         INNER JOIN feed_post_versions fpv ON fpv.id = cee.feed_post_version_id
         INNER JOIN feed_posts fp ON fp.id = fpv.feed_post_id
         WHERE cee.occurred_at >= ? AND cee.occurred_at < DATE_ADD(?, INTERVAL 1 DAY)
         GROUP BY DATE(cee.occurred_at), fp.merchant_user_id, cee.feed_post_version_id, cee.event_type'
    )->execute([$date, $date]);
    $pdo->commit();
    echo "Aggregated engagement for {$date}.\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
