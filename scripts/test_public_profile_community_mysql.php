<?php
declare(strict_types=1);

putenv('MG_PUBLIC_DONATIONS_FEATURE_STATE=enabled');

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MYSQL_PORT') ?: 3306);
$database = getenv('MYSQL_DATABASE') ?: 'microgifter_phase8';
$user = getenv('MYSQL_USER') ?: 'root';
$password = getenv('MYSQL_PASSWORD') ?: 'root';
$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$tables = [
    'campaign_donation_rewards','campaign_community_assignments','microgift_instances','pppm_items','wallet_items',
    'campaigns','reward_templates','public_profiles','users',
];
foreach ($tables as $table) $pdo->exec("DROP TABLE IF EXISTS `{$table}`");

$ddl = [
    "CREATE TABLE users (id BIGINT UNSIGNED PRIMARY KEY,display_name VARCHAR(180) NULL,full_name VARCHAR(180) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE public_profiles (user_id BIGINT UNSIGNED PRIMARY KEY,slug VARCHAR(120) NULL,display_name VARCHAR(180) NULL,headline VARCHAR(240) NULL,avatar_url VARCHAR(900) NULL,cover_url VARCHAR(900) NULL,location_label VARCHAR(180) NULL,status VARCHAR(40) NOT NULL,visibility VARCHAR(40) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE reward_templates (id BIGINT UNSIGNED PRIMARY KEY,title VARCHAR(180) NOT NULL,description VARCHAR(1000) NULL,metadata_json JSON NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE campaigns (id BIGINT UNSIGNED PRIMARY KEY,public_id CHAR(36) NOT NULL,public_slug VARCHAR(190) NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,reward_template_id BIGINT UNSIGNED NULL,campaign_type VARCHAR(80) NOT NULL,title VARCHAR(180) NOT NULL,description VARCHAR(1000) NULL,status VARCHAR(40) NOT NULL,starts_at DATETIME NULL,ends_at DATETIME NULL,rules_json JSON NULL,metadata_json JSON NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE campaign_community_assignments (id BIGINT UNSIGNED PRIMARY KEY,merchant_user_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NOT NULL,community_user_id BIGINT UNSIGNED NOT NULL,status VARCHAR(40) NOT NULL,public_display_status VARCHAR(40) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE wallet_items (id BIGINT UNSIGNED PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,status VARCHAR(40) NOT NULL,claimed_at DATETIME NULL,redeemed_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE pppm_items (id BIGINT UNSIGNED PRIMARY KEY,owner_user_id BIGINT UNSIGNED NOT NULL,status VARCHAR(40) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE microgift_instances (id BIGINT UNSIGNED PRIMARY KEY,owner_user_id BIGINT UNSIGNED NOT NULL,status VARCHAR(40) NOT NULL,claimed_at DATETIME NULL,redeemed_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE campaign_donation_rewards (id BIGINT UNSIGNED PRIMARY KEY,merchant_user_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NOT NULL,original_community_user_id BIGINT UNSIGNED NOT NULL,wallet_item_id BIGINT UNSIGNED NOT NULL,pppm_item_id BIGINT UNSIGNED NOT NULL,microgift_instance_id BIGINT UNSIGNED NOT NULL,status VARCHAR(40) NOT NULL,value_cents_snapshot INT UNSIGNED NOT NULL,currency_snapshot CHAR(3) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($ddl as $sql) $pdo->exec($sql);

$pdo->exec("INSERT INTO users VALUES
 (1,'Copper Table','Copper Table Hospitality'),(2,'Alice Community','Alice Community'),(3,'Bob Community','Bob Community'),
 (4,'Carol Private','Carol Private'),(5,'Dana Pending','Dana Pending'),(6,'Secret Final Recipient','Secret Final Recipient'),
 (9,'Other Merchant','Other Merchant'),(10,'Other Community','Other Community')");
$pdo->exec("INSERT INTO public_profiles VALUES
 (1,'copper-table','Copper Table','Local meals and experiences','/uploads/merchant.jpg','/uploads/merchant-cover.jpg','Phoenix, Arizona','active','public'),
 (2,'alice-community','Alice Community','Neighborhood organizer','/uploads/alice.jpg','/uploads/alice-cover.jpg','Phoenix, Arizona','active','public'),
 (3,'bob-community','Bob Community','Community artist','/uploads/bob.jpg','/uploads/bob-cover.jpg','Tempe, Arizona','active','unlisted'),
 (4,'carol-private','Carol Private','Private profile',NULL,NULL,'Mesa, Arizona','active','private'),
 (5,'dana-pending','Dana Pending','Pending consent',NULL,NULL,'Glendale, Arizona','active','public'),
 (6,'secret-recipient','Secret Final Recipient','Private downstream recipient',NULL,NULL,'Phoenix, Arizona','active','private'),
 (9,'other-merchant','Other Merchant','Other business',NULL,NULL,'Other City','active','public'),
 (10,'other-community','Other Community','Other community',NULL,NULL,'Other City','active','public')");
$pdo->exec("INSERT INTO reward_templates VALUES
 (501,'Community Meal','Merchant-funded meal reward.',JSON_OBJECT('reward_image_url','/uploads/reward.jpg')),
 (502,'Other Reward','Other reward.',NULL)");
$pdo->exec("INSERT INTO campaigns VALUES
 (101,'10000000-0000-4000-8000-000000000101','community-meals',1,501,'public_donation','Community Meals','Active merchant-funded meal support.','active',DATE_SUB(NOW(),INTERVAL 2 DAY),DATE_ADD(NOW(),INTERVAL 30 DAY),JSON_OBJECT('campaign_image_url','/uploads/active.jpg'),NULL,NOW()),
 (102,'10000000-0000-4000-8000-000000000102','completed-meals',1,501,'public_donation','Completed Meals','Completed campaign history.','ended',DATE_SUB(NOW(),INTERVAL 60 DAY),DATE_SUB(NOW(),INTERVAL 30 DAY),JSON_OBJECT('campaign_image_url','/uploads/completed.jpg'),NULL,NOW()),
 (103,'10000000-0000-4000-8000-000000000103','paused-meals',1,501,'public_donation','Paused Meals','Paused campaign history.','paused',DATE_SUB(NOW(),INTERVAL 5 DAY),DATE_ADD(NOW(),INTERVAL 20 DAY),NULL,NULL,NOW()),
 (104,'10000000-0000-4000-8000-000000000104','newsletter',1,NULL,'newsletter_signup','Newsletter','Standard campaign.','active',NULL,NULL,NULL,NULL,NOW()),
 (201,'20000000-0000-4000-8000-000000000201','other-support',9,502,'public_donation','Other Support','Other merchant campaign.','active',NULL,NULL,NULL,NULL,NOW())");
$pdo->exec("INSERT INTO campaign_community_assignments VALUES
 (301,1,101,2,'active','approved'),(302,1,102,2,'removed','approved'),(303,1,101,3,'active','approved'),
 (304,1,103,3,'paused','approved'),(305,1,101,4,'active','approved'),(306,1,101,5,'active','pending'),
 (401,9,201,10,'active','approved')");

$wallet = [
 [1001,2,'issued',null,null],[1002,6,'issued',null,null],[1003,3,'claimed','NOW()',null],
 [1004,4,'redeemed','DATE_SUB(NOW(),INTERVAL 2 DAY)','DATE_SUB(NOW(),INTERVAL 1 DAY)'],
 [1005,5,'issued',null,null],[1006,2,'cancelled',null,null],
 [1007,2,'redeemed','DATE_SUB(NOW(),INTERVAL 20 DAY)','DATE_SUB(NOW(),INTERVAL 19 DAY)'],
 [1008,10,'issued',null,null],
];
foreach ($wallet as [$id,$owner,$status,$claimed,$redeemed]) {
    $pdo->exec("INSERT INTO wallet_items VALUES ({$id},{$owner},".$pdo->quote($status).",".($claimed??'NULL').",".($redeemed??'NULL').")");
}
$pppm = [[1101,2,'assigned'],[1102,6,'sent'],[1103,3,'verified'],[1104,4,'redeemed'],[1105,5,'assigned'],[1106,2,'cancelled'],[1107,2,'redeemed'],[1108,10,'assigned']];
foreach ($pppm as [$id,$owner,$status]) $pdo->exec("INSERT INTO pppm_items VALUES ({$id},{$owner},".$pdo->quote($status).")");
$microgift = [
 [1201,2,'issued',null,null],[1202,6,'delivered',null,null],[1203,3,'claimed','NOW()',null],
 [1204,4,'redeemed','DATE_SUB(NOW(),INTERVAL 2 DAY)','DATE_SUB(NOW(),INTERVAL 1 DAY)'],
 [1205,5,'issued',null,null],[1206,2,'revoked',null,null],
 [1207,2,'redeemed','DATE_SUB(NOW(),INTERVAL 20 DAY)','DATE_SUB(NOW(),INTERVAL 19 DAY)'],[1208,10,'issued',null,null],
];
foreach ($microgift as [$id,$owner,$status,$claimed,$redeemed]) {
    $pdo->exec("INSERT INTO microgift_instances VALUES ({$id},{$owner},".$pdo->quote($status).",".($claimed??'NULL').",".($redeemed??'NULL').")");
}
$pdo->exec("INSERT INTO campaign_donation_rewards VALUES
 (801,1,101,2,1001,1101,1201,'allocated',1000,'USD'),
 (802,1,101,2,1002,1102,1202,'allocated',1000,'USD'),
 (803,1,101,3,1003,1103,1203,'allocated',1000,'USD'),
 (804,1,101,4,1004,1104,1204,'allocated',1000,'USD'),
 (805,1,101,5,1005,1105,1205,'allocated',1000,'USD'),
 (806,1,101,2,1006,1106,1206,'recalled',1000,'USD'),
 (807,1,102,2,1007,1107,1207,'allocated',1000,'USD'),
 (901,9,201,10,1008,1108,1208,'allocated',1000,'USD')");

require_once dirname(__DIR__) . '/includes/public-profile-community.php';
$assert = static function(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR,"FAIL: {$message}\n"); exit(1); }
};

$data = mg_public_profile_community_build($pdo, 'copper-table');
$summary = $data['summary'];
$assert($data['schema_ready'] === true && $data['has_data'] === true, 'Community payload should be available.');
$assert($summary['campaigns'] === 3, 'Active, paused, and completed history should be included.');
$assert($summary['active_campaigns'] === 1, 'Exactly one campaign should be currently active.');
$assert($summary['paused_campaigns'] === 1, 'Exactly one campaign should be paused.');
$assert($summary['completed_campaigns'] === 1, 'Exactly one campaign should be completed.');
$assert($summary['supported_accounts'] === 4, 'Supported accounts should reconcile across assignments and reward history.');
$assert($summary['publicly_featured_accounts'] === 2, 'Only approved public or unlisted profiles should render.');
$assert($summary['anonymous_accounts'] === 2, 'Private and pending profiles should remain aggregate-only.');
$assert($summary['gross_allocated'] === 7 && $summary['recalled'] === 1 && $summary['net_allocated'] === 6, 'Gross, recalled, and net totals should reconcile.');
$assert($summary['regifted'] === 1, 'Regifted lifecycle should be cumulative.');
$assert($summary['claimed'] === 3, 'Claimed should include claimed and redeemed rewards.');
$assert($summary['redeemed'] === 2, 'Redeemed lifecycle should reconcile.');
$assert(($summary['stated_value_by_currency'][0]['net_cents'] ?? null) === 6000, 'Net stated promotional value should reconcile.');
$assert(count($data['campaigns']) === 3, 'Campaign history cards should include active, paused, and completed.');
$assert(count($data['active_campaigns']) === 1 && $data['active_campaigns'][0]['id'] === '10000000-0000-4000-8000-000000000101', 'Only the current active campaign should enrich Active Campaigns.');
$assert(array_column($data['community_accounts'],'display_name') === ['Alice Community','Bob Community'], 'Eligible accounts should render once in support order.');
$assert($data['community_accounts'][0]['campaign_count'] === 2, 'Alice should aggregate once across active and completed campaigns.');
$assert($data['community_accounts'][1]['campaign_count'] === 2, 'Bob should aggregate once across active and paused campaigns.');

$items = [
 ['id'=>'10000000-0000-4000-8000-000000000101','campaign_type'=>'public_donation','title'=>'Community Meals'],
 ['id'=>'10000000-0000-4000-8000-000000000103','campaign_type'=>'public_donation','title'=>'Paused Meals'],
 ['id'=>'10000000-0000-4000-8000-000000000104','campaign_type'=>'newsletter_signup','title'=>'Newsletter','url'=>'/campaign.php?id=x'],
];
$enriched = mg_public_profile_community_enrich_campaign_items($items,$data);
$assert(count($enriched) === 2, 'Paused Public Donations must not remain in Active Campaigns while standard campaigns remain.');
$assert($enriched[0]['url'] === '/public-donations.php?campaign=community-meals', 'Active Public Donations must use the dedicated route.');
$assert($enriched[0]['gross_rewards_allocated'] === 6 && $enriched[0]['rewards_allocated'] === 5, 'Active card reward totals should distinguish gross and net.');
$assert($enriched[0]['image_url'] === '/uploads/active.jpg', 'Active card should include campaign artwork.');

$json = json_encode($data, JSON_THROW_ON_ERROR);
foreach (['Carol Private','Dana Pending','Secret Final Recipient','Other Merchant','Other Community'] as $privateValue) {
    $assert(!str_contains($json,$privateValue),$privateValue.' must not appear in public profile payload.');
}
foreach (['wallet_item_id','pppm_item_id','microgift_instance_id','claim_code','internal_note','email','phone'] as $forbidden) {
    $assert(!str_contains($json,$forbidden),$forbidden.' must not appear in public profile payload.');
}
$other = mg_public_profile_community_build($pdo,'other-merchant');
$otherJson = json_encode($other, JSON_THROW_ON_ERROR);
$assert(!str_contains($otherJson,'Copper Table') && !str_contains($otherJson,'Alice Community'), 'Cross-merchant data must be impossible.');

echo json_encode(['ok'=>true,'summary'=>$summary,'accounts'=>array_column($data['community_accounts'],'display_name'),'privacy'=>$data['privacy']],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
