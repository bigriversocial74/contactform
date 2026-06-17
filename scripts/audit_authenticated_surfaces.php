<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$policy=require $root.'/config/security-route-policy.php';
$strict=in_array('--strict',$argv,true);
$json=in_array('--json',$argv,true);

function mg_audit_matches(string $path,array $patterns): bool
{
    foreach($patterns as $pattern){
        if(preg_match($pattern,$path)===1)return true;
    }
    return false;
}

function mg_audit_files(string $base): array
{
    if(!is_dir($base))return [];
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));
    $files=[];
    foreach($iterator as $file){
        if($file->isFile()&&strtolower($file->getExtension())==='php')$files[]=$file->getPathname();
    }
    sort($files);
    return $files;
}

$report=['pages'=>[],'apis'=>[],'violations'=>[],'warnings'=>[]];
$publicPages=array_fill_keys($policy['public_pages'],true);
$rootPages=glob($root.'/*.php')?:[];
sort($rootPages);

foreach($rootPages as $file){
    $name=basename($file);
    $source=(string)file_get_contents($file);
    $explicitPrivate=mg_audit_matches($name,$policy['private_page_patterns']);
    $protectedMode=preg_match('/\$header_mode\s*=\s*[\'\"](?:agent|account|crm|builder)[\'\"]/',$source)===1;
    $directGuard=str_contains($source,'mg_require_auth(');
    $isPublic=isset($publicPages[$name]);
    $classification=$isPublic?'public':(($explicitPrivate||$protectedMode||$directGuard)?'private':'unclassified');
    $protected=$directGuard||($protectedMode&&str_contains($source,'includes/header.php'));
    $report['pages'][]=['path'=>$name,'classification'=>$classification,'protected'=>$protected];

    if($classification==='private'&&!$protected){
        $report['violations'][]=$name.': private page lacks canonical auth protection.';
    }elseif($classification==='unclassified'){
        $report['warnings'][]=$name.': page is not classified by route policy.';
    }
}

foreach(mg_audit_files($root.'/api') as $file){
    $relative=str_replace('\\','/',substr($file,strlen($root)+1));
    if(str_starts_with(basename($relative),'_'))continue;
    $source=(string)file_get_contents($file);
    $isPublic=mg_audit_matches($relative,$policy['public_api_patterns']);
    $privatePrefix=false;
    foreach($policy['private_api_prefixes'] as $prefix){if(str_starts_with($relative,$prefix)){$privatePrefix=true;break;}}
    $permission=str_contains($source,'mg_require_permission(');
    $auth=str_contains($source,'mg_require_auth(')||str_contains($source,'mg_require_authenticated')||str_contains($source,'mg_current_user(');
    $ownership=preg_match('/\buser_id\s*=\s*\?|\bowner_user_id\s*=\s*\?|\brecipient_user_id\s*=\s*\?|\bsender_user_id\s*=\s*\?/i',$source)===1;
    $methods=[];
    if(preg_match_all('/mg_require_method\([\'\"]([A-Z]+)[\'\"]\)/',$source,$matches))$methods=$matches[1];
    $isWrite=(bool)array_intersect($methods,$policy['write_methods']);
    $csrf=str_contains($source,'mg_require_csrf_for_write(');
    $csrfExempt=mg_audit_matches($relative,$policy['csrf_exempt_patterns']);
    $classification=$isPublic?'public':($privatePrefix?'private':'unclassified');
    $report['apis'][]=['path'=>$relative,'classification'=>$classification,'permission'=>$permission,'auth'=>$auth,'ownership_signal'=>$ownership,'write'=>$isWrite,'csrf'=>$csrf];

    if($classification==='private'&&!$permission&&!$auth){
        $report['violations'][]=$relative.': private API lacks an authentication or permission gate.';
    }
    if($classification==='private'&&$isWrite&&!$csrf&&!$csrfExempt){
        $report['violations'][]=$relative.': session write lacks CSRF enforcement or an explicit exemption.';
    }
    if($classification==='private'&&!$ownership&&!str_contains($relative,'/preferences.php')&&!str_contains($relative,'/dashboard.php')&&!str_contains($relative,'/index.php')){
        $report['warnings'][]=$relative.': object-ownership scope needs manual verification.';
    }
    if($classification==='unclassified'){
        $report['warnings'][]=$relative.': API is not classified by route policy.';
    }
}

$summary=[
    'page_count'=>count($report['pages']),
    'api_count'=>count($report['apis']),
    'violations'=>count($report['violations']),
    'warnings'=>count($report['warnings']),
];

if($json){
    echo json_encode(['summary'=>$summary]+$report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
}else{
    echo "Authenticated Surface Audit\n";
    echo str_repeat('=',28)."\n";
    foreach($summary as $key=>$value)echo str_pad($key,18).": {$value}\n";
    if($report['violations']){
        echo "\nViolations\n----------\n";
        foreach($report['violations'] as $message)echo "- {$message}\n";
    }
    if($report['warnings']){
        echo "\nWarnings\n--------\n";
        foreach($report['warnings'] as $message)echo "- {$message}\n";
    }
}

exit($strict&&$report['violations']?1:0);
