<?php
declare(strict_types=1);

function mg_admin_system_health_storage(array $access): array
{
    $config=mg_storage_config();
    try{
        $status=mg_storage_assert_ready(false,false);
        $root=(string)$status['root'];
        $total=@disk_total_space($root);
        $free=@disk_free_space($root);
        $freePercent=is_numeric($total)&&$total>0&&is_numeric($free)?round(((float)$free/(float)$total)*100,1):null;
        $tone='healthy';
        if(!$status['persistent']||!$status['writable'])$tone='critical';
        elseif(($freePercent!==null&&$freePercent<10)||(is_numeric($free)&&$free<2147483648))$tone='warning';
        return [
            'status'=>$tone,
            'driver'=>(string)$status['driver'],
            'provider_label'=>'Local persistent storage',
            'persistent'=>(bool)$status['persistent'],
            'initialized'=>(bool)$status['initialized'],
            'writable'=>(bool)$status['writable'],
            'root'=>$access['super_admin']?$root:basename($root),
            'root_redacted'=>!$access['super_admin'],
            'free_bytes'=>is_numeric($free)?(int)$free:null,
            'total_bytes'=>is_numeric($total)?(int)$total:null,
            'free_percent'=>$freePercent,
            'public_endpoint'=>(string)$config['public_endpoint'],
            'message'=>$tone==='healthy'?'Persistent storage is available.':($tone==='warning'?'Persistent storage is low on free space.':'Persistent storage needs attention.'),
        ];
    }catch(Throwable $error){
        return [
            'status'=>'critical','driver'=>(string)($config['driver']??'persistent_local'),
            'provider_label'=>'Local persistent storage','persistent'=>false,'initialized'=>false,'writable'=>false,
            'root'=>$access['super_admin']?(string)($config['root']??''):'Unavailable','root_redacted'=>!$access['super_admin'],
            'free_bytes'=>null,'total_bytes'=>null,'free_percent'=>null,
            'public_endpoint'=>(string)($config['public_endpoint']??'/api/public/media.php'),'message'=>$error->getMessage(),
        ];
    }
}
