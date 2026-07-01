<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/page.php';
require_once dirname(__DIR__) . '/api/db.php';

$inferred_page_id = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'public.php'), '.php');
$manifest_overrides = is_array($page_manifest ?? null) ? $page_manifest : [];

$manifest_seed = [
    'id' => $manifest_overrides['id'] ?? $inferred_page_id,
    'title' => $page_title ?? 'Microgifter',
    'section' => $page_section ?? 'public',
    'header_mode' => $header_mode ?? 'public',
    'styles' => is_array($page_styles ?? null) ? $page_styles : [],
    'scripts' => is_array($page_scripts ?? null) ? $page_scripts : [],
];

if (isset($page_body_class) && trim((string) $page_body_class) !== '') {
    $manifest_seed['body_class'] = (string) $page_body_class;
}

if (isset($header_controls) && is_array($header_controls)) {
    $manifest_seed['header_controls'] = $header_controls;
}

$page_manifest = mg_page_manifest(
    array_replace_recursive($manifest_seed, $manifest_overrides)
);
$page_assets_resolved = mg_resolve_page_assets($page_manifest);

$page_title = $page_manifest['title'];
$page_section = $page_manifest['section'];
$header_mode = $page_manifest['header_mode'];
$header_controls = $page_manifest['header_controls'];
$page_styles = $page_assets_resolved['styles'];
$page_scripts = $page_assets_resolved['scripts'];
$page_body_class = trim((string) $page_manifest['body_class']);
$page_onboarding = is_array($page_manifest['onboarding'] ?? null)
    ? $page_manifest['onboarding']
    : mg_onboarding_config($page_manifest['id']);
$page_meta = is_array($page_meta ?? null) ? $page_meta : [];
$page_description = trim((string) ($page_meta['description'] ?? $page_manifest['description'] ?? ''));
$page_canonical_url = trim((string) ($page_meta['canonical'] ?? ''));
$page_og_title = trim((string) ($page_meta['og_title'] ?? $page_title));
$page_og_description = trim((string) ($page_meta['og_description'] ?? $page_description));
$page_og_image = trim((string) ($page_meta['og_image'] ?? ''));
$page_robots = trim((string) ($page_meta['robots'] ?? ''));

$agent_tab = $agent_tab ?? '';
$section_css = $section_css ?? null;
$is_app_page = in_array($header_mode, ['agent', 'account', 'crm', 'builder'], true);
$is_profile_page = !$is_app_page && in_array((string) ($page_manifest['id'] ?? ''), ['profile', 'public-profile'], true);
$public_header_fix_style = '/assets/css/public-header-footer-fixes.css';
$public_dark_shell_style = '/assets/css/public-dark-shell.css';
$public_header_cleanup_style = '/assets/css/public-header-cleanup.css';
if (!$is_app_page && !in_array($public_header_fix_style, $page_styles, true)) {
    $page_styles[] = $public_header_fix_style;
}
if (!$is_app_page && !in_array($public_dark_shell_style, $page_styles, true)) {
    $page_styles[] = $public_dark_shell_style;
}
if (!$is_app_page && !in_array($public_header_cleanup_style, $page_styles, true)) {
    $page_styles[] = $public_header_cleanup_style;
}
$user = $is_app_page ? mg_require_auth() : mg_current_user();

if ($user && in_array((string) $page_manifest['id'], ['home', 'index'], true)) {
    header('Cache-Control: no-store, private');
    header('Location: /inbox.php', true, 302);
    exit;
}

if ($is_app_page) {
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
}

$display_name = $user ? mg_user_display_name() : 'Account';
$display_email = $user ? (string) ($user['email'] ?? '') : 'Guest';
$display_initial = strtoupper(substr($display_name !== '' ? $display_name : 'A', 0, 1));
$user_permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$user_roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$mg_package_context = $user ? mg_user_package_context(null, $user) : mg_package_entitlement_free_context(null);
$mg_package_limits = is_array($mg_package_context['limits'] ?? null) ? $mg_package_context['limits'] : [];
$can_merchant_nav = $user && !empty($mg_package_context['merchant_access']);
$can_create_microgift = $can_merchant_nav && mg_package_limit_allows_create($mg_package_context, 'max_microgifts', 0);
$can_create_campaigns = $can_merchant_nav && mg_package_limit_allows_create($mg_package_context, 'max_active_campaigns', 0);
$can_create_rewards = $can_merchant_nav && mg_package_limit_allows_create($mg_package_context, 'max_rewards', 0);
$can_manage_storefront = $can_merchant_nav;
$can_manage_locations = $can_merchant_nav && mg_package_limit_allows_create($mg_package_context, 'max_locations', 0);
$can_create_post = (bool) $user;

$account_profile_url = null;
$account_storefront_url = null;
if ($user) {
    try {
        $accountUserId = (int) ($user['id'] ?? 0);
        if ($accountUserId > 0) {
            $pdo = mg_db();
            $profileStmt = $pdo->prepare("SELECT slug,status,visibility FROM public_profiles WHERE user_id=? LIMIT 1");
            $profileStmt->execute([$accountUserId]);
            $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
            $profileSlug = trim((string) ($profile['slug'] ?? ''));
            $profileStatus = (string) ($profile['status'] ?? '');
            $profileVisibility = (string) ($profile['visibility'] ?? '');
            if ($profileSlug !== '' && $profileStatus !== 'hidden' && $profileStatus !== 'suspended') {
                $account_profile_url = '/profile.php?slug=' . rawurlencode($profileSlug);
                if ($profileStatus !== 'active' || !in_array($profileVisibility, ['public', 'unlisted'], true)) {
                    $account_profile_url .= '&preview=1';
                }
            }

            if ($can_merchant_nav) {
                $storeStmt = $pdo->prepare("SELECT slug,status FROM merchant_storefronts WHERE merchant_user_id=? LIMIT 1");
                $storeStmt->execute([$accountUserId]);
                $storefront = $storeStmt->fetch(PDO::FETCH_ASSOC);
                $storeSlug = trim((string) ($storefront['slug'] ?? ''));
                if ($storeSlug !== '' && (string) ($storefront['status'] ?? '') === 'published') {
                    $account_storefront_url = '/store.php?s=' . rawurlencode($storeSlug);
                }
            }
        }
    } catch (Throwable) {
        $account_profile_url = null;
        $account_storefront_url = null;
    }
}

$can_sales_crm = $user && (
    in_array('sales.leads.view_own', $user_permissions, true)
    || in_array('sales.leads.view_all', $user_permissions, true)
    || in_array('super_admin', $user_roles, true)
);

$admin_navigation_permissions = [
    'admin.users.view',
    'admin.users.manage',
    'admin.audit.view',
    'admin.health.view',
    'security.logs.view',
    'admin.security_logs.view',
    'admin.sessions.view',
    'operational.alerts.view',
    'demand.dashboard.view',
    'intelligence.dashboard.view',
    'merchant.payments.view',
    'subscriptions.admin',
    'microgift.operations.view',
    'tips.reverse',
    'admin.pwa_branding.view',
    'admin.pwa_branding.manage',
    'admin.pwa_notifications.test',
];
$can_admin_dashboard = $user && (
    in_array('super_admin', $user_roles, true)
    || count(array_intersect($admin_navigation_permissions, $user_permissions)) > 0
);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= mg_e(mg_csrf_token()) ?>">
<title><?= mg_e($page_title) ?></title>
<?php if ($page_description !== ''): ?><meta name="description" content="<?= mg_e($page_description) ?>"><?php endif; ?>
<?php if ($page_robots !== ''): ?><meta name="robots" content="<?= mg_e($page_robots) ?>"><?php endif; ?>
<?php if ($page_canonical_url !== ''): ?><link rel="canonical" href="<?= mg_e($page_canonical_url) ?>"><?php endif; ?>
<?php if ($page_og_title !== ''): ?><meta property="og:title" content="<?= mg_e($page_og_title) ?>"><?php endif; ?>
<?php if ($page_og_description !== ''): ?><meta property="og:description" content="<?= mg_e($page_og_description) ?>"><?php endif; ?>
<meta property="og:type" content="website">
<?php if ($page_canonical_url !== ''): ?><meta property="og:url" content="<?= mg_e($page_canonical_url) ?>"><?php endif; ?>
<?php if ($page_og_image !== ''): ?><meta property="og:image" content="<?= mg_e($page_og_image) ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= $page_og_image !== '' ? 'summary_large_image' : 'summary' ?>">
<?php if ($page_og_title !== ''): ?><meta name="twitter:title" content="<?= mg_e($page_og_title) ?>"><?php endif; ?>
<?php if ($page_og_description !== ''): ?><meta name="twitter:description" content="<?= mg_e($page_og_description) ?>"><?php endif; ?>
<?php if ($page_og_image !== ''): ?><meta name="twitter:image" content="<?= mg_e($page_og_image) ?>"><?php endif; ?>
<?php require __DIR__ . '/pwa-head.php'; ?>
<link rel="stylesheet" href="/assets/css/microgifter.css">
<?php if (!$is_profile_page): ?>
<link rel="stylesheet" href="/assets/css/public-program-pages.css">
<?php endif; ?>
<?php if ($is_app_page): ?>
<link rel="stylesheet" href="/assets/css/app-shell.css">
<link rel="stylesheet" href="/assets/css/compact-sidebars.css">
<link rel="stylesheet" href="/assets/css/create-menu.css">
<link rel="stylesheet" href="/assets/css/social-feed.css">
<link rel="stylesheet" href="/assets/css/social-feed-upload.css">
<link rel="stylesheet" href="/assets/css/post-composer-modal.css">
<?php endif; ?>
<?php if ($user && !$is_app_page): ?>
<link rel="stylesheet" href="/assets/css/create-menu.css">
<link rel="stylesheet" href="/assets/css/social-feed.css">
<link rel="stylesheet" href="/assets/css/social-feed-upload.css">
<link rel="stylesheet" href="/assets/css/post-composer-modal.css">
<?php endif; ?>
<?php if ($section_css): ?><link rel="stylesheet" href="<?= mg_e($section_css) ?>"><?php endif; ?>
<?php foreach ($page_styles as $style): ?><link rel="stylesheet" href="<?= mg_e($style) ?>"><?php endforeach; ?>
<?php if ($is_app_page): ?>
<link rel="stylesheet" href="/assets/css/mobile-app.css">
<link rel="stylesheet" href="/assets/css/app-header-sidebar.css">
<link rel="stylesheet" href="/assets/css/app-fixes.css">
<link rel="stylesheet" href="/assets/css/app-mobile-unified.css">
<link rel="stylesheet" href="/assets/css/mobile-sidebar-layering-fix.css">
<link rel="stylesheet" href="/assets/css/cart-drawer-layering-fix.css">
<?php endif; ?>
<style>
@media (max-width: 760px){
  body:not(.mg-app-page) .mg-unified-header .mg-account-menu,
  body:not(.mg-app-page) .mg-site-header .mg-account-menu,
  body:not(.mg-app-page) .mg-market-universal-header .mg-account-menu,
  body:not(.mg-app-page) .mg-public-header .mg-account-menu,
  body:not(.mg-app-page) .mg-app-header .mg-account-menu,
  body:not(.mg-app-page) [data-mg-auth-menu],
  body:not(.mg-app-page) [data-mg-auth-trigger]{display:none!important;visibility:hidden!important;pointer-events:none!important;}
  html body .mg-v4 .mg-v4-visual .mg-v10-desktop{position:relative!important;z-index:2!important;}
  html body .mg-v4 .mg-v4-visual .mg-v4-phone{top:auto!important;left:10px!important;right:auto!important;bottom:-24px!important;z-index:9!important;width:min(128px,34vw)!important;max-width:128px!important;transform:rotate(-2deg)!important;opacity:1!important;}
}
@media (max-width: 440px){
  html body .mg-v4 .mg-v4-visual .mg-v4-phone{top:auto!important;left:8px!important;bottom:-22px!important;width:min(118px,33vw)!important;max-width:118px!important;transform:rotate(-2deg)!important;}
}
</style>
</head>
<body
  class="mg-page mg-section-<?= mg_e($page_section) ?><?= $is_app_page ? ' mg-app-page' : '' ?><?= $page_body_class !== '' ? ' ' . mg_e($page_body_class) : '' ?>"
  data-authenticated="<?= $user ? 'true' : 'false' ?>"
  data-page-id="<?= mg_e((string) $page_manifest['id']) ?>"
  data-package-id="<?= mg_e((string) ($mg_package_context['package_id'] ?? 'free')) ?>"
>
<?php if ($is_app_page): ?>
  <?php require __DIR__ . '/header-components/app-header.php'; ?>
<?php else: ?>
  <?php require __DIR__ . '/header-components/public-header.php'; ?>
<?php endif; ?>
<script type="application/json" id="mg-page-manifest"><?= json_encode($page_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mg-page-onboarding"><?= json_encode($page_onboarding, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php if ($is_app_page): ?>
<div class="mg-mobile-sidebar-backdrop" data-mobile-sidebar-backdrop></div>
<?php endif; ?>
<main class="mg-main">