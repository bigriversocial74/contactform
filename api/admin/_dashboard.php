<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_admin_schema.php';
require_once __DIR__ . '/_dashboard_queries.php';

const MG_ADMIN_DASHBOARD_DEFAULT_WINDOW_DAYS = 30;
const MG_ADMIN_DASHBOARD_MIN_WINDOW_DAYS = 7;
const MG_ADMIN_DASHBOARD_MAX_WINDOW_DAYS = 90;

function mg_admin_dashboard_window_days(mixed $value): int
{
    $days=filter_var($value,FILTER_VALIDATE_INT,['options'=>['default'=>MG_ADMIN_DASHBOARD_DEFAULT_WINDOW_DAYS]]);
    return max(MG_ADMIN_DASHBOARD_MIN_WINDOW_DAYS,min((int)$days,MG_ADMIN_DASHBOARD_MAX_WINDOW_DAYS));
}

function mg_admin_dashboard_access(array $user): array
{
    $roles=is_array($user['roles']??null)?$user['roles']:[];
    $permissions=is_array($user['permissions']??null)?$user['permissions']:[];
    $super=in_array('super_admin',$roles,true);
    $has=static fn(string $permission):bool=>$super||in_array($permission,$permissions,true);
    $access=[
        'users'=>$has('admin.users.view'),
        'models'=>$has('admin.users.view')||$has('admin.users.manage'),
        'moderation'=>$has('admin.profiles.moderation.view')||$has('admin.profiles.moderation.manage'),
        'moderation_manage'=>$has('admin.profiles.moderation.manage'),
        'audit'=>$has('admin.audit.view'),
        'health'=>$has('admin.health.view'),
        'security'=>$has('security.logs.view')||$has('admin.security_logs.view'),
        'sessions'=>$has('admin.sessions.view'),
        'alerts'=>$has('operational.alerts.view')||$has('admin.health.view'),
        'notifications'=>$has('admin.notifications.view')||$has('admin.support_queue.view')||$has('admin.user_notes.view')||$has('admin.users.manage'),
        'demand'=>$has('demand.dashboard.view')||$has('intelligence.dashboard.view')||$has('admin.health.view'),
        'commerce'=>$has('admin.commerce.view')||$has('admin.commerce.manage')||$has('merchant.payments.view')||$has('subscriptions.admin')||$has('microgift.operations.view')||$has('tips.reverse'),
        'super_admin'=>$super,
    ];
    $access['dashboard']=$super||in_array(true,$access,true);
    return $access;
}

function mg_admin_dashboard_require_user(): array
{
    $user=mg_require_api_user();
    if(!mg_admin_dashboard_access($user)['dashboard']){
        mg_security_log('warning','admin.dashboard.denied','Administrative dashboard access denied.',[],(int)$user['id']);
        mg_fail('Permission denied.',403);
    }
    return $user;
}

function mg_admin_dashboard_shortcuts(array $access): array
{
    $items=[];
    if($access['users']){
        $items[]=['label'=>'Users','description'=>'Inspect platform identities.','href'=>'/admin/users.php'];
        $items[]=['label'=>'Pending models','description'=>'Review requested user models.','href'=>'/admin/pending-models.php'];
    }
    if($access['notifications'])$items[]=['label'=>'Notifications','description'=>'Review queue alerts and notification read state.','href'=>'/admin/notifications.php'];
    if($access['commerce'])$items[]=['label'=>'Commerce operations','description'=>'Inspect cross-domain payments, lifecycle activity, and review cases.','href'=>'/commerce-operations.php'];
    if($access['moderation'])$items[]=['label'=>'Profile moderation','description'=>'Review profile cases, restrictions, and appeals.','href'=>'/account-profile-moderation.php'];
    if($access['audit'])$items[]=['label'=>'Audit logs','description'=>'Inspect administrative activity.','href'=>'/admin/audit-logs.php'];
    if($access['security'])$items[]=['label'=>'Security logs','description'=>'Review security events.','href'=>'/admin/security-logs.php'];
    if($access['sessions'])$items[]=['label'=>'Sessions','description'=>'Inspect active sessions.','href'=>'/admin/sessions.php'];
    if($access['health'])$items[]=['label'=>'System health','description'=>'Inspect storage, notifications, migrations, and recovery tools.','href'=>'/admin/system-health.php'];
    return $items;
}

function mg_admin_dashboard_notifications(PDO $pdo,array $tables,int $actorId): ?array
{
    if(empty($tables['admin_queue_notifications'])||empty($tables['admin_user_notes']))return null;

    $noteColumns=mg_admin_schema_columns($pdo,'admin_user_notes');
    $notificationColumns=mg_admin_schema_columns($pdo,'admin_queue_notifications');
    if(empty($notificationColumns['read_at'])||empty($notificationColumns['severity'])||empty($notificationColumns['notification_type'])){
        return [
            'total'=>0,
            'unread_total'=>0,
            'critical_unread_total'=>0,
            'overdue_unread_total'=>0,
            'escalated_unread_total'=>0,
            'overdue_queue_total'=>0,
            'escalated_queue_total'=>0,
            'assigned_to_me_total'=>0,
            'review_total'=>0,
            'schema_required'=>['admin_queue_notifications.read_at','admin_queue_notifications.severity','admin_queue_notifications.notification_type'],
        ];
    }

    $overdueQueue=!empty($noteColumns['due_at'])&&!empty($noteColumns['status'])?'(SELECT COUNT(*) FROM admin_user_notes WHERE status<>"resolved" AND due_at IS NOT NULL AND due_at<NOW())':'0';
    $escalatedQueue=!empty($noteColumns['status'])?'(SELECT COUNT(*) FROM admin_user_notes WHERE status="escalated")':'0';
    $assignedToMe=!empty($noteColumns['assigned_admin_user_id'])&&!empty($noteColumns['status'])?'(SELECT COUNT(*) FROM admin_user_notes WHERE assigned_admin_user_id=? AND status<>"resolved")':'0';
    $reviewTotal=!empty($noteColumns['flag_state'])&&!empty($noteColumns['status'])?'(SELECT COUNT(*) FROM admin_user_notes WHERE flag_state IN ("flagged","review") AND status<>"resolved")':'0';

    $stmt=$pdo->prepare('SELECT COUNT(*) total,
        SUM(n.read_at IS NULL) unread_total,
        SUM(n.read_at IS NULL AND n.severity="critical") critical_unread_total,
        SUM(n.read_at IS NULL AND n.notification_type="overdue") overdue_unread_total,
        SUM(n.read_at IS NULL AND n.notification_type="escalated") escalated_unread_total,
        '.$overdueQueue.' overdue_queue_total,
        '.$escalatedQueue.' escalated_queue_total,
        '.$assignedToMe.' assigned_to_me_total,
        '.$reviewTotal.' review_total
        FROM admin_queue_notifications n');
    $stmt->execute(!empty($noteColumns['assigned_admin_user_id'])&&!empty($noteColumns['status'])?[$actorId]:[]);
    $payload=array_map('intval',$stmt->fetch(PDO::FETCH_ASSOC)?:[]);
    $payload['schema_required']=[];
    return $payload;
}

function mg_admin_dashboard_read(PDO $pdo,array $user,array $options=[]): array
{
    mg_admin_dashboard_query_count_reset();
    $access=mg_admin_dashboard_access($user);
    if(!$access['dashboard'])throw new RuntimeException('Permission denied.');

    $days=mg_admin_dashboard_window_days($options['window_days']??null);
    $cutoff=gmdate('Y-m-d H:i:s',time()-($days*86400));
    $required=['users','public_profiles','user_model_assignments','merchant_storefronts','catalog_products','feed_posts','commerce_orders','payment_refunds','payment_disputes','subscriptions','tips','microgift_instances','microgift_claims','microgift_redemptions','operational_alerts','security_logs','audit_logs','user_sessions','demand_signal_orchestrations','operational_incidents','deployment_releases','operational_check_results','profile_moderation_cases','profile_moderation_actions','profile_moderation_appeals'];
    $tables=mg_admin_dashboard_existing_tables($pdo);
    $noteTableStmt=$pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?)');
    $noteTableStmt->execute(['admin_queue_notifications','admin_user_notes']);
    foreach($noteTableStmt->fetchAll(PDO::FETCH_ASSOC) as $row){$tables[(string)$row['TABLE_NAME']]=true;}
    $missing=array_values(array_diff($required,array_keys($tables)));

    $platform=($access['users']||$access['models']||$access['moderation'])?mg_admin_dashboard_platform($pdo,$tables,$cutoff,$access['moderation']):null;
    $commerce=$access['commerce']?mg_admin_dashboard_commerce($pdo,$tables,$cutoff):null;
    $operations=($access['health']||$access['alerts']||$access['security']||$access['sessions']||$access['demand'])?mg_admin_dashboard_operations($pdo,$tables,$cutoff):null;
    $notifications=$access['notifications']?mg_admin_dashboard_notifications($pdo,$tables,(int)$user['id']):null;

    $status=$missing?'degraded':'healthy';
    if(($operations['critical_alerts']??0)>0||($operations['open_incidents']??0)>0||($operations['failed_checks']??0)>0||($platform['moderation_urgent']??0)>0||($notifications['critical_unread_total']??0)>0||($notifications['overdue_queue_total']??0)>0)$status='attention';

    $data=[
        'access'=>$access,
        'platform'=>$platform,
        'commerce'=>$commerce,
        'operations'=>$operations,
        'notifications'=>$notifications,
        'alerts'=>$access['alerts']?mg_admin_dashboard_recent_alerts($pdo,$tables):[],
        'security'=>$access['security']?mg_admin_dashboard_recent_security($pdo,$tables):[],
        'audit'=>$access['audit']?mg_admin_dashboard_recent_audit($pdo,$tables):[],
        'checks'=>$access['health']?mg_admin_dashboard_recent_checks($pdo,$tables):[],
        'incidents'=>$access['health']?mg_admin_dashboard_recent_incidents($pdo,$tables):[],
        'release'=>$access['health']?mg_admin_dashboard_latest_release($pdo,$tables):null,
        'shortcuts'=>mg_admin_dashboard_shortcuts($access),
    ];
    $data['meta']=[
        'generated_at'=>gmdate('c'),
        'window_days'=>$days,
        'status'=>$status,
        'query_count'=>mg_admin_dashboard_query_count(),
        'missing_tables'=>$missing,
    ];
    return $data;
}
