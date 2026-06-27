<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-playbooks.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'POST' ? mg_require_permission('merchant.campaigns.manage') : mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    $defs = array_values(mg_crm_playbook_defs());
    $recommendations = mg_crm_playbook_scan($pdo, $merchantId, $_GET);
    mg_ok([
        'playbooks' => $defs,
        'recommendations' => mg_crm_playbook_public_recs($recommendations),
        'summary' => mg_crm_playbook_summary($recommendations),
        'agentic_management' => ['mode' => 'deterministic_rules', 'guardrail' => 'merchant_owned_actions', 'next_layer' => 'agent_monitoring'],
    ]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? '')));
if (!in_array($action, ['preview', 'configure'], true)) mg_fail('Unsupported playbook action.', 422);
$defs = array_values(mg_crm_playbook_defs());
$recommendations = mg_crm_playbook_scan($pdo, $merchantId, $input);
mg_ok([
    'playbooks' => $defs,
    'recommendations' => mg_crm_playbook_public_recs($recommendations),
    'summary' => mg_crm_playbook_summary($recommendations),
    'configuration_status' => 'default_rules_active',
], 'Retention playbooks previewed.');
