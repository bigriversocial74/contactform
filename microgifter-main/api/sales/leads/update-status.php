<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/crm.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$user = mg_crm_require_sales_access('sales.leads.update_status');
$leadId = (int) ($input['lead_id'] ?? 0);
$status = trim((string) ($input['status'] ?? ''));
$note = trim((string) ($input['note'] ?? ''));

if ($leadId <= 0) {
    mg_fail('Lead is required.', 422, ['lead_id' => 'Lead is required.']);
}
if ($status === '') {
    mg_fail('Status is required.', 422, ['status' => 'Status is required.']);
}

$lead = mg_crm_get_lead($leadId);
if (!$lead) {
    mg_fail('Lead not found.', 404);
}
if (!mg_crm_user_can_view_all($user) && (int) ($lead['assigned_user_id'] ?? 0) !== (int) $user['id']) {
    mg_fail('You can only update assigned leads.', 403);
}

try {
    $updated = mg_crm_update_lead_status($leadId, $status, (int) $user['id'], $note ?: null);
    mg_ok(['lead' => $updated], 'Lead updated.');
} catch (Throwable $e) {
    mg_security_log('error', 'sales.lead_status_update_failed', 'Lead status update failed.', ['exception' => $e->getMessage()], (int) $user['id']);
    mg_fail('Unable to update lead.', 500);
}
