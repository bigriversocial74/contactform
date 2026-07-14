<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $fullPath = $root . '/' . $path;
    return is_file($fullPath) ? (string) file_get_contents($fullPath) : '';
};

$requiredFiles = [
    'database/20260714_user_contact_lists_phase1.sql',
    'config/migrations.php',
    'includes/user-contact-lists.php',
    'includes/user-contact-search.php',
    'includes/header-components/create-list-extension.php',
    'api/user-lists/_bootstrap.php',
    'api/user-lists/index.php',
    'api/user-lists/create.php',
    'api/user-lists/item.php',
    'api/user-lists/search-contacts.php',
    'api/user-lists/add-contact.php',
    'api/user-lists/remove-contact.php',
    'api/user-contacts/create.php',
    'lists.php',
    'list.php',
    'assets/css/user-lists.css',
    'assets/js/user-lists.js',
    'assets/js/create-list-extension.js',
    'tests/phpunit/UserContactListsFoundationTest.php',
];

$checks = [];
foreach ($requiredFiles as $path) {
    $checks['file: ' . $path] = is_file($root . '/' . $path);
}

$sql = $read('database/20260714_user_contact_lists_phase1.sql');
$manifest = $read('config/migrations.php');
$service = $read('includes/user-contact-lists.php');
$search = $read('includes/user-contact-search.php');
$apiBootstrap = $read('api/user-lists/_bootstrap.php');
$listCreateApi = $read('api/user-lists/create.php');
$addContactApi = $read('api/user-lists/add-contact.php');
$removeContactApi = $read('api/user-lists/remove-contact.php');
$privateContactApi = $read('api/user-contacts/create.php');
$searchApi = $read('api/user-lists/search-contacts.php');
$appHeader = $read('includes/header-components/app-header.php');
$loggedInHeader = $read('includes/header-templates/logged-in.php');
$createExtension = $read('includes/header-components/create-list-extension.php');
$agentPage = $read('agent.php');
$agentWorkspace = $read('includes/agent-workspace.php');
$agentCss = $read('assets/css/agent-workspace-layout.css');
$listsPage = $read('lists.php');
$listPage = $read('list.php');

$tables = [
    'user_contact_preferences',
    'user_contact_lists',
    'user_contacts',
    'user_contact_list_members',
    'user_contact_dates',
    'user_contact_profile_permissions',
    'user_contact_profile_imports',
];
$tableCoverage = true;
foreach ($tables as $table) {
    $tableCoverage = $tableCoverage && str_contains($sql, 'CREATE TABLE IF NOT EXISTS ' . $table);
}
$checks['normalized schema tables'] = $tableCoverage;
$checks['migration is canonical'] = str_contains($manifest, "'20260714_user_contact_lists_phase1.sql'")
    && str_contains($manifest, "'stage_14_posts_feed_social.sql'")
    && strpos($manifest, "'stage_14_posts_feed_social.sql'") < strpos($manifest, "'20260714_user_contact_lists_phase1.sql'");
$checks['membership targets are normalized and unique'] = str_contains($sql, 'uq_user_contact_list_member_linked')
    && str_contains($sql, 'uq_user_contact_list_member_private')
    && str_contains($sql, 'chk_user_contact_list_member_target')
    && !str_contains($sql, 'contact_ids_csv');

$checks['reusable mutual-follow eligibility'] = str_contains($service, 'function mg_user_contact_list_eligible')
    && str_contains($service, 'social_follows')
    && str_contains($service, 'social_blocks')
    && str_contains($service, '$ownerFollows')
    && str_contains($service, '$contactFollows')
    && str_contains($service, 'allow_list_membership');
$checks['relationship-safe discovery'] = str_contains($search, 'INNER JOIN social_follows sf_rel')
    && str_contains($search, "sf_rel.status='active'")
    && str_contains($search, "pp.status='active'")
    && str_contains($search, "pp.visibility IN ('public','unlisted')")
    && str_contains($searchApi, 'mg_user_contact_relationship_search');

$checks['phone encryption and masking'] = str_contains($service, "'aes-256-gcm'")
    && str_contains($service, "mg_env('MG_CONTACT_DATA_KEY'")
    && str_contains($service, "'phone_masked'")
    && !str_contains($service, "'phone' =>");
$apiFiles = glob($root . '/api/user-{lists,contacts}/*.php', GLOB_BRACE) ?: [];
$apiPhoneSafe = true;
foreach ($apiFiles as $apiFile) {
    $apiPhoneSafe = $apiPhoneSafe && !str_contains((string) file_get_contents($apiFile), 'phone_ciphertext');
}
$checks['standard APIs never return phone ciphertext'] = $apiPhoneSafe;

$checks['API authentication wrapper'] = str_contains($apiBootstrap, 'mg_require_api_user')
    || (str_contains($listCreateApi, 'mg_require_api_user') && str_contains($searchApi, 'mg_require_api_user'));
$checks['write endpoints enforce CSRF'] = str_contains($listCreateApi, 'mg_require_csrf_for_write')
    && str_contains($addContactApi, 'mg_require_csrf_for_write')
    && str_contains($removeContactApi, 'mg_require_csrf_for_write')
    && str_contains($privateContactApi, 'mg_require_csrf_for_write');
$checks['owner scoping is server-side'] = str_contains($service, 'owner_user_id=?')
    && str_contains($service, '$ownerUserId')
    && str_contains($removeContactApi, 'owner_user_id=?');

$checks['customer Create Center remains permission separated'] = str_contains($loggedInHeader, '$can_create_list')
    && str_contains($loggedInHeader, '$can_merchant_nav && ($can_create_microgift || $can_create_campaigns || $can_create_rewards)')
    && str_contains($createExtension, 'data-create-menu-option="contact_list"')
    && str_contains($createExtension, 'data-create-inline-form="list"');
$checks['Agent tab restored for authenticated customers'] = str_contains($appHeader, '$is_authenticated_user = mg_current_user() !== null;')
    && str_contains($appHeader, "['agent','Agent','/agent.php',\$can_agent_workspace]")
    && str_contains($appHeader, "['inbox','Inbox','/inbox.php',true]")
    && str_contains($appHeader, "['sent','Sent','/sent.php',true]")
    && str_contains($appHeader, "['claimed','Claimed','/claimed.php',true]");
$checks['existing Agent shell and sticky composer preserved'] = str_contains($agentPage, "require __DIR__ . '/includes/agent-workspace.php';")
    && str_contains($agentWorkspace, 'data-agent-composer')
    && str_contains($agentCss, '.mg-agent-workspace .mg-app-composer');
$checks['new pages use shared authenticated app shell'] = str_contains($listsPage, 'class="mg-app-shell mg-user-lists-shell"')
    && str_contains($listPage, 'class="mg-app-shell mg-user-lists-shell"')
    && str_contains($listsPage, "require __DIR__ . '/includes/header.php';")
    && str_contains($listPage, "require __DIR__ . '/includes/footer.php';");

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed[] = $name;
    }
}

if ($failed !== []) {
    fwrite(STDERR, PHP_EOL . 'User Contact Lists Phase 1 validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'User Contact Lists Phase 1 contract: 10/10.' . PHP_EOL;
