<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-agent-phase6.php';

function mg_admin_agent_phase6_readiness_state(PDO $pdo, string $environment = 'production'): array
{
    $checks = mg_admin_agent_phase6_readiness_checks($pdo, $environment);
    $required = array_values(array_filter($checks, static fn(array $row): bool => $row['required_for_production']));
    $passed = count(array_filter($required, static fn(array $row): bool => $row['status'] === 'passed'));
    $failed = count(array_filter($required, static fn(array $row): bool => in_array($row['status'], ['failed', 'not_configured'], true)));
    $score = $required === [] ? 0 : (int) round(($passed / count($required)) * 100);
    $status = $failed === 0 && $score === 100 ? 'production_ready' : ($score >= 70 ? 'attention_required' : 'not_ready');
    return ['status' => $status, 'score' => $score, 'passed' => $passed, 'required' => count($required), 'failed' => $failed, 'checks' => $checks];
}

function mg_admin_agent_phase6_latest_retention_preview(PDO $pdo, string $environment = 'production'): ?array
{
    $row = mg_admin_agent_safe_row($pdo, 'SELECT scorecards_eligible,events_eligible,resolved_alerts_eligible,policy_json,generated_at FROM admin_agent_retention_previews WHERE environment_key=? ORDER BY generated_at DESC,id DESC LIMIT 1', [mg_admin_agent_phase6_environment($environment)]);
    if ($row === []) return null;
    return [
        'scorecards_eligible' => (int) $row['scorecards_eligible'],
        'events_eligible' => (int) $row['events_eligible'],
        'resolved_alerts_eligible' => (int) $row['resolved_alerts_eligible'],
        'policy' => mg_admin_agent_json($row['policy_json'] ?? null),
        'generated_at' => (string) $row['generated_at'],
    ];
}

function mg_admin_agent_phase6_state_readonly(PDO $pdo, int $adminId, array $options = []): array
{
    $state = mg_admin_agent_phase5_state($pdo, $adminId, $options);
    $schema = mg_admin_agent_phase6_schema_state($pdo);
    $state['phase6_schema'] = $schema;
    $state['phase6_ready'] = $schema['ready'];
    if (!$schema['ready']) return $state;

    $environment = mg_admin_agent_phase6_environment((string) ($options['environment_key'] ?? 'production'));
    $state['phase6_settings'] = mg_admin_agent_phase6_settings($pdo, $environment);
    $state['scheduler_health'] = mg_admin_agent_phase6_heartbeat_state($pdo, $environment);
    $state['continuity_alerts'] = mg_admin_agent_phase6_alerts($pdo, $environment);
    $state['drill_schedules'] = mg_admin_agent_phase6_drill_schedules($pdo, $environment);
    $state['attestations'] = mg_admin_agent_phase6_attestations($pdo);
    $state['readiness'] = mg_admin_agent_phase6_readiness_state($pdo, $environment);
    $state['continuity_briefs'] = mg_admin_agent_phase6_briefs($pdo, $environment);
    $state['readiness_exports'] = mg_admin_agent_phase6_exports($pdo, $environment);
    $state['retention_preview'] = mg_admin_agent_phase6_latest_retention_preview($pdo, $environment);
    $state['phase6_setup'] = [
        'manual_analysis_available' => true,
        'evidence_upload_available' => true,
        'cron_optional_for_manual_operation' => true,
        'cron_required_for_automatic_monitoring' => true,
        'cron_command' => '*/5 * * * * cd /path/to/contactform && php scripts/run_admin_agent_phase6.php --trigger=scheduled --environment=production >> storage/logs/main-admin-agent-phase6.log 2>&1',
        'hosting_steps' => [
            'Open the hosting control panel.',
            'Open Cron Jobs or Scheduled Tasks.',
            'Choose Every 5 Minutes.',
            'Paste the displayed command after replacing /path/to/contactform with the website project path.',
            'Save, then return to this page and wait up to 10 minutes for Scheduler Healthy.',
        ],
        'database_only' => true,
        'used_ai' => false,
        'credits_used' => 0,
    ];
    return $state;
}
