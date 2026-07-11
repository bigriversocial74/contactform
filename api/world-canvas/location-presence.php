<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/store/_presence.php';

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=$method==='GET'?mg_require_api_user():mg_require_permission('merchant.locations.manage');
$pdo=mg_db();$merchantId=(int)($user['id']??0);

function mg_world_presence_payload(PDO $pdo,int $merchantId): array {
    $rows=mg_presence_locations($pdo,$merchantId);return [
        'schema_ready'=>mg_presence_ready($pdo),
        'locations'=>array_map(static fn(array $row):array=>mg_presence_project($row),$rows),
        'policy_options'=>[
            ['id'=>'allow_unattended','label'=>'Shop without merchant present','description'=>'Customers may enter and receive an automatic away message.'],
            ['id'=>'temporarily_closed','label'=>'Temporarily closed','description'=>'Customer entry is blocked and the customer is notified when the merchant returns.'],
        ],
        'coordinate_source'=>'merchant_locations',
    ];
}

try{
    if($method==='GET'){
        mg_rate_limit('world_canvas.location_presence.read','user:'.$merchantId,120,60);
        if(!mg_presence_table($pdo,'merchant_locations'))throw new RuntimeException('Merchant locations are unavailable.');
        mg_ok(mg_world_presence_payload($pdo,$merchantId));
    }
    if($method!=='POST')mg_fail('Method not allowed.',405);
    $input=mg_input();mg_require_csrf_for_write($input);mg_rate_limit('world_canvas.location_presence.write','user:'.$merchantId,30,60);
    if(!mg_presence_ready($pdo))throw new RuntimeException('Merchant presence schema is not installed. Run database/stage_33_world_canvas_persona_presence.sql.');
    $location=mg_presence_location($pdo,$merchantId,(string)($input['location_id']??''),true);if(!$location)throw new RuntimeException('Registered merchant location not found.');
    $mode=mg_presence_mode($input['presence_mode']??'allow_unattended');
    $away=mg_presence_text($input['away_message']??'',500);$return=mg_presence_text($input['return_message']??'',500);
    $radius=max(50,min(5000,(int)($input['world_zone_radius_meters']??250)));
    $pdo->prepare('UPDATE merchant_locations SET world_presence_mode=?,world_presence_message=?,world_return_message=?,world_zone_radius_meters=?,updated_at=NOW() WHERE id=?')->execute([$mode,$away!==''?$away:null,$return!==''?$return:null,$radius,(int)$location['id']]);
    mg_ok(mg_world_presence_payload($pdo,$merchantId),'Merchant location presence policy saved.');
}catch(InvalidArgumentException $e){mg_fail($e->getMessage(),422);}catch(RuntimeException $e){mg_fail($e->getMessage(),400);}catch(Throwable $e){mg_security_log('error','world_canvas.location_presence_failed','Merchant location presence policy failed.',['exception_class'=>$e::class,'message'=>$e->getMessage()],$merchantId);mg_fail('Unable to save merchant location presence policy.',500);}
