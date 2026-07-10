<?php
declare(strict_types=1);
require_once __DIR__ . '/_participant.php';

function mg_lqp_safe_proof_url(mixed $value): ?string
{
    $url=trim((string)$value);
    if($url==='')return null;
    if(strlen($url)>700||filter_var($url,FILTER_VALIDATE_URL)===false)mg_fail('Invalid proof URL.',422);
    $parts=parse_url($url);
    if(!is_array($parts)||!in_array(strtolower((string)($parts['scheme']??'')),['https','http'],true)||empty($parts['host']))mg_fail('Invalid proof URL.',422);
    return $url;
}

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$ref=strtolower(trim((string)($input['campaign_id']??$input['campaign']??'')));
$participationRef=strtolower(trim((string)($input['participation_id']??'')));
$code=trim((string)($input['code']??$input['qr_code']??''));
$proofNote=trim((string)($input['proof_note']??''));
$proofUrl=mg_lqp_safe_proof_url($input['proof_url']??'');
$referenceId=trim((string)($input['reference_id']??''));
$latitude=is_numeric($input['latitude']??null)?(float)$input['latitude']:null;
$longitude=is_numeric($input['longitude']??null)?(float)$input['longitude']:null;
$accuracy=is_numeric($input['accuracy_meters']??null)?max(0.0,(float)$input['accuracy_meters']):null;
if($ref===''||mb_strlen($ref)>160||($participationRef!==''&&(strlen($participationRef)!==36||preg_match('/^[a-f0-9-]{36}$/',$participationRef)!==1))||mb_strlen($proofNote)>4000||mb_strlen($referenceId)>190)mg_fail('Invalid quest submission.',422);
if(($latitude===null)!==($longitude===null)||($latitude!==null&&($latitude < -90||$latitude > 90||$longitude < -180||$longitude > 180)))mg_fail('Invalid location evidence.',422);

$pdo=mg_db();
$pdo->beginTransaction();
try{
    $campaign=mg_lqp_campaign($pdo,$ref,true);
    $contact=mg_lqp_contact($pdo,$campaign,$user);
    $find=$pdo->prepare('SELECT * FROM loyalty_quest_participations WHERE campaign_id=? AND participant_user_id=? LIMIT 1 FOR UPDATE');
    $find->execute([(int)$campaign['id'],(int)$user['id']]);
    $participation=$find->fetch(PDO::FETCH_ASSOC);
    if(!$participation)mg_fail('Start this Loyalty Quest before submitting completion evidence.',409);
    if($participationRef!==''&&!hash_equals((string)$participation['public_id'],$participationRef))mg_fail('Participation does not match this account.',403);
    if((string)$participation['status']==='completed'){
        $reward=mg_lqp_issue_reward($pdo,$campaign,$contact,$participation,$user);
        $pdo->commit();mg_ok(['participation_id'=>(string)$participation['public_id'],'status'=>'completed','reward'=>$reward],'Loyalty Quest was already completed.');
    }
    if((string)$participation['status']==='pending_review')mg_fail('This completion is already awaiting merchant review.',409);

    $rules=$campaign['rules'];
    $verification=(string)($rules['verification_type']??'manual_review');
    $action=(string)($rules['action_type']??'milestone');
    $evidenceType='note';$verified=false;$distance=null;$codeHash=null;$nonceHash=null;

    if(in_array($verification,['signed_qr','static_qr'],true)){
        if($code==='')mg_fail('Scan the quest QR code or enter its completion code.',422);
        $expected=(string)($rules['qr_code_token']??$campaign['qr_code_token']??$rules['completion_code']??'');
        $expectedHash=(string)($rules['completion_code_hash']??'');
        $codeHash=hash('sha256',$code);
        $valid=$expected!==''&&hash_equals($expected,$code);
        if(!$valid&&$expectedHash!=='')$valid=hash_equals($expectedHash,$codeHash);
        if(!$valid&&$verification==='signed_qr'){
            $parts=explode('.',$code);
            if(count($parts)===3){
                [$payload,$nonce,$signature]=$parts;
                $secret=(string)($rules['qr_signing_secret']??$campaign['qr_code_token']??'');
                $valid=$secret!==''&&hash_equals(hash_hmac('sha256',$payload.'.'.$nonce,$secret),$signature);
                $nonceHash=hash('sha256',$nonce);
            }
        }
        if(!$valid)mg_fail('The quest completion code is invalid.',422);
        $replay=$pdo->prepare('INSERT INTO loyalty_quest_code_uses (campaign_id,participant_user_id,code_hash,nonce_hash,used_at) VALUES (?,?,?,?,NOW())');
        try{$replay->execute([(int)$campaign['id'],(int)$user['id'],$codeHash,$nonceHash]);}catch(PDOException){mg_fail('This quest code has already been used.',409);}
        $evidenceType=$verification==='signed_qr'?'qr':'manual_code';$verified=true;
    }elseif($verification==='geolocation'){
        if($latitude===null||$longitude===null)mg_fail('Allow location access to verify this quest.',422);
        if($campaign['location_latitude']===null||$campaign['location_longitude']===null)mg_fail('Merchant location verification is not configured.',409);
        $distance=mg_lqp_distance_meters($latitude,$longitude,(float)$campaign['location_latitude'],(float)$campaign['location_longitude']);
        $radius=max(25,min(5000,(int)($rules['radius_meters']??$campaign['location_radius']??150)));
        $accuracyLimit=max(25,min(1000,(int)($rules['maximum_accuracy_meters']??250)));
        if($accuracy!==null&&$accuracy>$accuracyLimit)mg_fail('Location accuracy is too low. Move closer and try again.',422);
        if($distance>$radius)mg_fail('You are outside the allowed quest location.',422);
        $evidenceType='geolocation';$verified=true;
    }elseif(in_array($verification,['purchase_record','microgifter_transaction'],true)){
        if($referenceId==='')mg_fail('A purchase or Microgifter transaction reference is required.',422);
        $evidenceType='purchase';$verified=false;
    }elseif($verification==='staff_confirmation'){
        if($code==='')mg_fail('Enter the staff confirmation code.',422);
        $expectedHash=(string)($rules['staff_confirmation_code_hash']??'');
        $codeHash=hash('sha256',strtoupper($code));
        if($expectedHash===''||!hash_equals($expectedHash,$codeHash))mg_fail('Staff confirmation code is invalid.',422);
        $evidenceType='staff_confirmation';$verified=true;
    }elseif($verification==='event_checkin'){
        if($code==='')mg_fail('Enter the event check-in code.',422);
        $expectedHash=(string)($rules['event_checkin_code_hash']??'');
        $codeHash=hash('sha256',strtoupper($code));
        if($expectedHash===''||!hash_equals($expectedHash,$codeHash))mg_fail('Event check-in code is invalid.',422);
        $evidenceType='event_checkin';$verified=true;
    }else{
        if($proofNote===''&&$proofUrl===null&&$referenceId==='')mg_fail('Add proof or a completion note for merchant review.',422);
        $evidenceType=match($action){'referral'=>'referral','social_action'=>'social','milestone','sequence','multi_location'=>'milestone',default=>'receipt'};
        $verified=false;
    }

    $evidenceId=mg_lqp_uuid();
    $evidenceStatus=$verified?'verified':'submitted';
    $metadata=['action_type'=>$action,'verification_type'=>$verification,'user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255),'ip'=>mg_client_ip()];
    $pdo->prepare('INSERT INTO loyalty_quest_evidence (public_id,participation_id,campaign_id,merchant_user_id,participant_user_id,evidence_type,status,code_hash,latitude,longitude,accuracy_meters,distance_meters,proof_url,proof_note,reference_id,verified_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
        ->execute([$evidenceId,(int)$participation['id'],(int)$campaign['id'],(int)$campaign['merchant_user_id'],(int)$user['id'],$evidenceType,$evidenceStatus,$codeHash,$latitude,$longitude,$accuracy,$distance,$proofUrl,$proofNote!==''?$proofNote:null,$referenceId!==''?$referenceId:null,$verified?gmdate('Y-m-d H:i:s'):null,json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);

    if(!$verified){
        $pdo->prepare("UPDATE loyalty_quest_participations SET status='pending_review',submitted_at=NOW(),last_activity_at=NOW(),updated_at=NOW() WHERE id=? AND participant_user_id=?")
            ->execute([(int)$participation['id'],(int)$user['id']]);
        mg_lqp_event($pdo,$campaign,null,(int)$contact['id'],'quest.evidence_submitted',['participation_id'=>(string)$participation['public_id'],'evidence_id'=>$evidenceId,'verification_type'=>$verification]);
        $pdo->commit();
        mg_ok(['participation_id'=>(string)$participation['public_id'],'status'=>'pending_review','evidence_id'=>$evidenceId,'reward'=>null],'Completion submitted for merchant review.',202);
    }

    $newProgress=min((int)$participation['required_count'],(int)$participation['progress_count']+1);
    $percent=(int)round(100*$newProgress/max(1,(int)$participation['required_count']));
    $pdo->prepare("UPDATE loyalty_quest_participations SET status='in_progress',progress_count=?,completion_percent=?,submitted_at=NOW(),last_activity_at=NOW(),updated_at=NOW() WHERE id=? AND participant_user_id=?")
        ->execute([$newProgress,$percent,(int)$participation['id'],(int)$user['id']]);
    $find->execute([(int)$campaign['id'],(int)$user['id']]);$participation=$find->fetch(PDO::FETCH_ASSOC);
    mg_lqp_event($pdo,$campaign,null,(int)$contact['id'],'quest.evidence_verified',['participation_id'=>(string)$participation['public_id'],'evidence_id'=>$evidenceId,'progress_count'=>$newProgress,'required_count'=>(int)$participation['required_count']]);
    if($newProgress<(int)$participation['required_count']){
        $pdo->commit();mg_ok(['participation_id'=>(string)$participation['public_id'],'status'=>'in_progress','progress_count'=>$newProgress,'required_count'=>(int)$participation['required_count'],'completion_percent'=>$percent,'reward'=>null],'Quest progress verified.');
    }
    $reward=mg_lqp_issue_reward($pdo,$campaign,$contact,$participation,$user);
    $pdo->commit();
    mg_ok(['participation_id'=>(string)$participation['public_id'],'status'=>'completed','progress_count'=>$newProgress,'required_count'=>(int)$participation['required_count'],'completion_percent'=>100,'evidence_id'=>$evidenceId,'reward'=>$reward],'Loyalty Quest completed and reward issued.',201);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','public.loyalty_quest.submit_failed','Unable to submit Loyalty Quest completion.',['exception_class'=>$error::class],(int)$user['id']);
    mg_fail('Unable to submit Loyalty Quest completion.',500);
}
