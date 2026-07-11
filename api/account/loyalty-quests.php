<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$status = trim((string)($_GET['status'] ?? 'all'));
if (!in_array($status, ['all','joined','in_progress','pending_review','completed','rejected','cancelled'], true)) $status = 'all';

try {
    $totalsStmt = $pdo->prepare("SELECT status,COUNT(*) total FROM loyalty_quest_participations WHERE participant_user_id=? GROUP BY status");
    $totalsStmt->execute([(int)$user['id']]);
    $totals = ['total'=>0,'joined'=>0,'in_progress'=>0,'pending_review'=>0,'completed'=>0,'rejected'=>0,'cancelled'=>0];
    foreach ($totalsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $key = (string)$row['status'];
        $totals[$key] = (int)$row['total'];
        $totals['total'] += (int)$row['total'];
    }

    $sql = "SELECT lqp.public_id,lqp.status,lqp.progress_count,lqp.required_count,lqp.completion_percent,lqp.joined_at,lqp.started_at,lqp.submitted_at,lqp.reviewed_at,lqp.completed_at,lqp.last_activity_at,
        c.public_id campaign_public_id,c.public_slug,c.title,c.description,c.status campaign_status,c.ends_at,c.rules_json,
        COALESCE(pp.display_name,mw.display_name,u.display_name,u.full_name,'Microgifter Merchant') merchant_name,
        rt.title reward_title,wi.public_id wallet_item_public_id,wi.status wallet_item_status,wi.expires_at wallet_expires_at,
        (SELECT COUNT(*) FROM loyalty_quest_evidence lqe WHERE lqe.participation_id=lqp.id AND lqe.participant_user_id=lqp.participant_user_id) evidence_count,
        (SELECT lqe.review_note FROM loyalty_quest_evidence lqe WHERE lqe.participation_id=lqp.id AND lqe.participant_user_id=lqp.participant_user_id AND lqe.review_note IS NOT NULL ORDER BY lqe.updated_at DESC,lqe.id DESC LIMIT 1) latest_review_note,
        (SELECT lqe.status FROM loyalty_quest_evidence lqe WHERE lqe.participation_id=lqp.id AND lqe.participant_user_id=lqp.participant_user_id ORDER BY lqe.created_at DESC,lqe.id DESC LIMIT 1) latest_evidence_status
        FROM loyalty_quest_participations lqp
        INNER JOIN campaigns c ON c.id=lqp.campaign_id AND c.campaign_type='loyalty_quest'
        INNER JOIN users u ON u.id=lqp.merchant_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=lqp.merchant_user_id
        LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=lqp.merchant_user_id
        LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
        LEFT JOIN wallet_items wi ON wi.id=lqp.wallet_item_id
        WHERE lqp.participant_user_id=?";
    $params = [(int)$user['id']];
    if ($status !== 'all') { $sql .= ' AND lqp.status=?'; $params[] = $status; }
    $sql .= ' ORDER BY lqp.last_activity_at DESC,lqp.id DESC LIMIT 100';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $rules = json_decode((string)($row['rules_json'] ?? ''), true);
        if (!is_array($rules)) $rules = [];
        $ref = (string)($row['public_slug'] ?: $row['campaign_public_id']);
        $items[] = [
            'id'=>(string)$row['public_id'],
            'status'=>(string)$row['status'],
            'progress_count'=>(int)$row['progress_count'],
            'required_count'=>(int)$row['required_count'],
            'completion_percent'=>(int)$row['completion_percent'],
            'joined_at'=>$row['joined_at'],
            'started_at'=>$row['started_at'],
            'submitted_at'=>$row['submitted_at'],
            'reviewed_at'=>$row['reviewed_at'],
            'completed_at'=>$row['completed_at'],
            'last_activity_at'=>$row['last_activity_at'],
            'evidence_count'=>(int)$row['evidence_count'],
            'latest_review_note'=>$row['latest_review_note'] ?? null,
            'latest_evidence_status'=>$row['latest_evidence_status'] ?? null,
            'quest'=>[
                'id'=>(string)$row['campaign_public_id'],
                'title'=>(string)$row['title'],
                'description'=>(string)($row['description'] ?? ''),
                'status'=>(string)$row['campaign_status'],
                'action_type'=>(string)($rules['action_type'] ?? ''),
                'verification_type'=>(string)($rules['verification_type'] ?? ''),
                'ends_at'=>$row['ends_at'],
                'url'=>'/loyalty-quest.php?campaign=' . rawurlencode($ref),
            ],
            'merchant'=>['name'=>(string)$row['merchant_name']],
            'reward'=>[
                'title'=>$row['reward_title'] ?? null,
                'wallet_item_id'=>$row['wallet_item_public_id'] ?? null,
                'wallet_status'=>$row['wallet_item_status'] ?? null,
                'expires_at'=>$row['wallet_expires_at'] ?? null,
            ],
        ];
    }
    mg_ok(['participations'=>$items,'totals'=>$totals,'filter'=>$status,'schema_ready'=>true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'account.loyalty_quests.unavailable', 'Participant quest portfolio is unavailable.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_ok(['participations'=>[],'totals'=>['total'=>0,'joined'=>0,'in_progress'=>0,'pending_review'=>0,'completed'=>0,'rejected'=>0,'cancelled'=>0],'filter'=>$status,'schema_ready'=>false], 'Quest portfolio is temporarily unavailable.');
}
