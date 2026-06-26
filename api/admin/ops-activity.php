<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';

mg_require_method('GET');
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_ops_activity_has(array $actor): bool
{
    return mg_admin_account_actor_has($actor, 'admin.audit.view')
        || mg_admin_account_actor_has($actor, 'admin.operations_command.view')
        || mg_admin_account_actor_has($actor, 'admin.operations_analytics.view')
        || mg_admin_account_actor_has($actor, 'admin.operations_forecast.view')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_ops_activity_category(string $action): string
{
    if (str_contains($action, 'risk_forecast')) { return 'Risk forecast'; }
    if (str_contains($action, 'ops_readiness') || str_contains($action, 'admin_ops_readiness') || str_contains($action, 'admin_ops_sql_plan')) { return 'Readiness'; }
    if (str_contains($action, 'incident_analytics')) { return 'Incident analytics'; }
    if (str_contains($action, 'incident_review') || str_contains($action, 'ops_review')) { return 'Postmortems'; }
    if (str_contains($action, 'incident')) { return 'Incident mode'; }
    if (str_contains($action, 'system_health')) { return 'System health'; }
    if (str_contains($action, 'queue_automation') || str_contains($action, 'automation')) { return 'Automation'; }
    if (str_contains($action, 'notification')) { return 'Notifications'; }
    if (str_contains($action, 'queue') || str_contains($action, 'support')) { return 'Queue'; }
    return 'Admin ops';
}

function mg_admin_ops_activity_actions(): array
{
    return [
        'admin_ops_readiness_viewed',
        'admin.system_health.admin_ops_sql_plan',
        'admin.system_health.migration_plan',
        'admin.system_health.verify_storage',
        'admin.system_health.retry_notifications',
        'admin.system_health.clean_uploads',
        'admin_incident_declared',
        'admin_incident_updated',
        'admin_incident_resolved',
        'admin_ops_reviews_viewed',
        'admin_ops_review_saved',
        'admin_incident_analytics_viewed',
        'admin_risk_forecast_viewed',
        'admin_notification_mark_read',
        'admin_notification_mark_unread',
        'admin_notification_mark_all_read',
        'admin_queue_automation_run',
        'admin_queue_automation_viewed',
        'admin_ops_command_viewed',
    ];
}

function mg_admin_ops_activity_like_patterns(): array
{
    return ['%ops%','%incident%','%postmortem%','%risk_forecast%','%system_health%','%readiness%','%queue_automation%','%notification%'];
}

function mg_admin_ops_activity_summary(PDO $pdo, array $params): array
{
    $sql = 'SELECT COUNT(*) total,
                   SUM(created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) last_24h,
                   SUM(action LIKE "%incident%") incidents,
                   SUM(action LIKE "%risk_forecast%") forecasts,
                   SUM(action LIKE "%readiness%" OR action LIKE "%system_health%") readiness,
                   COUNT(DISTINCT user_id) actors
            FROM audit_logs
            WHERE ' . $params['where'];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params['values']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'total'=>(int)($row['total'] ?? 0),
        'last_24h'=>(int)($row['last_24h'] ?? 0),
        'incidents'=>(int)($row['incidents'] ?? 0),
        'forecasts'=>(int)($row['forecasts'] ?? 0),
        'readiness'=>(int)($row['readiness'] ?? 0),
        'actors'=>(int)($row['actors'] ?? 0),
    ];
}

function mg_admin_ops_activity_filters(array $query): array
{
    $where = [];
    $values = [];
    $actions = mg_admin_ops_activity_actions();
    $patterns = mg_admin_ops_activity_like_patterns();
    $actionFilter = strtolower(trim((string)($query['action'] ?? '')));
    $category = strtolower(trim((string)($query['category'] ?? '')));
    $q = trim((string)($query['q'] ?? ''));
    $days = max(1, min(365, (int)($query['days'] ?? 30)));
    $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';
    if ($actionFilter !== '' && in_array($actionFilter, $actions, true)) {
        $where[] = 'action = ?';
        $values[] = $actionFilter;
    } else {
        $ops = [];
        foreach ($actions as $action) { $ops[] = 'action = ?'; $values[] = $action; }
        foreach ($patterns as $pattern) { $ops[] = 'action LIKE ?'; $values[] = $pattern; }
        $where[] = '(' . implode(' OR ', $ops) . ')';
    }
    if ($category !== '') {
        $map = [
            'readiness'=>['%readiness%','%system_health%','%admin_ops_sql_plan%'],
            'incident'=>['%incident%'],
            'postmortems'=>['%incident_review%','%ops_review%','%postmortem%'],
            'analytics'=>['%incident_analytics%'],
            'forecast'=>['%risk_forecast%'],
            'automation'=>['%automation%'],
            'notifications'=>['%notification%'],
        ];
        if (isset($map[$category])) {
            $parts = [];
            foreach ($map[$category] as $pattern) { $parts[] = 'action LIKE ?'; $values[] = $pattern; }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
    }
    if ($q !== '') {
        $where[] = '(action LIKE ? OR entity_type LIKE ? OR metadata_json LIKE ? OR users.email LIKE ? OR users.display_name LIKE ? OR users.full_name LIKE ?)';
        $needle = '%' . $q . '%';
        array_push($values, $needle, $needle, $needle, $needle, $needle, $needle);
    }
    return ['where'=>implode(' AND ', $where), 'values'=>$values, 'days'=>$days, 'action'=>$actionFilter, 'category'=>$category, 'q'=>$q];
}

try {
    mg_rate_limit('admin.ops_activity.read', 'user:' . $actorId, 180, 60);
    if (!mg_admin_ops_activity_has($actor)) {
        mg_audit('permission_denied', 'security', ['permission'=>'admin.ops_activity.view'], $actorId);
        mg_security_log('warning', 'admin.ops_activity.denied', 'Admin ops activity permission denied.', [], $actorId);
        mg_fail('Permission denied.', 403);
    }
    $filters = mg_admin_ops_activity_filters($_GET);
    $limit = max(25, min(200, (int)($_GET['limit'] ?? 100)));
    $summary = mg_admin_ops_activity_summary($pdo, $filters);
    $sql = 'SELECT audit_logs.id, audit_logs.user_id, audit_logs.action, audit_logs.entity_type, audit_logs.metadata_json, audit_logs.ip_address, audit_logs.user_agent, audit_logs.created_at,
                   users.email, users.display_name, users.full_name
            FROM audit_logs
            LEFT JOIN users ON users.id = audit_logs.user_id
            WHERE ' . $filters['where'] . '
            ORDER BY audit_logs.created_at DESC, audit_logs.id DESC
            LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($filters['values']);
    $items = array_map(static function (array $row): array {
        $action = (string)$row['action'];
        $meta = $row['metadata_json'] ? json_decode((string)$row['metadata_json'], true) : null;
        return [
            'id'=>(int)$row['id'],
            'action'=>$action,
            'category'=>mg_admin_ops_activity_category($action),
            'entity_type'=>(string)$row['entity_type'],
            'metadata'=>is_array($meta) ? $meta : null,
            'actor'=>$row['user_id'] !== null ? [
                'id'=>(int)$row['user_id'],
                'email'=>(string)($row['email'] ?? ''),
                'display_name'=>(string)($row['display_name'] ?: $row['full_name'] ?: $row['email'] ?: ('User #' . (int)$row['user_id'])),
            ] : null,
            'ip_address'=>(string)($row['ip_address'] ?? ''),
            'user_agent'=>(string)($row['user_agent'] ?? ''),
            'created_at'=>(string)$row['created_at'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    mg_audit('admin_ops_activity_viewed', 'user', ['count'=>count($items), 'days'=>$filters['days']], $actorId);
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok([
        'items'=>$items,
        'summary'=>$summary,
        'filters'=>[
            'days'=>$filters['days'],
            'category'=>$filters['category'],
            'action'=>$filters['action'],
            'q'=>$filters['q'],
            'categories'=>['readiness','incident','postmortems','analytics','forecast','automation','notifications'],
            'actions'=>mg_admin_ops_activity_actions(),
        ],
        'score'=>['section'=>'Admin ops activity log','score'=>10,'max'=>10,'status'=>'cleared'],
    ], 'Admin ops activity loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'admin.ops_activity.failed', 'Admin ops activity request failed.', ['exception_class'=>$error::class], $actorId);
    mg_fail('Unable to load admin ops activity.', 500);
}
