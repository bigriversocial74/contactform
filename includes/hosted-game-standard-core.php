<?php
declare(strict_types=1);

require_once __DIR__ . '/hosted-games.php';

const MG_HOSTED_GAME_STANDARD_SCHEMA = 'microgifter.hosted-game/v1';
const MG_HOSTED_GAME_STANDARD_SDK_VERSION = '1.1.0';
const MG_HOSTED_GAME_STANDARD_MAX_MANIFEST_BYTES = 65536;

function mg_hosted_game_standard_events(): array
{
    return ['game_loaded','run_started','level_started','score_updated','level_completed','player_qualified','run_completed','run_abandoned','runtime_error'];
}

function mg_hosted_game_standard_capabilities(): array
{
    return ['player','runs','events','state','scores','leaderboard','inbox','fullscreen','pointer_lock','forms','modals','popups','downloads','gamepad','motion','audio','clipboard_write'];
}

function mg_hosted_game_standard_default_capabilities(bool $legacy): array
{
    return $legacy ? mg_hosted_game_standard_capabilities() : ['player','runs','events','state','scores','leaderboard','inbox','fullscreen','audio'];
}

function mg_hosted_game_standard_text(mixed $value, string $fallback, int $maxLength): string
{
    $value = trim((string)$value);
    if ($value === '') $value = $fallback;
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    if ($length > $maxLength) throw new InvalidArgumentException('A game.json text value exceeds the supported length.');
    return $value;
}

function mg_hosted_game_standard_enum(mixed $value, array $allowed, string $fallback, string $label): string
{
    $value = strtolower(trim((string)$value));
    if ($value === '') return $fallback;
    if (!in_array($value,$allowed,true)) throw new InvalidArgumentException("game.json contains an unsupported {$label}.");
    return $value;
}

function mg_hosted_game_standard_int(mixed $value, int $fallback, int $min, int $max, string $label): int
{
    if ($value === null || $value === '') return $fallback;
    $value = filter_var($value,FILTER_VALIDATE_INT);
    if ($value === false || $value < $min || $value > $max) throw new InvalidArgumentException("game.json {$label} must be between {$min} and {$max}.");
    return (int)$value;
}

function mg_hosted_game_standard_list(mixed $value, array $allowed, array $fallback, string $label): array
{
    if ($value === null) return $fallback;
    if (!is_array($value)) throw new InvalidArgumentException("game.json {$label} must be an array.");
    $result=[];
    foreach($value as $item){
        $item=strtolower(trim((string)$item));
        if($item===''||!in_array($item,$allowed,true)) throw new InvalidArgumentException("game.json contains an unsupported {$label} value.");
        $result[$item]=true;
    }
    return array_keys($result);
}

function mg_hosted_game_standard_safe_path(mixed $value, string $fallback=''): string
{
    $path=str_replace('\\','/',trim((string)$value));
    $path=ltrim(preg_replace('#/+#','/',$path)??'','/');
    if($path==='')return $fallback;
    if(strlen($path)>700||str_contains($path,"\0"))throw new InvalidArgumentException('game.json contains an invalid file path.');
    $parts=[];
    foreach(explode('/',$path) as $part){
        if($part===''||$part==='.')continue;
        if($part==='..'||str_starts_with($part,'.'))throw new InvalidArgumentException('game.json file paths may not leave the game package.');
        $parts[]=$part;
    }
    return implode('/',$parts);
}

function mg_hosted_game_standard_network_sources(mixed $value): array
{
    if($value===null)return [];
    if(!is_array($value))throw new InvalidArgumentException('game.json network.connect must be an array.');
    $sources=[];
    foreach($value as $source){
        $source=trim((string)$source);
        if($source==='self'){$sources['self']=true;continue;}
        $parts=parse_url($source);
        if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host']))throw new InvalidArgumentException('game.json network.connect accepts only "self" or HTTPS origins.');
        if(!empty($parts['path'])&&$parts['path']!=='/')throw new InvalidArgumentException('game.json network.connect values must be origins, not URL paths.');
        $origin='https://'.strtolower((string)$parts['host']);
        if(isset($parts['port']))$origin.=':'.(int)$parts['port'];
        $sources[$origin]=true;
        if(count($sources)>12)throw new InvalidArgumentException('game.json declares too many network origins.');
    }
    return array_keys($sources);
}

function mg_hosted_game_standard_normalize_manifest(array $manifest,array $game,array $availablePaths=[],?string $resolvedEntry=null): array
{
    $manifestPresent=$manifest!==[];
    $declaredSchema=trim((string)($manifest['schema']??''));
    if($declaredSchema!==''&&$declaredSchema!==MG_HOSTED_GAME_STANDARD_SCHEMA)throw new InvalidArgumentException('game.json schema is unsupported. Use '.MG_HOSTED_GAME_STANDARD_SCHEMA.'.');
    $standard=$declaredSchema===MG_HOSTED_GAME_STANDARD_SCHEMA;
    $legacy=!$standard;
    $entry=mg_hosted_game_standard_safe_path($resolvedEntry??($manifest['entry']??($game['entry_file']??'index.html')),'index.html');
    if($availablePaths!==[]&&!isset($availablePaths[$entry]))throw new InvalidArgumentException('game.json entry does not exist in the game package.');
    if(!in_array(strtolower(pathinfo($entry,PATHINFO_EXTENSION)),['html','htm'],true))throw new InvalidArgumentException('game.json entry must reference an HTML file.');
    $version=mg_hosted_game_standard_text($manifest['version']??'1.0.0','1.0.0',40);
    if(preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/',$version)!==1)throw new InvalidArgumentException('game.json version must use semantic version format such as 1.0.0.');
    $viewport=is_array($manifest['viewport']??null)?$manifest['viewport']:[];
    $session=is_array($manifest['session']??null)?$manifest['session']:[];
    $scoring=is_array($manifest['scoring']??null)?$manifest['scoring']:[];
    $qualification=is_array($manifest['qualification']??null)?$manifest['qualification']:[];
    $network=is_array($manifest['network']??null)?$manifest['network']:[];
    $assets=is_array($manifest['assets']??null)?$manifest['assets']:[];
    $capabilities=mg_hosted_game_standard_list($manifest['capabilities']??null,mg_hosted_game_standard_capabilities(),mg_hosted_game_standard_default_capabilities($legacy),'capability');
    $events=mg_hosted_game_standard_list($manifest['events']??null,mg_hosted_game_standard_events(),mg_hosted_game_standard_events(),'event');
    $assetResult=['cover'=>null,'icon'=>null];
    foreach(['cover','icon'] as $role){
        $path=mg_hosted_game_standard_safe_path($assets[$role]??'');
        if($path==='')continue;
        if($availablePaths!==[]&&!isset($availablePaths[$path]))throw new InvalidArgumentException("game.json assets.{$role} does not exist in the game package.");
        if(!in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['jpg','jpeg','png','gif','webp','svg','ico','avif'],true))throw new InvalidArgumentException("game.json assets.{$role} must be an image file.");
        $assetResult[$role]=$path;
    }
    $normalized=[
        'schema'=>MG_HOSTED_GAME_STANDARD_SCHEMA,
        'standard'=>['version'=>'1.0.0','compliance'=>$standard?'standard':'legacy','manifest_present'=>$manifestPresent,'declared_schema'=>$declaredSchema!==''?$declaredSchema:null],
        'name'=>mg_hosted_game_standard_text($manifest['name']??($game['name']??'Hosted Game'),(string)($game['name']??'Hosted Game'),180),
        'version'=>$version,
        'entry'=>$entry,
        'description'=>mg_hosted_game_standard_text($manifest['description']??($game['description']??''),'',2000),
        'category'=>mg_hosted_game_standard_enum($manifest['category']??'casual',['casual','arcade','puzzle','trivia','music','video','adventure','simulation','educational','promotional','other'],'casual','category'),
        'orientation'=>mg_hosted_game_standard_enum($manifest['orientation']??'any',['any','portrait','landscape'],'any','orientation'),
        'viewport'=>['min_width'=>mg_hosted_game_standard_int($viewport['min_width']??320,320,240,4096,'viewport.min_width'),'min_height'=>mg_hosted_game_standard_int($viewport['min_height']??480,480,240,4096,'viewport.min_height')],
        'session'=>['max_duration_seconds'=>mg_hosted_game_standard_int($session['max_duration_seconds']??1800,1800,30,86400,'session.max_duration_seconds')],
        'capabilities'=>$capabilities,
        'events'=>$events,
        'scoring'=>['mode'=>mg_hosted_game_standard_enum($scoring['mode']??'points',['none','points','time','distance','custom'],'points','scoring.mode'),'sort'=>mg_hosted_game_standard_enum($scoring['sort']??'high',['high','low'],'high','scoring.sort'),'integer'=>array_key_exists('integer',$scoring)?(bool)$scoring['integer']:true],
        'qualification'=>['mode'=>mg_hosted_game_standard_enum($qualification['mode']??'game_reported',['none','game_reported','server_review'],'game_reported','qualification.mode')],
        'network'=>['connect'=>mg_hosted_game_standard_network_sources($network['connect']??null)],
        'assets'=>$assetResult,
    ];
    $json=json_encode($normalized,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if(!is_string($json)||strlen($json)>MG_HOSTED_GAME_STANDARD_MAX_MANIFEST_BYTES)throw new InvalidArgumentException('The normalized game.json exceeds the 64 KB manifest limit.');
    return $normalized;
}

function mg_hosted_game_standard_manifest_from_game(array $game): array
{
    return mg_hosted_game_standard_normalize_manifest(mg_hosted_game_json_decode($game['manifest_json']??null),$game,[],(string)($game['entry_file']??'index.html'));
}

function mg_hosted_game_standard_public_manifest(array $manifest): array
{
    return array_intersect_key($manifest,array_flip(['schema','standard','name','version','entry','description','category','orientation','viewport','session','capabilities','events','scoring','qualification','assets']));
}

function mg_hosted_game_standard_has_capability(array $manifest,string $capability): bool
{
    return in_array($capability,$manifest['capabilities']??[],true);
}
