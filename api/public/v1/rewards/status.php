<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_public.php';

mg_require_method('GET');
$context = mg_public_context('distribution:rewards.status');
$pdo = $context['pdo'];
$rewardId = trim((string) ($_GET['id'] ?? ''));
if ($rewardId === '') {
    mg_public_log($pdo, $context, 422, 'invalid_request', 'Missing reward id.');
    mg_fail('Reward ID is required.', 422);
}

if (str_starts_with($rewardId, 'sandbox_reward_')) {
    $sandboxStmt = $pdo->prepare('SELECT * FROM public_api_sandbox_rewards WHERE public_id=? AND merchant_user_id=? AND app_id=? LIMIT 1');
    $sandboxStmt->execute([$rewardId,(int)$context['merchant_user_id'],(int)$context['app_id']]);
    $sandbox = $sandboxStmt->fetch();
    if (!$sandbox) {
        mg_public_log($pdo, $context, 404, 'not_found');
        mg_fail('Reward not found.', 404);
    }
    $sandboxItemId = 'sandbox_item_' . substr(hash('sha256', $rewardId . '|item'), 0, 24);
    $reward = [
        'sandbox' => true,
        'reward_id' => $sandbox['public_id'],
        'status' => $sandbox['status'],
        'program_id' => $sandbox['program_public_id'],
        'template_id' => $sandbox['template_public_id'],
        'linked_account_id' => $sandbox['linked_account_public_id'],
        'external_event_id' => $sandbox['external_event_id'],
        'event_type' => $sandbox['event_type'],
        'quantity' => (int)$sandbox['quantity'],
        'job_count' => (int)$sandbox['quantity'],
        'queued_jobs' => 0,
        'issued_jobs' => (int)$sandbox['quantity'],
        'failed_jobs' => $sandbox['status'] === 'sandbox_failed' ? (int)$sandbox['quantity'] : 0,
        'jobs' => [],
    ];
    for ($i = 1; $i <= (int)$sandbox['quantity']; $i++) {
        $reward['jobs'][] = [
            'job_id' => 'sandbox_job_' . substr(hash('sha256', $rewardId . '|job|' . $i), 0, 24),
            'item_sequence' => $i,
            'job_status' => $sandbox['status'] === 'sandbox_failed' ? 'failed' : 'issued',
            'pppm_item_id' => $sandboxItemId,
            'pppm_item_status' => $sandbox['status'] === 'sandbox_failed' ? null : 'delivered',
            'failure_message' => $sandbox['status'] === 'sandbox_failed' ? 'Sandbox failure state.' : null,
        ];
    }
    mg_public_log($pdo, $context, 200, 'sandbox_ok');
    mg_ok(['reward'=>$reward]);
}

$stmt = $pdo->prepare("SELECT da.id AS allocation_db_id,da.public_id AS reward_id,da.quantity,da.unit_value_cents,da.status,da.allocation_method,da.reserved_at,da.issued_at,da.failure_message,dp.public_id AS program_id,dp.name AS program_name,cpt.public_id AS template_id,cpv.title AS product_title,dr.public_id AS recipient_id,dr.eligibility_status AS recipient_status,dse.public_id AS event_id,dse.external_event_id,dse.event_type,COUNT(dij.id) AS job_count,SUM(dij.status='queued') AS queued_jobs,SUM(dij.status='issued') AS issued_jobs,SUM(dij.status IN ('failed','dead_letter')) AS failed_jobs,MAX(dij.updated_at) AS last_job_update FROM distribution_allocations da INNER JOIN distribution_programs dp ON dp.id=da.program_id INNER JOIN distribution_recipients dr ON dr.id=da.recipient_id INNER JOIN distribution_program_products dpp ON dpp.id=da.program_product_id INNER JOIN catalog_pppm_templates cpt ON cpt.id=dpp.pppm_template_id INNER JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id LEFT JOIN distribution_source_events dse ON dse.id=da.source_event_id LEFT JOIN distribution_issuance_jobs dij ON dij.allocation_id=da.id WHERE da.public_id=? AND dp.merchant_user_id=? AND (dse.connection_id IS NULL OR dse.connection_id=?) GROUP BY da.id,dp.id,dr.id,dpp.id,cpt.id,cpv.id,dse.id LIMIT 1");
$stmt->execute([$rewardId,(int)$context['merchant_user_id'],$context['source_connection_id']]);
$reward = $stmt->fetch();
if (!$reward) {
    mg_public_log($pdo, $context, 404, 'not_found');
    mg_fail('Reward not found.', 404);
}

$table = 'pppm_' . 'items';
$sql = 'SELECT j.public_id AS job_id,j.item_sequence,j.status AS job_status,j.failure_message,i.public_id AS issued_item_id,i.status AS issued_item_status FROM distribution_issuance_jobs j LEFT JOIN ' . $table . ' i ON i.id=j.pppm_item_id WHERE j.allocation_id=? ORDER BY j.item_sequence ASC,j.id ASC';
$jobStmt = $pdo->prepare($sql);
$jobStmt->execute([(int)$reward['allocation_db_id']]);
$reward['jobs'] = array_map(static function(array $row): array {
    return [
        'job_id' => $row['job_id'],
        'item_sequence' => (int)$row['item_sequence'],
        'job_status' => $row['job_status'],
        'pppm_item_id' => $row['issued_item_id'],
        'pppm_item_status' => $row['issued_item_status'],
        'failure_message' => $row['failure_message'],
    ];
}, $jobStmt->fetchAll());
unset($reward['allocation_db_id']);

mg_public_log($pdo, $context, 200, 'ok');
mg_ok(['reward'=>$reward]);
