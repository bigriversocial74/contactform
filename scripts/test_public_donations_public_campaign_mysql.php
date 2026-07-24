<?php
declare(strict_types=1);

putenv('MG_PUBLIC_DONATIONS_FEATURE_STATE=enabled');

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MYSQL_PORT') ?: 3306);
$database = getenv('MYSQL_DATABASE') ?: 'microgifter_phase7';
$user = getenv('MYSQL_USER') ?: 'root';
$password = getenv('MYSQL_PASSWORD') ?: 'root';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

foreach (['campaign_donation_rewards','campaign_community_assignments','campaigns','reward_templates','public_profiles','users'] as $table) {
    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
}

$ddl = [
    "CREATE TABLE users (
        id BIGINT UNSIGNED PRIMARY KEY,
        display_name VARCHAR(180) NULL,
        full_name VARCHAR(180) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE public_profiles (
        user_id BIGINT UNSIGNED PRIMARY KEY,
        slug VARCHAR(120) NULL,
        display_name VARCHAR(180) NULL,
        headline VARCHAR(240) NULL,
        avatar_url VARCHAR(900) NULL,
        cover_url VARCHAR(900) NULL,
        location_label VARCHAR(180) NULL,
        status VARCHAR(40) NOT NULL,
        visibility VARCHAR(40) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE reward_templates (
        id BIGINT UNSIGNED PRIMARY KEY,
        public_id CHAR(36) NOT NULL,
        title VARCHAR(180) NOT NULL,
        description VARCHAR(1000) NULL,
        reward_type VARCHAR(80) NULL,
        value_type VARCHAR(40) NULL,
        value_amount_cents INT UNSIGNED NULL,
        value_percent DECIMAL(8,2) NULL,
        currency CHAR(3) NULL,
        metadata_json JSON NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE campaigns (
        id BIGINT UNSIGNED PRIMARY KEY,
        public_id CHAR(36) NOT NULL,
        public_slug VARCHAR(190) NULL,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        reward_template_id BIGINT UNSIGNED NULL,
        campaign_type VARCHAR(80) NOT NULL,
        title VARCHAR(180) NOT NULL,
        description VARCHAR(2000) NULL,
        form_headline VARCHAR(240) NULL,
        form_description VARCHAR(2000) NULL,
        status VARCHAR(40) NOT NULL,
        starts_at DATETIME NULL,
        ends_at DATETIME NULL,
        rules_json JSON NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE campaign_community_assignments (
        id BIGINT UNSIGNED PRIMARY KEY,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        campaign_id BIGINT UNSIGNED NOT NULL,
        community_user_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(40) NOT NULL,
        public_display_status VARCHAR(40) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE campaign_donation_rewards (
        id BIGINT UNSIGNED PRIMARY KEY,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        campaign_id BIGINT UNSIGNED NOT NULL,
        original_community_user_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(40) NOT NULL,
        value_cents_snapshot INT UNSIGNED NOT NULL,
        currency_snapshot CHAR(3) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
}

$pdo->exec("INSERT INTO users VALUES
    (1,'Copper Table','Copper Table Hospitality'),
    (2,'Alice Community','Alice Community'),
    (3,'Bob Community','Bob Community'),
    (4,'Carol Private','Carol Private'),
    (5,'Dana Pending','Dana Pending'),
    (9,'Other Merchant','Other Merchant'),
    (10,'Other Community','Other Community')");
$pdo->exec("INSERT INTO public_profiles VALUES
    (1,'copper-table','Copper Table','Local meals and experiences','/uploads/merchant-avatar.jpg','/uploads/merchant-cover.jpg','Phoenix, Arizona','active','public'),
    (2,'alice-community','Alice Community','Neighborhood organizer','/uploads/alice.jpg','/uploads/alice-cover.jpg','Phoenix, Arizona','active','public'),
    (3,'bob-community','Bob Community','Community artist','/uploads/bob.jpg','/uploads/bob-cover.jpg','Tempe, Arizona','active','unlisted'),
    (4,'carol-private','Carol Private','Private profile',NULL,NULL,'Mesa, Arizona','active','private'),
    (5,'dana-pending','Dana Pending','Pending display consent',NULL,NULL,'Glendale, Arizona','active','public'),
    (9,'other-merchant','Other Merchant','Other business',NULL,NULL,'Other City','active','public'),
    (10,'other-community','Other Community','Other account',NULL,NULL,'Other City','active','public')");
$pdo->exec("INSERT INTO reward_templates VALUES
    (501,'50000000-0000-4000-8000-000000000501','Community Meal','A merchant-funded meal reward.','free_item','fixed_amount',1000,NULL,'USD',JSON_OBJECT('reward_image_url','/uploads/community-meal.jpg')),
    (502,'50000000-0000-4000-8000-000000000502','Other Reward','Other merchant reward.','free_item','fixed_amount',1000,NULL,'USD',NULL)");
$pdo->exec("INSERT INTO campaigns VALUES
    (101,'10000000-0000-4000-8000-000000000101','community-meals',1,501,'public_donation','Community Meal Support','Merchant-directed promotional meals for Community accounts.','Meals for Community','Copper Table is allocating promotional meal rewards directly to selected Community accounts.','active',DATE_SUB(NOW(),INTERVAL 2 DAY),DATE_ADD(NOW(),INTERVAL 30 DAY),JSON_OBJECT('campaign_image_url','/uploads/community-campaign.jpg')),
    (102,'10000000-0000-4000-8000-000000000102','inactive-support',1,501,'public_donation','Inactive Support','Not public.','Inactive','Not public.','paused',NULL,NULL,NULL),
    (201,'20000000-0000-4000-8000-000000000201','other-support',9,502,'public_donation','Other Merchant Support','Other merchant campaign.','Other Support','Other merchant.','active',NULL,NULL,NULL)");
$pdo->exec("INSERT INTO campaign_community_assignments VALUES
    (301,1,101,2,'active','approved'),
    (302,1,101,3,'paused','approved'),
    (303,1,101,4,'active','approved'),
    (304,1,101,5,'active','pending'),
    (305,1,101,10,'removed','approved'),
    (401,9,201,10,'active','approved')");
$pdo->exec("INSERT INTO campaign_donation_rewards VALUES
    (801,1,101,2,'allocated',1000,'USD'),
    (802,1,101,2,'allocated',1000,'USD'),
    (803,1,101,2,'recalled',1000,'USD'),
    (804,1,101,3,'allocated',1000,'USD'),
    (805,1,101,4,'allocated',1000,'USD'),
    (901,9,201,10,'allocated',1000,'USD')");

require_once dirname(__DIR__) . '/includes/public-donations-public.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$payload = mg_public_donations_public_payload($pdo, 'community-meals');
$assert(is_array($payload), 'Active Public Donations campaign should load.');
$assert($payload['campaign']['public_transactional'] === false, 'Public campaign must be informational only.');
$assert($payload['campaign']['public_mode'] === 'informational', 'Public mode must be informational.');
$assert($payload['impact']['supported_accounts'] === 4, 'Active and paused assignments should reconcile in supported totals.');
$assert($payload['impact']['publicly_featured_accounts'] === 2, 'Only approved eligible profiles should be publicly featured.');
$assert($payload['impact']['anonymous_accounts'] === 2, 'Private and pending profiles should remain anonymous in aggregate.');
$assert($payload['impact']['funded_accounts'] === 3, 'Funded account total should use attribution records.');
$assert($payload['impact']['gross_allocated'] === 5, 'Gross allocated should include recalled history.');
$assert($payload['impact']['recalled'] === 1, 'Recall total should remain distinct.');
$assert($payload['impact']['net_allocated'] === 4, 'Net allocated should reconcile.');
$assert(($payload['impact']['stated_value_by_currency'][0]['gross_cents'] ?? null) === 5000, 'Gross stated value should reconcile.');
$assert(($payload['impact']['stated_value_by_currency'][0]['recalled_cents'] ?? null) === 1000, 'Recalled stated value should reconcile.');
$assert(($payload['impact']['stated_value_by_currency'][0]['net_cents'] ?? null) === 4000, 'Net stated value should reconcile.');
$assert(count($payload['community_accounts']) === 2, 'Only approved public or unlisted Community profiles should render.');
$assert(array_column($payload['community_accounts'], 'display_name') === ['Alice Community', 'Bob Community'], 'Community cards should be ordered by net support then name.');
$assert($payload['community_accounts'][0]['support']['net_allocated'] === 2, 'Alice support totals should reconcile.');
$assert($payload['community_accounts'][1]['profile_indexable'] === false, 'Unlisted Community profile should remain nofollow.');
$assert($payload['seo']['indexable'] === true && $payload['seo']['robots'] === 'index,follow', 'Public merchant profile should allow indexing.');
$assert($payload['governance']['public_purchase_available'] === false, 'Public purchase must be unavailable.');
$assert($payload['governance']['public_request_available'] === false, 'Public request must be unavailable.');
$assert($payload['privacy']['final_recipient_identity_exposed'] === false, 'Final recipient identity must remain private.');

$json = json_encode($payload, JSON_THROW_ON_ERROR);
foreach (['Carol Private','Dana Pending','Other Merchant','Other Community'] as $privateValue) {
    $assert(!str_contains($json, $privateValue), $privateValue . ' must not appear in the public payload.');
}
foreach (['wallet_item_id','pppm_item_id','microgift_instance_id','claim_code','internal_note','email','phone'] as $forbiddenKey) {
    $assert(!str_contains($json, $forbiddenKey), $forbiddenKey . ' must not appear in the public payload.');
}

$pdo->exec("UPDATE public_profiles SET visibility='unlisted' WHERE user_id=1");
$unlistedPayload = mg_public_donations_public_payload($pdo, 'community-meals');
$assert(is_array($unlistedPayload), 'Unlisted merchant campaign should remain shareable by direct link.');
$assert($unlistedPayload['seo']['indexable'] === false && $unlistedPayload['seo']['robots'] === 'noindex,nofollow', 'Unlisted merchant profile should force noindex.');

$assert(mg_public_donations_public_payload($pdo, 'inactive-support') === null, 'Inactive campaign must not load publicly.');
$assert(mg_public_donations_public_payload($pdo, 'other-support') !== null, 'Enabled feature should allow another active merchant campaign by direct reference.');
$otherJson = json_encode(mg_public_donations_public_payload($pdo, 'other-support'), JSON_THROW_ON_ERROR);
$assert(!str_contains($otherJson, 'Copper Table'), 'Campaign queries must not leak another merchant.');

putenv('MG_PUBLIC_DONATIONS_FEATURE_STATE=disabled');
$assert(mg_public_donations_public_payload($pdo, 'community-meals') === null, 'Feature rollout must gate the public page.');

echo json_encode([
    'ok' => true,
    'impact' => $payload['impact'],
    'community_accounts' => array_column($payload['community_accounts'], 'display_name'),
    'privacy' => $payload['privacy'],
    'seo' => $payload['seo'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
