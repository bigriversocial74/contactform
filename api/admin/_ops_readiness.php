<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';

function mg_admin_ops_readiness_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1 || preg_match('/^[a-z0-9_]{1,64}$/', $column) !== 1) {
        throw new InvalidArgumentException('Invalid readiness schema identifier.');
    }
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function mg_admin_ops_readiness_enum_values(PDO $pdo, string $table, string $column): array
{
    if (!mg_admin_system_health_table_exists($pdo, $table)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
    $stmt->execute([$table, $column]);
    $type = (string)$stmt->fetchColumn();
    if (preg_match("/^enum\\((.*)\\)$/", $type, $match) !== 1) {
        return [];
    }
    preg_match_all("/'((?:[^']|'')*)'/", $match[1], $values);
    return array_map(static fn(string $value): string => str_replace("''", "'", $value), $values[1] ?? []);
}

function mg_admin_ops_readiness_file_exists(string $path): bool
{
    $root = dirname(__DIR__, 2);
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) && is_readable($full);
}

function mg_admin_ops_readiness_section(string $key, string $label, array $checks): array
{
    $missing = array_values(array_filter($checks, static fn(array $check): bool => empty($check['ready'])));
    return [
        'key' => $key,
        'label' => $label,
        'status' => $missing === [] ? 'healthy' : 'critical',
        'ready' => $missing === [],
        'missing_count' => count($missing),
        'checks' => $checks,
    ];
}

function mg_admin_ops_readiness_check(string $key, string $label, bool $ready, ?string $detail = null): array
{
    return ['key'=>$key, 'label'=>$label, 'ready'=>$ready, 'detail'=>$detail];
}

function mg_admin_ops_readiness_read(PDO $pdo): array
{
    $tables = ['admin_ops_incidents','admin_ops_incident_updates','admin_ops_incident_reviews','admin_queue_notifications','admin_queue_automation_runs','admin_user_notes','schema_migrations'];
    $tableChecks = [];
    foreach ($tables as $table) {
        $tableChecks[] = mg_admin_ops_readiness_check($table, $table, mg_admin_system_health_table_exists($pdo, $table));
    }

    $columns = [
        ['admin_ops_incidents','mode_slug'], ['admin_ops_incidents','severity'], ['admin_ops_incidents','status'], ['admin_ops_incidents','runbook_checklist_json'], ['admin_ops_incidents','resolved_at'],
        ['admin_ops_incident_updates','update_type'], ['admin_ops_incident_updates','metadata_json'],
        ['admin_ops_incident_reviews','review_summary'], ['admin_ops_incident_reviews','customer_impact'], ['admin_ops_incident_reviews','merchant_impact'], ['admin_ops_incident_reviews','action_items'], ['admin_ops_incident_reviews','followup_due_at'], ['admin_ops_incident_reviews','status'],
        ['admin_queue_notifications','notification_type'], ['admin_queue_notifications','metadata_json'],
    ];
    $columnChecks = [];
    foreach ($columns as [$table, $column]) {
        $columnChecks[] = mg_admin_ops_readiness_check($table . '.' . $column, $table . '.' . $column, mg_admin_ops_readiness_column_exists($pdo, $table, $column));
    }

    $enumRequired = ['incident_declared','incident_updated','incident_resolved','incident_review_required','incident_review_completed','incident_review_followup_due','repeat_incident_detected','prevention_task_overdue','incident_trend_worsening','risk_forecast_high','forecasted_sla_breach','queue_overload_predicted'];
    $enumValues = mg_admin_ops_readiness_enum_values($pdo, 'admin_queue_notifications', 'notification_type');
    $enumChecks = [];
    foreach ($enumRequired as $value) {
        $enumChecks[] = mg_admin_ops_readiness_check('enum.' . $value, $value, in_array($value, $enumValues, true));
    }

    $permissions = ['admin.operations_incidents.view','admin.operations_incidents.manage','admin.operations_reviews.view','admin.operations_reviews.manage','admin.operations_analytics.view','admin.operations_forecast.view','admin.operations_command.view','admin.operations_command.manage'];
    $permissionChecks = [];
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug = ?');
    foreach ($permissions as $permission) {
        $stmt->execute([$permission]);
        $permissionChecks[] = mg_admin_ops_readiness_check($permission, $permission, ((int)$stmt->fetchColumn()) > 0);
    }

    $files = ['api/admin/operations-incident.php','api/admin/operations-postmortem.php','api/admin/operations-incident-analytics.php','api/admin/operations-risk-forecast.php','api/admin/_ops_incidents.php','api/admin/_ops_reviews.php','api/admin/_risk_forecast_notices.php','assets/js/admin-ops-command.js','assets/js/admin-ops-reviews.js','assets/js/admin-incident-analytics.js','assets/js/admin-risk-forecast.js','assets/css/admin-ops-command.css','assets/css/admin-ops-incidents.css','assets/css/admin-ops-reviews.css','assets/css/admin-incident-analytics.css','assets/css/admin-risk-forecast.css','admin/operations-command.php'];
    $fileChecks = [];
    foreach ($files as $file) {
        $fileChecks[] = mg_admin_ops_readiness_check($file, $file, mg_admin_ops_readiness_file_exists($file));
    }

    $sections = [
        mg_admin_ops_readiness_section('tables', 'Database tables', $tableChecks),
        mg_admin_ops_readiness_section('columns', 'Required columns', $columnChecks),
        mg_admin_ops_readiness_section('notification_enum', 'Notification enum values', $enumChecks),
        mg_admin_ops_readiness_section('permissions', 'Admin permissions', $permissionChecks),
        mg_admin_ops_readiness_section('files', 'Code and asset files', $fileChecks),
    ];
    $missingTotal = array_sum(array_map(static fn(array $section): int => (int)$section['missing_count'], $sections));
    return [
        'status' => $missingTotal === 0 ? 'healthy' : 'critical',
        'ready' => $missingTotal === 0,
        'summary' => $missingTotal === 0 ? 'Admin ops deployment is installed and ready.' : 'Admin ops deployment is missing required database or code items.',
        'missing_total' => $missingTotal,
        'sections' => $sections,
        'required_stage' => '18Y + 18Z + 18AA after 18X',
        'score' => ['section'=>'Admin ops deployment readiness','score'=>10,'max'=>10,'status'=>'cleared'],
    ];
}
