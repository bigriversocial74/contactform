<?php
declare(strict_types=1);

require_once __DIR__ . '/sql-adapter.php';

if (!function_exists('mg_share_market_safe_count')) {
    function mg_share_market_safe_count(mixed $value): int { return max(0, (int)($value ?? 0)); }
}
if (!function_exists('mg_share_market_default_treasury')) {
    function mg_share_market_default_treasury(): array { return ['status'=>'not_created','credits_allocated'=>0,'credits_available'=>0,'credits_assigned'=>0,'credits_circulating'=>0,'credits_redeemed'=>0,'credits_burned'=>0,'credits_frozen'=>0,'updated_at'=>'']; }
}
if (!function_exists('mg_share_market_fetch_treasury_by_user')) {
    function mg_share_market_fetch_treasury_by_user(PDO $pdo, int $userId): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) return mg_share_market_default_treasury();
        try { $stmt=$pdo->prepare('SELECT t.* FROM share_market_credit_treasuries t INNER JOIN share_market_enrollments e ON e.id=t.enrollment_id WHERE e.participant_user_id=? ORDER BY t.updated_at DESC,t.id DESC LIMIT 1'); $stmt->execute([$userId]); $row=$stmt->fetch(PDO::FETCH_ASSOC); if(!$row) return mg_share_market_default_treasury(); return ['public_id'=>(string)$row['public_id'],'status'=>(string)$row['status'],'credits_allocated'=>mg_share_market_safe_count($row['credits_allocated']??0),'credits_available'=>mg_share_market_safe_count($row['credits_available']??0),'credits_assigned'=>mg_share_market_safe_count($row['credits_assigned']??0),'credits_circulating'=>mg_share_market_safe_count($row['credits_circulating']??0),'credits_redeemed'=>mg_share_market_safe_count($row['credits_redeemed']??0),'credits_burned'=>mg_share_market_safe_count($row['credits_burned']??0),'credits_frozen'=>mg_share_market_safe_count($row['credits_frozen']??0),'updated_at'=>(string)($row['updated_at']??'')]; } catch (Throwable) { return mg_share_market_default_treasury(); }
    }
}
if (!function_exists('mg_share_market_latest_series')) {
    function mg_share_market_latest_series(array $series): ?array { return $series && is_array($series[0] ?? null) ? $series[0] : null; }
}
if (!function_exists('mg_share_market_workflow_from_snapshot')) {
    function mg_share_market_workflow_from_snapshot(?array $enrollment, ?array $latestSeries, array $treasury): array
    {
        $enrollmentStatus=(string)($enrollment['status']??'not_enrolled'); $seriesState=(string)($latestSeries['state']??'none'); $treasuryFunded=mg_share_market_safe_count($treasury['credits_allocated']??0)>0||mg_share_market_safe_count($treasury['credits_available']??0)>0; $current='not_enrolled';
        if($enrollmentStatus==='under_review')$current='review_requested'; if(in_array($enrollmentStatus,['approved','active'],true))$current='approved'; if($treasuryFunded)$current='credits_purchased'; if($seriesState==='draft')$current='series_draft'; if($seriesState==='submitted')$current='series_submitted'; if($seriesState==='changes_requested')$current='changes_requested'; if($seriesState==='approved')$current='approved_to_launch'; if($seriesState==='live')$current='live'; if(in_array($enrollmentStatus,['paused','suspended'],true)||in_array($seriesState,['paused','frozen'],true))$current='paused'; if($enrollmentStatus==='rejected'||$seriesState==='rejected')$current='rejected'; if($enrollmentStatus==='closed'||$seriesState==='closed')$current='closed';
        return ['current'=>$current,'enrollment_status'=>$enrollmentStatus,'series_state'=>$seriesState,'review_status'=>$enrollment?($seriesState==='submitted'?'Series submitted':ucfirst(str_replace('_',' ',$enrollmentStatus))):'Not submitted','treasury_funded'=>$treasuryFunded,'can_request_review'=>!$enrollment||in_array($enrollmentStatus,['not_enrolled','interested','rejected','closed'],true),'can_buy_credits'=>in_array($enrollmentStatus,['approved','active'],true),'can_draft_series'=>(bool)$enrollment&&!in_array($enrollmentStatus,['rejected','closed','suspended'],true),'can_submit_series'=>(bool)$latestSeries&&in_array($seriesState,['draft','changes_requested'],true),'admin_gate_required'=>!in_array($seriesState,['live'],true)];
    }
}
if (!function_exists('mg_share_market_review_feedback')) {
    function mg_share_market_review_feedback(?array $enrollment, ?array $latestSeries, array $workflow): array
    {
        $current=(string)($workflow['current']??'not_enrolled'); $enrollmentNote=trim((string)($enrollment['admin_note']??'')); $seriesNote=trim((string)($latestSeries['admin_note']??'')); $latestNote=$seriesNote!==''?$seriesNote:$enrollmentNote; $seriesName=trim((string)($latestSeries['name']??'controlled market series'))?:'controlled market series';
        $messages=['not_enrolled'=>['Request DAVE Share Market review','Submit the merchant opt-in request so Microgifter can review participation before any public market activity.'],'review_requested'=>['Waiting for admin review','Your merchant opt-in has been submitted. You can prepare draft details, but public launch remains locked.'],'approved'=>['Merchant opt-in approved','Your merchant account is approved for the optional DAVE Share Market workflow. Next, reserve credits or create the first controlled series.'],'credits_purchased'=>['Treasury ready','Share credits are available. Next, create or submit a controlled market series for review.'],'series_draft'=>['Draft series ready','Review the current draft terms, then submit the series for admin review.'],'series_submitted'=>['Series submitted',$seriesName.' has been submitted. Wait for admin review before any launch activity.'],'changes_requested'=>['Changes requested','Admin requested updates to '.$seriesName.'. Review the note, edit the draft, and resubmit.'],'approved_to_launch'=>['Series approved, launch locked',$seriesName.' is approved for the next stage, but public activation remains an admin-gated execution step.'],'live'=>['Series live',$seriesName.' is live. Monitor review notes, ledger state, redemption, and risk controls.'],'paused'=>['Share Market paused','Participation or the current series is paused. Review admin notes before taking another action.'],'rejected'=>['Review rejected','Review the admin note and use the resubmission path when ready.'],'closed'=>['Participation closed','DAVE Share Market participation is closed for this merchant. Normal Microgifter commerce tools are unaffected.']];
        $message=$messages[$current]??$messages['not_enrolled']; return ['status'=>$current,'title'=>$message[0],'body'=>$message[1],'latest_note'=>$latestNote,'enrollment_note'=>$enrollmentNote,'series_note'=>$seriesNote,'next_action'=>$message[1],'highlight'=>in_array($current,['changes_requested','rejected','paused','closed'],true)?'attention':'normal'];
    }
}
if (!function_exists('mg_share_market_review_timeline')) {
    function mg_share_market_review_timeline(PDO $pdo, ?array $enrollment, array $series): array
    {
        $items=[]; if($enrollment)$items[]=['type'=>'merchant_submission','label'=>'Merchant opt-in submitted','state'=>(string)($enrollment['status']??'under_review'),'note'=>trim((string)($enrollment['admin_note']??'')),'created_at'=>(string)($enrollment['submitted_at']?:$enrollment['created_at']?:$enrollment['updated_at']?:''),'target_type'=>'enrollment','target_public_id'=>(string)($enrollment['public_id']??$enrollment['participant_id']??'')];
        foreach($series as $entry){ if(!is_array($entry))continue; $items[]=['type'=>'series_'.(string)($entry['state']??'draft'),'label'=>'Series '.ucfirst(str_replace('_',' ',(string)($entry['state']??'draft'))),'state'=>(string)($entry['state']??'draft'),'note'=>trim((string)($entry['admin_note']??'')),'created_at'=>(string)($entry['updated_at']?:$entry['created_at']?:''),'target_type'=>'series','target_public_id'=>(string)($entry['public_id']??$entry['series_id']??'')]; }
        $targetIds=[]; if($enrollment)$targetIds[]=(string)($enrollment['public_id']??$enrollment['participant_id']??''); foreach($series as $entry){ if(is_array($entry))$targetIds[]=(string)($entry['public_id']??$entry['series_id']??''); } $targetIds=array_values(array_filter(array_unique($targetIds)));
        if($targetIds!==[]){ try{$placeholders=implode(',',array_fill(0,count($targetIds),'?')); $stmt=$pdo->prepare('SELECT event_type,target_type,target_public_id,old_state,new_state,note,created_at FROM share_market_admin_events WHERE target_public_id IN ('.$placeholders.') ORDER BY created_at DESC,id DESC LIMIT 50'); $stmt->execute($targetIds); foreach(($stmt->fetchAll(PDO::FETCH_ASSOC)?:[]) as $row)$items[]=['type'=>(string)$row['event_type'],'label'=>ucwords(str_replace(['share_market.sql.','_','.'],['',' ',' '],(string)$row['event_type'])),'state'=>(string)($row['new_state']??''),'old_state'=>(string)($row['old_state']??''),'note'=>trim((string)($row['note']??'')),'created_at'=>(string)($row['created_at']??''),'target_type'=>(string)($row['target_type']??''),'target_public_id'=>(string)($row['target_public_id']??'')]; }catch(Throwable){} }
        usort($items,static fn(array $a,array $b):int=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??''))); return array_slice($items,0,12);
    }
}
if (!function_exists('mg_share_market_credit_reserve_status_for_user')) {
    function mg_share_market_credit_reserve_status_for_user(PDO $pdo, int $userId): array
    {
        try { $stmt=$pdo->prepare("SELECT * FROM share_market_approval_requests WHERE request_type='treasury' AND action_key='credit_reserve_request' AND requester_user_id=? ORDER BY updated_at DESC,id DESC LIMIT 20"); $stmt->execute([$userId]); $items=[]; foreach(($stmt->fetchAll(PDO::FETCH_ASSOC)?:[]) as $row){ $manifest=mg_share_market_sql_decode($row['manifest_json']??null); $projection=mg_share_market_sql_decode($row['projection_json']??null); $items[]=['id'=>(int)$row['id'],'public_id'=>(string)$row['public_id'],'status'=>(string)$row['status'],'review_status'=>(string)($projection['review_status']??$row['status']),'admin_note'=>(string)($projection['admin_note']??''),'credits_requested'=>(int)($manifest['credits_requested']??0),'series_name'=>(string)($manifest['series_name']??''),'estimated_launch_price_cents'=>(int)($manifest['estimated_launch_price_cents']??0),'intended_use'=>(string)($manifest['intended_use']??''),'created_at'=>(string)($row['created_at']??''),'updated_at'=>(string)($row['updated_at']??''),'execution_enabled'=>false]; } return ['reserve_requests'=>$items,'latest_reserve_request'=>$items[0]??null]; } catch(Throwable) { return ['reserve_requests'=>[],'latest_reserve_request'=>null]; }
    }
}
if (!function_exists('mg_share_market_merchant_state')) {
    function mg_share_market_merchant_state(PDO $pdo, int $userId): array
    {
        if(!mg_share_market_sql_schema_available($pdo)){ $treasury=mg_share_market_default_treasury(); $workflow=mg_share_market_workflow_from_snapshot(null,null,$treasury); return ['enrollment'=>null,'series'=>[],'latest_series'=>null,'treasury'=>$treasury,'workflow'=>$workflow,'review_feedback'=>mg_share_market_review_feedback(null,null,$workflow),'review_timeline'=>[],'credit_reserve'=>['reserve_requests'=>[],'latest_reserve_request'=>null],'execution_enabled'=>false,'storage_mode'=>'share_market_sql_unavailable']; }
        $snapshot=mg_share_market_sql_user_snapshot($pdo,$userId); $enrollment=is_array($snapshot['enrollment']??null)?$snapshot['enrollment']:null; $series=is_array($snapshot['series']??null)?$snapshot['series']:[]; $latestSeries=mg_share_market_latest_series($series); $treasury=mg_share_market_fetch_treasury_by_user($pdo,$userId); $workflow=mg_share_market_workflow_from_snapshot($enrollment,$latestSeries,$treasury);
        $snapshot['latest_series']=$latestSeries; $snapshot['treasury']=$treasury; $snapshot['workflow']=$workflow; $snapshot['review_feedback']=mg_share_market_review_feedback($enrollment,$latestSeries,$workflow); $snapshot['review_timeline']=mg_share_market_review_timeline($pdo,$enrollment,$series); $snapshot['credit_reserve']=mg_share_market_credit_reserve_status_for_user($pdo,$userId); return $snapshot;
    }
}
