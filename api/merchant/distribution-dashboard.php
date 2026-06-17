<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
mg_require_method('GET');
$user=mg_require_permission('merchant.distribution.view');
$pdo=mg_db();
$programs=$pdo->prepare("SELECT dp.public_id,dp.name,dp.program_type,dp.status,dp.starts_at,dp.ends_at,dp.budget_cents,dp.reserved_cents,dp.issued_cents,dp.max_items,dp.issued_items,dp.per_recipient_limit,dp.updated_at,COUNT(DISTINCT dr.id) recipient_count,SUM(dr.eligibility_status='eligible') eligible_count,SUM(dr.eligibility_status='selected') selected_count,SUM(dr.eligibility_status='allocated') allocated_count,COUNT(DISTINCT da.id) allocation_count,SUM(da.status='issued') issued_allocations,COUNT(DISTINCT dsc.id) source_count FROM distribution_programs dp LEFT JOIN distribution_recipients dr ON dr.program_id=dp.id LEFT JOIN distribution_allocations da ON da.program_id=dp.id LEFT JOIN distribution_source_connections dsc ON dsc.program_id=dp.id WHERE dp.merchant_user_id=? GROUP BY dp.id ORDER BY dp.updated_at DESC,dp.id DESC");
$programs->execute([(int)$user['id']]);
$summary=$pdo->prepare("SELECT COUNT(*) program_count,SUM(status='active') active_programs,SUM(status='paused') paused_programs,SUM(status='completed') completed_programs,COALESCE(SUM(budget_cents),0) budget_cents,COALESCE(SUM(reserved_cents),0) reserved_cents,COALESCE(SUM(issued_cents),0) issued_cents,COALESCE(SUM(issued_items),0) issued_items FROM distribution_programs WHERE merchant_user_id=?");
$summary->execute([(int)$user['id']]);
$queue=$pdo->prepare("SELECT dij.status,COUNT(*) jobs FROM distribution_issuance_jobs dij INNER JOIN distribution_allocations da ON da.id=dij.allocation_id INNER JOIN distribution_programs dp ON dp.id=da.program_id WHERE dp.merchant_user_id=? GROUP BY dij.status");
$queue->execute([(int)$user['id']]);
$sources=$pdo->prepare("SELECT source_type,COUNT(*) connections,SUM(status='active') active_connections,MAX(last_event_at) last_event_at FROM distribution_source_connections WHERE merchant_user_id=? GROUP BY source_type ORDER BY source_type");
$sources->execute([(int)$user['id']]);
mg_ok(['summary'=>$summary->fetch()?:[],'programs'=>$programs->fetchAll(),'queue'=>$queue->fetchAll(),'sources'=>$sources->fetchAll()]);
