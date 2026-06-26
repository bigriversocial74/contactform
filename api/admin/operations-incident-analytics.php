<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';

$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_incident_analytics_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.operations_reviews.view')
        || mg_admin_account_actor_has($actor, 'admin.operations_incidents.view')
        || mg_admin_account_actor_has($actor, 'admin.operations_command.view')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_incident_analytics_require(array $actor, string $permission): void
{
    if (!mg_admin_incident_analytics_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission'=>$permission, 'area'=>'admin_incident_analytics'], (int)$actor['id']);
        mg_security_log('warning', 'admin.incident_analytics.denied', 'Admin incident analytics permission denied.', ['permission'=>$permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

function mg_incident_analytics_rows(PDO $pdo, string $sql): array
{
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_incident_analytics_summary(PDO $pdo): array
{
    $summary = $pdo->query('SELECT COUNT(*) total_incidents, SUM(status <> "resolved") active_incidents, SUM(status = "resolved") resolved_incidents, AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, declared_at, resolved_at) END) avg_resolution_minutes FROM admin_ops_incidents')->fetch(PDO::FETCH_ASSOC) ?: [];
    $reviews = $pdo->query('SELECT COUNT(*) total_reviews, SUM(status IN ("completed","followup_complete")) completed_reviews, SUM(status = "followup_open") open_followups, SUM(status = "followup_open" AND followup_due_at IS NOT NULL AND followup_due_at < NOW()) overdue_followups FROM admin_ops_incident_reviews')->fetch(PDO::FETCH_ASSOC) ?: [];
    $repeatRows = mg_incident_analytics_rows($pdo, 'SELECT mode_slug, COUNT(*) total, SUM(declared_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) recent_30, SUM(declared_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)) recent_60, SUM(declared_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)) recent_90 FROM admin_ops_incidents GROUP BY mode_slug HAVING total > 1 OR recent_30 > 1 OR recent_60 > 1 OR recent_90 > 1 ORDER BY recent_30 DESC, recent_60 DESC, total DESC');
    $topMode = mg_incident_analytics_rows($pdo, 'SELECT mode_slug, COUNT(*) total FROM admin_ops_incidents GROUP BY mode_slug ORDER BY total DESC LIMIT 1');
    $totalReviews = (int)($reviews['total_reviews'] ?? 0);
    $completedReviews = (int)($reviews['completed_reviews'] ?? 0);
    $completionRate = $totalReviews > 0 ? round(($completedReviews / $totalReviews) * 100, 1) : 100.0;
    $repeatPenalty = min(30, count($repeatRows) * 8);
    $overduePenalty = min(25, ((int)($reviews['overdue_followups'] ?? 0)) * 8);
    $openPenalty = min(15, ((int)($reviews['open_followups'] ?? 0)) * 3);
    $completionPenalty = max(0, (int)round((100 - $completionRate) / 4));
    $lastIncident = $pdo->query('SELECT MAX(declared_at) last_incident_at FROM admin_ops_incidents')->fetch(PDO::FETCH_ASSOC) ?: [];
    $recentPenalty = 0;
    if (!empty($lastIncident['last_incident_at']) && strtotime((string)$lastIncident['last_incident_at'] . ' UTC') > time() - 604800) {
        $recentPenalty = 10;
    }
    $preventionScore = max(0, 100 - $repeatPenalty - $overduePenalty - $openPenalty - $completionPenalty - $recentPenalty);
    return [
        'total_incidents'=>(int)($summary['total_incidents'] ?? 0),
        'active_incidents'=>(int)($summary['active_incidents'] ?? 0),
        'resolved_incidents'=>(int)($summary['resolved_incidents'] ?? 0),
        'avg_resolution_hours'=>round(((float)($summary['avg_resolution_minutes'] ?? 0)) / 60, 1),
        'postmortem_completion_rate'=>$completionRate,
        'open_followups'=>(int)($reviews['open_followups'] ?? 0),
        'overdue_followups'=>(int)($reviews['overdue_followups'] ?? 0),
        'repeat_modes'=>count($repeatRows),
        'top_mode'=>$topMode[0] ?? null,
        'prevention_score'=>['value'=>$preventionScore, 'label'=>$preventionScore >= 90 ? 'healthy' : ($preventionScore >= 70 ? 'watch' : 'at_risk')],
        'last_incident_at'=>$lastIncident['last_incident_at'] ?? null,
    ];
}

function mg_incident_analytics_trend(PDO $pdo): array
{
    $rows = mg_incident_analytics_rows($pdo, 'SELECT DATE_FORMAT(declared_at, "%Y-%m") month_key, COUNT(*) total, SUM(severity = "critical") critical_total, SUM(status <> "resolved") unresolved_total FROM admin_ops_incidents WHERE declared_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(declared_at, "%Y-%m") ORDER BY month_key ASC');
    return array_map(static fn(array $r): array => ['month'=>(string)$r['month_key'],'total'=>(int)$r['total'],'critical_total'=>(int)$r['critical_total'],'unresolved_total'=>(int)$r['unresolved_total']], $rows);
}

function mg_incident_analytics_payload(PDO $pdo): array
{
    $byMode = mg_incident_analytics_rows($pdo, 'SELECT mode_slug, COUNT(*) total, SUM(status <> "resolved") active_total, AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, declared_at, resolved_at) END) avg_resolution_minutes FROM admin_ops_incidents GROUP BY mode_slug ORDER BY total DESC');
    $bySeverity = mg_incident_analytics_rows($pdo, 'SELECT severity, COUNT(*) total, SUM(status <> "resolved") active_total FROM admin_ops_incidents GROUP BY severity ORDER BY CASE severity WHEN "critical" THEN 1 WHEN "high" THEN 2 WHEN "medium" THEN 3 ELSE 4 END');
    $repeat = mg_incident_analytics_rows($pdo, 'SELECT mode_slug, COUNT(*) total, SUM(declared_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) recent_30, SUM(declared_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)) recent_60, SUM(declared_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)) recent_90, MAX(declared_at) last_seen_at FROM admin_ops_incidents GROUP BY mode_slug HAVING total > 1 OR recent_30 > 1 OR recent_60 > 1 OR recent_90 > 1 ORDER BY recent_30 DESC, recent_60 DESC, total DESC LIMIT 20');
    $worsening = mg_incident_analytics_rows($pdo, 'SELECT mode_slug, SUM(declared_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) recent_30, SUM(declared_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND declared_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)) previous_30 FROM admin_ops_incidents WHERE declared_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) GROUP BY mode_slug HAVING recent_30 > previous_30 AND recent_30 > 0 ORDER BY recent_30 DESC LIMIT 20');
    $openWork = mg_incident_analytics_rows($pdo, 'SELECT r.public_id review_id, i.public_id incident_id, i.title, i.mode_slug, r.status, r.followup_due_at, owner.email owner_email, owner.display_name owner_display_name FROM admin_ops_incident_reviews r INNER JOIN admin_ops_incidents i ON i.id = r.incident_id LEFT JOIN users owner ON owner.id = r.followup_owner_user_id WHERE r.status = "followup_open" ORDER BY r.followup_due_at IS NULL ASC, r.followup_due_at ASC LIMIT 25');
    return [
        'summary'=>mg_incident_analytics_summary($pdo),
        'by_mode'=>array_map(static fn(array $r): array => ['mode_slug'=>(string)$r['mode_slug'],'total'=>(int)$r['total'],'active_total'=>(int)$r['active_total'],'avg_resolution_hours'=>round(((float)($r['avg_resolution_minutes'] ?? 0)) / 60, 1)], $byMode),
        'by_severity'=>array_map(static fn(array $r): array => ['severity'=>(string)$r['severity'],'total'=>(int)$r['total'],'active_total'=>(int)$r['active_total']], $bySeverity),
        'repeat_patterns'=>array_map(static fn(array $r): array => ['mode_slug'=>(string)$r['mode_slug'],'total'=>(int)$r['total'],'recent_30'=>(int)$r['recent_30'],'recent_60'=>(int)$r['recent_60'],'recent_90'=>(int)$r['recent_90'],'last_seen_at'=>(string)$r['last_seen_at'],'risk'=>((int)$r['recent_30'] > 1 ? 'high' : (((int)$r['recent_60'] > 1 || (int)$r['recent_90'] > 1) ? 'medium' : 'low'))], $repeat),
        'worsening_modes'=>array_map(static fn(array $r): array => ['mode_slug'=>(string)$r['mode_slug'],'recent_30'=>(int)$r['recent_30'],'previous_30'=>(int)$r['previous_30']], $worsening),
        'open_prevention_work'=>array_map(static fn(array $r): array => ['review_id'=>(string)$r['review_id'],'incident_id'=>(string)$r['incident_id'],'title'=>(string)$r['title'],'mode_slug'=>(string)$r['mode_slug'],'status'=>(string)$r['status'],'followup_due_at'=>$r['followup_due_at'] !== null ? (string)$r['followup_due_at'] : null,'owner'=>$r['owner_email'] !== null ? ['email'=>(string)$r['owner_email'],'display_name'=>(string)($r['owner_display_name'] ?: $r['owner_email'])] : null], $openWork),
        'trend'=>mg_incident_analytics_trend($pdo),
        'score'=>['section'=>'Incident analytics','score'=>10,'max'=>10,'status'=>'cleared'],
        'generated_at'=>gmdate('Y-m-d H:i:s'),
    ];
}

try {
    mg_rate_limit('admin.incident_analytics.read', 'user:' . $actorId, 180, 60);
    mg_admin_incident_analytics_require($actor, 'admin.operations_reviews.view');
    $payload = mg_incident_analytics_payload($pdo);
    mg_audit('admin_incident_analytics_viewed', 'user', ['repeat_modes'=>$payload['summary']['repeat_modes'], 'prevention_score'=>$payload['summary']['prevention_score']['value']], $actorId);
    mg_event('admin.incident_analytics.viewed', ['admin_user_id'=>$actorId, 'repeat_modes'=>$payload['summary']['repeat_modes']], $actorId);
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok($payload, 'Incident analytics loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'admin.incident_analytics.failed', 'Admin incident analytics request failed.', ['exception_class'=>$error::class], $actorId);
    mg_fail('Unable to load incident analytics.', 500);
}
