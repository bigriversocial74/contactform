<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $fullPath = $root . '/' . ltrim($path, '/');
    $content = file_get_contents($fullPath);
    if (!is_string($content)) {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
    return $content;
};

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$header = $read('includes/header-templates/logged-in.php');
$sidebar = $read('includes/account-sidebar.php');
$profileApi = $read('api/public/profile.php');
$profileJs = $read('assets/js/public-profile.js');
$tabCss = $read('assets/css/account-dropdown-tabs.css');
$portalPage = $read('investor-portal.php');
$portalNavigation = $read('assets/js/investor-portal-navigation-v1.js');

$assert(str_contains($header, "in_array('investor', \$user_roles, true)"), 'Header must derive approved Investor access from the Investor role.');
$assert(str_contains($header, 'id="mg-account-tab-investor"'), 'Approved Investor accounts must receive a third account dropdown tab.');
$assert(str_contains($header, 'mg-account-investor-panel'), 'Header must render the Investor tab panel.');
$assert(str_contains($header, '/investor-portal.php#dataroom'), 'Investor dropdown must link directly to the Data Room.');
$assert(str_contains($header, '/investor-portal.php#governance'), 'Investor dropdown must link directly to Governance.');

$assert(!str_contains($sidebar, '/investor-access.php'), 'Account sidebar must not contain Request Investor Access.');
$assert(!str_contains($sidebar, '/investor-portal.php'), 'Account sidebar must not contain Investor Portal.');

$assert(str_contains($profileApi, "r.slug='super_admin'"), 'Profile API must restrict the investor-access host capability to Super Admin profiles.');
$assert(str_contains($profileApi, 'is_investor_access_host'), 'Profile API must expose the Super Admin investor-access host capability.');
$assert(str_contains($profileApi, "r.slug='investor'"), 'Profile API must identify approved Investor viewers.');
$assert(str_contains($profileJs, 'is_investor_access_host'), 'Profile UI must condition the action on the Super Admin profile capability.');
$assert(str_contains($profileJs, 'data-profile-investor-access'), 'Profile UI must render a dedicated Investor access action.');
$assert(str_contains($profileJs, 'Request Investor Access'), 'Super Admin profiles must expose the request action.');
$assert(str_contains($profileJs, 'Open Investor Portal'), 'Approved Investor viewers must receive the portal action.');
$assert(str_contains($profileJs, "'/signin.php?return='"), 'Anonymous visitors must be sent through sign-in before requesting access.');

$assert(str_contains($tabCss, '#mg-account-tab-investor:checked'), 'Account dropdown CSS must support the third Investor tab.');
$assert(str_contains($portalPage, 'investor-portal-navigation-v1.js'), 'Investor Portal must load deep-link navigation.');
foreach (['summary','dataroom','qa','requests','updates','interest','relations','governance'] as $section) {
    $assert(str_contains($portalNavigation, "'{$section}'"), "Investor Portal navigation must support {$section}.");
}

if ($failures !== []) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: {$failure}\n");
    exit(1);
}

fwrite(STDOUT, "Investor profile entry and dropdown navigation validation passed.\n");
