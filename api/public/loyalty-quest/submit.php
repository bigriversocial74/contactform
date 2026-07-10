<?php
declare(strict_types=1);
require_once __DIR__ . '/_participant.php';
require_once __DIR__ . '/_verification.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);
$ref = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? '')));
$participationRef = strtolower(trim((string)($input['participation_id'] ?? '')));
if ($ref === '' || mb_strlen($ref) > 160) mg_fail('Invalid Loyalty Quest.', 422);
if ($participationRef !== '' && (strlen($participationRef) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $participationRef) !== 1)) mg_fail('Invalid quest participation.', 422);

$pdo = mg_db();
$pdo->beginTransaction();
try {
    $campaign = mg_lqp_campaign($pdo, $ref, true);
    $contact = mg_lqp_contact($pdo, $campaign, $user);
    $find = $pdo->prepare('SELECT * FROM loyalty_quest_participations WHERE campaign_id=? AND participant_user_id=? LIMIT 1 FOR UPDATE');
    $find->execute([(int)$campaign['id'], (int)$user['id']]);
    $participation = $find->fetch(PDO::FETCH_ASSOC);
    if (!$participation) mg_fail('Start this Loyalty Quest before submitting completion evidence.', 409);
    if ($participationRef !== '' && !hash_equals((string)$participation['public_id'], $participationRef)) mg_fail('Participation does not match this account.', 403);
    if ((string)$participation['status'] === 'completed') {
        $reward = mg_lqp_issue_reward($pdo, $campaign, $contact, $participation, $user);
        $pdo->commit();
        mg_ok(['participation_id'=>(string)$participation['public_id'],'status'=>'completed','reward'=>$reward], 'Loyalty Quest was already completed.');
    }
    if ((string)$participation['status'] === 'pending_review') mg_fail('This completion is already awaiting merchant review.', 409);
    if ((string)$participation['status'] === 'cancelled') mg_fail('Resume this Loyalty Quest before submitting completion evidence.', 409);

    mg_lqp_enforce_cooldown($pdo, $campaign, $participation);
    $evidence = mg_lqv_resolve($pdo, $campaign, $participation, $user, $input);
    if (!empty($evidence['verified'])) mg_lqp_enforce_daily_limit($pdo, $campaign);

    $evidenceId = mg_lqp_uuid();
    $evidenceStatus = !empty($evidence['verified']) ? 'verified' : 'submitted';
    $metadata = [
        'action_type'=>(string)$evidence['action_type'],
        'verification_type'=>(string)$evidence['verification_type'],
        'user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'ip_hash'=>hash('sha256', mg_client_ip()),
        'nonce_hash'=>$evidence['nonce_hash'],
        'signed_payload'=>$evidence['signed_payload'],
    ];
    $pdo->prepare('INSERT INTO loyalty_quest_evidence (public_id,participation_id,campaign_id,merchant_user_id,participant_user_id,evidence_type,status,code_hash,latitude,longitude,accuracy_meters,distance_meters,proof_url,proof_note,reference_id,verified_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
        ->execute([
            $evidenceId,(int)$participation['id'],(int)$campaign['id'],(int)$campaign['merchant_user_id'],(int)$user['id'],
            (string)$evidence['evidence_type'],$evidenceStatus,$evidence['code_hash'],$evidence['latitude'],$evidence['longitude'],
            $evidence['accuracy_meters'],$evidence['distance_meters'],$evidence['proof_url'],$evidence['proof_note'],$evidence['reference_id'],
            !empty($evidence['verified']) ? gmdate('Y-m-d H:i:s') : null,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

    if (empty($evidence['verified'])) {
        $pdo->prepare("UPDATE loyalty_quest_participations SET status='pending_review',submitted_at=NOW(),last_activity_at=NOW(),updated_at=NOW() WHERE id=? AND participant_user_id=?")
            ->execute([(int)$participation['id'], (int)$user['id']]);
        mg_lqp_event($pdo, $campaign, null, (int)$contact['id'], 'quest.evidence_submitted', ['participation_id'=>(string)$participation['public_id'],'evidence_id'=>$evidenceId,'verification_type'=>(string)$evidence['verification_type']]);
        mg_audit('participant.loyalty_quest_evidence_submitted', 'loyalty_quest_evidence', ['campaign_id'=>(string)$campaign['public_id'],'participation_id'=>(string)$participation['public_id'],'evidence_id'=>$evidenceId], (int)$user['id']);
        $pdo->commit();
        mg_ok(['participation_id'=>(string)$participation['public_id'],'status'=>'pending_review','evidence_id'=>$evidenceId,'reward'=>null], 'Completion submitted for merchant review.', 202);
    }

    $newProgress = min((int)$participation['required_count'], (int)$participation['progress_count'] + 1);
    $percent = (int)round(100 * $newProgress / max(1, (int)$participation['required_count']));
    $pdo->prepare("UPDATE loyalty_quest_participations SET status='in_progress',progress_count=?,completion_percent=?,submitted_at=NOW(),last_activity_at=NOW(),updated_at=NOW() WHERE id=? AND participant_user_id=?")
        ->execute([$newProgress,$percent,(int)$participation['id'],(int)$user['id']]);
    $find->execute([(int)$campaign['id'], (int)$user['id']]);
    $participation = $find->fetch(PDO::FETCH_ASSOC);
    mg_lqp_event($pdo, $campaign, null, (int)$contact['id'], 'quest.evidence_verified', ['participation_id'=>(string)$participation['public_id'],'evidence_id'=>$evidenceId,'progress_count'=>$newProgress,'required_count'=>(int)$participation['required_count']]);
    mg_audit('participant.loyalty_quest_evidence_verified', 'loyalty_quest_evidence', ['campaign_id'=>(string)$campaign['public_id'],'participation_id'=>(string)$participation['public_id'],'evidence_id'=>$evidenceId], (int)$user['id']);
    if ($newProgress < (int)$participation['required_count']) {
        $pdo->commit();
        mg_ok(['participation_id'=>(string)$participation['public_id'],'status'=>'in_progress','progress_count'=>$newProgress,'required_count'=>(int)$participation['required_count'],'completion_percent'=>$percent,'reward'=>null], 'Quest progress verified.');
    }

    $reward = mg_lqp_issue_reward($pdo, $campaign, $contact, $participation, $user);
    $pdo->commit();
    mg_ok(['participation_id'=>(string)$participation['public_id'],'status'=>'completed','progress_count'=>$newProgress,'required_count'=>(int)$participation['required_count'],'completion_percent'=>100,'evidence_id'=>$evidenceId,'reward'=>$reward], 'Loyalty Quest completed and reward issued.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'public.loyalty_quest.submit_failed', 'Unable to submit Loyalty Quest completion.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to submit Loyalty Quest completion.', 500);
}
