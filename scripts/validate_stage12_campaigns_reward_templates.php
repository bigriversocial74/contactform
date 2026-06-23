<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'database/stage_12_campaigns_reward_templates.sql',
    'docs/stage-12-campaigns-reward-templates-build-plan.md',
    'merchant-campaigns.php',
    'merchant-reward-templates.php',
    'includes/merchant-campaigns-view.php',
    'includes/merchant-reward-templates-view.php',
    'includes/merchant-workspace.php',
    'includes/merchant-view.php',
    'includes/header-components/app-header.php',
    'api/merchant/reward-templates.php',
    'api/merchant/campaigns.php',
    'api/public/campaigns/signup.php',
    'api/public/campaigns/qr-pickup.php',
];

$ok = true;
$files = [];
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $ok = $ok && $exists;
    $files[] = ['path' => $path, 'exists' => $exists];
}

$sql = is_file($root . '/database/stage_12_campaigns_reward_templates.sql') ? (string)file_get_contents($root . '/database/stage_12_campaigns_reward_templates.sql') : '';
$manifest = is_file($root . '/config/migrations.php') ? (string)file_get_contents($root . '/config/migrations.php') : '';
$header = is_file($root . '/includes/header-components/app-header.php') ? (string)file_get_contents($root . '/includes/header-components/app-header.php') : '';
$nav = is_file($root . '/includes/merchant-workspace.php') ? (string)file_get_contents($root . '/includes/merchant-workspace.php') : '';
$view = is_file($root . '/includes/merchant-view.php') ? (string)file_get_contents($root . '/includes/merchant-view.php') : '';
$campaignView = is_file($root . '/includes/merchant-campaigns-view.php') ? (string)file_get_contents($root . '/includes/merchant-campaigns-view.php') : '';
$templateView = is_file($root . '/includes/merchant-reward-templates-view.php') ? (string)file_get_contents($root . '/includes/merchant-reward-templates-view.php') : '';
$templateApi = is_file($root . '/api/merchant/reward-templates.php') ? (string)file_get_contents($root . '/api/merchant/reward-templates.php') : '';
$campaignApi = is_file($root . '/api/merchant/campaigns.php') ? (string)file_get_contents($root . '/api/merchant/campaigns.php') : '';
$signupApi = is_file($root . '/api/public/campaigns/signup.php') ? (string)file_get_contents($root . '/api/public/campaigns/signup.php') : '';
$qrApi = is_file($root . '/api/public/campaigns/qr-pickup.php') ? (string)file_get_contents($root . '/api/public/campaigns/qr-pickup.php') : '';

$hasTables = true;
foreach (['reward_templates','campaigns','campaign_contacts','wallet_items','campaign_events'] as $table) {
    $hasTables = $hasTables && str_contains($sql, 'CREATE TABLE IF NOT EXISTS ' . $table);
}
$hasAgentFields = str_contains($sql, 'agent_discoverable') && str_contains($sql, 'agent_add_to_wallet_allowed') && str_contains($sql, 'agent_gift_send_allowed');
$hasSourceTracking = str_contains($sql, "'newsletter_signup'") && str_contains($sql, "'contest_entry'") && str_contains($sql, "'qr_scan'") && str_contains($sql, "'agent_discovery'");
$hasManifest = str_contains($manifest, 'stage_12_campaigns_reward_templates.sql');
$hasCreateMenu = str_contains($header, 'data-create-menu-option="campaign"') && str_contains($header, 'data-create-menu-option="agent_offer"') && str_contains($header, '/merchant-campaigns.php') && str_contains($header, '/merchant-reward-templates.php');
$hasNav = str_contains($nav, "'campaigns'=>") && str_contains($nav, "'reward_templates'=>");
$hasViewRoutes = str_contains($view, 'merchant-campaigns-view.php') && str_contains($view, 'merchant-reward-templates-view.php');
$hasCampaignShell = str_contains($campaignView, 'Newsletter Signup') && str_contains($campaignView, 'Contest / Giveaway') && str_contains($campaignView, 'QR Reward Drop');
$hasTemplateShell = str_contains($templateView, 'Reward type') && str_contains($templateView, 'agent_discoverable') && str_contains($templateView, 'Redemption instructions');
$hasTemplateApi = str_contains($templateApi, 'merchant.reward_templates.view') && str_contains($templateApi, 'merchant.reward_templates.manage') && str_contains($templateApi, 'INSERT INTO reward_templates') && str_contains($templateApi, 'UPDATE reward_templates') && str_contains($templateApi, 'mg_require_csrf_for_write');
$hasTemplateApiOutput = str_contains($templateApi, "'templates'") && str_contains($templateApi, "'template'") && str_contains($templateApi, "'schema_ready'");
$hasCampaignApi = str_contains($campaignApi, 'merchant.campaigns.view') && str_contains($campaignApi, 'merchant.campaigns.manage') && str_contains($campaignApi, 'INSERT INTO campaigns') && str_contains($campaignApi, 'UPDATE campaigns') && str_contains($campaignApi, 'mg_require_csrf_for_write');
$hasCampaignApiOutput = str_contains($campaignApi, "'campaigns'") && str_contains($campaignApi, "'campaign'") && str_contains($campaignApi, "'schema_ready'");
$hasSignupApi = str_contains($signupApi, 'campaign_contacts') && str_contains($signupApi, 'wallet_items') && str_contains($signupApi, 'campaign_events') && str_contains($signupApi, 'wallet_item.issued') && str_contains($signupApi, 'form.submitted');
$hasSignupLimits = str_contains($signupApi, 'quantity_limit') && str_contains($signupApi, 'issued_count') && str_contains($signupApi, 'Reward template limit has been reached');
$hasQrApi = str_contains($qrApi, 'qr_reward_drop') && str_contains($qrApi, 'qr.scanned') && str_contains($qrApi, 'wallet_item.issued') && str_contains($qrApi, 'qr_code_token');
$hasQrLimits = str_contains($qrApi, 'QR reward drop limit has been reached') && str_contains($qrApi, 'Reward template limit has been reached');

$ok = $ok && $hasTables && $hasAgentFields && $hasSourceTracking && $hasManifest && $hasCreateMenu && $hasNav && $hasViewRoutes && $hasCampaignShell && $hasTemplateShell && $hasTemplateApi && $hasTemplateApiOutput && $hasCampaignApi && $hasCampaignApiOutput && $hasSignupApi && $hasSignupLimits && $hasQrApi && $hasQrLimits;

echo json_encode([
    'ok' => $ok,
    'files' => $files,
    'has_tables' => $hasTables,
    'has_agent_fields' => $hasAgentFields,
    'has_source_tracking' => $hasSourceTracking,
    'has_manifest' => $hasManifest,
    'has_create_menu' => $hasCreateMenu,
    'has_nav' => $hasNav,
    'has_view_routes' => $hasViewRoutes,
    'has_campaign_shell' => $hasCampaignShell,
    'has_template_shell' => $hasTemplateShell,
    'has_template_api' => $hasTemplateApi,
    'has_template_api_output' => $hasTemplateApiOutput,
    'has_campaign_api' => $hasCampaignApi,
    'has_campaign_api_output' => $hasCampaignApiOutput,
    'has_signup_api' => $hasSignupApi,
    'has_signup_limits' => $hasSignupLimits,
    'has_qr_api' => $hasQrApi,
    'has_qr_limits' => $hasQrLimits,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($ok ? 0 : 1);
