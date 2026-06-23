<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$required = [
  'api/account/wallet-claim.php',
  'api/merchant/wallet-redeem.php',
  'api/public/campaigns/detail.php',
  'campaign.php',
  'assets/js/public-campaign.js',
];
$ok = true;
foreach ($required as $path) { $ok = $ok && is_file($root . '/' . $path); }
$get = static function(string $path) use ($root): string { return is_file($root . '/' . $path) ? (string) file_get_contents($root . '/' . $path) : ''; };
$claim = $get('api/account/wallet-claim.php');
$redeem = $get('api/merchant/wallet-redeem.php');
$detail = $get('api/public/campaigns/detail.php');
$page = $get('campaign.php');
$js = $get('assets/js/public-campaign.js');
$checks = [
  'claim_updates_status' => str_contains($claim, "status = \\'claimed\\'") && str_contains($claim, 'wallet_item.claimed'),
  'claim_ownership' => str_contains($claim, 'contact_email') && str_contains($claim, 'source_id'),
  'redeem_requires_merchant' => str_contains($redeem, 'merchant.campaigns.manage') && str_contains($redeem, 'mg_require_csrf_for_write'),
  'redeem_updates_status' => str_contains($redeem, "status = \\'redeemed\\'") && str_contains($redeem, 'wallet_item.redeemed'),
  'detail_endpoint' => str_contains($detail, 'submit_endpoint') && str_contains($detail, 'qr_reward_drop') && str_contains($detail, 'contest_giveaway'),
  'page_loads_js' => str_contains($page, '/assets/js/public-campaign.js') && str_contains($page, 'data-public-campaign'),
  'js_submits_public_form' => str_contains($js, '/api/public/campaigns/detail.php') && str_contains($js, 'Microgifter.post'),
];
foreach ($checks as $pass) { $ok = $ok && $pass; }
echo json_encode(['ok'=>$ok,'checks'=>$checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
