<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_admin_system_health_access(array $user): array
{
    $roles=is_array($user['roles']??null)?$user['roles']:[];
    $permissions=is_array($user['permissions']??null)?$user['permissions']:[];
    $super=in_array('super_admin',$roles,true);
    return [
        'view'=>$super||in_array('admin.health.view',$permissions,true)||in_array('operations.readiness.view',$permissions,true),
        'manage'=>$super||in_array('admin.health.manage',$permissions,true),
        'archive'=>$super,
        'super_admin'=>$super,
    ];
}

function mg_admin_system_health_require_user(bool $manage=false): array
{
    $user=mg_require_api_user();
    $access=mg_admin_system_health_access($user);
    if(!$access['view']||($manage&&!$access['manage'])){
        mg_security_log('warning','admin.system_health.denied','System health access denied.',['manage'=>$manage],(int)$user['id']);
        mg_fail('Permission denied.',403);
    }
    return $user;
}

function mg_admin_system_health_tables(PDO $pdo,array $names): array
{
    $stmt=$pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($names),'?')).')');
    $stmt->execute($names);
    return array_fill_keys(array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN)),true);
}
