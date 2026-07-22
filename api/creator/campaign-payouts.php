<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__,2).'/includes/creator-campaigns.php';
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));$user=mg_require_api_user();$pdo=mg_db();$actorUserId=(int)($user['id']??0);
try{
    if($method==='GET')mg_ok(mg_creator_campaign_payout_dashboard_creator($pdo,$user));
    if($method!=='POST')mg_fail('Method not allowed.',405);
    $input=mg_input();mg_require_csrf_for_write($input);$action=strtolower(trim((string)($input['action']??'')));
    $data=match($action){
        'open_dispute'=>mg_creator_campaign_dispute_open($pdo,$user,(string)($input['source_type']??''),(string)($input['source_public_id']??''),(string)($input['reason']??''),true),
        default=>throw new InvalidArgumentException('Creator Campaign payout action is invalid.'),
    };mg_ok($data,'Creator Campaign dispute opened.');
}catch(InvalidArgumentException $e){mg_fail($e->getMessage(),422);
}catch(DomainException $e){mg_fail($e->getMessage(),409);
}catch(RuntimeException $e){$m=strtolower($e->getMessage());mg_fail($e->getMessage(),str_contains($m,'schema is incomplete')?503:(str_contains($m,'not found')?404:409));
}catch(PDOException $e){if((string)$e->getCode()==='23000')mg_fail('An active dispute already exists for this record.',409);mg_fail_unexpected($e,'creator.campaign_payouts.database_failure','Unable to update Creator Campaign payout records.',500,['action'=>$action??null],$actorUserId);
}catch(Throwable $e){mg_fail_unexpected($e,'creator.campaign_payouts.failure','Unable to process Creator Campaign payout records.',500,['action'=>$action??null],$actorUserId);}
