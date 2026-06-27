<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-automation-controls.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$limit = max(1, min(150, (int)($_GET['limit'] ?? 60)));
$events = mg_automation_log($pdo, $merchantId, $limit);
$summary = ['total' => count($events), 'settings' => 0, 'tasks' => 0, 'playbooks' => 0, 'approvals' => 0, 'drafts' => 0];
foreach ($events as $event) {
    $type = (string)($event['event_type'] ?? '');
    if ($type === 'crm.automation.settings.updated') $summary['settings']++;
    elseif ($type === 'crm.followup.created') $summary['tasks']++;
    elseif ($type === 'crm.playbook.triggered') $summary['playbooks']++;
    elseif (str_starts_with($type, 'crm.automation.approval.')) $summary['approvals']++;
    elseif ($type === 'crm.automation.message.drafted') $summary['drafts']++;
}
mg_ok(['events' => $events, 'summary' => $summary]);
