<?php
declare(strict_types=1);

function mg_lqi_schema_ready(PDO $pdo): bool
{
    try {
        $required = [
            'loyalty_quest_integrity_attempts' => ['participant_user_id','action_type','ip_hash','device_hash','created_at'],
            'loyalty_quest_integrity_signals' => ['campaign_id','participant_user_id','signal_type','severity','score','status'],
            'loyalty_quest_evidence' => ['evidence_fingerprint','ip_hash','device_hash','integrity_score','integrity_status'],
        ];
        foreach ($required as $table => $columns) {
            $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $stmt->execute([$table]);
            $found = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            foreach ($columns as $column) if (!in_array($column, $found, true)) return false;
        }
        return true;
    } catch (Throwable) {
        return false;
    }
}

function mg_lqi_require_schema(PDO $pdo): void
{
    if (mg_lqi_schema_ready($pdo)) return;
    mg_security_log('critical','loyalty_quest.integrity_schema_missing','Loyalty Quest integrity controls are not installed.');
    mg_fail('Quest integrity service is temporarily unavailable.',503);
}

function mg_lqi_pepper(): string
{
    $pepper = trim((string)(getenv('MG_LOYALTY_QUEST_INTEGRITY_PEPPER') ?: ''));
    if ($pepper === '') $pepper = trim((string)mg_config_value('security','claim_code_pepper',''));
    if (strlen($pepper) < 24) {
        mg_security_log('critical','loyalty_quest.integrity_pepper_missing','Loyalty Quest integrity pepper is not configured.');
        mg_fail('Quest integrity service is temporarily unavailable.',503);
    }
    return $pepper;
}

function mg_lqi_hash(string $scope,string $value): string
{
    return hash_hmac('sha256',$scope.'|'.$value,mg_lqi_pepper());
}

function mg_lqi_device_token(): string
{
    $name='mg_lq_device';
    $token=strtolower(trim((string)($_COOKIE[$name]??'')));
    if (preg_match('/^[a-f0-9]{64}$/',$token)===1) return $token;
    $token=bin2hex(random_bytes(32));
    if (!headers_sent()) {
        $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||((bool)mg_config_value('app','trust_proxy',false)&&strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https');
        setcookie($name,$token,['expires'=>time()+31536000,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    }
    $_COOKIE[$name]=$token;
    return $token;
}

function mg_lqi_request_context(int $userId,string $action,string $campaignRef): array
{
    $ip=(string)(mg_client_ip()??'unknown');
    $ipHash=mg_lqi_hash('ip',$ip);
    $deviceHash=mg_lqi_hash('device',mg_lqi_device_token());
    $requestHash=mg_lqi_hash('request',$action.'|'.$campaignRef.'|'.$userId.'|'.mg_request_id());
    return ['ip_hash'=>$ipHash,'device_hash'=>$deviceHash,'request_hash'=>$requestHash];
}

function mg_lqi_gate_request(PDO $pdo,int $userId,string $action,string $campaignRef): array
{
    mg_lqi_require_schema($pdo);
    if (!in_array($action,['start','submit'],true)) throw new InvalidArgumentException('Invalid integrity action.');
    $context=mg_lqi_request_context($userId,$action,$campaignRef);
    if ($action==='start') {
        mg_rate_limit('loyalty_quest.start.user',(string)$userId,20,600);
        mg_rate_limit('loyalty_quest.start.ip',$context['ip_hash'],80,600);
        mg_rate_limit('loyalty_quest.start.device',$context['device_hash'],40,600);
    } else {
        mg_rate_limit('loyalty_quest.submit.user',(string)$userId,10,600);
        mg_rate_limit('loyalty_quest.submit.ip',$context['ip_hash'],40,600);
        mg_rate_limit('loyalty_quest.submit.device',$context['device_hash'],20,600);
    }
    return $context;
}

function mg_lqi_record_attempt(PDO $pdo,array $campaign,int $userId,string $action,array $context,string $outcome='allowed'): string
{
    $publicId=mg_public_uuid();
    $pdo->prepare('INSERT INTO loyalty_quest_integrity_attempts (public_id,campaign_id,merchant_user_id,participant_user_id,action_type,outcome,ip_hash,device_hash,request_hash,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([$publicId,(int)$campaign['id'],(int)$campaign['merchant_user_id'],$userId,$action,$outcome,$context['ip_hash'],$context['device_hash'],$context['request_hash']]);
    return $publicId;
}

function mg_lqi_normalized_text(string $value,int $limit=1000): string
{
    $value=mb_strtolower(trim($value));
    $value=preg_replace('/\s+/u',' ',$value)??'';
    return mb_substr($value,0,$limit);
}

function mg_lqi_evidence_fingerprint(array $evidence): ?string
{
    $strong=[];
    $reference=mg_lqi_normalized_text((string)($evidence['reference_id']??''),190);
    $proofUrl=mg_lqi_normalized_text((string)($evidence['proof_url']??''),700);
    if ($reference!=='') $strong['reference_id']=$reference;
    if ($proofUrl!=='') $strong['proof_url']=$proofUrl;
    if ($strong===[]) return null;
    ksort($strong);
    return mg_lqi_hash('evidence',json_encode($strong,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}

function mg_lqi_signal(string $type,string $severity,int $score,string $source,array $context=[]): array
{
    return ['type'=>$type,'severity'=>$severity,'score'=>max(0,min(100,$score)),'source_hash'=>mg_lqi_hash('signal',$type.'|'.$source),'context'=>$context];
}

function mg_lqi_minimum_seconds(array $campaign,array $evidence): int
{
    $configured=(int)($campaign['rules']['minimum_completion_seconds']??0);
    if ($configured>0) return max(5,min(86400,$configured));
    return match ((string)($evidence['verification_type']??'')) {
        'signed_qr','static_qr','staff_confirmation','event_check_in' => 10,
        'geolocation' => 30,
        'purchase_record','microgifter_transaction' => 60,
        default => 20,
    };
}

function mg_lqi_evaluate(PDO $pdo,array $campaign,array $participation,array $user,array $evidence,array $requestContext): array
{
    $signals=[];
    $campaignId=(int)$campaign['id'];
    $merchantId=(int)$campaign['merchant_user_id'];
    $userId=(int)$user['id'];
    $fingerprint=mg_lqi_evidence_fingerprint($evidence);

    if ($fingerprint!==null) {
        $stmt=$pdo->prepare("SELECT participant_user_id,public_id FROM loyalty_quest_evidence WHERE campaign_id=? AND merchant_user_id=? AND evidence_fingerprint=? AND status<>'rejected' AND participant_user_id<>? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$campaignId,$merchantId,$fingerprint,$userId]);
        if ($duplicate=$stmt->fetch(PDO::FETCH_ASSOC)) {
            $signals[]=mg_lqi_signal('duplicate_evidence','critical',90,$fingerprint,['matched_evidence_id'=>(string)$duplicate['public_id']]);
        }
    }

    $stmt=$pdo->prepare("SELECT COUNT(DISTINCT participant_user_id) FROM loyalty_quest_integrity_attempts WHERE campaign_id=? AND action_type='submit' AND ip_hash=? AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)");
    $stmt->execute([$campaignId,$requestContext['ip_hash']]);
    $ipUsers=(int)$stmt->fetchColumn();
    if ($ipUsers>=5) $signals[]=mg_lqi_signal('shared_ip_velocity','high',35,$requestContext['ip_hash'].'|'.gmdate('Y-m-d'),['distinct_participants'=>$ipUsers,'window_hours'=>24]);

    $stmt=$pdo->prepare("SELECT COUNT(DISTINCT participant_user_id) FROM loyalty_quest_integrity_attempts WHERE device_hash=? AND action_type='submit' AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)");
    $stmt->execute([$requestContext['device_hash']]);
    $deviceUsers=(int)$stmt->fetchColumn();
    if ($deviceUsers>=3) $signals[]=mg_lqi_signal('shared_device_velocity','high',45,$requestContext['device_hash'].'|'.gmdate('Y-m-d'),['distinct_participants'=>$deviceUsers,'window_hours'=>24]);

    $joinedAt=strtotime((string)($participation['joined_at']??$participation['started_at']??''))?:time();
    $elapsed=max(0,time()-$joinedAt);
    $minimum=mg_lqi_minimum_seconds($campaign,$evidence);
    if ($elapsed<$minimum) $signals[]=mg_lqi_signal('rapid_completion','medium',25,(string)$participation['public_id'].'|'.$minimum,['elapsed_seconds'=>$elapsed,'minimum_seconds'=>$minimum]);

    $stmt=$pdo->prepare("SELECT COUNT(*) FROM loyalty_quest_evidence WHERE participant_user_id=? AND status='rejected' AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)");
    $stmt->execute([$userId]);
    $rejections=(int)$stmt->fetchColumn();
    if ($rejections>=3) $signals[]=mg_lqi_signal('rejection_history','medium',20,(string)$userId.'|'.gmdate('Y-m'),['rejected_evidence'=>$rejections,'window_days'=>30]);

    $stmt=$pdo->prepare("SELECT COUNT(*) FROM loyalty_quest_participations WHERE participant_user_id=? AND status='completed' AND completed_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)");
    $stmt->execute([$userId]);
    $completions=(int)$stmt->fetchColumn();
    if ($completions>=8) $signals[]=mg_lqi_signal('reward_velocity','high',40,(string)$userId.'|'.gmdate('Y-m-d'),['completed_quests'=>$completions,'window_hours'=>24]);

    if (($evidence['verification_type']??'')==='geolocation'&&$evidence['latitude']!==null&&$evidence['longitude']!==null) {
        $stmt=$pdo->prepare("SELECT latitude,longitude,verified_at FROM loyalty_quest_evidence WHERE participant_user_id=? AND status='verified' AND evidence_type='geolocation' AND verified_at>=DATE_SUB(NOW(),INTERVAL 2 HOUR) ORDER BY verified_at DESC,id DESC LIMIT 1");
        $stmt->execute([$userId]);
        if ($previous=$stmt->fetch(PDO::FETCH_ASSOC)) {
            $seconds=max(1,time()-(strtotime((string)$previous['verified_at'])?:time()));
            $meters=mg_lqp_distance_meters((float)$previous['latitude'],(float)$previous['longitude'],(float)$evidence['latitude'],(float)$evidence['longitude']);
            $speedKph=($meters/1000)/($seconds/3600);
            if ($meters>=50000&&$speedKph>300) $signals[]=mg_lqi_signal('impossible_travel','critical',90,(string)$userId.'|'.gmdate('Y-m-d-H'),['distance_km'=>round($meters/1000,1),'elapsed_seconds'=>$seconds,'speed_kph'=>round($speedKph,1)]);
        }
    }

    if (!empty($evidence['code_hash'])&&in_array((string)($evidence['verification_type']??''),['static_qr','staff_confirmation','event_check_in'],true)) {
        $stmt=$pdo->prepare("SELECT COUNT(DISTINCT participant_user_id) FROM loyalty_quest_evidence WHERE campaign_id=? AND code_hash=? AND created_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
        $stmt->execute([$campaignId,(string)$evidence['code_hash']]);
        $codeUsers=(int)$stmt->fetchColumn();
        if ($codeUsers>=20) $signals[]=mg_lqi_signal('code_velocity','high',35,(string)$evidence['code_hash'].'|'.gmdate('Y-m-d-H'),['distinct_participants'=>$codeUsers,'window_minutes'=>15]);
    }

    $score=0;$critical=false;
    foreach($signals as $signal){$score=min(100,$score+(int)$signal['score']);if($signal['severity']==='critical')$critical=true;}
    $decision=$critical||$score>=50?'review':'allow';
    return ['decision'=>$decision,'score'=>$score,'status'=>$decision==='review'?'review':'clear','signals'=>$signals,'evidence_fingerprint'=>$fingerprint,'ip_hash'=>$requestContext['ip_hash'],'device_hash'=>$requestContext['device_hash']];
}

function mg_lqi_persist_signals(PDO $pdo,array $campaign,array $participation,array $user,int $evidenceId,array $integrity): array
{
    $ids=[];
    foreach($integrity['signals'] as $signal){
        $publicId=mg_public_uuid();
        $stmt=$pdo->prepare("INSERT INTO loyalty_quest_integrity_signals (public_id,campaign_id,merchant_user_id,participant_user_id,participation_id,evidence_id,signal_type,severity,score,status,source_hash,context_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,'open',?,?,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),score=GREATEST(score,VALUES(score)),severity=VALUES(severity),context_json=VALUES(context_json),updated_at=NOW()");
        $stmt->execute([$publicId,(int)$campaign['id'],(int)$campaign['merchant_user_id'],(int)$user['id'],(int)$participation['id'],$evidenceId,$signal['type'],$signal['severity'],$signal['score'],$signal['source_hash'],json_encode($signal['context'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        $signalId=(int)$pdo->lastInsertId();
        $lookup=$pdo->prepare('SELECT public_id FROM loyalty_quest_integrity_signals WHERE id=? LIMIT 1');
        $lookup->execute([$signalId]);
        $ids[]=(string)($lookup->fetchColumn()?:$publicId);
    }
    return $ids;
}

function mg_lqi_security_event(array $campaign,array $participation,array $integrity,int $userId): void
{
    if (($integrity['score']??0)<50) return;
    mg_security_log(
        ($integrity['score']??0)>=90?'critical':'warning',
        'loyalty_quest.integrity_review_required',
        'Loyalty Quest evidence was routed to integrity review.',
        ['campaign_id'=>(string)$campaign['public_id'],'participation_id'=>(string)$participation['public_id'],'integrity_score'=>(int)$integrity['score'],'signal_types'=>array_values(array_map(static fn(array $signal):string=>(string)$signal['type'],$integrity['signals']))],
        $userId
    );
}
