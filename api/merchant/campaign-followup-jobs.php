<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/public/campaigns/_followups.php';

function mg_followup_job_row(array $r): array
{
    $payload = [];
    if (!empty($r['payload_json'])) {
        $decoded = json_decode((string)$r['payload_json'], true);
        $payload = is_array($decoded) ? $decoded : [];
    }
    return [
        'id' => (string)$r['public_id'],
        'rule_id' => (string)$r['rule_public_id'],
        'rule_name' => (string)$r['rule_name'],
        'campaign_id' => (string)$r['campaign_public_id'],
        'campaign_title' => (string)$r['campaign_title'],
        'campaign_type' => (string)$r['campaign_type'],
        'contact_id' => (string)($r['contact_public_id'] ?? ''),
        'contact_name' => (string)($r['contact_name'] ?? ''),
        'contact_email' => (string)($r['contact_email'] ?? ''),
        'wallet_item_id' => (string)($r['wallet_public_id'] ?? ''),
        'reward_title' => (string)($r['reward_title'] ?? ''),
        'trigger_event' => (string)$r['trigger_event'],
        'channel' => (string)$r['channel'],
        'message_mode' => (string)$r['message_mode'],
        'status' => (string)$r['status'],
        'due_at' => $r['due_at'] ?? null,
        'sent_at' => $r['sent_at'] ?? null,
        'attempt_count' => (int)($r['attempt_count'] ?? 0),
        'last_error' => (string)($r['last_error'] ?? ''),
        'payload' => $payload,
        'created_at' => $r['created_at'] ?? null,
        'updated_at' => $r['updated_at'] ?? null,
    ];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = $method === 'GET' ? mg_require_permission('merchant.campaigns.view') : mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
mg_campaign_followup_install($pdo);

if ($method === 'GET') {
    $campaignRef = strtolower(trim((string)($_GET['campaign'] ?? $_GET['campaign_id'] ?? '')));
    $status = strtolower(trim((string)($_GET['status'] ?? 'all')));
    $allowed = ['queued','processing','sent','skipped','failed','cancelled'];
    $sql = "SELECT j.*,r.public_id rule_public_id,r.name rule_name,r.channel,r.message_mode,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type,cc.public_id contact_public_id,cc.name contact_name,cc.email contact_email,wi.public_id wallet_public_id,wi.title_snapshot reward_title FROM campaign_followup_jobs j INNER JOIN campaign_followup_rules r ON r.id=j.rule_id INNER JOIN campaigns c ON c.id=j.campaign_id LEFT JOIN campaign_contacts cc ON cc.id=j.contact_id LEFT JOIN wallet_items wi ON wi.id=j.wallet_item_id WHERE j.merchant_user_id=?";
    $params = [$merchantId];
    if ($campaignRef !== '') { $sql .= ' AND (c.public_id=? OR c.public_slug=?)'; $params[] = $campaignRef; $params[] = $campaignRef; }
    if (in_array($status, $allowed, true)) { $sql .= ' AND j.status=?'; $params[] = $status; }
    $sql .= ' ORDER BY FIELD(j.status,\'failed\',\'queued\',\'processing\',\'skipped\',\'sent\',\'cancelled\'), j.due_at ASC, j.id DESC LIMIT 250';
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $jobs = array_map('mg_followup_job_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
    $counts = ['queued'=>0,'processing'=>0,'sent'=>0,'skipped'=>0,'failed'=>0,'cancelled'=>0,'due_now'=>0];
    $countSql = "SELECT status,COUNT(*) c,SUM(status='queued' AND due_at<=NOW()) due_now FROM campaign_followup_jobs WHERE merchant_user_id=? GROUP BY status";
    $countStmt = $pdo->prepare($countSql); $countStmt->execute([$merchantId]);
    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $counts[(string)$row['status']] = (int)$row['c']; $counts['due_now'] += (int)$row['due_now']; }
    mg_ok(['jobs'=>$jobs,'counts'=>$counts,'schema_ready'=>true]);
}

if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$jobRef = strtolower(trim((string)($input['job_id'] ?? $input['id'] ?? '')));
$action = strtolower(trim((string)($input['action'] ?? '')));
if ($jobRef === '' || strlen($jobRef) !== 36 || !in_array($action, ['retry','cancel'], true)) mg_fail('Invalid follow-up job action.',422);
$stmt = $pdo->prepare('SELECT j.*,r.public_id rule_public_id FROM campaign_followup_jobs j INNER JOIN campaign_followup_rules r ON r.id=j.rule_id WHERE j.public_id=? AND j.merchant_user_id=? LIMIT 1');
$stmt->execute([$jobRef,$merchantId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$job) mg_fail('Follow-up job not found.',404);
if ($action === 'retry') {
    if (!in_array((string)$job['status'], ['failed','skipped','cancelled'], true)) mg_fail('Only failed, skipped, or cancelled jobs can be retried.',409);
    $pdo->prepare("UPDATE campaign_followup_jobs SET status='queued',due_at=NOW(),last_error=NULL,updated_at=NOW() WHERE id=? AND merchant_user_id=?")->execute([(int)$job['id'],$merchantId]);
    mg_ok(['job_id'=>$jobRef,'status'=>'queued'],'Follow-up job queued for retry.');
}
if ($action === 'cancel') {
    if (!in_array((string)$job['status'], ['queued','processing'], true)) mg_fail('Only queued or processing jobs can be cancelled.',409);
    $pdo->prepare("UPDATE campaign_followup_jobs SET status='cancelled',last_error=NULL,updated_at=NOW() WHERE id=? AND merchant_user_id=?")->execute([(int)$job['id'],$merchantId]);
    mg_ok(['job_id'=>$jobRef,'status'=>'cancelled'],'Follow-up job cancelled.');
}
mg_fail('Unsupported follow-up job action.',422);
