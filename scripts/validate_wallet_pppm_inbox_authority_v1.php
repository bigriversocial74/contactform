<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (string)file_get_contents($root . '/' . $path) : '';
$files = [
    'api/rewards/_zero_value_bridge.php',
    'api/rewards/_wallet_pppm_bridge.php',
    'api/account/_wallet_pppm_authority.php',
    'api/account/wallet-claim.php',
    'api/account/reward-wallet.php',
    'api/account/reward-wallet-action.php',
    'api/account/reward-wallet-support.php',
    'api/merchant/reward-wallet-redemption.php',
    'api/public/loyalty-quest/_participant.php',
    'wallet.php','wallet-classic.php','wallet-reward.php','merchant-reward-redemptions.php',
    'includes/header-templates/logged-in.php','includes/app-sidebar.php',
    'assets/js/my-loyalty-quests.js','loyalty-quest.php',
    'database/wallet_redemption_experience_v1.sql',
    'scripts/validate_stage12c_wallet_campaign_pages.php',
    '.github/workflows/wallet-pppm-inbox-authority-validation.yml',
];
$removed = [
    'api/account/_reward_wallet.php',
    'assets/js/reward-wallet-experience.js',
    'assets/css/reward-wallet-experience.css',
    'assets/js/merchant-wallet-redemptions.js',
    'assets/css/merchant-wallet-redemptions.css',
    'includes/account/merchant-wallet-redemptions-view.php',
];
$checks = [];
foreach ($files as $file) $checks[] = ['name'=>'file:' . $file,'ok'=>is_file($root . '/' . $file)];
foreach ($removed as $file) $checks[] = ['name'=>'removed:' . $file,'ok'=>!is_file($root . '/' . $file)];

$entry = $read('api/rewards/_zero_value_bridge.php');
$bridge = $read('api/rewards/_wallet_pppm_bridge.php');
$authority = $read('api/account/_wallet_pppm_authority.php');
$claim = $read('api/account/wallet-claim.php');
$rewardApi = $read('api/account/reward-wallet.php');
$actionApi = $read('api/account/reward-wallet-action.php');
$supportApi = $read('api/account/reward-wallet-support.php');
$merchantParallel = $read('api/merchant/reward-wallet-redemption.php');
$questReward = $read('api/public/loyalty-quest/_participant.php');
$walletPage = $read('wallet.php');
$classicPage = $read('wallet-classic.php');
$rewardPage = $read('wallet-reward.php');
$merchantPage = $read('merchant-reward-redemptions.php');
$header = $read('includes/header-templates/logged-in.php');
$sidebar = $read('includes/app-sidebar.php');
$myQuests = $read('assets/js/my-loyalty-quests.js');
$questPage = $read('loyalty-quest.php');
$migration = $read('database/wallet_redemption_experience_v1.sql');
$reconcile = $read('api/rewards/_account_link_reconciliation.php');

$checks[] = ['name'=>'thin compatibility bridge entry','ok'=>str_contains($entry,"_wallet_pppm_bridge.php")&&!str_contains($entry,'INSERT INTO gifts')];
$checks[] = ['name'=>'schema readiness is explicit','ok'=>str_contains($bridge,'mg_zero_reward_require_authority_schema')&&str_contains($bridge,"'pppm_items'")&&str_contains($bridge,"'microgift_instances'")&&str_contains($bridge,"'microgift_inbox_items'")];
$checks[] = ['name'=>'merchant-scoped PPPM source','ok'=>str_contains($bridge,"source_type='reward'")&&str_contains($bridge,"provider='wallet_staging'")&&str_contains($bridge,'owner_user_id=?')];
$checks[] = ['name'=>'idempotent source event','ok'=>str_contains($bridge,"'wallet.reward.'")&&str_contains($bridge,'external_event_id=?')&&str_contains($bridge,'FOR UPDATE')];
$checks[] = ['name'=>'earned reward PPPM issuance','ok'=>str_contains($bridge,'INSERT INTO pppm_issuance_requests')&&str_contains($bridge,"'reward','earned_reward'")&&str_contains($bridge,'INSERT INTO pppm_items')&&str_contains($bridge,"'delivered'")&&str_contains($bridge,'mg_pppm_record_event')];
$checks[] = ['name'=>'canonical Microgift creation','ok'=>str_contains($bridge,'mg_microgift_create_template')&&str_contains($bridge,'mg_microgift_create_version')&&str_contains($bridge,'mg_microgift_publish_version')&&str_contains($bridge,'INSERT INTO microgift_instances')&&str_contains($bridge,"'wallet_reward'")];
$checks[] = ['name'=>'PPPM linkage is mandatory','ok'=>str_contains($bridge,'pppm_item_id')&&str_contains($bridge,'UPDATE wallet_items SET user_id=?,pppm_item_id=?')&&str_contains($bridge,'microgift_instances SET pppm_item_id')];
$checks[] = ['name'=>'Action Center is final destination','ok'=>str_contains($bridge,'mg_action_center_sent')&&str_contains($bridge,'recipient_inbox_item_id')&&str_contains($bridge,"'destination'=>'inbox'")];
$checks[] = ['name'=>'no legacy gift-only bridge','ok'=>!str_contains($bridge,'INSERT INTO gifts')&&!str_contains($bridge,"'gift_id'")];
$checks[] = ['name'=>'unlinked account remains staged','ok'=>str_contains($bridge,"'pending_account_link'=>true")&&str_contains($reconcile,'mg_zero_reward_issue_from_wallet')];
$checks[] = ['name'=>'compatibility claim only projects','ok'=>str_contains($claim,'mg_wallet_claim_to_pppm')&&str_contains($authority,'mg_zero_reward_issue_from_wallet')&&str_contains($authority,'projected_to_inbox')&&!str_contains($authority,'mg_microgift_integrity_claim')];
$checks[] = ['name'=>'no parallel claim tokens','ok'=>!str_contains($actionApi,'wallet_reward_claim_tokens')&&!str_contains($merchantParallel,'wallet_reward_claim_tokens')&&str_contains($actionApi,'Standalone wallet claim codes are retired')&&str_contains($merchantParallel,'canonical Microgift/PPPM redemption console')];
$checks[] = ['name'=>'no parallel wallet support','ok'=>!str_contains($supportApi,'wallet_reward_support_cases')&&str_contains($supportApi,'PPPM actions')];
$checks[] = ['name'=>'read API does not expose staging library','ok'=>str_contains($rewardApi,"'deprecated_wallet_ui'=>true")&&str_contains($rewardApi,"'redirect_url'=>'/inbox.php'")&&!str_contains($rewardApi,'SELECT')];
$checks[] = ['name'=>'customer wallet routes are hidden','ok'=>str_contains($walletPage,"header('Location: /inbox.php'")&&str_contains($classicPage,"header('Location: /inbox.php'")&&str_contains($rewardPage,"header('Location: /inbox.php'")];
$checks[] = ['name'=>'merchant compatibility route is canonical','ok'=>str_contains($merchantPage,"header('Location: /merchant-wallet-redemptions.php'")];
$checks[] = ['name'=>'wallet removed from navigation','ok'=>!str_contains($header,'>My Wallet<')&&!str_contains($sidebar,"'label' => 'Wallet'")&&str_contains($sidebar,"'href' => '/inbox.php'")];
$checks[] = ['name'=>'quest UX points to Inbox','ok'=>str_contains($myQuests,'Open reward in Inbox')&&str_contains($myQuests,'/inbox.php')&&str_contains($questPage,'Delivered to your Microgifter Inbox')&&str_contains($questPage,'Open Inbox')];
$checks[] = ['name'=>'quest reward uses shared bridge','ok'=>str_contains($questReward,'mg_zero_reward_issue_from_wallet')&&str_contains($questReward,"'pppm_bridge'=>\$bridge")];
$checks[] = ['name'=>'obsolete migration is no-op','ok'=>str_contains($migration,'Do not import this file')&&!str_contains($migration,'CREATE TABLE')&&!str_contains($migration,'ALTER TABLE')];
$checks[] = ['name'=>'Microgift insert maps PPPM correctly','ok'=>str_contains($bridge,"recipient_reference,commerce_order_item_id,pppm_item_id,legacy_gift_id")&&str_contains($bridge,"?,?,?,?,?,?,NULL,?,NULL")];

$failed = array_values(array_filter($checks, static fn(array $check): bool => !$check['ok']));
$score = max(0, 10 - count($failed) * 0.4);
echo json_encode(['ok'=>$failed===[],'score'=>number_format($score,1).'/10','checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed===[]?0:1);
