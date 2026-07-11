<?php
declare(strict_types=1);
require_once __DIR__ . '/_participant.php';
require_once __DIR__ . '/_integrity.php';
require_once dirname(__DIR__, 2) . '/communications/_loyalty_quest_notifications.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);
$ref = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? $input['slug'] ?? '')));
$latitude = is_numeric($input['latitude'] ?? null) ? (float)$input['latitude'] : null;
$longitude = is_numeric($input['longitude'] ?? null) ? (float)$input['longitude'] : null;
if ($ref === '' || mb_strlen($ref) > 160) mg_fail('Invalid Loyalty Quest.', 422);
if (($latitude === null) !== ($longitude === null) || ($latitude !== null && ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180))) {
    mg_fail('Invalid enrollment location.', 422);
}
$pdo = mg_db();
$integrityContext = mg_lqi_gate_request($pdo, (int)$user['id'], 'start', $ref);
$pdo->beginTransaction();
try {
    $campaign = mg_lqp_campaign($pdo, $ref, true);
    mg_lqi_record_attempt($pdo, $campaign, (int)$user['id'], 'start', $integrityContext, 'allowed');
    $existing = $pdo->prepare('SELECT * FROM loyalty_quest_participations WHERE campaign_id=? AND participant_user_id=? LIMIT 1 FOR UPDATE');
    $existing->execute([(int)$campaign['id'], (int)$user['id']]);
    $participation = $existing->fetch(PDO::FETCH_ASSOC);
    $created = false;

    if (!$participation) {
        $audience = mg_lqp_audience_require($pdo, $campaign, $user, $input);
        $contact = mg_lqp_contact($pdo, $campaign, $user);
        $publicId = mg_lqp_uuid();
        $required = mg_lqp_required_count($campaign);
        $metadata = [
            'action_type'=>(string)($campaign['rules']['action_type'] ?? ''),
            'verification_type'=>(string)($campaign['rules']['verification_type'] ?? ''),
            'joined_from'=>'public_quest_page',
            'audience'=>$audience,
            'integrity_device_hash'=>$integrityContext['device_hash'],
        ];
        $pdo->prepare("INSERT INTO loyalty_quest_participations (public_id,campaign_id,merchant_user_id,participant_user_id,contact_id,status,progress_count,required_count,completion_percent,joined_at,started_at,last_activity_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,'in_progress',0,?,0,NOW(),NOW(),NOW(),?,NOW(),NOW())")
            ->execute([$publicId,(int)$campaign['id'],(int)$campaign['merchant_user_id'],(int)$user['id'],(int)$contact['id'],$required,json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        $existing->execute([(int)$campaign['id'], (int)$user['id']]);
        $participation = $existing->fetch(PDO::FETCH_ASSOC);
        $created = true;
        mg_lqp_event($pdo, $campaign, null, (int)$contact['id'], 'quest.joined', ['participation_id'=>$publicId,'action_type'=>$metadata['action_type'],'verification_type'=>$metadata['verification_type'],'audience'=>$audience]);
        mg_lqn_notify_participant($pdo, 'participant_joined', $campaign, (int)$user['id'], ['participation_id'=>$publicId,'source_public_id'=>$publicId]);
        mg_lqn_notify_merchant($pdo, 'merchant_participant_joined', $campaign, ['participation_id'=>$publicId,'source_public_id'=>$publicId]);
        mg_audit('participant.loyalty_quest_joined', 'loyalty_quest_participation', ['campaign_id'=>(string)$campaign['public_id'],'participation_id'=>$publicId], (int)$user['id']);
    } else {
        $contact = mg_lqp_contact($pdo, $campaign, $user);
        if (in_array((string)$participation['status'], ['cancelled','rejected'], true)) {
            $pdo->prepare("UPDATE loyalty_quest_participations SET status='in_progress',started_at=COALESCE(started_at,NOW()),cancelled_at=NULL,last_activity_at=NOW(),updated_at=NOW() WHERE id=? AND participant_user_id=?")
                ->execute([(int)$participation['id'], (int)$user['id']]);
            $existing->execute([(int)$campaign['id'], (int)$user['id']]);
            $participation = $existing->fetch(PDO::FETCH_ASSOC);
            mg_lqp_event($pdo, $campaign, null, (int)$contact['id'], 'quest.resumed', ['participation_id'=>(string)$participation['public_id']]);
        }
    }

    $pdo->commit();
    mg_ok([
        'participation'=>[
            'id'=>(string)$participation['public_id'],
            'status'=>(string)$participation['status'],
            'progress_count'=>(int)$participation['progress_count'],
            'required_count'=>(int)$participation['required_count'],
            'completion_percent'=>(int)$participation['completion_percent'],
        ],
        'campaign'=>[
            'id'=>(string)$campaign['public_id'],
            'title'=>(string)$campaign['title'],
            'action_type'=>(string)($campaign['rules']['action_type'] ?? ''),
            'verification_type'=>(string)($campaign['rules']['verification_type'] ?? ''),
        ],
        'created'=>$created,
    ], $created ? 'Loyalty Quest started.' : 'Loyalty Quest is ready to continue.', $created ? 201 : 200);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'public.loyalty_quest.start_failed', 'Unable to start Loyalty Quest.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to start Loyalty Quest.', 500);
}
