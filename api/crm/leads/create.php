<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/crm.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$ip = mg_client_ip() ?: 'unknown';
mg_rate_limit('crm.lead.create.ip', $ip, 12, 3600);

try {
    $lead = mg_crm_create_lead($input, null, true);
    mg_event('crm_lead.created', ['lead_id' => (int) $lead['id'], 'source_page' => $lead['source_page'] ?? 'learn-more'], null);
    mg_ok(['lead' => $lead], 'Thanks — your request was received.', 201);
} catch (Throwable $e) {
    mg_security_log('error', 'crm.lead_create_failed', 'CRM lead create failed.', ['exception' => $e->getMessage()]);
    mg_fail('Unable to submit your request right now.', 500);
}
