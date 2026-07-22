<?php
declare(strict_types=1);
require_once __DIR__.'/_merchant.php';
require_once dirname(__DIR__,2).'/includes/creator-campaigns.php';
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));$user=mg_require_api_user();$pdo=mg_db();$actorUserId=(int)($user['id']??0);
try{
    if($method==='GET'){
        $action=strtolower(trim((string)($_GET['action']??'dashboard')));
        $data=match($action){
            'dashboard'=>mg_creator_campaign_compensation_dashboard_merchant($pdo,$user,$_GET),
            'rules'=>mg_creator_campaign_compensation_rules_merchant($pdo,$user,$_GET),
            'earnings'=>mg_creator_campaign_earnings_merchant($pdo,$user,$_GET),
            default=>throw new RuntimeException('Creator compensation route not found.'),
        };mg_ok($data);
    }
    if($method!=='POST')mg_fail('Method not allowed.',405);
    $input=mg_input();mg_require_csrf_for_write($input);$action=strtolower(trim((string)($input['action']??'')));
    $data=match($action){
        'save_rule'=>mg_creator_campaign_compensation_save_rule($pdo,$user,(string)($input['campaign_id']??''),$input),
        'adjust'=>mg_creator_campaign_compensation_adjust($pdo,$user,(string)($input['participant_id']??''),$input),
        'reverse'=>mg_creator_campaign_compensation_reverse($pdo,$user,(string)($input['earning_event_id']??''),$input),
        default=>throw new InvalidArgumentException('Creator compensation action is invalid.'),
    };mg_ok($data,'Creator compensation updated.');
}catch(InvalidArgumentException $e){mg_fail($e->getMessage(),422);
}catch(DomainException $e){mg_fail($e->getMessage(),409);
}catch(RuntimeException $e){$m=strtolower($e->getMessage());mg_fail($e->getMessage(),str_contains($m,'schema is incomplete')?503:(str_contains($m,'not found')?404:409));
}catch(PDOException $e){if((string)$e->getCode()==='23000')mg_fail('The request conflicts with an existing compensation record.',409);mg_fail_unexpected($e,'merchant.creator_compensation.database_failure','Unable to update creator compensation.',500,['action'=>$action??null],$actorUserId);
}catch(Throwable $e){mg_fail_unexpected($e,'merchant.creator_compensation.failure','Unable to process creator compensation.',500,['action'=>$action??null],$actorUserId);}
