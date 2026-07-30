<?php
declare(strict_types=1);
require_once __DIR__.'/_merchant.php';
require_once dirname(__DIR__,2).'/includes/creator-campaigns.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$user=mg_require_api_user();
$pdo=mg_db();
$actorUserId=(int)($user['id']??0);
$action=null;

try{
    if($method==='GET'){
        mg_ok(mg_creator_campaign_operations_dashboard($pdo,$user,$_GET));
    }
    if($method!=='POST')mg_fail('Method not allowed.',405);
    $input=mg_input();
    mg_require_csrf_for_write($input);
    $action=strtolower(trim((string)($input['action']??'')));
    $data=match($action){
        'save_policy'=>mg_creator_campaign_operations_policy_save($pdo,$user,$input),
        'scan'=>mg_creator_campaign_operations_scan($pdo,$user),
        'update_case'=>mg_creator_campaign_operations_case_update($pdo,$user,(string)($input['case_id']??''),$input),
        'save_profile'=>mg_creator_campaign_payout_save_profile($pdo,$user,(string)($input['participant_id']??''),$input),
        'create_payout'=>mg_creator_campaign_payout_create($pdo,$user,(string)($input['participant_id']??''),$input),
        default=>throw new InvalidArgumentException('Creator affiliate operations action is invalid.'),
    };
    mg_ok($data,'Creator affiliate operations updated.');
}catch(InvalidArgumentException $e){mg_fail($e->getMessage(),422);
}catch(DomainException $e){mg_fail($e->getMessage(),409);
}catch(RuntimeException $e){$message=strtolower($e->getMessage());mg_fail($e->getMessage(),str_contains($message,'schema is incomplete')?503:(str_contains($message,'not found')?404:409));
}catch(PDOException $e){if((string)$e->getCode()==='23000')mg_fail('The request conflicts with an existing Creator affiliate operation.',409);mg_fail_unexpected($e,'merchant.creator_affiliate_operations.database_failure','Unable to update Creator affiliate operations.',500,['action'=>$action],$actorUserId);
}catch(Throwable $e){mg_fail_unexpected($e,'merchant.creator_affiliate_operations.failure','Unable to process Creator affiliate operations.',500,['action'=>$action],$actorUserId);}
