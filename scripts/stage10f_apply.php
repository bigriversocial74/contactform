<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli')exit(1);

require_once dirname(__DIR__).'/api/db.php';

$root=dirname(__DIR__);
$files=[
    'stage_10b_location_claim_authority.sql',
    'stage_10c_atomic_claim_redemption_inbox.sql',
    'stage_10d_merchant_claim_operations.sql',
    'stage_10e_outbox_dashboard_policies_retention.sql',
    'stage_10f_architecture_deployment_action_center.sql',
];
$pdo=mg_db();
$lock='microgifter_stage10_migrations';
$lockStmt=$pdo->prepare('SELECT GET_LOCK(?,30)');
$lockStmt->execute([$lock]);
if((int)$lockStmt->fetchColumn()!==1)throw new RuntimeException('Could not acquire Stage 10 migration lock.');

try{
    foreach($files as $file){
        $path=$root.'/database/'.$file;
        $sql=file_get_contents($path);
        if(!is_string($sql)||trim($sql)==='')throw new RuntimeException('Missing or empty migration: '.$file);
        echo 'APPLY '.$file.PHP_EOL;
        $pdo->exec($sql);
    }
    echo "Stage 10B-10F migrations applied.\n";
}finally{
    $release=$pdo->prepare('SELECT RELEASE_LOCK(?)');
    $release->execute([$lock]);
}
