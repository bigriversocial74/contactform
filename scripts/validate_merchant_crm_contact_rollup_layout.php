<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) throw new RuntimeException('Missing required file: ' . $path);
    $content = file_get_contents($full);
    if (!is_string($content)) throw new RuntimeException('Unable to read required file: ' . $path);
    return $content;
};

$expect = static function (bool $condition, string $label) use (&$failures, &$passes): void {
    if ($condition) { $passes++; echo "PASS: {$label}\n"; return; }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
};

try {
    $page = $read('merchant-crm.php');
    $rollup = $read('assets/js/merchant-crm-directory-data.js');
    $directory = $read('includes/merchant-crm-directory.php');
    $polish = $read('assets/js/merchant-crm-contact-link-polish.js');
    $layout = $read('assets/css/merchant-crm-layout-stability.css');
    $contactsCss = $read('assets/css/merchant-crm-contacts-clean.css');
    $api = $read('api/merchant/campaign-contacts.php');
    $view = $read('includes/merchant-crm-view.php');

    $rollupPosition = strpos($page, '/assets/js/merchant-crm-directory-data.js?v=1.0.0');
    $corePosition = strpos($page, '/assets/js/merchant-crm.js');
    $expect(
        $rollupPosition !== false && $corePosition !== false && $rollupPosition < $corePosition,
        'Canonical customer directory bridge loads before the Merchant CRM controller'
    );

    $expect(
        str_contains($rollup, "return 'email:' + email")
        && str_contains($rollup, 'var groups = new Map()')
        && str_contains($rollup, 'campaign_count')
        && str_contains($rollup, "contact_rollup: 'canonical_merchant_customer'")
        && str_contains($directory, 'MG_MERCHANT_CRM_DIRECTORY_CONTRACT_VERSION = 1'),
        'CRM contacts collapse to one canonical merchant customer row using normalized identity'
    );

    $expect(
        str_contains($rollup, "'wallet_count'")
        && str_contains($rollup, "'issued_count'")
        && str_contains($rollup, "'claimed_count'")
        && str_contains($rollup, "'redeemed_count'")
        && str_contains($rollup, 'sent: totalSent')
        && str_contains($rollup, 'claimed: totalClaimed'),
        'Merchant customer activity totals aggregate Inbox, Sent, Claimed, and Redeemed states'
    );

    $expect(
        str_contains($api, 'WHERE cc.merchant_user_id=?')
        && str_contains($api, 'LEFT JOIN wallet_items wi ON wi.contact_id=cc.id')
        && str_contains($directory, "'mc.merchant_user_id=?'"),
        'Campaign activity and canonical CRM identity remain scoped to the authenticated merchant'
    );

    $expect(
        str_contains($polish, 'moveAccountData(row)')
        && str_contains($polish, "accountCell.remove()")
        && str_contains($polish, "row.querySelector('.mg-crm-score-line')")
        && str_contains($polish, 'addLatestActivity(row')
        && str_contains($polish, "campaignCount + ' campaigns'"),
        'Account data moves into Contact, score badge is removed, and latest campaign activity is shown'
    );

    $expect(
        str_contains($layout, '.mg-merchant-main{')
        && str_contains($layout, 'overflow-x:hidden!important')
        && str_contains($layout, '.mg-crm-tabs{')
        && str_contains($layout, 'overflow-x:auto!important')
        && str_contains($layout, 'grid-template-columns:38px minmax(205px,.88fr) minmax(230px,1.08fr) minmax(235px,1fr) 172px!important'),
        'Merchant CRM shell and five-column contact table stay inside the available workspace'
    );

    $expect(
        str_contains($layout, '.mg-crm-contacts-card > .mg-app-panel-body')
        && str_contains($layout, 'padding-top:34px!important'),
        'Contacts stats row has explicit top breathing room'
    );

    $expect(
        str_contains($view, 'data-crm-contact-stat-strip')
        && str_contains($view, 'data-merchant-crm-table')
        && str_contains($contactsCss, '.mg-crm-engagement-stats'),
        'Existing Contacts stats and engagement table contracts remain present'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Merchant CRM contact rollup and layout validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "Merchant CRM contact rollup and layout validation passed: {$passes} checks.\n";
