<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$required = [
  'api/account/wallet-items.php',
  'api/account/wallet-claim.php',
  'api/account/_wallet_pppm_authority.php',
  'api/rewards/_zero_value_bridge.php',
  'api/rewards/_wallet_pppm_bridge.php',
  'api/merchant/wallet-redeem.php',
  'api/merchant/campaign-contacts.php',
  'api/merchant/campaign-events.php',
  'api/merchant/campaign-public-tools.php',
  'api/public/campaigns/detail.php',
  'campaign.php',
  'wallet.php',
  'inbox.php',
  'merchant-wallet-redemptions.php',
  'includes/merchant-wallet-redemptions-view.php',
  'assets/js/public-campaign.js',
  'assets/js/stage12-redemptions.js',
  'assets/js/stage12-campaign-contacts.js',
  'assets/js/stage12-campaign-tools.js',
];
$ok = true;
foreach ($required as $path) { $ok = $ok && is_file($root . '/' . $path); }
$get = static function(string $path) use ($root): string { return is_file($root . '/' . $path) ? (string) file_get_contents($root . '/' . $path) : ''; };
$walletItems = $get('api/account/wallet-items.php');
$claim = $get('api/account/wallet-claim.php');
$authority = $get('api/account/_wallet_pppm_authority.php');
$bridgeEntry = $get('api/rewards/_zero_value_bridge.php');
$bridge = $get('api/rewards/_wallet_pppm_bridge.php');
$redeem = $get('api/merchant/wallet-redeem.php');
$contacts = $get('api/merchant/campaign-contacts.php');
$events = $get('api/merchant/campaign-events.php');
$tools = $get('api/merchant/campaign-public-tools.php');
$detail = $get('api/public/campaigns/detail.php');
$page = $get('campaign.php');
$walletPage = $get('wallet.php');
$merchantCompletePage = $get('merchant-wallet-redemptions.php');
$merchantCompleteView = $get('includes/merchant-wallet-redemptions-view.php');
$publicJs = $get('assets/js/public-campaign.js');
$completeJs = $get('assets/js/stage12-redemptions.js');
$contactJs = $get('assets/js/stage12-campaign-contacts.js');
$toolJs = $get('assets/js/stage12-campaign-tools.js');
$redeemStatusMarker = str_contains($redeem, "status = 'redeemed'") || str_contains($redeem, "status='redeemed'") || str_contains($redeem, "status = \\'redeemed\\'");
$checks = [
  'wallet_list_endpoint' => str_contains($walletItems, 'wallet_items') && str_contains($walletItems, 'campaign_contacts'),
  'wallet_hidden_route' => str_contains($walletPage, "header('Location: /inbox.php'") && !str_contains($walletPage, 'data-reward-wallet'),
  'wallet_claim_delegates_to_authority' => str_contains($claim, '_wallet_pppm_authority.php') && str_contains($claim, 'mg_wallet_claim_to_pppm'),
  'wallet_authority_projects_not_claims' => str_contains($authority, 'mg_zero_reward_issue_from_wallet') && str_contains($authority, "'destination'=>'inbox'") && !str_contains($authority, 'mg_microgift_integrity_claim'),
  'bridge_compatibility_entry' => str_contains($bridgeEntry, "_wallet_pppm_bridge.php"),
  'bridge_creates_pppm' => str_contains($bridge, 'pppm_issuance_requests') && str_contains($bridge, 'INSERT INTO pppm_items') && str_contains($bridge, "'earned_reward'") && str_contains($bridge, 'mg_pppm_record_event'),
  'bridge_creates_microgift' => str_contains($bridge, 'INSERT INTO microgift_instances') && str_contains($bridge, "'delivered'") && str_contains($bridge, 'pppm_item_id'),
  'bridge_projects_inbox' => str_contains($bridge, 'mg_action_center_sent') && str_contains($bridge, "'destination'=>'inbox'") && str_contains($bridge, 'recipient_inbox_item_id'),
  'claim_no_parallel_tokens' => !str_contains($claim, 'wallet_reward_claim_tokens') && !str_contains($authority, 'wallet_reward_claim_tokens'),
  'redeem_requires_merchant' => str_contains($redeem, 'merchant.campaigns.manage') && str_contains($redeem, 'mg_require_csrf_for_write'),
  'redeem_updates_status' => $redeemStatusMarker && str_contains($redeem, 'wallet_item.redeemed'),
  'merchant_complete_page' => str_contains($merchantCompletePage, 'includes/merchant-workspace.php') && str_contains($merchantCompletePage, '/assets/js/stage12-redemptions.js'),
  'merchant_complete_view' => str_contains($merchantCompleteView, 'data-stage12-redemptions') && str_contains($merchantCompleteView, 'data-redemption-form'),
  'merchant_complete_js' => str_contains($completeJs, '/api/merchant/wallet-redeem.php'),
  'contacts_endpoint' => str_contains($contacts, 'campaign_contacts') && str_contains($contacts, 'wallet_count'),
  'events_endpoint' => str_contains($events, 'campaign_events') && str_contains($events, 'event_type'),
  'contacts_js' => str_contains($contactJs, '/api/merchant/campaign-contacts.php') && str_contains($contactJs, '/api/merchant/campaign-winner.php'),
  'public_tools_endpoint' => str_contains($tools, 'public_url') && str_contains($tools, 'qr_url'),
  'public_tools_js' => str_contains($toolJs, '/api/merchant/campaign-public-tools.php') || str_contains($toolJs, '/api/merchant/campaign-detail.php'),
  'detail_endpoint' => str_contains($detail, 'submit_endpoint') && str_contains($detail, 'qr_reward_drop') && str_contains($detail, 'contest_giveaway'),
  'page_loads_js' => str_contains($page, '/assets/js/public-campaign.js') && str_contains($page, 'data-public-campaign'),
  'js_submits_public_form' => str_contains($publicJs, '/api/public/campaigns/detail.php') && str_contains($publicJs, 'Microgifter.post'),
];
foreach ($checks as $pass) { $ok = $ok && $pass; }
echo json_encode(['ok'=>$ok,'checks'=>$checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
