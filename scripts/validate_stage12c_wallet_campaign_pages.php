<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$required = [
  'api/account/wallet-items.php',
  'api/account/wallet-claim.php',
  'api/merchant/wallet-redeem.php',
  'api/merchant/campaign-contacts.php',
  'api/merchant/campaign-events.php',
  'api/merchant/campaign-public-tools.php',
  'api/public/campaigns/detail.php',
  'campaign.php',
  'wallet.php',
  'merchant-wallet-redemptions.php',
  'assets/js/public-campaign.js',
  'assets/js/stage12-wallet.js',
  'assets/js/stage12-redemptions.js',
  'assets/js/stage12-campaign-contacts.js',
  'assets/js/stage12-campaign-tools.js',
];
$ok = true;
foreach ($required as $path) { $ok = $ok && is_file($root . '/' . $path); }
$get = static function(string $path) use ($root): string { return is_file($root . '/' . $path) ? (string) file_get_contents($root . '/' . $path) : ''; };
$walletItems = $get('api/account/wallet-items.php');
$claim = $get('api/account/wallet-claim.php');
$redeem = $get('api/merchant/wallet-redeem.php');
$contacts = $get('api/merchant/campaign-contacts.php');
$events = $get('api/merchant/campaign-events.php');
$tools = $get('api/merchant/campaign-public-tools.php');
$detail = $get('api/public/campaigns/detail.php');
$page = $get('campaign.php');
$walletPage = $get('wallet.php');
$merchantCompletePage = $get('merchant-wallet-redemptions.php');
$publicJs = $get('assets/js/public-campaign.js');
$walletJs = $get('assets/js/stage12-wallet.js');
$completeJs = $get('assets/js/stage12-redemptions.js');
$contactJs = $get('assets/js/stage12-campaign-contacts.js');
$toolJs = $get('assets/js/stage12-campaign-tools.js');
$checks = [
  'wallet_list_endpoint' => str_contains($walletItems, 'wallet_items') && str_contains($walletItems, 'campaign_contacts'),
  'wallet_page' => str_contains($walletPage, 'data-stage12-wallet') && str_contains($walletPage, '/assets/js/stage12-wallet.js'),
  'wallet_js_claims' => str_contains($walletJs, '/api/account/wallet-items.php') && str_contains($walletJs, '/api/account/wallet-claim.php'),
  'claim_updates_status' => str_contains($claim, "status = \'claimed\'") && str_contains($claim, 'wallet_item.claimed'),
  'claim_ownership' => str_contains($claim, 'contact_email') && str_contains($claim, 'source_id'),
  'redeem_requires_merchant' => str_contains($redeem, 'merchant.campaigns.manage') && str_contains($redeem, 'mg_require_csrf_for_write'),
  'redeem_updates_status' => str_contains($redeem, "status = \'redeemed\'") && str_contains($redeem, 'wallet_item.redeemed'),
  'merchant_complete_page' => str_contains($merchantCompletePage, 'data-stage12-redemptions') && str_contains($merchantCompletePage, '/assets/js/stage12-redemptions.js'),
  'merchant_complete_js' => str_contains($completeJs, '/api/merchant/wallet-redeem.php'),
  'contacts_endpoint' => str_contains($contacts, 'campaign_contacts') && str_contains($contacts, 'wallet_count'),
  'events_endpoint' => str_contains($events, 'campaign_events') && str_contains($events, 'event_type'),
  'contacts_js' => str_contains($contactJs, '/api/merchant/campaign-contacts.php') && str_contains($contactJs, '/api/merchant/campaign-winner.php'),
  'public_tools_endpoint' => str_contains($tools, 'public_url') && str_contains($tools, 'qr_url'),
  'public_tools_js' => str_contains($toolJs, '/api/merchant/campaign-public-tools.php'),
  'detail_endpoint' => str_contains($detail, 'submit_endpoint') && str_contains($detail, 'qr_reward_drop') && str_contains($detail, 'contest_giveaway'),
  'page_loads_js' => str_contains($page, '/assets/js/public-campaign.js') && str_contains($page, 'data-public-campaign'),
  'js_submits_public_form' => str_contains($publicJs, '/api/public/campaigns/detail.php') && str_contains($publicJs, 'Microgifter.post'),
];
foreach ($checks as $pass) { $ok = $ok && $pass; }
echo json_encode(['ok'=>$ok,'checks'=>$checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
