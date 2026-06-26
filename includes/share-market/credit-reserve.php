<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-state.php';
require_once __DIR__ . '/notifications.php';

if (!function_exists('mg_share_market_credit_reserve_json')) {
    function mg_share_market_credit_reserve_json(array $value): string { return json_encode(mg_share_market_admin_canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
}
if (!function_exists('mg_share_market_credit_reserve_hash')) {
    function mg_share_market_credit_reserve_hash(array $value): string { return hash('sha256', mg_share_market_credit_reserve_json($value)); }
}
if (!function_exists('mg_share_market_credit_reserve_validate')) {
    function mg_share_market_credit_reserve_validate(array $input): array
    {
        $credits = filter_var($input['credits_requested'] ?? null, FILTER_VALIDATE_INT);
        if ($credits === false || $credits < 100 || $credits > 10000000) throw new InvalidArgumentException('Request between 100 and 10,000,000 share credits.');
        $seriesName = trim((string)($input['series_name'] ?? ''));
        if ($seriesName === '' || mb_strlen($seriesName) > 160) throw new InvalidArgumentException('Enter a series name up to 160 characters.');
        $launchPrice = trim((string)($input['estimated_launch_price'] ?? ''));
        if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/', $launchPrice)) throw new InvalidArgumentException('Enter a valid estimated launch price.');
        $launchPriceCents = (int)round(((float)$launchPrice) * 100);
        if ($launchPriceCents < 100 || $launchPriceCents > 1000000) throw new InvalidArgumentException('Estimated launch price must be between $1.00 and $10,000.00.');
        $useCase = trim((string)($input['intended_use'] ?? ''));
        if (mb_strlen($useCase) < 20 || mb_strlen($useCase) > 1200) throw new InvalidArgumentException('Describe the intended use in 20 to 1,200 characters.');
        return ['credits_requested'=>(int)$credits,'series_name'=>$seriesName,'estimated_launch_price_cents'=>$launchPriceCents,'intended_use'=>$useCase,'merchant_note'=>trim((string)($input['merchant_note'] ?? ''))];
    }
}
if (!function_exists('mg_share_market_credit_reserve_submit')) {
    function mg_share_market_credit_reserve_submit(PDO $pdo, array $input, array $user): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) throw new RuntimeException('Share Market SQL schema is not installed.');
        $payload = mg_share_market_credit_reserve_validate($input); $userId = (int)$user['id'];
        $enrollment = mg_share_market_sql_fetch_enrollment_by_user($pdo, $userId);
        if (!$enrollment || !in_array((string)$enrollment['status'], ['approved','active'], true)) throw new DomainException('Merchant opt-in approval is required before reserving share credits.');
        $manifest = ['kind'=>'credit_reserve_request','requested_by_user_id'=>$userId,'enrollment_public_id'=>(string)$enrollment['public_id'],'credits_requested'=>$payload['credits_requested'],'series_name'=>$payload['series_name'],'estimated_launch_price_cents'=>$payload['estimated_launch_price_cents'],'intended_use'=>$payload['intended_use'],'merchant_note'=>$payload['merchant_note'],'created_at'=>gmdate('c'),'execution_enabled'=>false];
        $hash = mg_share_market_credit_reserve_hash($manifest);
        $pdo->prepare("INSERT INTO share_market_approval_requests (public_id,request_type,action_key,event_type,target_type,target_public_id,requester_user_id,status,risk_level,required_approvals,approval_count,manifest_json,projection_json,payload_hash,execution_enabled,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'awaiting_first_approval','medium',1,0,?,?,?,0,NOW(),NOW())")->execute([mg_share_market_sql_public_id(),'treasury','credit_reserve_request','share_market.sql.credit_reserve_requested','merchant_treasury',(string)$enrollment['public_id'],$userId,mg_share_market_credit_reserve_json($manifest),mg_share_market_credit_reserve_json(['review_status'=>'pending','execution_enabled'=>false]),$hash]);
        $requestId = (string)$pdo->lastInsertId();
        mg_share_market_notify_admins($pdo, $userId, 'DAVE share credit reserve requested', $payload['series_name'] . ' requested ' . number_format($payload['credits_requested']) . ' share credits for admin review.', 'credit_reserve_requested.' . $requestId, ['target_type'=>'credit_reserve','approval_request_id'=>$requestId,'share_market_status'=>'pending']);
        return mg_share_market_credit_reserve_user_snapshot($pdo, $userId);
    }
}
if (!function_exists('mg_share_market_credit_reserve_payload')) {
    function mg_share_market_credit_reserve_payload(array $row): array
    {
        $manifest = mg_share_market_sql_decode($row['manifest_json'] ?? null); $projection = mg_share_market_sql_decode($row['projection_json'] ?? null);
        return ['id'=>(int)$row['id'],'public_id'=>(string)$row['public_id'],'status'=>(string)$row['status'],'review_status'=>(string)($projection['review_status'] ?? $row['status']),'admin_note'=>(string)($projection['admin_note'] ?? ''),'requester_user_id'=>(int)$row['requester_user_id'],'target_public_id'=>(string)$row['target_public_id'],'credits_requested'=>(int)($manifest['credits_requested'] ?? 0),'series_name'=>(string)($manifest['series_name'] ?? ''),'estimated_launch_price_cents'=>(int)($manifest['estimated_launch_price_cents'] ?? 0),'intended_use'=>(string)($manifest['intended_use'] ?? ''),'merchant_note'=>(string)($manifest['merchant_note'] ?? ''),'created_at'=>(string)($row['created_at'] ?? ''),'updated_at'=>(string)($row['updated_at'] ?? ''),'execution_enabled'=>false];
    }
}
if (!function_exists('mg_share_market_credit_reserve_user_snapshot')) {
    function mg_share_market_credit_reserve_user_snapshot(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare("SELECT * FROM share_market_approval_requests WHERE request_type='treasury' AND action_key='credit_reserve_request' AND requester_user_id=? ORDER BY updated_at DESC,id DESC LIMIT 20"); $stmt->execute([$userId]); $requests=[]; foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) $requests[] = mg_share_market_credit_reserve_payload($row); return ['reserve_requests'=>$requests,'latest_reserve_request'=>$requests[0] ?? null,'execution_enabled'=>false];
    }
}
if (!function_exists('mg_share_market_credit_reserve_admin_queue')) {
    function mg_share_market_credit_reserve_admin_queue(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT r.*,u.email,u.display_name,u.full_name FROM share_market_approval_requests r LEFT JOIN users u ON u.id=r.requester_user_id WHERE r.request_type='treasury' AND r.action_key='credit_reserve_request' ORDER BY r.updated_at DESC,r.id DESC LIMIT 200"); $items=[]; foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) ?: [] as $row) { $item=mg_share_market_credit_reserve_payload($row); $item['merchant_label']=trim((string)($row['display_name'] ?? $row['full_name'] ?? $row['email'] ?? 'Merchant')); $items[]=$item; } return ['credit_reserve_requests'=>$items,'summary'=>['total'=>count($items),'pending'=>count(array_filter($items, fn($i)=>$i['status']==='awaiting_first_approval')),'execution_enabled'=>false]];
    }
}
if (!function_exists('mg_share_market_credit_reserve_decide')) {
    function mg_share_market_credit_reserve_decide(PDO $pdo, array $input, array $user): array
    {
        if (!mg_share_market_admin_authorized($user)) throw new DomainException('Share Market Admin permission is required.');
        $id = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT); if ($id === false || $id < 1) throw new InvalidArgumentException('Select a valid reserve request.');
        $decision = strtolower(trim((string)($input['decision'] ?? ''))); if (!in_array($decision, ['approve','reject','changes'], true)) throw new InvalidArgumentException('Select approve, reject, or request changes.');
        $note = trim((string)($input['note'] ?? '')); if ($note === '' || mb_strlen($note) > 1000) throw new InvalidArgumentException('Admin note is required and cannot exceed 1,000 characters.');
        $actorId=(int)$user['id']; $pdo->beginTransaction();
        try {
            $stmt=$pdo->prepare("SELECT * FROM share_market_approval_requests WHERE id=? AND request_type='treasury' AND action_key='credit_reserve_request' LIMIT 1 FOR UPDATE"); $stmt->execute([(int)$id]); $row=$stmt->fetch(PDO::FETCH_ASSOC); if(!$row) throw new InvalidArgumentException('Reserve request was not found.');
            $item=mg_share_market_credit_reserve_payload($row); $newStatus=$decision==='approve'?'approved':($decision==='reject'?'rejected':'awaiting_first_approval'); $reviewStatus=$decision==='changes'?'changes_requested':$newStatus; $projection=['review_status'=>$reviewStatus,'admin_note'=>$note,'reviewed_by_user_id'=>$actorId,'reviewed_at'=>gmdate('c'),'execution_enabled'=>false];
            $pdo->prepare('UPDATE share_market_approval_requests SET status=?,approval_count=?,projection_json=?,updated_at=NOW() WHERE id=?')->execute([$newStatus,$decision==='approve'?1:0,mg_share_market_credit_reserve_json($projection),(int)$id]);
            if($decision==='approve'){ $enrollment=mg_share_market_sql_fetch_enrollment_by_user($pdo,(int)$row['requester_user_id'],true); if(!$enrollment) throw new RuntimeException('Enrollment no longer exists.'); $existing=mg_share_market_fetch_treasury_by_user($pdo,(int)$row['requester_user_id']); if(($existing['status']??'not_created')==='not_created'){ $pdo->prepare("INSERT INTO share_market_credit_treasuries (public_id,enrollment_id,participant_user_id,status,credits_allocated,credits_available,created_by_user_id,metadata_json) VALUES (?,?,?,?,?,?,?,?)")->execute([mg_share_market_sql_public_id(),(int)$enrollment['id'],(int)$row['requester_user_id'],'active',$item['credits_requested'],$item['credits_requested'],$actorId,mg_share_market_credit_reserve_json(['source'=>'credit_reserve_approval','approval_request_id'=>(int)$id,'execution_enabled'=>false])]); } else { $pdo->prepare("UPDATE share_market_credit_treasuries SET status='active',credits_allocated=credits_allocated+?,credits_available=credits_available+?,metadata_json=? WHERE public_id=?")->execute([$item['credits_requested'],$item['credits_requested'],mg_share_market_credit_reserve_json(['source'=>'credit_reserve_approval','approval_request_id'=>(int)$id,'execution_enabled'=>false]),(string)$existing['public_id']]); } }
            mg_share_market_sql_admin_event($pdo,$actorId,'share_market.sql.credit_reserve_'.$reviewStatus,'treasury_reserve',(string)$row['public_id'],(string)$row['status'],$reviewStatus,$note); $pdo->commit();
            mg_share_market_notify_merchant($pdo,(int)$row['requester_user_id'],$actorId,'DAVE share credit reserve '.str_replace('_',' ',$reviewStatus),$note,'credit_reserve_decision.'.(int)$id.'.'.$reviewStatus,['target_type'=>'credit_reserve','approval_request_id'=>(int)$id,'share_market_status'=>$reviewStatus]); return mg_share_market_credit_reserve_admin_queue($pdo);
        } catch (Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
}
