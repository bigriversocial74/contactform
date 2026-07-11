<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/store/_presence.php';

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=$method==='GET'?mg_require_api_user():mg_require_api_user();
$pdo=mg_db();$merchantId=(int)($user['id']??0);

try{
    if(!mg_user_has_merchant_access($user,$pdo))mg_fail('Merchant access required.',403);
    if($method==='GET'){
        mg_rate_limit('merchant_canvas.presence.read','user:'.$merchantId,120,60);
        mg_ok(['schema_ready'=>mg_presence_ready($pdo),'persona_state'=>mg_presence_persona_state($pdo,$merchantId),'locations'=>array_map(static fn(array $row):array=>mg_presence_project($row),mg_presence_locations($pdo,$merchantId))]);
    }
    if($method!=='POST')mg_fail('Method not allowed.',405);
    $input=mg_input();mg_require_csrf_for_write($input);mg_rate_limit('merchant_canvas.presence.write','user:'.$merchantId,180,60);
    if(!mg_presence_ready($pdo))throw new RuntimeException('Merchant presence schema is not installed. Run database/stage_33_world_canvas_persona_presence.sql.');
    $action=strtolower(trim((string)($input['action']??'heartbeat')));$saved=mg_presence_persona_state($pdo,$merchantId);$locationId=$input['location_id']??($saved['merchant_location_id']??null);
    if($action==='leave'){
        $transition=mg_presence_transition($pdo,$merchantId,$locationId,'world',$merchantId);
        $locationDb=(int)($transition['location']['database_id']??0);mg_presence_save_persona($pdo,$merchantId,'merchant',$locationDb?:null,'world_canvas',['reason'=>'left_store_canvas','at'=>gmdate('c')]);
        mg_ok(['transition'=>$transition],'Merchant moved into World Canvas.');
    }
    if(!in_array($action,['return','heartbeat'],true))throw new InvalidArgumentException('Invalid merchant presence action.');
    $transition=mg_presence_transition($pdo,$merchantId,$locationId,'in_store',$merchantId);
    $locationDb=(int)($transition['location']['database_id']??0);mg_presence_save_persona($pdo,$merchantId,'merchant',$locationDb?:null,'store_canvas',['reason'=>$action,'at'=>gmdate('c')]);
    mg_ok(['transition'=>$transition],'Merchant is present in Store Canvas.');
}catch(InvalidArgumentException $e){mg_fail($e->getMessage(),422);}catch(RuntimeException $e){mg_fail($e->getMessage(),400);}catch(Throwable $e){mg_security_log('error','merchant_canvas.presence_failed','Merchant Store Canvas presence failed.',['exception_class'=>$e::class,'message'=>$e->getMessage()],$merchantId);mg_fail('Unable to update merchant presence.',500);}
