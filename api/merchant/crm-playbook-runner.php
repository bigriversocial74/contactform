<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-automation-controls.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

$playbookKey = strtolower(trim((string)($input['playbook_key'] ?? '')));
$defs = mg_crm_playbook_defs();
if ($playbookKey !== '' && !isset($defs[$playbookKey])) mg_fail('Unknown retention playbook.', 422);
$current = mg_automation_current_settings($pdo, $merchantId);
$settings = $current['settings'];
$scanInput = $input;
if ($playbookKey !== '') $scanInput['playbook_key'] = $playbookKey;
$recommendations = mg_crm_playbook_scan($pdo, $merchantId, $scanInput);
$requested = $input['recommendation_ids'] ?? [];
if (is_string($requested)) $requested = preg_split('/[\s,]+/', $requested) ?: [];
if (is_array($requested) && $requested) {
    $wanted = array_flip(array_map('strval', $requested));
    $recommendations = array_values(array_filter($recommendations, static fn(array $rec): bool => isset($wanted[(string)$rec['id']])));
}
if (!$recommendations) mg_ok(['summary' => ['created' => 0, 'duplicates' => 0, 'skipped' => 0, 'approval_required' => 0], 'results' => []], 'No matching playbook recommendations found.');

$results = [];
try {
    $pdo->beginTransaction();
    foreach ($recommendations as $rec) {
        $key = (string)$rec['playbook_key'];
        $def = $defs[$key] ?? null;
        $setting = $settings[$key] ?? null;
        if (!$def || !$setting) { $results[] = ['status' => 'skipped', 'reason' => 'unknown_playbook', 'recommendation_id' => (string)$rec['id']]; continue; }
        if (empty($setting['enabled'])) { $results[] = ['status' => 'skipped', 'reason' => 'automation_disabled', 'recommendation_id' => (string)$rec['id']]; continue; }
        if ((int)($setting['max_actions_per_day'] ?? 0) <= 0) { $results[] = ['status' => 'skipped', 'reason' => 'daily_limit_zero', 'recommendation_id' => (string)$rec['id']]; continue; }
        $taskAllowed = !empty($setting['auto_create_followups']) || !empty($setting['agent_can_create_task']) || in_array((string)($setting['automation_level'] ?? ''), ['create_task','execute_with_approval'], true);
        if (!$taskAllowed) { $results[] = ['status' => 'approval_required', 'reason' => 'task_creation_not_enabled', 'recommendation_id' => (string)$rec['id']]; continue; }
        if (!empty($setting['require_approval']) || !empty($setting['agent_requires_approval'])) {
            mg_automation_record_event($pdo, $merchantId, 'crm.automation.approval.granted', ['playbook_key' => $key, 'playbook_title' => (string)$def['title'], 'recommendation_id' => (string)$rec['id'], 'approved_by_user_id' => (int)$user['id'], 'automation_level' => (string)$setting['automation_level'], 'requires_approval' => true], (int)($rec['_campaign_db_id'] ?? 0) ?: null, (int)($rec['_contact_db_id'] ?? 0) ?: null);
        }
        $created = mg_crm_playbook_create_followup($pdo, $merchantId, $rec, $def);
        $created['guardrail'] = ['automation_level' => (string)$setting['automation_level'], 'require_approval' => (bool)$setting['require_approval'], 'agent_requires_approval' => (bool)$setting['agent_requires_approval']];
        $results[] = $created;
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.crm_playbook_runner.failed', 'Unable to run CRM retention playbook.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to run retention playbook.', 500);
}
$summary = ['created' => 0, 'duplicates' => 0, 'skipped' => 0, 'approval_required' => 0];
foreach ($results as $result) {
    if (($result['status'] ?? '') === 'created') $summary['created']++;
    elseif (($result['status'] ?? '') === 'duplicate') $summary['duplicates']++;
    elseif (($result['status'] ?? '') === 'approval_required') $summary['approval_required']++;
    else $summary['skipped']++;
}
mg_ok(['summary' => $summary, 'results' => $results, 'guardrail_source' => $current['source']], 'Retention playbook run complete.');
