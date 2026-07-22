<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/creator-campaigns.php';

if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))!=='POST')mg_fail('Method not allowed.',405);
$pdo=mg_db();
try{
    $input=mg_input();
    $eventType=strtolower(trim((string)($input['event_type']??'')));
    if(!in_array($eventType,mg_creator_campaign_tracking_browser_event_types(),true)){
        throw new InvalidArgumentException('Public tracking accepts landing_view or engagement events.');
    }
    $code=strtolower(trim((string)($input['tracking_code']??'')));
    $session=(string)($input['session_key']??($_COOKIE['mg_cc_session']??''));
    $visitor=(string)($input['visitor_key']??($_COOKIE['mg_cc_visitor']??''));
    $referrerHost='';
    $ref=(string)($_SERVER['HTTP_REFERER']??'');
    if($ref!=='')$referrerHost=(string)(parse_url($ref,PHP_URL_HOST)??'');
    $input['session_key']=$session;
    $input['visitor_key']=$visitor;
    $input['request_key']=(string)($input['request_key']??($input['event_key']??''));
    $input['referrer_host']=$referrerHost;
    $event=mg_creator_campaign_tracking_record_by_code($pdo,$code,$input);
    mg_ok(['event_id'=>$event['public_id'],'status'=>$event['status'],'is_unique'=>(int)$event['is_unique']]);
}catch(InvalidArgumentException $e){mg_fail($e->getMessage(),422);
}catch(RuntimeException $e){$m=strtolower($e->getMessage());mg_fail($e->getMessage(),str_contains($m,'schema is incomplete')?503:404);
}catch(PDOException $e){if((string)$e->getCode()==='23000')mg_ok(['duplicate'=>true]);mg_fail_unexpected($e,'public.creator_tracking.database_failure','Unable to record the campaign event.',500);
}catch(Throwable $e){mg_fail_unexpected($e,'public.creator_tracking.failure','Unable to record the campaign event.',500);}
