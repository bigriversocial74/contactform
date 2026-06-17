<?php
declare(strict_types=1);
require_once __DIR__ . '/_distribution.php';
mg_require_method('GET');
$user=mg_require_permission('distribution.analytics.view');
$from=trim((string)($_GET['from']??date('Y-m-d',strtotime('-30 days'))));$to=trim((string)($_GET['to']??date('Y-m-d')));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))mg_fail('Invalid analytics range.',422);
$pdo=mg_db();
$metrics=$pdo->prepare('SELECT ddm.metric_date,dp.public_id AS program_id,dp.name,ddm.source_type,ddm.received_events,ddm.duplicate_events,ddm.rejected_events,ddm.allocations,ddm.items_queued,ddm.items_issued,ddm.issued_value_cents,ddm.unique_recipients FROM distribution_daily_metrics ddm INNER JOIN distribution_programs dp ON dp.id=ddm.program_id WHERE ddm.merchant_user_id=? AND ddm.metric_date BETWEEN ? AND ? ORDER BY ddm.metric_date,dp.name');$metrics->execute([(int)$user['id'],$from,$to]);
$summary=$pdo->prepare("SELECT COUNT(*) AS program_count,SUM(status='active') AS active_programs,COALESCE(SUM(budget_cents),0) AS budget_cents,COALESCE(SUM(reserved_cents),0) AS reserved_cents,COALESCE(SUM(issued_cents),0) AS issued_cents,COALESCE(SUM(issued_items),0) AS issued_items FROM distribution_programs WHERE merchant_user_id=?");$summary->execute([(int)$user['id']]);
$sources=$pdo->prepare("SELECT source_type,COUNT(*) AS connections,SUM(status='active') AS active_connections,MAX(last_event_at) AS last_event_at FROM distribution_source_connections WHERE merchant_user_id=? GROUP BY source_type ORDER BY source_type");$sources->execute([(int)$user['id']]);
$queue=$pdo->prepare("SELECT dij.status,COUNT(*) AS jobs FROM distribution_issuance_jobs dij INNER JOIN distribution_allocations da ON da.id=dij.allocation_id INNER JOIN distribution_programs dp ON dp.id=da.program_id WHERE dp.merchant_user_id=? GROUP BY dij.status");$queue->execute([(int)$user['id']]);
mg_ok(['range'=>['from'=>$from,'to'=>$to],'summary'=>$summary->fetch()?:[],'sources'=>$sources->fetchAll(),'queue'=>$queue->fetchAll(),'daily'=>$metrics->fetchAll()]);
