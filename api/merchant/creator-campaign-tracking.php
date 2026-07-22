<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/creator-campaigns.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$user=mg_require_api_user();$pdo=mg_db();$actorUserId=(int)($user['id']??0);
try{
    if($method==='GET'){
        $action=strtolower(trim((string)($_GET['action']??'dashboard')));
        $data=match($action){
            'dashboard'=>mg_creator_campaign_tracking_dashboard_merchant($pdo,$user),
            'sources'=>mg_creator_campaign_tracking_sources_merchant($pdo,$user,$_GET),
            'events'=>mg_creator_campaign_tracking_events_merchant($pdo,$user,$_GET),
            'attributions'=>mg_creator_campaign_attributions_merchant($pdo,$user,$_GET),
            default=>throw new RuntimeException('Creator tracking route not found.'),
        };mg_ok($data);
    }
    if($method!=='POST')mg_fail('Method not allowed.',405);
    $input=mg_input();mg_require_csrf_for_write($input);$action=strtolower(trim((string)($input['action']??'')));
    $data=match($action){
        'save_source'=>mg_creator_campaign_tracking_save_source_merchant($pdo,$user,(string)($input['participant_id']??''),$input),
        'retire_source'=>mg_creator_campaign_tracking_retire_source_merchant($pdo,$user,(string)($input['source_id']??''),$input),
        'invalidate_event'=>mg_creator_campaign_tracking_invalidate_event_merchant($pdo,$user,(string)($input['event_id']??''),$input),
        'override_attribution'=>mg_creator_campaign_attribution_override_merchant($pdo,$user,(string)($input['attribution_id']??''),$input),
        'reprocess_attribution'=>mg_creator_campaign_attribution_reprocess_merchant($pdo,$user,(string)($input['event_id']??'')),
        default=>throw new InvalidArgumentException('Creator tracking action is invalid.'),
    };mg_ok($data,'Creator tracking updated.');
}catch(InvalidArgumentException $e){mg_fail($e->getMessage(),422);
}catch(DomainException $e){mg_fail($e->getMessage(),409);
}catch(RuntimeException $e){$m=strtolower($e->getMessage());mg_fail($e->getMessage(),str_contains($m,'schema is incomplete')?503:(str_contains($m,'not found')?404:409));
}catch(PDOException $e){if((string)$e->getCode()==='23000')mg_fail('The tracking request conflicts with an existing event or source.',409);mg_fail_unexpected($e,'merchant.creator_tracking.database_failure','Unable to update creator tracking because of a database error.',500,['action'=>$action??null],$actorUserId);
}catch(Throwable $e){mg_fail_unexpected($e,'merchant.creator_tracking.failure','Unable to process the creator tracking request.',500,['action'=>$action??null],$actorUserId);}
