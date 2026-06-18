<?php
declare(strict_types=1);

const MG_ADMIN_HEALTH_MEDIA_SCAN_LIMIT = 500;

function mg_admin_system_health_media(PDO $pdo,array $tables): array
{
    if(empty($tables['catalog_assets'])) return ['available'=>false,'status'=>'critical'];
    $stmt=$pdo->query("SELECT COUNT(*) total_assets,COALESCE(SUM(byte_size),0) total_bytes FROM catalog_assets WHERE status='ready'");
    $row=$stmt->fetch(PDO::FETCH_ASSOC)?:[];
    return ['available'=>true,'status'=>'healthy','total_assets'=>(int)($row['total_assets']??0),'ready_bytes'=>(int)($row['total_bytes']??0)];
}
