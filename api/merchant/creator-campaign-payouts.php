<?php
declare(strict_types=1);
require_once __DIR__.'/_merchant.php';
require_once dirname(__DIR__,2).'/includes/creator-campaigns.php';
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));$user=mg_require_api_user();$pdo=mg_db();$actorUserId=(int)($user['id']??0);
try{
    if($method==='GET')mg_ok(mg_creator_campaign_payout_dashboard_merchant($pdo,$user,$_GET));
    if($method!=='POST')mg_fail('Method not allowed.',405);
    $input=mg_input();mg_require_csrf_for_write($input);$action=strtolower(trim((string)($input['action']??'')));
    $data=match($action){
        'save_profile'=>mg_creator_campaign_payout_save_profile($pdo,$user,(string)($input['participant_id']??''),$input),
        'create_payout'=>mg_creator_campaign_payout_create($pdo,$user,(string)($input['participant_id']??''),$input),
        'transition_payout'=>mg_creator_campaign_payout_transition($pdo,$user,(string)($input['payout_id']??''),(string)($input['to_status']??''),$input),
        'open_dispute'=>mg_creator_campaign_dispute_open($pdo,$user,(string)($input['source_type']??''),(string)($input['source_public_id']??''),(string)($input['reason']??''),false),
        'transition_dispute'=>mg_creator_campaign_dispute_transition($pdo,$user,(string)($input['dispute_id']??''),(string)($input['to_status']??''),$input),
        default=>throw new InvalidArgumentException('Creator Campaign payout action is invalid.'),
    };mg_ok($data,'Creator Campaign payout records updated.');
}catch(InvalidArgumentException $e){mg_fail($e->getMessage(),422);
}catch(DomainException $e){mg_fail($e->getMessage(),409);
}catch(RuntimeException $e){$m=strtolower($e->getMessage());mg_fail($e->getMessage(),str_contains($m,'schema is incomplete')?503:(str_contains($m,'not found')?404:409));
}catch(PDOException $e){if((string)$e->getCode()==='23000')mg_fail('The request conflicts with an existing payout or dispute record.',409);mg_fail_unexpected($e,'merchant.creator_payouts.database_failure','Unable to update Creator Campaign payout records.',500,['action'=>$action??null],$actorUserId);
}catch(Throwable $e){mg_fail_unexpected($e,'merchant.creator_payouts.failure','Unable to process Creator Campaign payout records.',500,['action'=>$action??null],$actorUserId);}
