<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__,2).'/includes/creator-campaigns.php';
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));$user=mg_require_api_user();$pdo=mg_db();$actorUserId=(int)($user['id']??0);
try{
    if($method!=='GET')mg_fail('Method not allowed.',405);
    mg_ok(mg_creator_campaign_earnings_creator($pdo,$user,$_GET));
}catch(DomainException $e){mg_fail($e->getMessage(),409);
}catch(RuntimeException $e){$m=strtolower($e->getMessage());mg_fail($e->getMessage(),str_contains($m,'schema is incomplete')?503:(str_contains($m,'not found')?404:409));
}catch(Throwable $e){mg_fail_unexpected($e,'creator.campaign_earnings.failure','Unable to load creator earnings.',500,[],$actorUserId);}
