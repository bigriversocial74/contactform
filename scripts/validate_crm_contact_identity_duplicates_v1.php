<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'migration' => $root . '/database/crm_contact_identity_duplicate_management_v1.sql',
    'manifest' => $root . '/config/migrations.php',
    'identity' => $root . '/includes/merchant-crm-identity.php',
    'crm' => $root . '/includes/merchant-crm.php',
    'api' => $root . '/api/merchant/crm-duplicates.php',
    'view' => $root . '/includes/merchant-crm-view.php',
    'page' => $root . '/merchant-crm.php',
    'profile' => $root . '/merchant-customer.php',
    'js' => $root . '/assets/js/merchant-crm-identity-duplicates.js',
    'css' => $root . '/assets/css/merchant-crm-identity-duplicates.css',
];
$files = [];
foreach ($paths as $key => $path) {
    $content = file_get_contents($path);
    if (!is_string($content) || trim($content) === '') {
        fwrite(STDERR, "Missing validation target: {$path}\n");
        exit(1);
    }
    $files[$key] = $content;
}

require_once $paths['identity'];
$sampleRows = [
    ['id'=>1,'public_id'=>'00000000-0000-4000-8000-000000000001','resolved_user_id'=>0,'user_id'=>0,'primary_email'=>'one@example.com','primary_phone'=>'(602) 555-1212'],
    ['id'=>2,'public_id'=>'00000000-0000-4000-8000-000000000002','resolved_user_id'=>0,'user_id'=>0,'primary_email'=>'two@example.com','primary_phone'=>'1-602-555-1212'],
    ['id'=>3,'public_id'=>'00000000-0000-4000-8000-000000000003','resolved_user_id'=>88,'user_id'=>88,'primary_email'=>'account@example.com','primary_phone'=>''],
    ['id'=>4,'public_id'=>'00000000-0000-4000-8000-000000000004','resolved_user_id'=>88,'user_id'=>0,'primary_email'=>'alternate@example.com','primary_phone'=>''],
];
$components = mg_crm_identity_signal_components($sampleRows);
$componentSizes = array_map('count', $components['groups']);
sort($componentSizes);

$checks = [
    'migration registered in canonical manifest' => str_contains($files['manifest'], "'crm_contact_identity_duplicate_management_v1.sql'"),
    'merge lineage column is guarded' => str_contains($files['migration'], "COLUMN_NAME='merged_into_contact_id'") && str_contains($files['migration'], 'ADD COLUMN merged_into_contact_id'),
    'merge timestamp and reason columns exist' => str_contains($files['migration'], 'ADD COLUMN merged_at') && str_contains($files['migration'], 'ADD COLUMN merge_reason'),
    'identity aliases table exists' => str_contains($files['migration'], 'CREATE TABLE IF NOT EXISTS merchant_crm_contact_aliases'),
    'merge audit table exists' => str_contains($files['migration'], 'CREATE TABLE IF NOT EXISTS merchant_crm_contact_merges'),
    'alias identity is merchant unique' => str_contains($files['migration'], 'uq_merchant_crm_contact_aliases_identity'),
    'source contact can only be merged once' => str_contains($files['migration'], 'uq_merchant_crm_contact_merges_source'),
    'identity schema readiness is explicit' => str_contains($files['identity'], 'function mg_crm_identity_schema_ready'),
    'email normalization is conservative' => mg_crm_identity_normalize_email(' TEST@Example.com ') === 'test@example.com',
    'US phone normalization is stable' => mg_crm_identity_normalize_phone('+1 (602) 555-1212') === '6025551212',
    'short phone values are rejected' => mg_crm_identity_normalize_phone('555') === '',
    'phone duplicate cluster is detected' => $componentSizes === [2,2],
    'duplicate analysis excludes merged profiles' => str_contains($files['identity'], 'merged_into_contact_id IS NULL'),
    'different resolved accounts block merges' => str_contains($files['identity'], 'Profiles linked to different Microgifter accounts cannot be merged.'),
    'selected profiles must share identity signal' => str_contains($files['identity'], 'do not share a verified account, email, or phone identity signal'),
    'merge locks selected contacts' => str_contains($files['identity'], 'ORDER BY c.id FOR UPDATE'),
    'merge is transactional' => str_contains($files['identity'], '$pdo->beginTransaction()') && str_contains($files['identity'], '$pdo->commit()') && str_contains($files['identity'], '$pdo->rollBack()'),
    'campaign histories are consolidated' => str_contains($files['identity'], 'INSERT INTO merchant_crm_contact_campaigns') && str_contains($files['identity'], 'event_count=event_count+VALUES(event_count)'),
    'events and notes are reassigned' => str_contains($files['identity'], 'UPDATE merchant_crm_contact_events SET crm_contact_id=?') && str_contains($files['identity'], 'UPDATE merchant_crm_notes SET crm_contact_id=?'),
    'source profiles are archived with lineage' => str_contains($files['identity'], "crm_status='archived'") && str_contains($files['identity'], 'merged_into_contact_id=?'),
    'canonical identity aliases are preserved' => str_contains($files['identity'], 'INSERT INTO merchant_crm_contact_aliases'),
    'merge audit snapshots are stored' => str_contains($files['identity'], 'canonical_before_json') && str_contains($files['identity'], 'source_before_json') && str_contains($files['identity'], 'moved_counts_json'),
    'canonical merge timeline event is emitted' => str_contains($files['identity'], "'crm_contact_merged'") && str_contains($files['identity'], "'merchant_crm_identity'"),
    'future CRM events resolve aliases' => str_contains($files['crm'], 'mg_crm_identity_alias_contact') && str_contains($files['crm'], 'mg_crm_identity_resolve_contact'),
    'API uses view and manage permissions' => str_contains($files['api'], "merchant.campaigns.manage") && str_contains($files['api'], "merchant.campaigns.view"),
    'API requires CSRF for merge' => str_contains($files['api'], 'mg_require_csrf_for_write'),
    'API keeps recent merge history' => str_contains($files['api'], 'mg_crm_duplicate_recent_merges'),
    'CRM page has duplicate review panel' => str_contains($files['view'], 'data-crm-duplicates-panel') && str_contains($files['view'], 'Possible Duplicates'),
    'merge UI requires explicit confirmation' => str_contains($files['js'], 'cannot be undone from this screen') && str_contains($files['js'], 'source_contact_ids'),
    'merged profile URL redirects to canonical' => str_contains($files['profile'], 'mg_crm_identity_resolve_contact') && str_contains($files['profile'], "header('Location: /merchant-customer.php?"),
    'identity assets are loaded' => str_contains($files['page'], 'merchant-crm-identity-duplicates.css') && str_contains($files['page'], 'merchant-crm-identity-duplicates.js'),
    'responsive duplicate workspace styling exists' => str_contains($files['css'], '@media(max-width:620px)') && str_contains($files['css'], '.mg-crm-duplicate-member'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}
if ($failed !== []) {
    fwrite(STDERR, 'CRM Contact Identity v1 validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo 'CRM Contact Identity & Duplicate Management v1 contract: ' . count($checks) . '/' . count($checks) . ' checks passed.' . PHP_EOL;
