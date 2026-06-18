<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/crm.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    mg_crm_record_page_view($input);
    mg_ok(['recorded' => true], 'Analytics recorded.');
} catch (Throwable $e) {
    mg_security_log('warning', 'crm.analytics_page_view_failed', 'Analytics page-view failed.', ['exception' => $e->getMessage()]);
    mg_ok(['recorded' => false], 'Analytics skipped.');
}
