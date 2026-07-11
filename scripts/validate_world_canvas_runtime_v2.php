<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$checks=[
    'world-canvas.php'=>['maplibre-gl@5.7.1','three@0.160.0','world-canvas-runtime-v2.js','data-world-persona-select'],
    'assets/js/world-canvas-runtime-v2.js'=>['new window.maplibregl.Map','new window.maplibregl.Marker','draggable:Boolean(d.owned)','new window.THREE.WebGLRenderer','/api/world-canvas/persona.php','/api/world-canvas/location-presence.php'],
    'api/world-canvas/activity.php'=>["require_once __DIR__ . '/_shared_users_v2.php'",'mg_world_canvas_merge_shared_users_v2($pdo, $user, $payload)'],
    'api/world-canvas/_runtime_v2.php'=>["'merchant_location_source' => 'merchant_locations'","'user_location_source' => 'user_world_positions'","'random_geo_fallback' => false",'entity_key'],
    'api/world-canvas/_shared_users_v2.php'=>['user_world_positions','shared_user_world_position',"'entity_key' => 'user:' . $userId",'current_user_position_is_world_share'],
    'api/store/_presence.php'=>['allow_unattended','temporarily_closed','mg_presence_watch','mg_presence_notify_return','store_presence','merchant_returned'],
    'api/store/enter.php'=>['mg_presence_entry_status','blocked_closed','entered_unattended','requires_confirmation','merchant_location_id','merchant_presence'],
    'merchant-canvas.php'=>['/assets/js/merchant-canvas-presence.js'],
    'database/stage_33_world_canvas_persona_presence.sql'=>['world_canvas_persona_state','mg_store_presence_watchers','world_presence_mode','merchant_location_id'],
];
$errors=[];
foreach($checks as $file=>$needles){
    $path=$root.'/'.$file;$source=is_file($path)?file_get_contents($path):false;
    if(!is_string($source)){$errors[]="Missing {$file}";continue;}
    foreach($needles as $needle)if(!str_contains($source,$needle))$errors[]="{$file} missing {$needle}";
}
$page=@file_get_contents($root.'/world-canvas.php')?:'';
foreach(['/assets/js/world-canvas-square-map.js','/assets/js/world-canvas-geo-zoom'] as $legacy)if(str_contains($page,$legacy))$errors[]="Legacy runtime still loaded: {$legacy}";
$entry=@file_get_contents($root.'/api/store/enter.php')?:'';
if(!str_contains($entry,"if (!$requiresConfirmation && !empty($presence['merchant_away'])"))$errors[]='Unattended entry messaging must wait until store-switch confirmation is complete.';
if($errors){fwrite(STDERR,"World Canvas Runtime v2 validation failed:\n- ".implode("\n- ",$errors)."\n");exit(1);}echo "World Canvas Runtime v2 validation passed.\n";
