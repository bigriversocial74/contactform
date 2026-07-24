<?php
declare(strict_types=1);

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MYSQL_PORT') ?: 3306);
$database = getenv('MYSQL_DATABASE') ?: 'microgifter_phase6';
$user = getenv('MYSQL_USER') ?: 'root';
$password = getenv('MYSQL_PASSWORD') ?: 'root';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$tables = [
    'campaign_donation_rewards','campaign_donation_batches','campaign_donation_operations',
    'campaign_community_assignments','microgift_instances','pppm_items','wallet_items',
    'reward_templates','campaigns','user_roles','roles','public_profiles','users',
];
foreach ($tables as $table) {
    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
}

$ddl = [
    "CREATE TABLE users (
        id BIGINT UNSIGNED PRIMARY KEY,
        full_name VARCHAR(180) NOT NULL,
        display_name VARCHAR(180) NULL,
        status VARCHAR(40) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE public_profiles (
        user_id BIGINT UNSIGNED PRIMARY KEY,
        public_id CHAR(36) NOT NULL,
        display_name VARCHAR(180) NULL,
        slug VARCHAR(120) NULL,
        status VARCHAR(40) NOT NULL,
        visibility VARCHAR(40) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE roles (
        id BIGINT UNSIGNED PRIMARY KEY,
        slug VARCHAR(80) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE user_roles (
        user_id BIGINT UNSIGNED NOT NULL,
        role_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY(user_id,role_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE campaigns (
        id BIGINT UNSIGNED PRIMARY KEY,
        public_id CHAR(36) NOT NULL,
        public_slug VARCHAR(120) NULL,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(180) NOT NULL,
        campaign_type VARCHAR(80) NOT NULL,
        status VARCHAR(40) NOT NULL,
        starts_at DATETIME NULL,
        ends_at DATETIME NULL,
        quantity_limit INT UNSIGNED NULL,
        issued_count INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE reward_templates (
        id BIGINT UNSIGNED PRIMARY KEY,
        public_id CHAR(36) NOT NULL,
        title VARCHAR(180) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE campaign_community_assignments (
        id BIGINT UNSIGNED PRIMARY KEY,
        public_id CHAR(36) NOT NULL,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        campaign_id BIGINT UNSIGNED NOT NULL,
        community_user_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(40) NOT NULL,
        last_allocated_at DATETIME NULL,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE campaign_donation_operations (
        id BIGINT UNSIGNED PRIMARY KEY,
        public_id CHAR(36) NOT NULL,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        campaign_id BIGINT UNSIGNED NOT NULL,
        reward_template_id BIGINT UNSIGNED NOT NULL,
        operation_kind VARCHAR(40) NOT NULL,
        status VARCHAR(40) NOT NULL,
        completed_quantity INT UNSIGNED NOT NULL DEFAULT 0,
        total_stated_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
        currency CHAR(3) NOT NULL,
        created_at DATETIME NOT NULL,
        completed_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE campaign_donation_batches (
        id BIGINT UNSIGNED PRIMARY KEY,
        public_id CHAR(36) NOT NULL,
        assignment_id BIGINT UNSIGNED NOT NULL,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        campaign_id BIGINT UNSIGNED NOT NULL,
        reward_template_id BIGINT UNSIGNED NOT NULL,
        community_user_id BIGINT UNSIGNED NOT NULL,
        quantity INT UNSIGNED NOT NULL,
        recalled_quantity INT UNSIGNED NOT NULL DEFAULT 0,
        stated_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
        currency CHAR(3) NOT NULL,
        status VARCHAR(40) NOT NULL,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE wallet_items (
        id BIGINT UNSIGNED PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(40) NOT NULL,
        claimed_at DATETIME NULL,
        redeemed_at DATETIME NULL,
        expires_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE pppm_items (
        id BIGINT UNSIGNED PRIMARY KEY,
        owner_user_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(40) NOT NULL,
        expires_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE microgift_instances (
        id BIGINT UNSIGNED PRIMARY KEY,
        owner_user_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(40) NOT NULL,
        claimed_at DATETIME NULL,
        redeemed_at DATETIME NULL,
        expires_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE campaign_donation_rewards (
        id BIGINT UNSIGNED PRIMARY KEY,
        batch_id BIGINT UNSIGNED NOT NULL,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        campaign_id BIGINT UNSIGNED NOT NULL,
        original_community_user_id BIGINT UNSIGNED NOT NULL,
        wallet_item_id BIGINT UNSIGNED NOT NULL,
        pppm_item_id BIGINT UNSIGNED NOT NULL,
        microgift_instance_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(40) NOT NULL,
        value_cents_snapshot INT UNSIGNED NOT NULL,
        currency_snapshot CHAR(3) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
}

$pdo->exec("INSERT INTO users VALUES
    (1,'Merchant Owner','Merchant Owner','active'),
    (2,'Alice Community','Alice','active'),
    (3,'Bob Community','Bob','active'),
    (4,'Private Downstream Recipient','Private Downstream Recipient','active'),
    (9,'Other Merchant','Other Merchant','active'),
    (10,'Other Community','Other Community','active')");
$pdo->exec("INSERT INTO public_profiles VALUES
    (1,'00000000-0000-4000-8000-000000000001','Merchant Owner','merchant-owner','active','public'),
    (2,'00000000-0000-4000-8000-000000000002','Alice','alice-community','active','public'),
    (3,'00000000-0000-4000-8000-000000000003','Bob','bob-community','active','unlisted'),
    (4,'00000000-0000-4000-8000-000000000004','Private Downstream Recipient','private-downstream','active','private'),
    (9,'00000000-0000-4000-8000-000000000009','Other Merchant','other-merchant','active','public'),
    (10,'00000000-0000-4000-8000-000000000010','Other Community','other-community','active','public')");
$pdo->exec("INSERT INTO roles VALUES (1,'community'),(2,'customer')");
$pdo->exec("INSERT INTO user_roles VALUES (2,1),(2,2),(10,1)");

$pdo->exec("INSERT INTO campaigns VALUES
    (101,'10000000-0000-4000-8000-000000000101','support-local','1','Support Local Families','public_donation','active',NOW(),DATE_ADD(NOW(),INTERVAL 7 DAY),20,8,NOW()),
    (102,'10000000-0000-4000-8000-000000000102','artist-relief','1','Artist Relief','public_donation','active',NOW(),NULL,NULL,0,NOW()),
    (201,'20000000-0000-4000-8000-000000000201','other-campaign','9','Other Merchant Campaign','public_donation','active',NOW(),NULL,100,1,NOW())");
$pdo->exec("INSERT INTO reward_templates VALUES
    (501,'50000000-0000-4000-8000-000000000501','Community Meal'),
    (502,'50000000-0000-4000-8000-000000000502','Other Reward')");
$pdo->exec("INSERT INTO campaign_community_assignments VALUES
    (301,'30000000-0000-4000-8000-000000000301',1,101,2,'active',NOW(),NOW()),
    (302,'30000000-0000-4000-8000-000000000302',1,102,2,'active',NULL,NOW()),
    (303,'30000000-0000-4000-8000-000000000303',1,101,3,'active',NOW(),NOW()),
    (401,'40000000-0000-4000-8000-000000000401',9,201,10,'active',NOW(),NOW())");
$pdo->exec("INSERT INTO campaign_donation_operations VALUES
    (601,'60000000-0000-4000-8000-000000000601',1,101,501,'allocation','completed',8,8000,'USD',DATE_SUB(NOW(),INTERVAL 3 DAY),DATE_SUB(NOW(),INTERVAL 3 DAY)),
    (602,'60000000-0000-4000-8000-000000000602',1,101,501,'recall','completed',2,2000,'USD',DATE_SUB(NOW(),INTERVAL 2 DAY),DATE_SUB(NOW(),INTERVAL 2 DAY)),
    (603,'60000000-0000-4000-8000-000000000603',1,101,501,'allocation','failed',0,0,'USD',DATE_SUB(NOW(),INTERVAL 1 DAY),NULL),
    (604,'60000000-0000-4000-8000-000000000604',9,201,502,'allocation','completed',1,1000,'USD',NOW(),NOW())");
$pdo->exec("INSERT INTO campaign_donation_batches VALUES
    (701,'70000000-0000-4000-8000-000000000701',301,1,101,501,2,6,2,6000,'USD','partially_recalled',DATE_SUB(NOW(),INTERVAL 3 DAY)),
    (702,'70000000-0000-4000-8000-000000000702',303,1,101,501,3,2,0,2000,'USD','allocated',DATE_SUB(NOW(),INTERVAL 3 DAY)),
    (703,'70000000-0000-4000-8000-000000000703',401,9,201,502,10,1,0,1000,'USD','allocated',NOW())");

$walletRows = [
    [1001,2,'issued',null,null,null],
    [1002,4,'issued',null,null,null],
    [1003,2,'claimed','NOW()',null,null],
    [1004,2,'redeemed','DATE_SUB(NOW(),INTERVAL 2 DAY)','DATE_SUB(NOW(),INTERVAL 1 DAY)',null],
    [1005,2,'cancelled',null,null,null],
    [1006,2,'cancelled',null,null,null],
    [1007,3,'issued',null,null,null],
    [1008,3,'viewed',null,null,null],
    [1009,10,'issued',null,null,null],
];
foreach ($walletRows as [$id,$owner,$status,$claimed,$redeemed,$expires]) {
    $claimedSql = $claimed ?? 'NULL';
    $redeemedSql = $redeemed ?? 'NULL';
    $expiresSql = $expires ?? 'NULL';
    $pdo->exec("INSERT INTO wallet_items VALUES ({$id},{$owner}," . $pdo->quote($status) . ",{$claimedSql},{$redeemedSql},{$expiresSql})");
}

$pppmRows = [
    [1101,2,'assigned'],[1102,4,'sent'],[1103,2,'verified'],[1104,2,'redeemed'],
    [1105,2,'cancelled'],[1106,2,'cancelled'],[1107,3,'delivered'],[1108,3,'viewed'],[1109,10,'assigned'],
];
foreach ($pppmRows as [$id,$owner,$status]) {
    $pdo->exec("INSERT INTO pppm_items VALUES ({$id},{$owner}," . $pdo->quote($status) . ",NULL)");
}

$microgiftRows = [
    [1201,2,'issued',null,null],[1202,4,'delivered',null,null],[1203,2,'claimed','NOW()',null],
    [1204,2,'redeemed','DATE_SUB(NOW(),INTERVAL 2 DAY)','DATE_SUB(NOW(),INTERVAL 1 DAY)'],
    [1205,2,'revoked',null,null],[1206,2,'revoked',null,null],[1207,3,'issued',null,null],
    [1208,3,'delivered',null,null],[1209,10,'issued',null,null],
];
foreach ($microgiftRows as [$id,$owner,$status,$claimed,$redeemed]) {
    $claimedSql = $claimed ?? 'NULL';
    $redeemedSql = $redeemed ?? 'NULL';
    $pdo->exec("INSERT INTO microgift_instances VALUES ({$id},{$owner}," . $pdo->quote($status) . ",{$claimedSql},{$redeemedSql},NULL)");
}

$pdo->exec("INSERT INTO campaign_donation_rewards VALUES
    (801,701,1,101,2,1001,1101,1201,'allocated',1000,'USD'),
    (802,701,1,101,2,1002,1102,1202,'allocated',1000,'USD'),
    (803,701,1,101,2,1003,1103,1203,'allocated',1000,'USD'),
    (804,701,1,101,2,1004,1104,1204,'allocated',1000,'USD'),
    (805,701,1,101,2,1005,1105,1205,'recalled',1000,'USD'),
    (806,701,1,101,2,1006,1106,1206,'recalled',1000,'USD'),
    (807,702,1,101,3,1007,1107,1207,'allocated',1000,'USD'),
    (808,702,1,101,3,1008,1108,1208,'allocated',1000,'USD'),
    (809,703,9,201,10,1009,1109,1209,'allocated',1000,'USD')");

require_once dirname(__DIR__) . '/includes/merchant-community-support.php';

$dashboard = mg_community_support_dashboard($pdo, 1);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$summary = $dashboard['summary'];
$assert($summary['campaigns'] === 2, 'Merchant should see exactly two Public Donations campaigns.');
$assert($summary['community_accounts'] === 2, 'Alice must aggregate once across two campaigns.');
$assert($summary['gross_allocated'] === 8, 'Gross allocated should include all original reward rows.');
$assert($summary['recalled'] === 2, 'Recalled quantity should remain distinct.');
$assert($summary['net_allocated'] === 6, 'Net allocated should be gross minus recalled.');
$assert($summary['available'] === 4, 'Available is a current lifecycle metric and may overlap regifted.');
$assert($summary['regifted'] === 1, 'Regifted should be detected by canonical owner mismatch.');
$assert($summary['claimed'] === 1, 'Claimed should be cumulative.');
$assert($summary['redeemed'] === 1, 'Redeemed should be cumulative.');
$assert($summary['remaining_inventory'] === 12, 'Remaining limited campaign inventory should reconcile.');
$assert(($summary['stated_value_by_currency'][0]['net_cents'] ?? null) === 6000, 'Net stated value should exclude recalled units.');

$alice = array_values(array_filter($dashboard['community_accounts'], static fn(array $row): bool => $row['display_name'] === 'Alice'))[0] ?? null;
$assert(is_array($alice), 'Alice account aggregation should exist.');
$assert($alice['campaign_count'] === 2, 'Alice should appear once with two campaigns.');
$assert($alice['metrics']['gross_allocated'] === 6, 'Alice reward totals should reconcile.');

$bob = array_values(array_filter($dashboard['community_accounts'], static fn(array $row): bool => $row['display_name'] === 'Bob'))[0] ?? null;
$assert(is_array($bob) && $bob['has_community_role'] === false, 'Role removal must be visible without deleting assignment history.');

$assert(count($dashboard['donation_batches']) === 2, 'Cross-merchant batches must be impossible.');
$types = array_column($dashboard['attention'], 'type');
$assert(in_array('ending_soon', $types, true), 'Ending-soon attention should be generated.');
$assert(in_array('role_removed', $types, true), 'Role-removal attention should be generated.');
$assert(in_array('failed_operation', $types, true), 'Failed-operation attention should be generated.');
$assert($dashboard['privacy']['downstream_recipient_identity_exposed'] === false, 'Privacy contract must remain explicit.');

$json = json_encode($dashboard, JSON_THROW_ON_ERROR);
$assert(!str_contains($json, 'Private Downstream Recipient'), 'Downstream recipient identity must never be returned.');
$assert(!str_contains($json, 'Other Merchant Campaign'), 'Cross-merchant campaign data must never be returned.');
$assert(!str_contains($json, 'Other Community'), 'Cross-merchant Community account data must never be returned.');

echo json_encode([
    'ok' => true,
    'summary' => $summary,
    'attention_types' => $types,
    'privacy' => $dashboard['privacy'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
