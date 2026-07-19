<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-agent-phase6.php';

$options = getopt('', ['trigger::', 'environment::']);
$trigger = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($options['trigger'] ?? 'scheduled'))) ?: 'scheduled';
$environment = mg_admin_agent_phase6_environment((string) ($options['environment'] ?? getenv('MG_DEPLOY_ENV') ?: 'production'));
$started = microtime(true);

try {
    $engineTrigger = $trigger === 'scheduled' ? 'manual' : $trigger;
    $pdo = mg_db();
    $result = mg_admin_agent_phase6_run($pdo, ['trigger_source' => $engineTrigger, 'environment_key' => $environment]);
    if ($trigger === 'scheduled') {
        $briefs = mg_admin_agent_phase6_maybe_generate_scheduled_briefs($pdo, $environment);
        $duration = max(0, (int) round((microtime(true) - $started) * 1000));
        $pdo->prepare('INSERT INTO admin_agent_scheduler_heartbeats (public_id,runner_key,environment_key,trigger_source,status,started_at,completed_at,duration_ms,summary_json,created_at,updated_at) VALUES (?,"main_admin_agent_phase6",?,"scheduled","succeeded",FROM_UNIXTIME(?),NOW(),?,?,NOW(),NOW())')->execute([
            mg_public_id(),
            $environment,
            (int) floor($started),
            $duration,
            json_encode(['briefs_generated' => count($briefs), 'readiness_score_before_scheduler_confirmation' => $result['readiness']['score'] ?? null], JSON_UNESCAPED_SLASHES),
        ]);
        $result['scheduled_briefs'] = $briefs;
        $result['readiness'] = mg_admin_agent_phase6_evaluate_readiness($pdo, $environment);
        $result['scheduler_health'] = mg_admin_agent_phase6_heartbeat_state($pdo, $environment);
    }
    fwrite(STDOUT, json_encode(['ok' => true, 'data' => $result, 'generated_at' => gmdate('c')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(0);
} catch (Throwable $error) {
    if ($trigger === 'scheduled' && isset($pdo) && $pdo instanceof PDO && mg_admin_agent_phase6_ready($pdo)) {
        $duration = max(0, (int) round((microtime(true) - $started) * 1000));
        $pdo->prepare('INSERT INTO admin_agent_scheduler_heartbeats (public_id,runner_key,environment_key,trigger_source,status,started_at,completed_at,duration_ms,error_class,created_at,updated_at) VALUES (?,"main_admin_agent_phase6",?,"scheduled","failed",FROM_UNIXTIME(?),NOW(),?,?,NOW(),NOW())')->execute([mg_public_id(), $environment, (int) floor($started), $duration, $error::class]);
    }
    if (function_exists('mg_security_log')) mg_security_log('error', 'admin_agent.phase6_runner_failed', 'Main Admin Agent Phase 6 scheduled runner failed.', ['exception_class' => $error::class], null);
    fwrite(STDERR, json_encode(['ok' => false, 'message' => 'Main Admin Agent Phase 6 run failed.', 'exception_class' => $error::class, 'generated_at' => gmdate('c')], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}
