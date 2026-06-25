<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
if (!in_array('super_admin', $roles, true) && !in_array('merchant.campaigns.view', $permissions, true)) {
    mg_fail('Permission denied.', 403);
}

$root = dirname(__DIR__, 2);
$checks = [];
$pass = static function (string $message, array $context = []) use (&$checks): void { $checks[] = ['status'=>'pass','message'=>$message,'context'=>$context]; };
$fail = static function (string $message, array $context = []) use (&$checks): void { $checks[] = ['status'=>'fail','message'=>$message,'context'=>$context]; };

$files = [
    'api/merchant/campaigns.php',
    'api/merchant/campaign-activity.php',
    'api/merchant/merchant-crm.php',
    'api/public/campaigns/signup.php',
    'api/public/campaigns/contest-entry.php',
    'api/public/campaigns/qr-pickup.php',
    'api/public/campaigns/engage.php',
    'includes/public-campaign-page.php',
    'includes/merchant-crm.php',
    'newsletter-signup.php',
    'contest.php',
    'contest-entry.php',
    'qr-reward.php',
    'qr-drop.php',
    'referral-reward.php',
    'birthday-vip.php',
    'agent-offer.php',
    'wallet.php',
    'claim.php',
    'inbox.php',
    'sent.php',
    'claimed.php',
    'api/account/wallet-items.php',
    'api/account/wallet-claim.php',
    'api/account/action-center.php',
];
foreach ($files as $file) {
    is_file($root . '/' . $file) ? $pass('File exists: ' . $file) : $fail('Missing file: ' . $file);
}

$tableChecks = [
    'campaigns', 'reward_templates', 'campaign_contacts', 'campaign_events', 'wallet_items',
    'merchant_crm_contacts', 'merchant_crm_contact_events', 'merchant_crm_contact_campaigns',
    'microgift_instances', 'microgift_inbox_items', 'pppm_items'
];
try {
    $pdo = mg_db();
    foreach ($tableChecks as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        ((int)$stmt->fetchColumn() > 0) ? $pass('Table exists: ' . $table) : $fail('Missing table: ' . $table);
    }

    $campaignTypes = ['newsletter_signup','contest_giveaway','qr_reward_drop','referral_reward','birthday_vip','agent_offer'];
    $rewardTypes = ['dollar_credit','free_item','discount','perk_upgrade','event_reward','custom'];
    $sourceTypes = ['purchase','manual_send','newsletter_signup','contest_entry','contest_winner','qr_scan','agent_discovery','api_issue'];
    $contactSources = ['newsletter_signup','contest_entry','qr_scan','referral','birthday_vip','agent_discovery','manual','api_issue'];
    $folderTypes = ['inbox','sent','claimed'];

    foreach ([
        ['campaigns','campaign_type',$campaignTypes],
        ['reward_templates','reward_type',$rewardTypes],
        ['wallet_items','source_type',$sourceTypes],
        ['campaign_contacts','source',$contactSources],
        ['microgift_inbox_items','folder',$folderTypes],
    ] as [$table,$column,$values]) {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
        $stmt->execute([$column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $fail('Column missing: ' . $table . '.' . $column); continue; }
        $type = (string)($row['Type'] ?? '');
        foreach ($values as $value) {
            str_contains($type, "'" . $value . "'") ? $pass('Enum supports ' . $table . '.' . $column . ': ' . $value) : $fail('Enum missing ' . $table . '.' . $column . ': ' . $value, ['type'=>$type]);
        }
    }

    foreach ([
        'merchant.campaigns.view','merchant.campaigns.manage','merchant.reward_templates.view','merchant.reward_templates.manage'
    ] as $permission) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=?');
        $stmt->execute([$permission]);
        ((int)$stmt->fetchColumn() > 0) ? $pass('Permission exists: ' . $permission) : $fail('Missing permission: ' . $permission);
    }
} catch (Throwable $error) {
    $fail('Database audit failed.', ['exception_class'=>$error::class, 'message'=>$error->getMessage()]);
}

$failures = array_values(array_filter($checks, static fn(array $c): bool => $c['status'] === 'fail'));
$score = count($checks) === 0 ? 0 : (int)round(((count($checks) - count($failures)) / count($checks)) * 10);
mg_ok([
    'score' => $score,
    'status' => count($failures) === 0 ? 'passed' : 'failed',
    'summary' => ['checks'=>count($checks), 'passed'=>count($checks)-count($failures), 'failed'=>count($failures)],
    'checks' => $checks,
], count($failures) === 0 ? 'Promotional CRM campaign/wallet audit passed.' : 'Promotional CRM campaign/wallet audit has failures.');
