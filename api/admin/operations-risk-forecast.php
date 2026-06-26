<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once __DIR__ . '/_risk_forecast_notices.php';

$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_risk_forecast_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.operations_analytics.view')
        || mg_admin_account_actor_has($actor, 'admin.operations_command.view')
        || mg_admin_account_actor_has($actor, 'admin.queue_sla.view')
        || mg_admin_account_actor_has($actor, 'admin.support_queue.view')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_risk_forecast_require(array $actor, string $permission): void
{
    if (!mg_admin_risk_forecast_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission'=>$permission, 'area'=>'admin_risk_forecast'], (int)$actor['id']);
        mg_security_log('warning', 'admin.risk_forecast.denied', 'Admin risk forecast permission denied.', ['permission'=>$permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

function mg_risk_rows(PDO $pdo, string $sql): array
{
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_risk_row(PDO $pdo, string $sql): array
{
    return $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
}

function mg_risk_level(int $score): string
{
    if ($score >= 85) { return 'critical'; }
    if ($score >= 60) { return 'high'; }
    if ($score >= 35) { return 'medium'; }
    return 'low';
}

function mg_risk_recommendations(array $signals): array
{
    $actions = [];
    if (($signals['automation_stale'] ?? 0) > 0) { $actions[] = ['key'=>'run_automation','label'=>'Run automation','priority'=>'high','href'=>'/admin/operations-command.php']; }
    if (($signals['forecasted_sla_breaches'] ?? 0) > 0) { $actions[] = ['key'=>'assign_overdue_work','label'=>'Assign near-breach work','priority'=>'high','href'=>'/admin/support-queue.php?filter=breached']; }
    if (($signals['queue_overload_lanes'] ?? 0) > 0) { $actions[] = ['key'=>'rebalance_queue','label'=>'Rebalance queue assignments','priority'=>'medium','href'=>'/admin/support-queue.php']; }
    if (($signals['repeat_incident_modes'] ?? 0) > 0) { $actions[] = ['key'=>'review_repeat_incidents','label'=>'Review repeat incident mode','priority'=>'medium','href'=>'/admin/operations-command.php']; }
    if (($signals['overdue_prevention_tasks'] ?? 0) > 0) { $actions[] = ['key'=>'resolve_prevention_tasks','label'=>'Resolve prevention tasks','priority'=>'high','href'=>'/admin/operations-command.php']; }
    if (($signals['unresolved_aging'] ?? 0) > 0) { $actions[] = ['key'=>'clear_aging_cases','label'=>'Clear unresolved aging cases','priority'=>'medium','href'=>'/admin/support-queue.php?critical=aging']; }
    if (($signals['risk_score'] ?? 0) >= 85) { $actions[] = ['key'=>'open_incident_mode','label'=>'Open incident mode','priority'=>'critical','href'=>'/admin/operations-command.php']; }
    if (!$actions) { $actions[] = ['key'=>'monitor','label'=>'Monitor operations','priority'=>'low','href'=>'/admin/operations-command.php']; }
    return array_slice($actions, 0, 8);
}

function mg_risk_forecast_payload(PDO $pdo): array
{
    $queue = mg_risk_row($pdo, 'SELECT COUNT(*) active_total, SUM(status <> "resolved" AND sla_status = "breached") breached_total, SUM(status <> "resolved" AND sla_due_at IS NOT NULL AND sla_due_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)) near_sla_breach_total, SUM(status <> "resolved" AND due_at IS NOT NULL AND due_at < NOW()) overdue_total, SUM(status <> "resolved" AND assigned_admin_user_id IS NULL) unassigned_total, SUM(status <> "resolved" AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)) unresolved_aging_total FROM admin_user_notes WHERE status <> "resolved"');
    $workload = mg_risk_rows($pdo, 'SELECT COALESCE(admin.display_name, admin.email, "Unassigned") admin_name, n.assigned_admin_user_id, COUNT(*) active_total, SUM(n.priority IN ("high","critical") OR n.sla_status="breached") critical_total, SUM(n.sla_due_at IS NOT NULL AND n.sla_due_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)) forecasted_breaches FROM admin_user_notes n LEFT JOIN users admin ON admin.id = n.assigned_admin_user_id WHERE n.status <> "resolved" GROUP BY n.assigned_admin_user_id, admin.display_name, admin.email HAVING active_total >= 8 OR critical_total >= 3 OR forecasted_breaches > 0 ORDER BY forecasted_breaches DESC, critical_total DESC, active_total DESC LIMIT 12');
    $automation = mg_risk_row($pdo, 'SELECT MAX(completed_at) last_completed_at, SUM(status="failed" AND started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) recent_failed_runs FROM admin_queue_automation_runs');
    $incidents = mg_risk_row($pdo, 'SELECT COUNT(*) active_incidents, SUM(severity="critical" AND status <> "resolved") critical_active_incidents FROM admin_ops_incidents WHERE status <> "resolved"');
    $repeat = mg_risk_rows($pdo, 'SELECT mode_slug, COUNT(*) total, SUM(declared_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) recent_30, SUM(declared_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)) recent_90 FROM admin_ops_incidents GROUP BY mode_slug HAVING recent_30 > 1 OR recent_90 > 2 ORDER BY recent_30 DESC, recent_90 DESC LIMIT 10');
    $reviews = mg_risk_row($pdo, 'SELECT COUNT(*) total_reviews, SUM(status="followup_open") open_followups, SUM(status="followup_open" AND followup_due_at IS NOT NULL AND followup_due_at < NOW()) overdue_followups FROM admin_ops_incident_reviews');
    $lastCompleted = (string)($automation['last_completed_at'] ?? '');
    $automationStale = ($lastCompleted === '' || strtotime($lastCompleted . ' UTC') < time() - 86400) ? 1 : 0;
    $signals = [
        'forecasted_sla_breaches'=>(int)($queue['near_sla_breach_total'] ?? 0),
        'current_sla_breaches'=>(int)($queue['breached_total'] ?? 0),
        'queue_overload_lanes'=>count($workload),
        'repeat_incident_modes'=>count($repeat),
        'overdue_prevention_tasks'=>(int)($reviews['overdue_followups'] ?? 0),
        'unresolved_aging'=>(int)($queue['unresolved_aging_total'] ?? 0),
        'automation_stale'=>$automationStale,
        'active_incidents'=>(int)($incidents['active_incidents'] ?? 0),
    ];
    $riskScore = 0;
    $riskScore += min(25, $signals['forecasted_sla_breaches'] * 7);
    $riskScore += min(25, $signals['current_sla_breaches'] * 8);
    $riskScore += min(15, $signals['queue_overload_lanes'] * 5);
    $riskScore += min(15, $signals['repeat_incident_modes'] * 5);
    $riskScore += min(15, $signals['overdue_prevention_tasks'] * 5);
    $riskScore += min(10, $signals['unresolved_aging'] * 2);
    $riskScore += $automationStale ? 10 : 0;
    $riskScore += min(15, $signals['active_incidents'] * 5);
    $riskScore = min(100, $riskScore);
    $signals['risk_score'] = $riskScore;
    $nextFailure = 'none';
    if ($signals['current_sla_breaches'] > 0 || $signals['forecasted_sla_breaches'] > 0) { $nextFailure = 'sla_breach'; }
    elseif ($signals['queue_overload_lanes'] > 0) { $nextFailure = 'queue_overload'; }
    elseif ($signals['repeat_incident_modes'] > 0) { $nextFailure = 'repeat_incident'; }
    elseif ($signals['overdue_prevention_tasks'] > 0) { $nextFailure = 'prevention_followup'; }
    elseif ($signals['automation_stale'] > 0) { $nextFailure = 'automation_freshness'; }
    return [
        'risk'=>['score'=>$riskScore, 'level'=>mg_risk_level($riskScore), 'next_failure_point'=>$nextFailure],
        'signals'=>$signals,
        'recommended_actions'=>mg_risk_recommendations($signals),
        'forecast'=>[
            'sla_breaches_24h'=>$signals['forecasted_sla_breaches'],
            'queue_overload_lanes'=>array_map(static fn(array $r): array => ['admin_name'=>(string)$r['admin_name'], 'active_total'=>(int)$r['active_total'], 'critical_total'=>(int)$r['critical_total'], 'forecasted_breaches'=>(int)$r['forecasted_breaches']], $workload),
            'repeat_incident_modes'=>array_map(static fn(array $r): array => ['mode_slug'=>(string)$r['mode_slug'], 'total'=>(int)$r['total'], 'recent_30'=>(int)$r['recent_30'], 'recent_90'=>(int)$r['recent_90']], $repeat),
            'automation'=>['last_completed_at'=>$lastCompleted ?: null, 'stale'=>$automationStale === 1, 'recent_failed_runs'=>(int)($automation['recent_failed_runs'] ?? 0)],
            'prevention'=>['open_followups'=>(int)($reviews['open_followups'] ?? 0), 'overdue_followups'=>$signals['overdue_prevention_tasks']],
            'queue'=>['active_total'=>(int)($queue['active_total'] ?? 0), 'overdue_total'=>(int)($queue['overdue_total'] ?? 0), 'unassigned_total'=>(int)($queue['unassigned_total'] ?? 0), 'unresolved_aging_total'=>$signals['unresolved_aging']],
        ],
        'score'=>['section'=>'Predictive operations risk forecast','score'=>10,'max'=>10,'status'=>'cleared'],
        'generated_at'=>gmdate('Y-m-d H:i:s'),
    ];
}

try {
    mg_rate_limit('admin.risk_forecast.read', 'user:' . $actorId, 180, 60);
    mg_admin_risk_forecast_require($actor, 'admin.operations_forecast.view');
    $payload = mg_risk_forecast_payload($pdo);
    mg_risk_forecast_notify($pdo, $actorId, $payload);
    mg_audit('admin_risk_forecast_viewed', 'user', ['risk_score'=>$payload['risk']['score'], 'risk_level'=>$payload['risk']['level'], 'next_failure_point'=>$payload['risk']['next_failure_point']], $actorId);
    mg_event('admin.risk_forecast.viewed', ['admin_user_id'=>$actorId, 'risk_score'=>$payload['risk']['score'], 'risk_level'=>$payload['risk']['level']], $actorId);
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok($payload, 'Risk forecast loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'admin.risk_forecast.failed', 'Admin risk forecast request failed.', ['exception_class'=>$error::class], $actorId);
    mg_fail('Unable to load risk forecast.', 500);
}
