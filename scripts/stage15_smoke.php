<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';
require_once dirname(__DIR__) . '/api/demand/_window.php';

$pdo = mg_db();
$tables = [
    'purchase_signal_records',
    'purchase_signal_events',
    'demand_scope_snapshots',
    'demand_agent_signals',
];

foreach ($tables as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException('Missing Stage 15 table: ' . $table);
    }
}

[$start,$end,$days]=mg_demand_snapshot_window(new DateTimeImmutable('2026-06-10 18:00:00-07:00'),7);
if($start->format('Y-m-d H:i:s')!=='2026-06-11 00:00:00'||$end->format('Y-m-d H:i:s')!=='2026-06-18 00:00:00'||$days!==7){
    throw new RuntimeException('Stage 15 UTC snapshot normalization failed.');
}
if(mg_demand_window_overlaps(new DateTimeImmutable('2026-06-10 23:59:59 UTC'),null,$start,$end)){
    throw new RuntimeException('Stage 15 old point signal was not excluded.');
}
if(!mg_demand_window_overlaps(new DateTimeImmutable('2026-06-11 00:00:00 UTC'),null,$start,$end)){
    throw new RuntimeException('Stage 15 start-boundary signal was not included.');
}
if(mg_demand_window_overlaps(new DateTimeImmutable('2026-06-18 00:00:00 UTC'),null,$start,$end)){
    throw new RuntimeException('Stage 15 end-boundary signal was not excluded.');
}

fwrite(STDOUT, "Stage 15 demand intelligence and window smoke validation passed.\n");
