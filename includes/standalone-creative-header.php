<?php
declare(strict_types=1);

$page_title = trim((string) ($page_title ?? 'Microgifter')) ?: 'Microgifter';
$page_section = trim((string) ($page_section ?? 'agent')) ?: 'agent';
$page_body_class = trim((string) ($page_body_class ?? ''));
$page_styles = is_array($page_styles ?? null) ? array_values(array_unique($page_styles)) : [];
$page_scripts = is_array($page_scripts ?? null) ? array_values(array_unique($page_scripts)) : [];
$user = is_array($user ?? null) ? $user : mg_require_auth();
$mg_package_context = mg_user_package_context(null, $user);
$page_manifest = is_array($page_manifest ?? null) ? $page_manifest : [
    'id' => basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'creative-workspace.php'), '.php'),
    'title' => $page_title,
    'section' => $page_section,
    'body_class' => $page_body_class,
];

header('Cache-Control: no-store, private');
header('Pragma: no-cache');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= mg_e(mg_csrf_token()) ?>">
<title><?= mg_e($page_title) ?></title>
<?php require __DIR__ . '/pwa-head.php'; ?>
<link rel="stylesheet" href="/assets/css/microgifter.css">
<link rel="stylesheet" href="/assets/css/app-shell.css">
<link rel="stylesheet" href="/assets/css/compact-sidebars.css">
<link rel="stylesheet" href="/assets/css/mobile-app.css">
<link rel="stylesheet" href="/assets/css/app-mobile-unified.css">
<link rel="stylesheet" href="/assets/css/mobile-sidebar-layering-fix.css">
<?php foreach ($page_styles as $style): ?>
<link rel="stylesheet" href="<?= mg_e((string) $style) ?>">
<?php endforeach; ?>
</head>
<body
  class="mg-page mg-section-<?= mg_e($page_section) ?> mg-app-page<?= $page_body_class !== '' ? ' ' . mg_e($page_body_class) : '' ?>"
  data-authenticated="true"
  data-page-id="<?= mg_e((string) ($page_manifest['id'] ?? 'creative-workspace')) ?>"
  data-package-id="<?= mg_e((string) ($mg_package_context['package_id'] ?? 'free')) ?>"
>
<script type="application/json" id="mg-page-manifest"><?= json_encode($page_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<div class="mg-mobile-sidebar-backdrop" data-mobile-sidebar-backdrop></div>
<main class="mg-main">
