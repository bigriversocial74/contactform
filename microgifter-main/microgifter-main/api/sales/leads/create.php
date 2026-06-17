<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/crm.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$user = mg_crm_require_sales_access('sales.leads.update_status');

try {
    $input['source_page'] = $input['source_page'] ?? 'sales-crm-manual';
    $lead = mg_crm_create_lead($input, (int) $user['id'], true);
    mg_audit('crm.lead.manual_create', 'crm_lead', ['lead_id' => (int) $lead['id']], (int) $user['id']);
    mg_ok(['lead' => $lead], 'Lead created.', 201);
} catch (Throwable $e) {
    mg_security_log('error', 'sales.lead_manual_create_failed', 'Manual lead create failed.', ['exception' => $e->getMessage()], (int) $user['id']);
    mg_fail('Unable to create lead.', 500);
}
