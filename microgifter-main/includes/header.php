<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/page.php';

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
$page_manifest = mg_page_manifest(array_replace_recursive($manifest_seed, $manifest_overrides));
$page_assets_resolved = mg_resolve_page_assets($page_manifest);
$page_title = $page_manifest['title'];
$page_section = $page_manifest['section'];
$header_mode = $page_manifest['header_mode'];
$header_controls = $page_manifest['header_controls'];
$page_styles = $page_assets_resolved['styles'];
$page_scripts = $page_assets_resolved['scripts'];
$page_body_class = trim((string) $page_manifest['body_class']);
$page_onboarding = is_array($page_manifest['onboarding'] ?? null) ? $page_manifest['onboarding'] : mg_onboarding_config($page_manifest['id']);
$agent_tab = $agent_tab ?? '';
$section_css = $section_css ?? null;
$is_app_page = in_array($header_mode, ['agent', 'account', 'crm', 'builder'], true);
$user = $is_app_page ? mg_require_auth() : mg_current_user();
if ($user && in_array((string) $page_manifest['id'], ['home', 'index'], true)) {
    header('Cache-Control: no-store, private');
    header('Location: /inbox.php', true, 302);
    exit;
}
if ($is_app_page) { header('Cache-Control: no-store, private'); header('Pragma: no-cache'); }
$display_name = $user ? mg_user_display_name() : 'Account';
$display_email = $user ? (string) ($user['email'] ?? '') : 'Guest';
$display_initial = strtoupper(substr($display_name !== '' ? $display_name : 'A', 0, 1));
$user_permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$user_roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$can_sales_crm = $user && (in_array('sales.leads.view_own', $user_permissions, true) || in_array('sales.leads.view_all', $user_permissions, true) || in_array('super_admin', $user_roles, true));
$admin_navigation_permissions = [
    'admin.users.view', 'admin.users.manage', 'admin.audit.view', 'admin.health.view',
    'security.logs.view', 'admin.security_logs.view', 'admin.sessions.view',
    'operational.alerts.view', 'demand.dashboard.view', 'intelligence.dashboard.view',
    'merchant.payments.view', 'subscriptions.admin', 'microgift.operations.view', 'tips.reverse',
];
$can_admin_dashboard = $user && (in_array('super_admin', $user_roles, true) || count(array_intersect($admin_navigation_permissions, $user_permissions)) > 0);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= mg_e(mg_csrf_token()) ?>">
<title><?= mg_e($page_title) ?></title>
<link rel="stylesheet" href="/assets/css/microgifter.css">
<?php if ($is_app_page): ?><link rel="stylesheet" href="/assets/css/app-shell.css"><?php endif; ?>
<?php if ($section_css): ?><link rel="stylesheet" href="<?= mg_e($section_css) ?>"><?php endif; ?>
<?php foreach ($page_styles as $style): ?><link rel="stylesheet" href="<?= mg_e($style) ?>"><?php endforeach; ?>
<?php if ($is_app_page): ?><link rel="stylesheet" href="/assets/css/mobile-app.css"><link rel="stylesheet" href="/assets/css/app-fixes.css"><?php endif; ?>
</head>
<body class="mg-page mg-section-<?= mg_e($page_section) ?><?= $is_app_page ? ' mg-app-page' : '' ?><?= $page_body_class !== '' ? ' ' . mg_e($page_body_class) : '' ?>" data-authenticated="<?= $user ? 'true' : 'false' ?>" data-page-id="<?= mg_e((string) $page_manifest['id']) ?>">
<?php require __DIR__ . '/header-components/' . ($is_app_page ? 'app-header.php' : 'public-header.php'); ?>
<script type="application/json" id="mg-page-manifest"><?= json_encode($page_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mg-page-onboarding"><?= json_encode($page_onboarding, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php if ($is_app_page): ?><div class="mg-mobile-sidebar-backdrop" data-mobile-sidebar-backdrop></div><?php endif; ?>
<main class="mg-main">
