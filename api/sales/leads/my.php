<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/crm.php';

mg_require_method('GET');
$user = mg_crm_require_sales_access('sales.leads.view_own');

$filters = [
    'status' => $_GET['status'] ?? 'all',
    'q' => $_GET['q'] ?? '',
];

mg_ok([
    'leads' => mg_crm_list_leads($user, $filters),
    'stats' => mg_crm_dashboard_stats($user),
    'can_view_all' => mg_crm_user_can_view_all($user),
], 'Sales leads.');
