<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health_access.php';
require_once __DIR__ . '/_system_health_storage.php';
require_once __DIR__ . '/_system_health_media.php';
require_once __DIR__ . '/_system_health_notifications.php';
require_once __DIR__ . '/_system_health_schema.php';

function mg_admin_system_health_read(PDO $pdo,array $user): array
{
    $tables=mg_admin_system_health_tables($pdo,[
        'schema_migrations','catalog_assets','feed_post_assets','feed_posts',
        'notification_delivery_jobs','security_logs','operational_check_results',
    ]);
    $access=mg_admin_system_health_access($user);
    $storage=mg_admin_system_health_storage($access);
    $media=mg_admin_system_health_media($pdo,$tables);
    $notifications=mg_admin_system_health_notifications($pdo,$tables);
    $schema=mg_admin_system_health_schema($pdo,$tables);
    $statuses=[(string)$storage['status'],(string)$media['status'],(string)$notifications['status'],(string)$schema['status']];
    $overall=in_array('critical',$statuses,true)?'critical':(in_array('warning',$statuses,true)?'warning':'healthy');
    return [
        'meta'=>['generated_at'=>gmdate('c'),'status'=>$overall,'php_version'=>PHP_VERSION,'request_id'=>mg_request_id()],
        'access'=>$access,
        'storage'=>$storage,
        'media'=>$media,
        'notifications'=>$notifications,
        'schema'=>$schema,
        'errors'=>[],
        'checks'=>[],
        'tables'=>$tables,
    ];
}
