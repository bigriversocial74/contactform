<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/store/_presence.php';
require_once __DIR__ . '/_locations.php';

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=$method==='GET'?mg_require_api_user():mg_require_api_user();
$pdo=mg_db();$userId=(int)($user['id']??0);

function mg_world_persona_profile(PDO $pdo,int $userId): array {
    $row=[];try{$s=$pdo->prepare('SELECT public_id,display_name,avatar_url,slug,profile_type FROM public_profiles WHERE user_id=? LIMIT 1');$s->execute([$userId]);$row=$s->fetch(PDO::FETCH_ASSOC)?:[];}catch(Throwable){}
    return $row;
}
function mg_world_persona_user_geo(PDO $pdo,int $userId): ?array {
    if(function_exists('mg_world_location_current_user'))return mg_world_location_current_user($pdo,$userId);return null;
}
function mg_world_persona_payload(PDO $pdo,array $user): array {
    $userId=(int)($user['id']??0);$profile=mg_world_persona_profile($pdo,$userId);$personas=[];$locations=[];
    $userGeo=mg_world_persona_user_geo($pdo,$userId);$personas[]=[
        'key'=>'user:'.$userId,'kind'=>'user','label'=>'Explore as '.(trim((string)($profile['display_name']??''))?:'yourself'),'title'=>trim((string)($profile['display_name']??''))?:'Your user avatar',
        'avatar_url'=>function_exists('mg_store_avatar_url')?mg_store_avatar_url($profile['avatar_url']??null):($profile['avatar_url']??null),
        'latitude'=>$userGeo['latitude']??null,'longitude'=>$userGeo['longitude']??null,'geo'=>$userGeo,'location_source'=>'user_world_positions',
    ];
    if(mg_presence_table($pdo,'merchant_locations')){
        foreach(mg_presence_locations($pdo,$userId) as $row){$projected=mg_presence_project($row);$locations[]=$projected;$personas[]=[
            'key'=>'merchant:'.(string)$row['public_id'],'kind'=>'merchant','label'=>'Operate as '.(trim((string)$row['name'])?:'Merchant location'),'title'=>trim((string)$row['name'])?:'Merchant location','location_name'=>trim((string)$row['name'])?:'Merchant location',
            'location_id'=>(string)$row['public_id'],'database_location_id'=>(int)$row['id'],'latitude'=>$row['latitude']===null?null:(float)$row['latitude'],'longitude'=>$row['longitude']===null?null:(float)$row['longitude'],
            'geo'=>['latitude'=>$row['latitude']===null?null:(float)$row['latitude'],'longitude'=>$row['longitude']===null?null:(float)$row['longitude'],'source'=>'merchant_locations'],
            'location_source'=>'merchant_locations','presence_mode'=>$projected['presence_mode'],'presence_status'=>$projected['presence_status'],'entry_allowed'=>$projected['entry_allowed'],
        ];}
    }
    $saved=mg_presence_persona_state($pdo,$userId);$active='user:'.$userId;
    if($saved&&($saved['persona_kind']??'')==='merchant'&&!empty($saved['merchant_location_id'])){
        foreach($locations as $location){if((int)$location['database_id']===(int)$saved['merchant_location_id']){$active='merchant:'.$location['id'];break;}}
    }
    return ['schema_ready'=>mg_presence_ready($pdo),'personas'=>$personas,'locations'=>$locations,'active_persona_key'=>$active,'persona_state'=>$saved,'dual_persona'=>true];
}

try{
    if($method==='GET'){mg_rate_limit('world_canvas.persona.read','user:'.$userId,180,60);mg_ok(mg_world_persona_payload($pdo,$user));}
    if($method!=='POST')mg_fail('Method not allowed.',405);
    $input=mg_input();mg_require_csrf_for_write($input);mg_rate_limit('world_canvas.persona.write','user:'.$userId,40,60);
    if(!mg_presence_ready($pdo))throw new RuntimeException('World Canvas persona schema is not installed. Run database/stage_33_world_canvas_persona_presence.sql.');
    $key=trim((string)($input['persona_key']??''));
    if($key==='user:'.$userId||$key==='user'){
        mg_presence_save_persona($pdo,$userId,'user',null,'world_canvas',['selected_at'=>gmdate('c')]);
        mg_ok(mg_world_persona_payload($pdo,$user),'User persona activated.');
    }
    if(preg_match('/^merchant:(.+)$/',$key,$m)!==1)throw new InvalidArgumentException('A valid World Canvas persona is required.');
    $location=mg_presence_location($pdo,$userId,$m[1]);if(!$location)throw new RuntimeException('That registered merchant location is unavailable.');
    $transition=mg_presence_transition($pdo,$userId,(int)$location['id'],'world',$userId);
    mg_presence_save_persona($pdo,$userId,'merchant',(int)$location['id'],'world_canvas',['selected_at'=>gmdate('c'),'location_public_id'=>(string)$location['public_id']]);
    $payload=mg_world_persona_payload($pdo,$user);$payload['transition']=$transition;mg_ok($payload,'Merchant persona activated in World Canvas.');
}catch(InvalidArgumentException $e){mg_fail($e->getMessage(),422);}catch(RuntimeException $e){mg_fail($e->getMessage(),400);}catch(Throwable $e){mg_security_log('error','world_canvas.persona_failed','World Canvas persona action failed.',['exception_class'=>$e::class,'message'=>$e->getMessage()],$userId);mg_fail('Unable to update World Canvas persona.',500);}
