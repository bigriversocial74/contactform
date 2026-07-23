<?php
declare(strict_types=1);

/**
 * Microgifter privacy request, retention and account-erasure lifecycle.
 *
 * This layer deliberately separates account closure from irreversible erasure.
 * Commerce, gift-lifecycle, audit and merchant-controlled records are retained
 * only in minimized/anonymized form or routed to controller review.
 */

if (!function_exists('mg_privacy_uuid')) {
    function mg_privacy_uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }
}

function mg_privacy_hash_key(): string
{
    $key = trim((string) getenv('MG_PRIVACY_HASH_KEY'));
    if ($key !== '') return $key;
    if (function_exists('mg_config_value')) {
        $key = trim((string) mg_config_value('security', 'privacy_hash_key', ''));
        if ($key !== '') return $key;
        $key = trim((string) mg_config_value('app', 'app_key', ''));
        if ($key !== '') return $key;
    }
    return hash('sha256', __FILE__ . '|' . (string) ($_SERVER['SERVER_NAME'] ?? 'microgifter'));
}

function mg_privacy_identity_hash(string $email): string
{
    return hash_hmac('sha256', strtolower(trim($email)), mg_privacy_hash_key());
}

function mg_privacy_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $stmt->execute([$table]);
    return $cache[$key] = (bool) $stmt->fetchColumn();
}

function mg_privacy_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table . ':' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
    $stmt->execute([$table, $column]);
    return $cache[$key] = (bool) $stmt->fetchColumn();
}

function mg_privacy_add_business_days(DateTimeImmutable $date, int $days): DateTimeImmutable
{
    $cursor = $date;
    $added = 0;
    while ($added < $days) {
        $cursor = $cursor->modify('+1 day');
        if ((int) $cursor->format('N') < 6) $added++;
    }
    return $cursor;
}

function mg_privacy_deadlines(string $jurisdiction, ?DateTimeImmutable $requestedAt = null): array
{
    $requestedAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    return match ($jurisdiction) {
        'eu_eea', 'uk' => [
            'acknowledgement_due_at' => $requestedAt,
            'response_due_at' => $requestedAt->modify('+28 days'),
            'grace_ends_at' => $requestedAt->modify('+14 days'),
        ],
        'california' => [
            'acknowledgement_due_at' => mg_privacy_add_business_days($requestedAt, 10),
            'response_due_at' => $requestedAt->modify('+45 days'),
            'grace_ends_at' => $requestedAt->modify('+30 days'),
        ],
        'other_us' => [
            'acknowledgement_due_at' => $requestedAt->modify('+10 days'),
            'response_due_at' => $requestedAt->modify('+45 days'),
            'grace_ends_at' => $requestedAt->modify('+30 days'),
        ],
        default => [
            'acknowledgement_due_at' => $requestedAt->modify('+7 days'),
            'response_due_at' => $requestedAt->modify('+30 days'),
            'grace_ends_at' => $requestedAt->modify('+30 days'),
        ],
    };
}

function mg_privacy_dt(DateTimeInterface $date): string
{
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function mg_privacy_event(PDO $pdo, int $requestId, string $eventType, array $details = [], ?int $actorUserId = null): void
{
    $stmt = $pdo->prepare('INSERT INTO privacy_request_events (request_id,actor_user_id,event_type,details_json,created_at) VALUES (?,?,?,?,NOW())');
    $stmt->execute([$requestId,$actorUserId,$eventType,json_encode($details,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
}

function mg_privacy_action(PDO $pdo, int $requestId, string $actionKey, ?string $table, string $type, string $status, int $rows = 0, ?string $basis = null, array $details = [], ?string $error = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO privacy_data_actions (request_id,action_key,table_name,action_type,status,row_count,legal_basis,error_message,details_json,started_at,completed_at,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,NOW(),IF(? IN ("completed","skipped","failed","retained_by_policy","blocked_by_hold"),NOW(),NULL),NOW(),NOW())
         ON DUPLICATE KEY UPDATE table_name=VALUES(table_name),action_type=VALUES(action_type),status=VALUES(status),row_count=VALUES(row_count),legal_basis=VALUES(legal_basis),error_message=VALUES(error_message),details_json=VALUES(details_json),started_at=COALESCE(started_at,NOW()),completed_at=VALUES(completed_at),updated_at=NOW()'
    );
    $stmt->execute([$requestId,$actionKey,$table,$type,$status,$rows,$basis,$error,json_encode($details,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$status]);
}

function mg_privacy_request_for_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM privacy_requests WHERE user_id=? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_privacy_request_by_id(PDO $pdo, int $requestId, bool $lock = false): ?array
{
    $sql = 'SELECT * FROM privacy_requests WHERE id=? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_privacy_active_hold(PDO $pdo, int $requestId, ?int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM privacy_legal_holds WHERE status="active" AND (request_id=? OR (? IS NOT NULL AND user_id=?)) ORDER BY id DESC LIMIT 1');
    $stmt->execute([$requestId,$userId,$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_privacy_verify_password(PDO $pdo, int $userId, string $password): void
{
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $hash = (string) ($stmt->fetchColumn() ?: '');
    if ($hash === '' || $password === '' || !password_verify($password, $hash)) {
        throw new RuntimeException('Password confirmation failed.');
    }
}

function mg_privacy_generate_merchant_handoffs(PDO $pdo, int $requestId, int $userId, string $dueAt): int
{
    if (!mg_privacy_table_exists($pdo, 'merchant_crm_contacts')) return 0;
    $stmt = $pdo->prepare('SELECT DISTINCT merchant_user_id FROM merchant_crm_contacts WHERE user_id=?');
    $stmt->execute([$userId]);
    $insert = $pdo->prepare('INSERT IGNORE INTO privacy_merchant_handoffs (request_id,merchant_user_id,status,due_at,created_at,updated_at) VALUES (?,? ,"pending",?,NOW(),NOW())');
    $count = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $merchantId) {
        $insert->execute([$requestId,(int)$merchantId,$dueAt]);
        $count += $insert->rowCount();
    }
    return $count;
}

function mg_privacy_restrict_account(PDO $pdo, int $requestId, int $userId): array
{
    $columns = ['status="disabled"','privacy_state="deletion_pending"','deletion_requested_at=COALESCE(deletion_requested_at,NOW())','privacy_restricted_at=COALESCE(privacy_restricted_at,NOW())','updated_at=NOW()'];
    if (mg_privacy_column_exists($pdo,'users','auth_version')) $columns[] = 'auth_version=auth_version+1';
    $pdo->prepare('UPDATE users SET '.implode(',',$columns).' WHERE id=?')->execute([$userId]);

    $revoked = 0;
    if (mg_privacy_table_exists($pdo,'user_sessions')) {
        $stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL');
        $stmt->execute([$userId]);
        $revoked = $stmt->rowCount();
    }
    foreach (['password_reset_tokens','email_verification_tokens'] as $table) {
        if (mg_privacy_table_exists($pdo,$table) && mg_privacy_column_exists($pdo,$table,'user_id')) {
            $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE user_id=?");
            $stmt->execute([$userId]);
            mg_privacy_action($pdo,$requestId,'restrict_'.$table,$table,'restrict','completed',$stmt->rowCount(),'Authentication credentials are invalidated immediately.');
        }
    }
    if (mg_privacy_table_exists($pdo,'public_profiles') && mg_privacy_column_exists($pdo,'public_profiles','user_id')) {
        $set = [];
        if (mg_privacy_column_exists($pdo,'public_profiles','visibility')) $set[]='visibility="private"';
        if (mg_privacy_column_exists($pdo,'public_profiles','status')) $set[]='status="disabled"';
        if (mg_privacy_column_exists($pdo,'public_profiles','published_at')) $set[]='published_at=NULL';
        if ($set) {
            $stmt=$pdo->prepare('UPDATE public_profiles SET '.implode(',',$set).' WHERE user_id=?');
            $stmt->execute([$userId]);
            mg_privacy_action($pdo,$requestId,'restrict_public_profiles','public_profiles','restrict','completed',$stmt->rowCount(),'Public identity is unpublished while the request is processed.');
        }
    }
    $pdo->prepare('UPDATE privacy_requests SET status="restricted",restricted_at=NOW(),decision="approve",updated_at=NOW() WHERE id=?')->execute([$requestId]);
    mg_privacy_action($pdo,$requestId,'restrict_account','users','restrict','completed',1,'Verified account closure request.', ['sessions_revoked'=>$revoked]);
    mg_privacy_event($pdo,$requestId,'account_restricted',['sessions_revoked'=>$revoked],$userId);
    return ['sessions_revoked'=>$revoked];
}

function mg_privacy_create_delete_request(PDO $pdo, array $user, array $input): array
{
    $userId = (int) ($user['id'] ?? 0);
    if ($userId < 1) throw new RuntimeException('Authentication required.');
    $jurisdiction = strtolower(trim((string)($input['jurisdiction'] ?? 'other')));
    if (!in_array($jurisdiction,['eu_eea','uk','california','other_us','other'],true)) $jurisdiction='other';
    if (strtoupper(trim((string)($input['confirmation'] ?? ''))) !== 'DELETE') throw new RuntimeException('Type DELETE to confirm the request.');
    mg_privacy_verify_password($pdo,$userId,(string)($input['password'] ?? ''));

    $existing = $pdo->prepare('SELECT id,public_id,status FROM privacy_requests WHERE user_id=? AND status NOT IN ("completed","denied","cancelled") ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $existing->execute([$userId]);
    if ($row=$existing->fetch(PDO::FETCH_ASSOC)) return $row;

    $userRow=$pdo->prepare('SELECT email FROM users WHERE id=? LIMIT 1 FOR UPDATE');
    $userRow->execute([$userId]);
    $email=(string)$userRow->fetchColumn();
    if ($email==='') throw new RuntimeException('Account identity could not be verified.');

    $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $deadlines=mg_privacy_deadlines($jurisdiction,$now);
    $finalGrace=$deadlines['grace_ends_at'] > $deadlines['response_due_at'] ? $deadlines['response_due_at'] : $deadlines['grace_ends_at'];
    $publicId=mg_privacy_uuid();
    $stmt=$pdo->prepare('INSERT INTO privacy_requests (public_id,user_id,request_type,jurisdiction,source,status,contact_email,contact_email_hash,verification_method,requested_at,acknowledgement_due_at,acknowledged_at,identity_verified_at,response_due_at,grace_ends_at,decision,created_by_user_id,metadata_json,created_at,updated_at) VALUES (?,? ,"delete",?,"self_service","identity_verified",?,?,"account_password",NOW(),?,NOW(),NOW(),?, ?,"approve",?, ?,NOW(),NOW())');
    $stmt->execute([$publicId,$userId,$jurisdiction,$email,mg_privacy_identity_hash($email),mg_privacy_dt($deadlines['acknowledgement_due_at']),mg_privacy_dt($deadlines['response_due_at']),mg_privacy_dt($finalGrace),$userId,json_encode(['requested_user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255)],JSON_UNESCAPED_SLASHES)]);
    $requestId=(int)$pdo->lastInsertId();
    mg_privacy_event($pdo,$requestId,'request_submitted',['jurisdiction'=>$jurisdiction,'identity_verified'=>true],$userId);
    $handoffs=mg_privacy_generate_merchant_handoffs($pdo,$requestId,$userId,mg_privacy_dt($deadlines['response_due_at']));
    mg_privacy_action($pdo,$requestId,'merchant_controller_review','merchant_crm_contacts','notify',$handoffs ? 'pending' : 'skipped',0,'Merchant-controlled CRM records require controller review.',['handoffs_created'=>$handoffs]);
    mg_privacy_restrict_account($pdo,$requestId,$userId);
    $pdo->prepare('UPDATE users SET deletion_due_at=? WHERE id=?')->execute([mg_privacy_dt($finalGrace),$userId]);
    return mg_privacy_request_by_id($pdo,$requestId) ?? [];
}

function mg_privacy_delete_user_rows(PDO $pdo, int $requestId, int $userId, string $table, string $actionKey): int
{
    if (!mg_privacy_table_exists($pdo,$table) || !mg_privacy_column_exists($pdo,$table,'user_id')) {
        mg_privacy_action($pdo,$requestId,$actionKey,$table,'delete','skipped',0,'Table or user key is not installed.');
        return 0;
    }
    $stmt=$pdo->prepare("DELETE FROM `{$table}` WHERE user_id=?");
    $stmt->execute([$userId]);
    $rows=$stmt->rowCount();
    mg_privacy_action($pdo,$requestId,$actionKey,$table,'delete','completed',$rows,'Data is no longer required after account closure.');
    return $rows;
}

function mg_privacy_anonymize_profile_table(PDO $pdo, int $requestId, int $userId, string $table): int
{
    if (!mg_privacy_table_exists($pdo,$table) || !mg_privacy_column_exists($pdo,$table,'user_id')) return 0;
    $sets=[];
    foreach (['avatar_url','headline','bio','phone','address_line1','address_line2','city','state','postal_code','website_url','cover_image_url'] as $column) {
        if (mg_privacy_column_exists($pdo,$table,$column)) $sets[]="`{$column}`=NULL";
    }
    if (mg_privacy_column_exists($pdo,$table,'display_name')) $sets[]='display_name="Deleted User"';
    if (mg_privacy_column_exists($pdo,$table,'visibility')) $sets[]='visibility="private"';
    if (mg_privacy_column_exists($pdo,$table,'status')) $sets[]='status="disabled"';
    if (!$sets) return 0;
    $stmt=$pdo->prepare("UPDATE `{$table}` SET ".implode(',',$sets)." WHERE user_id=?");
    $stmt->execute([$userId]);
    mg_privacy_action($pdo,$requestId,'anonymize_'.$table,$table,'anonymize','completed',$stmt->rowCount(),'Remove public and profile identifiers.');
    return $stmt->rowCount();
}

function mg_privacy_anonymize_merchant_crm(PDO $pdo, int $requestId, int $userId): int
{
    if (!mg_privacy_table_exists($pdo,'merchant_crm_contacts')) return 0;
    $token='Deleted customer '.substr(hash('sha256',(string)$userId),0,10);
    $stmt=$pdo->prepare('UPDATE merchant_crm_contacts SET user_id=NULL,primary_email=NULL,primary_phone=NULL,display_name=?,crm_status="archived",metadata_json=JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),"$.privacy_erased",true,"$.privacy_request_id",?),updated_at=NOW() WHERE user_id=?');
    $stmt->execute([$token,$requestId,$userId]);
    $rows=$stmt->rowCount();
    if (mg_privacy_table_exists($pdo,'merchant_crm_contact_events')) {
        $event=$pdo->prepare('UPDATE merchant_crm_contact_events SET user_id=NULL,email=NULL,phone=NULL,name=NULL,metadata_json=JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),"$.privacy_erased",true) WHERE user_id=?');
        $event->execute([$userId]);
        $rows += $event->rowCount();
    }
    mg_privacy_action($pdo,$requestId,'anonymize_merchant_crm','merchant_crm_contacts','anonymize','completed',$rows,'Merchant records are preserved as non-identifying business history.');
    return $rows;
}

function mg_privacy_finalize_request(PDO $pdo, int $requestId, ?int $actorUserId = null, bool $force = false): array
{
    $request=mg_privacy_request_by_id($pdo,$requestId,true);
    if (!$request) throw new RuntimeException('Privacy request not found.');
    if (in_array((string)$request['status'],['completed','denied','cancelled'],true)) return ['status'=>$request['status'],'already_final'=>true];
    $userId=(int)($request['user_id']??0);
    if ($userId<1) throw new RuntimeException('The request is no longer linked to an account.');

    $hold=mg_privacy_active_hold($pdo,$requestId,$userId);
    if ($hold) {
        $pdo->prepare('UPDATE privacy_requests SET status="blocked_by_hold",updated_at=NOW() WHERE id=?')->execute([$requestId]);
        mg_privacy_action($pdo,$requestId,'legal_hold','privacy_legal_holds','retain','blocked_by_hold',0,'Active legal hold prevents irreversible erasure.',['hold_id'=>(int)$hold['id']]);
        return ['status'=>'blocked_by_hold','hold_id'=>(int)$hold['id']];
    }
    $due=(string)($request['grace_ends_at']??$request['response_due_at']);
    if (!$force && $due!=='' && strtotime($due)>time()) return ['status'=>$request['status'],'due_at'=>$due,'not_due'=>true];

    $pdo->prepare('UPDATE privacy_requests SET status="processing",processing_started_at=COALESCE(processing_started_at,NOW()),updated_at=NOW() WHERE id=?')->execute([$requestId]);
    mg_privacy_event($pdo,$requestId,'processing_started',['force'=>$force],$actorUserId);

    $deleted=0;
    foreach (['user_sessions','password_reset_tokens','email_verification_tokens','user_agent_messages','user_agent_settings','user_gifting_plans','user_gifting_schedules','user_recipient_data_requests','user_gift_bundles','notification_preferences','push_subscriptions','user_devices','user_saved_searches'] as $table) {
        $deleted += mg_privacy_delete_user_rows($pdo,$requestId,$userId,$table,'delete_'.$table);
    }
    $anonymized=0;
    foreach (['user_profiles','public_profiles'] as $table) $anonymized += mg_privacy_anonymize_profile_table($pdo,$requestId,$userId,$table);
    $anonymized += mg_privacy_anonymize_merchant_crm($pdo,$requestId,$userId);

    $userStmt=$pdo->prepare('SELECT email FROM users WHERE id=? LIMIT 1 FOR UPDATE');
    $userStmt->execute([$userId]);
    $email=(string)$userStmt->fetchColumn();
    $identityHash=(string)$request['contact_email_hash'];
    if ($identityHash==='') $identityHash=mg_privacy_identity_hash($email);
    $anonymousEmail='deleted+'.substr($identityHash,0,16).'.'.$userId.'@privacy.invalid';
    $passwordHash=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
    $set=['email=?','password_hash=?','full_name="Deleted User"','display_name="Deleted User"','status="disabled"','privacy_state="anonymized"','email_verified_at=NULL','anonymized_at=NOW()','identity_tombstone_hash=?','updated_at=NOW()'];
    if (mg_privacy_column_exists($pdo,'users','auth_version')) $set[]='auth_version=auth_version+1';
    $pdo->prepare('UPDATE users SET '.implode(',',$set).' WHERE id=?')->execute([$anonymousEmail,$passwordHash,$identityHash,$userId]);
    $pdo->prepare('INSERT INTO privacy_suppression_tombstones (identity_hash,request_id,reason,created_at) VALUES (?,? ,"account_erasure",NOW()) ON DUPLICATE KEY UPDATE request_id=VALUES(request_id),reason=VALUES(reason)')->execute([$identityHash,$requestId]);

    mg_privacy_action($pdo,$requestId,'retain_commerce_evidence',null,'retain','retained_by_policy',0,'Minimum commerce, tax, gift-ownership, fraud, dispute and audit evidence remains linked only to an anonymized identity.');
    $receipt=hash_hmac('sha256',$requestId.'|'.$identityHash.'|'.gmdate('c'),mg_privacy_hash_key());
    $pdo->prepare('UPDATE privacy_requests SET user_id=NULL,status="completed",decision="approve",contact_email=NULL,completed_at=NOW(),completed_receipt_hash=?,updated_at=NOW() WHERE id=?')->execute([$receipt,$requestId]);
    mg_privacy_event($pdo,$requestId,'request_completed',['deleted_rows'=>$deleted,'anonymized_rows'=>$anonymized,'receipt_hash'=>$receipt],$actorUserId);
    if (function_exists('mg_audit')) mg_audit('privacy.request.completed','privacy_request',['request_id'=>$requestId,'deleted_rows'=>$deleted,'anonymized_rows'=>$anonymized,'receipt_hash'=>$receipt],$actorUserId);
    return ['status'=>'completed','deleted_rows'=>$deleted,'anonymized_rows'=>$anonymized,'receipt_hash'=>$receipt];
}

function mg_privacy_list_requests(PDO $pdo, array $filters = []): array
{
    $where=['1=1'];$params=[];
    if (!empty($filters['status'])) {$where[]='pr.status=?';$params[]=(string)$filters['status'];}
    if (!empty($filters['jurisdiction'])) {$where[]='pr.jurisdiction=?';$params[]=(string)$filters['jurisdiction'];}
    if (!empty($filters['q'])) {$where[]='(pr.public_id LIKE ? OR pr.contact_email LIKE ? OR u.display_name LIKE ?)';$needle='%'.trim((string)$filters['q']).'%';array_push($params,$needle,$needle,$needle);}
    $sql='SELECT pr.*,u.display_name,u.email AS current_email,(SELECT COUNT(*) FROM privacy_legal_holds h WHERE h.status="active" AND (h.request_id=pr.id OR h.user_id=pr.user_id)) AS active_holds,(SELECT COUNT(*) FROM privacy_merchant_handoffs mh WHERE mh.request_id=pr.id AND mh.status NOT IN ("completed","not_applicable")) AS pending_handoffs FROM privacy_requests pr LEFT JOIN users u ON u.id=pr.user_id WHERE '.implode(' AND ',$where).' ORDER BY FIELD(pr.status,"blocked_by_hold","submitted","identity_verified","acknowledged","under_review","approved","restricted","processing","partially_completed","completed","denied","cancelled"),COALESCE(pr.extended_due_at,pr.response_due_at),pr.id DESC LIMIT 200';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
}
