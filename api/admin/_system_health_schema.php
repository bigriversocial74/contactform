<?php
declare(strict_types=1);

function mg_admin_system_health_schema(PDO $pdo,array $tables): array
{
    if(empty($tables['schema_migrations'])){
        return ['available'=>false,'ready'=>false,'status'=>'critical','expected_key'=>'stage_18j_admin_system_health','latest'=>null];
    }
    $expected='stage_18j_admin_system_health';
    $latest=$pdo->query('SELECT migration_key,applied_at FROM schema_migrations ORDER BY applied_at DESC,id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC)?:null;
    $check=$pdo->prepare('SELECT applied_at FROM schema_migrations WHERE migration_key=? LIMIT 1');
    $check->execute([$expected]);
    $appliedAt=$check->fetchColumn();
    $count=(int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    return [
        'available'=>true,
        'ready'=>$appliedAt!==false,
        'status'=>$appliedAt!==false?'healthy':'critical',
        'expected_key'=>$expected,
        'expected_applied_at'=>$appliedAt!==false?(string)$appliedAt:null,
        'applied_key_count'=>$count,
        'latest'=>$latest?['key'=>(string)$latest['migration_key'],'applied_at'=>(string)$latest['applied_at']]:null,
    ];
}
