<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';
require_once __DIR__ . '/_system_sql_diagnostics.php';

mg_require_method('GET');
$user = mg_admin_system_health_require_user();

function mg_admin_sql_diag_false_positive_columns(): array
{
    return array_fill_keys([
        'ad_campaigns.merchant_user_id',
        'ad_creatives.campaign_id',
        'ad_creatives.body',
        'ad_creatives.status',
        'ad_campaign_placements.campaign_id',
        'ad_campaign_placements.placement_id',
        'ad_targeting_rules.campaign_id',
        'ad_events.campaign_id',
        'ad_reviews.campaign_id',
        'ad_placements.status',
        'feed_posts.user_id',
        'feed_posts.media_json',
        'feed_post_assets.post_id',
        'catalog_products.name',
        'notification_preferences.channel',
        'notification_preferences.enabled',
        'commerce_orders.status',
        'microgift_claims.microgift_instance_id',
        'microgift_redemptions.microgift_instance_id',
    ], true);
}

function mg_admin_sql_diag_recount(array &$data): void
{
    $findings = is_array($data['findings'] ?? null) ? $data['findings'] : [];
    $recent = is_array($data['recent_sql_errors'] ?? null) ? $data['recent_sql_errors'] : [];
    $modules = is_array($data['modules'] ?? null) ? $data['modules'] : [];
    $critical = count(array_filter($findings, static fn(array $f): bool => ($f['severity'] ?? '') === 'critical'));
    $warning = count(array_filter($findings, static fn(array $f): bool => ($f['severity'] ?? '') === 'warning'));
    $healthyModules = count(array_filter($modules, static fn(array $m): bool => ($m['status'] ?? '') === 'healthy'));
    $warningModules = count(array_filter($modules, static fn(array $m): bool => ($m['status'] ?? '') === 'warning'));
    $criticalModules = count(array_filter($modules, static fn(array $m): bool => ($m['status'] ?? '') === 'critical'));
    $data['status'] = $critical > 0 ? 'critical' : (($warning > 0 || $recent) ? 'warning' : 'healthy');
    $data['summary'] = $data['status'] === 'healthy'
        ? 'No SQL dependency issues were detected in the diagnostics catalog.'
        : ($critical > 0 ? $critical . ' critical SQL dependency issue(s) need attention.' : 'SQL diagnostics found warnings or recent SQL-related failures.');
    $data['counts'] = [
        'modules' => count($modules),
        'healthy_modules' => $healthyModules,
        'warning_modules' => $warningModules,
        'critical_modules' => $criticalModules,
        'findings' => count($findings),
        'critical_findings' => $critical,
        'warning_findings' => $warning,
        'recent_sql_errors' => count($recent),
        'repairable_findings' => count(array_filter($findings, static fn(array $f): bool => !empty($f['repairable']))),
    ];
}

function mg_admin_sql_diag_plan(array $data): array
{
    $findings = is_array($data['findings'] ?? null) ? $data['findings'] : [];
    $recent = is_array($data['recent_sql_errors'] ?? null) ? $data['recent_sql_errors'] : [];
    $existing = is_array($data['repair_plan'] ?? null) ? $data['repair_plan'] : [];
    $sql = "-- Microgifter System SQL Diagnostics Plan\n";
    $sql .= "-- Generated: " . gmdate('c') . "\n";
    $sql .= "-- Review before running. Some findings require their original migration file instead of an automatic ALTER.\n";
    $sql .= "-- Findings: " . count($findings) . "; recent SQL-related warnings: " . count($recent) . "\n\n";
    foreach (array_slice($findings, 0, 200) as $finding) {
        $sql .= '-- [' . strtoupper((string)($finding['severity'] ?? 'warning')) . '] ' . (string)($finding['item'] ?? $finding['type'] ?? 'finding') . ' — ' . str_replace(["\r", "\n"], ' ', (string)($finding['message'] ?? 'Review required.')) . "\n";
        if (!empty($finding['migration_hint'])) {
            $sql .= '--     Migration hint: ' . str_replace(["\r", "\n"], ' ', (string)$finding['migration_hint']) . "\n";
        }
    }
    if ($recent) {
        $sql .= "\n-- Recent SQL-related warnings/errors\n";
        foreach (array_slice($recent, 0, 20) as $error) {
            $sql .= '-- [' . strtoupper((string)($error['severity'] ?? 'warning')) . '] ' . (string)($error['title'] ?? 'SQL warning') . ' — ' . str_replace(["\r", "\n"], ' ', (string)($error['message'] ?? '')) . "\n";
        }
    }
    if (!empty($existing['available']) && !empty($existing['sql'])) {
        $sql .= "\n-- Auto-generated repair statements from diagnostics engine\n" . (string)$existing['sql'] . "\n";
    } else {
        $sql .= "\n-- No automatic ALTER statements were generated for the current remaining findings.\n";
        $sql .= "SELECT 'System SQL diagnostics plan generated' AS status;\n";
    }
    return [
        'available' => count($findings) > 0 || count($recent) > 0 || !empty($existing['available']),
        'repairable_count' => (int)($existing['repairable_count'] ?? 0),
        'filename' => 'microgifter_system_sql_diagnostics_' . gmdate('Ymd_His') . '.sql',
        'sql' => $sql,
        'sql_bytes' => strlen($sql),
    ];
}

function mg_admin_sql_diag_normalize(array $data): array
{
    $ignored = mg_admin_sql_diag_false_positive_columns();
    $data['findings'] = array_values(array_filter(is_array($data['findings'] ?? null) ? $data['findings'] : [], static function (array $finding) use ($ignored): bool {
        if (($finding['type'] ?? '') !== 'missing_column') return true;
        return empty($ignored[(string)($finding['item'] ?? '')]);
    }));
    foreach ($data['modules'] as &$module) {
        $module['missing_columns'] = array_values(array_filter(is_array($module['missing_columns'] ?? null) ? $module['missing_columns'] : [], static fn(string $item): bool => empty($ignored[$item])));
        $hasCritical = (is_array($module['missing_tables'] ?? null) && $module['missing_tables']) || $module['missing_columns'] || (is_array($module['probe_errors'] ?? null) && $module['probe_errors']);
        $hasWarning = (is_array($module['missing_enums'] ?? null) && $module['missing_enums']) || (is_array($module['missing_indexes'] ?? null) && $module['missing_indexes']);
        $module['status'] = $hasCritical ? 'critical' : ($hasWarning ? 'warning' : 'healthy');
        $module['ready'] = $module['status'] === 'healthy';
        $module['summary'] = $module['status'] === 'healthy' ? 'All required SQL dependencies are present.' : ($module['status'] === 'warning' ? 'Required tables and columns are present, but enum/index drift needs review.' : 'One or more required SQL dependencies are missing.');
    }
    unset($module);
    foreach ($data['endpoint_checks'] as &$endpoint) {
        $moduleKey = (string)($endpoint['module'] ?? '');
        foreach ($data['modules'] as $module) {
            if (($module['key'] ?? '') === $moduleKey) {
                $endpoint['status'] = ($module['status'] ?? '') === 'critical' ? 'blocked' : 'ready';
                $endpoint['missing_count'] = count($module['missing_tables'] ?? []) + count($module['missing_columns'] ?? []);
                break;
            }
        }
    }
    unset($endpoint);
    mg_admin_sql_diag_recount($data);
    $data['repair_plan'] = mg_admin_sql_diag_plan($data);
    $data['catalog_version'] = '2026-07-01.2-normalized';
    return $data;
}

try {
    mg_rate_limit('admin.system_sql_diagnostics.read', 'user:' . (int)$user['id'], 60, 60);
    $pdo = mg_db();
    $data = mg_admin_sql_diag_normalize(mg_system_sql_diagnostics($pdo));
    mg_security_log('info', 'admin.system_sql_diagnostics.viewed', 'System SQL diagnostics viewed.', [
        'status' => $data['status'],
        'critical_findings' => $data['counts']['critical_findings'] ?? 0,
        'warning_findings' => $data['counts']['warning_findings'] ?? 0,
        'catalog_version' => $data['catalog_version'] ?? null,
    ], (int)$user['id']);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.system_sql_diagnostics.failed', 'System SQL diagnostics request failed.', [
        'exception_class' => $error::class,
        'message' => mb_substr($error->getMessage(), 0, 240),
    ], (int)$user['id']);
    mg_fail('Unable to run system SQL diagnostics.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
mg_ok($data, 'System SQL diagnostics loaded.');
