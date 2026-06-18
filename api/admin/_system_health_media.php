<?php
declare(strict_types=1);

const MG_ADMIN_HEALTH_MEDIA_SCAN_LIMIT = 500;

function mg_admin_system_health_media(PDO $pdo,array $tables): array
{
    if(empty($tables['catalog_assets'])) return ['available'=>false,'status'=>'critical','message'=>'Asset table is unavailable.'];

    $row=$pdo->query(
        "SELECT COUNT(*) total_assets,
                COALESCE(SUM(status='ready'),0) ready_assets,
                COALESCE(SUM(status='archived'),0) archived_assets,
                COALESCE(SUM(CASE WHEN status='ready' THEN byte_size ELSE 0 END),0) ready_bytes
         FROM catalog_assets
         WHERE JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.source'))='social_feed'"
    )->fetch(PDO::FETCH_ASSOC)?:[];
    $totals=array_map('intval',$row);
    $attached=0;
    $stale=0;
    if(!empty($tables['feed_post_assets'])){
        $attached=(int)$pdo->query(
            "SELECT COUNT(DISTINCT a.id)
             FROM catalog_assets a
             INNER JOIN feed_post_assets fpa ON fpa.asset_id=a.id
             WHERE a.status='ready'
               AND JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json,'$.source'))='social_feed'"
        )->fetchColumn();
        $stale=(int)$pdo->query(
            "SELECT COUNT(*)
             FROM catalog_assets a
             LEFT JOIN feed_post_assets fpa ON fpa.asset_id=a.id
             WHERE a.status='ready' AND fpa.id IS NULL
               AND a.updated_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 24 HOUR)
               AND JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json,'$.source'))='social_feed'"
        )->fetchColumn();
    }
    $ready=(int)($totals['ready_assets']??0);
    return array_merge($totals,[
        'available'=>true,
        'status'=>$stale>0?'warning':'healthy',
        'attached_assets'=>$attached,
        'unattached_assets'=>max(0,$ready-$attached),
        'stale_assets'=>$stale,
        'missing_count'=>null,
        'scanned_count'=>0,
        'scan_truncated'=>false,
    ]);
}
