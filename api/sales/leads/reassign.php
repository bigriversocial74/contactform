<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/crm.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$user = mg_crm_require_sales_access('sales.leads.assign');
$leadId = (int) ($input['lead_id'] ?? 0);
$salesUserId = (int) ($input['sales_user_id'] ?? 0);
$reason = trim((string) ($input['reason'] ?? 'Manual CRM routing'));

if ($leadId <= 0 || $salesUserId <= 0) {
    mg_fail('Lead and sales user are required.', 422);
}

try {
    mg_crm_assign_lead($leadId, $salesUserId, (int) $user['id'], 'manual', $reason);
    mg_ok(['lead' => mg_crm_get_lead($leadId)], 'Lead routed.');
} catch (Throwable $e) {
    mg_security_log('error', 'sales.lead_route_failed', 'Lead route failed.', ['exception' => $e->getMessage()], (int) $user['id']);
    mg_fail('Unable to route lead.', 500);
}
