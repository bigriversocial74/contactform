<?php
declare(strict_types=1);
require_once __DIR__.'/_merchant.php';
require_once dirname(__DIR__,2).'/includes/creator-campaigns.php';
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));$user=mg_require_api_user();$pdo=mg_db();$actorUserId=(int)($user['id']??0);
try{
    if($method==='GET')mg_ok(mg_creator_campaign_budget_dashboard($pdo,$user,$_GET));
    if($method!=='POST')mg_fail('Method not allowed.',405);
    $input=mg_input();mg_require_csrf_for_write($input);$action=strtolower(trim((string)($input['action']??'')));
    $data=match($action){
        'save_budget'=>mg_creator_campaign_budget_save($pdo,$user,(string)($input['campaign_id']??''),$input),
        'reserve_earning'=>mg_creator_campaign_budget_reserve_earning($pdo,$user,(string)($input['earning_event_id']??''),$input),
        'sync_earnings'=>mg_creator_campaign_budget_sync_earnings($pdo,$user,(string)($input['campaign_id']??'')),
        'commit_reservation'=>mg_creator_campaign_budget_transition($pdo,$user,(string)($input['reservation_id']??''),'commit',$input),
        'release_reservation'=>mg_creator_campaign_budget_transition($pdo,$user,(string)($input['reservation_id']??''),'release',$input),
        default=>throw new InvalidArgumentException('Creator campaign budget action is invalid.'),
    };mg_ok($data,'Creator campaign budget updated.');
}catch(InvalidArgumentException $e){mg_fail($e->getMessage(),422);
}catch(DomainException $e){mg_fail($e->getMessage(),409);
}catch(RuntimeException $e){$m=strtolower($e->getMessage());mg_fail($e->getMessage(),str_contains($m,'schema is incomplete')?503:(str_contains($m,'not found')?404:409));
}catch(PDOException $e){if((string)$e->getCode()==='23000')mg_fail('The request conflicts with an existing budget record.',409);mg_fail_unexpected($e,'merchant.creator_budgets.database_failure','Unable to update Creator campaign budgets.',500,['action'=>$action??null],$actorUserId);
}catch(Throwable $e){mg_fail_unexpected($e,'merchant.creator_budgets.failure','Unable to process Creator campaign budgets.',500,['action'=>$action??null],$actorUserId);}
