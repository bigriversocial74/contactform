<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'page' => 'merchant-crm.php',
    'view' => 'includes/merchant-crm-view.php',
    'directory' => 'includes/merchant-crm-directory.php',
    'search' => 'includes/merchant-crm-search.php',
    'api' => 'api/merchant/merchant-crm.php',
    'campaign_api' => 'api/merchant/campaign-contacts.php',
    'controller' => 'assets/js/merchant-crm.js',
    'data' => 'assets/js/merchant-crm-directory-data.js',
    'runtime' => 'assets/js/merchant-crm-directory.js',
    'mobile' => 'assets/js/merchant-crm-mobile-dashboard.js',
    'styles' => 'assets/css/merchant-crm-directory-v1.css',
    'audit' => 'docs/merchant-crm-current-state-audit-v1.md',
];
$content = [];
foreach ($files as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) throw new RuntimeException('Missing required file: ' . $relative);
    $content[$key] = (string)file_get_contents($path);
}

$checks = [
    'CRM page preserves the current workspace and authoritative KPI layout' =>
        str_contains($content['page'], "'/assets/css/merchant-crm-kpi-authoritative-v1.css?v=1.0.0'")
        && str_contains($content['view'], 'class="mg-crm-desktop-kpis"')
        && str_contains($content['view'], 'data-merchant-crm-table'),
    'CRM page loads one canonical data bridge and one directory runtime' =>
        str_contains($content['page'], '/assets/js/merchant-crm-directory-data.js?v=1.0.0')
        && str_contains($content['page'], '/assets/js/merchant-crm-directory.js?v=1.0.0')
        && str_contains($content['page'], '/assets/css/merchant-crm-directory-v1.css?v=1.0.0'),
    'legacy rollup and desktop-only search runtimes are removed' =>
        !is_file($root . '/assets/js/merchant-crm-contact-rollup.js')
        && !is_file($root . '/assets/js/merchant-crm-desktop-search.js')
        && !str_contains($content['page'], 'merchant-crm-contact-rollup.js')
        && !str_contains($content['page'], 'merchant-crm-desktop-search.js'),
    'canonical directory endpoint remains authenticated owner scoped' =>
        str_contains($content['api'], "mg_require_permission('merchant.campaigns.view')")
        && str_contains($content['api'], 'mg_merchant_ensure_workspace($pdo, $user)')
        && str_contains($content['directory'], "'mc.merchant_user_id=?'")
        && str_contains($content['directory'], 'merged_into_contact_id IS NULL'),
    'directory contract is versioned and bounded with pagination' =>
        str_contains($content['directory'], 'MG_MERCHANT_CRM_DIRECTORY_CONTRACT_VERSION = 1')
        && str_contains($content['directory'], 'min(250, $limit)')
        && str_contains($content['directory'], "'has_more'=>")
        && str_contains($content['directory'], "'next_offset'=>"),
    'canonical identities expose Agent-compatible usernames and mentions' =>
        str_contains($content['directory'], "'crm_username'")
        && str_contains($content['directory'], "'crm_mention'")
        && str_contains($content['search'], "'mention'=>'@' . \$handle")
        && str_contains($content['search'], "return 'crm-' . substr(\$publicId, 0, 10)"),
    'directory search covers identity campaign source lifecycle and action context' =>
        str_contains($content['directory'], "\$contact['crm_username']")
        && str_contains($content['directory'], "\$contact['campaign_title']")
        && str_contains($content['directory'], "\$contact['source']")
        && str_contains($content['directory'], "\$contact['lifecycle_stage']")
        && str_contains($content['directory'], "\$contact['crm_status']")
        && str_contains($content['directory'], "\$contact['next_best_action']"),
    'campaign activity remains the operational row and timeline source' =>
        str_contains($content['controller'], '/api/merchant/campaign-contacts.php')
        && str_contains($content['controller'], '/api/merchant/campaign-timeline.php')
        && str_contains($content['campaign_api'], 'WHERE cc.merchant_user_id=?'),
    'data bridge only transforms campaign contact reads and fetches canonical data without recursion' =>
        str_contains($content['data'], "url.indexOf('/api/merchant/campaign-contacts.php') === -1")
        && str_contains($content['data'], "originalGet.call(window.Microgifter, '/api/merchant/merchant-crm.php?limit=250')")
        && str_contains($content['data'], '__mgMerchantCrmOriginalGet'),
    'data bridge collapses campaign rows and attaches canonical identity fields' =>
        str_contains($content['data'], 'collapseContacts')
        && str_contains($content['data'], 'canonical_merchant_customer')
        && str_contains($content['data'], 'contact.crm_contact_id')
        && str_contains($content['data'], 'contact.crm_username')
        && str_contains($content['data'], 'contact.lifecycle_stage')
        && str_contains($content['data'], 'contact.crm_status'),
    'desktop and mobile use one synchronized query state' =>
        str_contains($content['runtime'], '[desktopInput, mobileInput]')
        && str_contains($content['runtime'], "params.get('q') || params.get('search')")
        && str_contains($content['runtime'], "replace(/^@+/, '')")
        && str_contains($content['runtime'], 'syncInputs'),
    'directory runtime provides one empty state and progressive 25-row pagination' =>
        str_contains($content['runtime'], 'pageSize: 25')
        && str_contains($content['runtime'], 'data-crm-directory-more')
        && str_contains($content['runtime'], 'state.visibleLimit += state.pageSize')
        && str_contains($content['runtime'], 'desktopEmpty')
        && str_contains($content['runtime'], 'mobileEmpty'),
    'directory runtime has no DOM-wide mutation observer loop' =>
        !str_contains($content['runtime'], 'MutationObserver')
        && !str_contains($content['mobile'], 'MutationObserver'),
    'mobile dashboard is limited to responsive overview behavior' =>
        str_contains($content['mobile'], 'setAccordion')
        && str_contains($content['mobile'], 'syncViewport')
        && !str_contains($content['mobile'], 'applySearch')
        && !str_contains($content['mobile'], 'search.addEventListener'),
    'canonical identity is visible and View Customer opens the canonical profile' =>
        str_contains($content['runtime'], 'mg-crm-directory-identity')
        && str_contains($content['runtime'], 'contact.customer_profile_url')
        && str_contains($content['runtime'], "viewLink.setAttribute('href'"),
    'existing message reward follow-up bulk and export operations remain present' =>
        str_contains($content['controller'], '/api/merchant/crm-message.php')
        && str_contains($content['controller'], '/api/merchant/crm-bulk-message.php')
        && str_contains($content['controller'], '/api/merchant/crm-bulk-reward.php')
        && str_contains($content['controller'], '/api/merchant/crm-followup.php')
        && str_contains($content['controller'], 'microgifter-crm-selected-contacts.csv'),
    'current drawer message and reward interfaces remain present' =>
        str_contains($content['view'], 'data-crm-drawer')
        && str_contains($content['view'], 'data-crm-message-modal')
        && str_contains($content['view'], 'data-crm-reward-modal'),
    'new directory layers add no schema or mutation authority' =>
        !str_contains($content['directory'], 'CREATE TABLE')
        && !str_contains($content['directory'], 'ALTER TABLE')
        && !str_contains($content['api'], 'INSERT INTO')
        && !str_contains($content['api'], 'UPDATE merchant_crm_contacts')
        && !str_contains($content['data'], 'Microgifter.post(')
        && !str_contains($content['runtime'], 'Microgifter.post('),
    'audit documents the dual data surfaces cleanup and Contact Action Center foundation' =>
        str_contains($content['audit'], '`campaign_contacts`')
        && str_contains($content['audit'], '`merchant_crm_contacts`')
        && str_contains($content['audit'], 'Merchant Contact Action Center v1')
        && str_contains($content['audit'], 'No SQL required'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}
if ($failed !== []) {
    fwrite(STDERR, "Merchant CRM current-state audit contract failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Merchant CRM current-state audit contract passed (' . count($checks) . ' checks).' . PHP_EOL;
