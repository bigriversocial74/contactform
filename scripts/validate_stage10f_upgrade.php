<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli')exit(1);

$root=dirname(__DIR__);
$temp=sys_get_temp_dir().'/microgifter_stage10f_'.bin2hex(random_bytes(4)).'.sql';
$command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/build_full_upgrade_sql.php').' '.escapeshellarg($temp);
passthru($command,$exitCode);
if($exitCode!==0)throw new RuntimeException('Full upgrade builder failed.');

$sql=file_get_contents($temp);
if(!is_string($sql)||$sql==='')throw new RuntimeException('Generated upgrade SQL is empty.');

$required=[
    'stage_10b_location_claim_authority.sql',
    'stage_10c_atomic_claim_redemption_inbox.sql',
    'stage_10d_merchant_claim_operations.sql',
    'stage_10e_outbox_dashboard_policies_retention.sql',
    'stage_10f_architecture_deployment_action_center.sql',
];
$last=-1;
foreach($required as $file){
    $position=strpos($sql,'-- BEGIN '.$file);
    if($position===false)throw new RuntimeException('Generated upgrade is missing '.$file);
    if($position<=$last)throw new RuntimeException('Migration order is invalid at '.$file);
    $last=$position;
}

preg_match_all("/INSERT\s+INTO\s+schema_migrations\s*\([^;]+?VALUES\s*\(\s*'([^']+)'/is",$sql,$matches);
$keys=$matches[1]??[];
$duplicates=array_keys(array_filter(array_count_values($keys),static fn(int $count):bool=>$count>1));
if($duplicates)throw new RuntimeException('Duplicate schema migration keys: '.implode(', ',$duplicates));

$manifest=preg_replace('/\.sql$/','.manifest.json',$temp);
if(!is_string($manifest)||!is_file($manifest))throw new RuntimeException('Migration manifest was not generated.');
$manifestData=json_decode((string)file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
if((int)($manifestData['migration_count']??0)<count($required))throw new RuntimeException('Migration manifest is incomplete.');

@unlink($temp);
@unlink($manifest);
echo "Stage 10F full-upgrade validation passed.\n";
