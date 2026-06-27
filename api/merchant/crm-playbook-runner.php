<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-playbooks.php';

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
$scanInput = $input;
if ($playbookKey !== '') $scanInput['playbook_key'] = $playbookKey;
$recommendations = mg_crm_playbook_scan($pdo, $merchantId, $scanInput);
$requested = $input['recommendation_ids'] ?? [];
if (is_string($requested)) $requested = preg_split('/[\s,]+/', $requested) ?: [];
if (is_array($requested) && $requested) {
    $wanted = array_flip(array_map('strval', $requested));
    $recommendations = array_values(array_filter($recommendations, static fn(array $rec): bool => isset($wanted[(string)$rec['id']])));
}
if (!$recommendations) mg_ok(['summary' => ['created' => 0, 'duplicates' => 0, 'skipped' => 0], 'results' => []], 'No matching playbook recommendations found.');

$results = [];
try {
    $pdo->beginTransaction();
    foreach ($recommendations as $rec) {
        $def = $defs[(string)$rec['playbook_key']] ?? null;
        if (!$def) { $results[] = ['status' => 'skipped', 'reason' => 'unknown_playbook', 'recommendation_id' => (string)$rec['id']]; continue; }
        if (($def['action_type'] ?? '') === 'create_followup_task' || in_array((string)$def['key'], ['high_value_inactive_after_30d','contest_entrant_reward_invite','tip_thank_you_followup'], true)) {
            $results[] = mg_crm_playbook_create_followup($pdo, $merchantId, $rec, $def);
            continue;
        }
        $results[] = ['status' => 'skipped', 'reason' => 'manual_recommendation_only', 'recommendation_id' => (string)$rec['id']];
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.crm_playbook_runner.failed', 'Unable to run CRM retention playbook.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to run retention playbook.', 500);
}
$summary = ['created' => 0, 'duplicates' => 0, 'skipped' => 0];
foreach ($results as $result) {
    if (($result['status'] ?? '') === 'created') $summary['created']++;
    elseif (($result['status'] ?? '') === 'duplicate') $summary['duplicates']++;
    else $summary['skipped']++;
}
mg_ok(['summary' => $summary, 'results' => $results], 'Retention playbook run complete.');
