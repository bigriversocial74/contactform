<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
if (!in_array('super_admin', $roles, true) && !in_array('merchant.reward_templates.view', $permissions, true)) {
    mg_fail('Permission denied.', 403);
}

$root = dirname(__DIR__, 2);
$checks = [];
$pass = static function (string $message, array $context = []) use (&$checks): void { $checks[] = ['status'=>'pass','message'=>$message,'context'=>$context]; };
$fail = static function (string $message, array $context = []) use (&$checks): void { $checks[] = ['status'=>'fail','message'=>$message,'context'=>$context]; };

$files = [
    'merchant-products.php',
    'merchant-reward-templates.php',
    'merchant-campaigns.php',
    'api/merchant/products.php',
    'api/merchant/reward-templates.php',
    'api/merchant/campaigns.php',
    'api/merchant/campaign-activity.php',
    'includes/merchant-products-view.php',
    'includes/merchant-reward-templates-view.php',
    'includes/merchant-campaigns-view.php',
    'includes/public-campaign-page.php',
    'assets/js/merchant-products.js',
    'assets/js/stage12-reward-templates.js',
    'assets/js/stage12-campaigns.js',
];
foreach ($files as $file) {
    is_file($root . '/' . $file) ? $pass('File exists: ' . $file) : $fail('Missing file: ' . $file);
}

$contracts = [
    'api/merchant/reward-templates.php' => [
        'mg_reward_template_validate_value',
        'mg_reward_template_source_product',
        'source_product_id',
        'Reward value type does not match the reward type',
        'value_label',
    ],
    'includes/merchant-reward-templates-view.php' => [
        'name="value_type"',
        'name="currency"',
        'name="value_percent"',
        'name="source_product_id"',
        'agent_add_to_wallet_allowed',
    ],
    'assets/js/stage12-reward-templates.js' => [
        'source_product_id',
        'value_label',
        'agent_add_to_wallet_allowed',
        'value_type',
    ],
    'includes/merchant-products-view.php' => [
        'Make reward template',
        'source_product_id',
        'merchant-reward-templates.php',
    ],
    'api/merchant/campaigns.php' => [
        "status = 'active'",
        'Reward template must be active',
        'landing_page_url',
        'mg_campaign_public_url',
    ],
    'assets/js/stage12-campaigns.js' => [
        'public_url',
        'landing_page_url',
        'reward_template_id',
    ],
    'includes/public-campaign-page.php' => [
        'reward_template_title',
        'value_percent',
        'value_amount_cents',
        'redemption_instructions',
    ],
];
foreach ($contracts as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) continue;
    $content = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        str_contains($content, $needle) ? $pass('Contract found in ' . $file . ': ' . $needle) : $fail('Missing contract in ' . $file . ': ' . $needle);
    }
}

try {
    $pdo = mg_db();
    $tables = ['catalog_products','catalog_product_versions','catalog_builder_drafts','reward_templates','campaigns','campaign_contacts','wallet_items','gift_merchant_eligibility'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        ((int)$stmt->fetchColumn() > 0) ? $pass('Table exists: ' . $table) : $fail('Missing table: ' . $table);
    }
    $columns = [
        'catalog_products' => ['public_id','merchant_user_id','product_type','status','current_version_id'],
        'catalog_product_versions' => ['public_id','product_id','title','description','unit_value_cents','currency','version_status'],
        'reward_templates' => ['public_id','merchant_user_id','title','reward_type','value_type','value_amount_cents','value_percent','currency','redemption_instructions','expiration_rule','quantity_limit','per_user_limit','agent_discoverable','agent_add_to_wallet_allowed','agent_gift_send_allowed','status'],
        'campaigns' => ['public_id','merchant_user_id','reward_template_id','campaign_type','title','public_slug','qr_code_token','status','quantity_limit','issued_count','per_user_limit'],
        'wallet_items' => ['public_id','reward_template_id','campaign_id','source_type','value_cents_snapshot','currency_snapshot','title_snapshot','expires_at'],
        'gift_merchant_eligibility' => ['gift_id','merchant_user_id','location_id'],
    ];
    foreach ($columns as $table => $cols) {
        foreach ($cols as $column) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $stmt->execute([$table, $column]);
            ((int)$stmt->fetchColumn() > 0) ? $pass('Column exists: ' . $table . '.' . $column) : $fail('Missing column: ' . $table . '.' . $column);
        }
    }
    foreach ([
        ['reward_templates','reward_type',['dollar_credit','free_item','discount','perk_upgrade','event_reward','custom']],
        ['reward_templates','value_type',['fixed_amount','percent','free_item','custom']],
        ['campaigns','campaign_type',['newsletter_signup','contest_giveaway','qr_reward_drop','referral_reward','birthday_vip','agent_offer']],
        ['wallet_items','source_type',['newsletter_signup','contest_entry','qr_scan','agent_discovery','api_issue']],
    ] as [$table,$column,$values]) {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
        $stmt->execute([$column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $type = (string)($row['Type'] ?? '');
        foreach ($values as $value) {
            str_contains($type, "'" . $value . "'") ? $pass('Enum supports ' . $table . '.' . $column . ': ' . $value) : $fail('Enum missing ' . $table . '.' . $column . ': ' . $value, ['type'=>$type]);
        }
    }
    foreach (['catalog.products.view','catalog.products.manage','catalog.products.publish','merchant.reward_templates.view','merchant.reward_templates.manage','merchant.campaigns.view','merchant.campaigns.manage'] as $permission) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=?');
        $stmt->execute([$permission]);
        ((int)$stmt->fetchColumn() > 0) ? $pass('Permission exists: ' . $permission) : $fail('Missing permission: ' . $permission);
    }
} catch (Throwable $error) {
    $fail('Database audit failed.', ['exception_class'=>$error::class, 'message'=>$error->getMessage()]);
}

$failures = array_values(array_filter($checks, static fn(array $check): bool => $check['status'] === 'fail'));
$score = count($checks) === 0 ? 0 : (int)round(((count($checks) - count($failures)) / count($checks)) * 10);
mg_ok([
    'score' => $score,
    'status' => count($failures) === 0 ? 'passed' : 'failed',
    'summary' => ['checks'=>count($checks), 'passed'=>count($checks)-count($failures), 'failed'=>count($failures)],
    'checks' => $checks,
], count($failures) === 0 ? 'Offer, reward template, and campaign attachment audit passed.' : 'Offer/reward/campaign audit has failures.');
