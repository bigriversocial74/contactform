<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-automation-controls.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'POST' ? mg_require_permission('merchant.campaigns.manage') : mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    $current = mg_automation_current_settings($pdo, $merchantId);
    mg_ok([
        'levels' => array_values(mg_automation_levels()),
        'agent_autonomy_levels' => array_values(mg_agent_autonomy_levels()),
        'agent_autonomy' => $current['agent_autonomy'],
        'playbooks' => array_values(mg_crm_playbook_defs()),
        'settings' => array_values($current['settings']),
        'settings_by_key' => $current['settings'],
        'summary' => mg_automation_settings_summary($current['settings']),
        'updated_at' => $current['updated_at'],
        'source' => $current['source'],
        'guardrails' => ['agent_can_monitor','agent_can_recommend','agent_can_create_task','agent_requires_approval'],
    ]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$raw = $input['settings'] ?? [];
if (!is_array($raw)) mg_fail('Automation settings payload is required.', 422);
$settings = mg_automation_normalize_settings($raw);
$agentAutonomy = mg_agent_autonomy_normalize($input['agent_autonomy'] ?? []);
try {
    $eventId = mg_automation_save_settings($pdo, $merchantId, (int)$user['id'], $settings, $agentAutonomy);
    mg_ok([
        'event_id' => $eventId,
        'settings' => array_values($settings),
        'settings_by_key' => $settings,
        'agent_autonomy' => $agentAutonomy,
        'agent_autonomy_levels' => array_values(mg_agent_autonomy_levels()),
        'summary' => mg_automation_settings_summary($settings),
    ], 'Automation settings saved.');
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.automation_settings.failed', 'Unable to save automation settings.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to save automation settings.', 500);
}
