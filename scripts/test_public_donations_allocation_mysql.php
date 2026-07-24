<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }

$GLOBALS['phase4_notifications'] = [];
$GLOBALS['phase4_bridge_calls'] = 0;
$GLOBALS['phase4_bridge_fail_on'] = 0;

function mg_zero_reward_issue_from_wallet(PDO $pdo, array $input): array
{
    $GLOBALS['phase4_bridge_calls']++;
    if ((int)$GLOBALS['phase4_bridge_fail_on'] > 0 && (int)$GLOBALS['phase4_bridge_calls'] === (int)$GLOBALS['phase4_bridge_fail_on']) {
        throw new RuntimeException('Simulated canonical bridge failure.');
    }
    $pppmPublicId = 'pppm-' . str_pad((string)$GLOBALS['phase4_bridge_calls'], 32, '0', STR_PAD_LEFT);
    $microgiftPublicId = 'microgift-' . str_pad((string)$GLOBALS['phase4_bridge_calls'], 27, '0', STR_PAD_LEFT);
    $pdo->prepare('INSERT INTO pppm_items (public_id) VALUES (?)')->execute([$pppmPublicId]);
    $pppmId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO microgift_instances (public_id,pppm_item_id) VALUES (?,?)')->execute([$microgiftPublicId, $pppmId]);
    $pdo->prepare('UPDATE wallet_items SET pppm_item_id=? WHERE id=?')->execute([$pppmId, (int)$input['wallet_item_db_id']]);
    return [
        'schema_ready' => true,
        'pending_account_link' => false,
        'wallet_item_id' => (string)$input['wallet_item_public_id'],
        'recipient_user_id' => (int)$input['recipient_user_id'],
        'source_type' => (string)$input['source_type'],
        'source_reference' => (string)$input['source_reference'],
        'microgift_instance_id' => $microgiftPublicId,
        'microgift_status' => 'delivered',
        'pppm_item_db_id' => $pppmId,
        'pppm_item_id' => $pppmPublicId,
        'pppm_status' => 'delivered',
        'action_center' => ['recipient_inbox_item_id' => 'inbox-' . $GLOBALS['phase4_bridge_calls']],
        'destination' => 'inbox',
    ];
}

function mg_create_notification(PDO $pdo, int $userId, string $type, string $title, ?string $body = null, ?string $actionUrl = null, array $context = []): string
{
    $id = 'notification-' . (count($GLOBALS['phase4_notifications']) + 1);
    $GLOBALS['phase4_notifications'][] = compact('id','userId','type','title','body','actionUrl','context');
    return $id;
}

function mg_audit(string $action, string $entityType = 'system', array $metadata = [], ?int $userId = null): void {}

require_once dirname(__DIR__) . '/includes/public-donations-allocation.php';

function phase4_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase4_count(PDO $pdo, string $table): int
{
    return (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = getenv('MYSQL_PORT') ?: '3306';
$db = getenv('MYSQL_DATABASE') ?: 'microgifter_phase4';
$user = getenv('MYSQL_USER') ?: 'root';
$pass = getenv('MYSQL_PASSWORD') ?: 'root';
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ([
    'campaign_donation_rewards','campaign_donation_batches','campaign_donation_operations','microgift_instances','pppm_items',
    'wallet_items','campaign_community_assignments','reward_templates','campaigns','user_roles','roles','public_profiles','users'
] as $table) {
    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->exec("CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(160) NOT NULL,
    display_name VARCHAR(160) NULL,
    status ENUM('active','disabled','pending') NOT NULL DEFAULT 'active',
    PRIMARY KEY(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE public_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id VARCHAR(40) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(120) NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    avatar_url VARCHAR(500) NULL,
    location_label VARCHAR(160) NULL,
    visibility ENUM('public','private','unlisted') NOT NULL DEFAULT 'public',
    status ENUM('draft','active','hidden','suspended') NOT NULL DEFAULT 'active',
    PRIMARY KEY(id),UNIQUE KEY uq_profile_public(public_id),UNIQUE KEY uq_profile_user(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    PRIMARY KEY(id),UNIQUE KEY uq_role_slug(slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY(user_id,role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaigns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    public_slug VARCHAR(140) NULL,
    title VARCHAR(180) NOT NULL,
    campaign_type ENUM('public_donation') NOT NULL,
    status ENUM('draft','active','paused','ended','archived') NOT NULL DEFAULT 'draft',
    quantity_limit INT UNSIGNED NULL,
    issued_count INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_campaign_public(public_id),UNIQUE KEY uq_campaign_slug(merchant_user_id,public_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE reward_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft',
    value_amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    quantity_limit INT UNSIGNED NULL,
    issued_count INT UNSIGNED NOT NULL DEFAULT 0,
    expiration_rule ENUM('none','after_issue','after_claim','fixed_date','event_date') NOT NULL DEFAULT 'none',
    expiration_days INT UNSIGNED NULL,
    expires_at DATETIME NULL,
    redemption_instructions TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_template_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_community_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    community_user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','paused','removed') NOT NULL DEFAULT 'active',
    last_allocated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_assignment_public(public_id),UNIQUE KEY uq_assignment_campaign_user(campaign_id,community_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE wallet_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    contact_id BIGINT UNSIGNED NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    reward_template_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NULL,
    pppm_item_id BIGINT UNSIGNED NULL,
    source_type ENUM('manual_send','public_donation') NOT NULL,
    source_id VARCHAR(190) NULL,
    status ENUM('issued','viewed','claimed','redeemed','expired','cancelled') NOT NULL DEFAULT 'issued',
    value_cents_snapshot INT UNSIGNED NOT NULL DEFAULT 0,
    currency_snapshot CHAR(3) NOT NULL DEFAULT 'USD',
    title_snapshot VARCHAR(180) NOT NULL,
    metadata_json JSON NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_wallet_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE pppm_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id VARCHAR(80) NOT NULL,
    PRIMARY KEY(id),UNIQUE KEY uq_pppm_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE microgift_instances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id VARCHAR(80) NOT NULL,
    pppm_item_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY(id),UNIQUE KEY uq_microgift_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_donation_operations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    reward_template_id BIGINT UNSIGNED NOT NULL,
    operation_kind ENUM('allocation','recall') NOT NULL,
    operation_mode ENUM('single','same_quantity','custom_quantity','whole_batch','selected_rewards') NOT NULL,
    status ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
    idempotency_key VARCHAR(190) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    recipient_count INT UNSIGNED NOT NULL,
    requested_quantity INT UNSIGNED NOT NULL,
    completed_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    inventory_before INT UNSIGNED NULL,
    inventory_after INT UNSIGNED NULL,
    total_stated_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    confirmation_level ENUM('standard','large_operation') NOT NULL DEFAULT 'standard',
    message VARCHAR(1000) NULL,
    internal_note VARCHAR(2000) NULL,
    failure_code VARCHAR(120) NULL,
    failure_message VARCHAR(1000) NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_operation_public(public_id),UNIQUE KEY uq_operation_idem(merchant_user_id,idempotency_key),
    CHECK (recipient_count BETWEEN 1 AND 50),CHECK (requested_quantity BETWEEN 1 AND 1000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_donation_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    operation_id BIGINT UNSIGNED NOT NULL,
    assignment_id BIGINT UNSIGNED NOT NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    reward_template_id BIGINT UNSIGNED NOT NULL,
    community_user_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    recalled_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    stated_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    status ENUM('allocated','partially_recalled','recalled') NOT NULL DEFAULT 'allocated',
    message VARCHAR(1000) NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_batch_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_donation_rewards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    operation_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    reward_template_id BIGINT UNSIGNED NOT NULL,
    original_community_user_id BIGINT UNSIGNED NOT NULL,
    wallet_item_id BIGINT UNSIGNED NOT NULL,
    pppm_item_id BIGINT UNSIGNED NOT NULL,
    microgift_instance_id BIGINT UNSIGNED NOT NULL,
    allocation_sequence INT UNSIGNED NOT NULL,
    reward_title_snapshot VARCHAR(180) NOT NULL,
    value_cents_snapshot INT UNSIGNED NOT NULL DEFAULT 0,
    currency_snapshot CHAR(3) NOT NULL DEFAULT 'USD',
    status ENUM('allocated','recalled','retained_after_partial_recall') NOT NULL DEFAULT 'allocated',
    allocated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    recalled_at DATETIME NULL,
    recalled_by_operation_id BIGINT UNSIGNED NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_reward_public(public_id),UNIQUE KEY uq_reward_wallet(wallet_item_id),
    UNIQUE KEY uq_reward_sequence(batch_id,allocation_sequence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("INSERT INTO users(id,full_name,display_name,status) VALUES
    (1,'Merchant Owner','Merchant Owner','active'),
    (2,'Community One','Community One','active'),
    (3,'Community Two','Community Two','active'),
    (4,'Community Paused','Community Paused','active')");
$pdo->exec("INSERT INTO public_profiles(public_id,user_id,slug,display_name,visibility,status) VALUES
    ('pp_merchant',1,'merchant-owner','Merchant Owner','public','active'),
    ('pp_one',2,'community-one','Community One','public','active'),
    ('pp_two',3,'community-two','Community Two','unlisted','active'),
    ('pp_paused',4,'community-paused','Community Paused','public','active')");
$pdo->exec("INSERT INTO roles(id,slug,name) VALUES (1,'merchant','Merchant'),(2,'community','Community')");
$pdo->exec("INSERT INTO user_roles(user_id,role_id) VALUES (1,1),(2,2),(3,2),(4,2)");
$pdo->exec("INSERT INTO campaigns(id,public_id,merchant_user_id,public_slug,title,campaign_type,status,quantity_limit,issued_count)
    VALUES (10,'123e4567-e89b-42d3-a456-426614174010',1,'community-impact','Community Impact','public_donation','active',10,1)");
$pdo->exec("INSERT INTO reward_templates(id,public_id,merchant_user_id,title,description,status,value_amount_cents,currency,quantity_limit,issued_count,expiration_rule)
    VALUES (20,'123e4567-e89b-42d3-a456-426614174020',1,'Community Meal','One community meal','active',2500,'USD',8,2,'none')");
$pdo->exec("INSERT INTO campaign_community_assignments(id,public_id,merchant_user_id,campaign_id,community_user_id,status) VALUES
    (30,'123e4567-e89b-42d3-a456-426614174030',1,10,2,'active'),
    (31,'123e4567-e89b-42d3-a456-426614174031',1,10,3,'active'),
    (32,'123e4567-e89b-42d3-a456-426614174032',1,10,4,'paused')");

phase4_assert(mg_public_donations_allocation_schema_ready($pdo), 'Phase 4 schema should be ready.');
$recipients = mg_public_donations_allocation_recipients([
    ['assignment_id' => '123e4567-e89b-42d3-a456-426614174030', 'quantity' => 2],
    ['assignment_id' => '123e4567-e89b-42d3-a456-426614174031', 'quantity' => 1],
]);
$preflight = mg_public_donations_allocation_preflight(
    $pdo,1,'community-impact','123e4567-e89b-42d3-a456-426614174020',$recipients,'Thank you','Internal batch note'
);
phase4_assert($preflight['recipient_count'] === 2, 'Preflight recipient count is incorrect.');
phase4_assert($preflight['requested_quantity'] === 3, 'Preflight unit count is incorrect.');
phase4_assert($preflight['inventory']['available_before'] === 6, 'Preflight inventory before is incorrect.');
phase4_assert($preflight['inventory']['available_after'] === 3, 'Preflight inventory after is incorrect.');
phase4_assert($preflight['total_stated_value_cents'] === 7500, 'Preflight stated value is incorrect.');
phase4_assert($preflight['large_operation'] === false, 'Small operation must not require large confirmation.');

$idempotency = 'public-donation:mysql-test-0001';
$operation = mg_public_donations_allocation_execute(
    $pdo,1,1,'community-impact','123e4567-e89b-42d3-a456-426614174020',$recipients,
    $idempotency,'Thank you','Internal batch note',false
);
phase4_assert($operation['duplicate'] === false, 'First allocation must not be marked duplicate.');
phase4_assert($operation['status'] === 'completed', 'Allocation operation must complete.');
phase4_assert($operation['recipient_count'] === 2 && $operation['completed_quantity'] === 3, 'Completed counts are incorrect.');
phase4_assert(count($operation['batches']) === 2, 'One batch per recipient is required.');
phase4_assert(phase4_count($pdo, 'campaign_donation_operations') === 1, 'One operation is required.');
phase4_assert(phase4_count($pdo, 'campaign_donation_batches') === 2, 'Two batches are required.');
phase4_assert(phase4_count($pdo, 'campaign_donation_rewards') === 3, 'One attribution row per unit is required.');
phase4_assert(phase4_count($pdo, 'wallet_items') === 3, 'One wallet item per unit is required.');
phase4_assert(phase4_count($pdo, 'pppm_items') === 3, 'One PPPM item per unit is required.');
phase4_assert(phase4_count($pdo, 'microgift_instances') === 3, 'One Microgift instance per unit is required.');
phase4_assert((int)$pdo->query("SELECT COUNT(*) FROM wallet_items WHERE source_type='public_donation'")->fetchColumn() === 3, 'Every wallet item must use public_donation source type.');
phase4_assert((int)$pdo->query('SELECT issued_count FROM campaigns WHERE id=10')->fetchColumn() === 4, 'Campaign inventory count is incorrect.');
phase4_assert((int)$pdo->query('SELECT issued_count FROM reward_templates WHERE id=20')->fetchColumn() === 5, 'Template inventory count is incorrect.');
phase4_assert(count($GLOBALS['phase4_notifications']) === 2, 'One notification per receiving account is required.');
phase4_assert((int)$pdo->query('SELECT COUNT(*) FROM campaign_community_assignments WHERE last_allocated_at IS NOT NULL')->fetchColumn() === 2, 'Assignments must record last allocation time.');

$counts = [
    'operations' => phase4_count($pdo, 'campaign_donation_operations'),
    'batches' => phase4_count($pdo, 'campaign_donation_batches'),
    'rewards' => phase4_count($pdo, 'campaign_donation_rewards'),
    'wallet' => phase4_count($pdo, 'wallet_items'),
    'pppm' => phase4_count($pdo, 'pppm_items'),
    'microgift' => phase4_count($pdo, 'microgift_instances'),
];
$duplicate = mg_public_donations_allocation_execute(
    $pdo,1,1,'community-impact','123e4567-e89b-42d3-a456-426614174020',$recipients,
    $idempotency,'Thank you','Internal batch note',false
);
phase4_assert($duplicate['duplicate'] === true, 'Retry with the same key and request must be idempotent.');
phase4_assert(phase4_count($pdo, 'campaign_donation_operations') === $counts['operations'], 'Duplicate retry created an operation.');
phase4_assert(phase4_count($pdo, 'campaign_donation_batches') === $counts['batches'], 'Duplicate retry created batches.');
phase4_assert(phase4_count($pdo, 'campaign_donation_rewards') === $counts['rewards'], 'Duplicate retry created reward attributions.');
phase4_assert(phase4_count($pdo, 'wallet_items') === $counts['wallet'], 'Duplicate retry created wallet items.');
phase4_assert(phase4_count($pdo, 'pppm_items') === $counts['pppm'], 'Duplicate retry created PPPM items.');
phase4_assert(phase4_count($pdo, 'microgift_instances') === $counts['microgift'], 'Duplicate retry created Microgifts.');
phase4_assert(count($GLOBALS['phase4_notifications']) === 2, 'Duplicate retry created notifications.');

$hashConflict = false;
try {
    mg_public_donations_allocation_execute(
        $pdo,1,1,'community-impact','123e4567-e89b-42d3-a456-426614174020',
        mg_public_donations_allocation_recipients([
            ['assignment_id' => '123e4567-e89b-42d3-a456-426614174030', 'quantity' => 1],
        ]),
        $idempotency,'Different message',null,false
    );
} catch (RuntimeException $error) {
    $hashConflict = $error->getCode() === 409;
}
phase4_assert($hashConflict, 'Reusing an idempotency key for a different request must fail.');
phase4_assert(phase4_count($pdo, 'wallet_items') === $counts['wallet'], 'Hash conflict changed wallet inventory.');

$inventoryRejected = false;
try {
    mg_public_donations_allocation_preflight(
        $pdo,1,'community-impact','123e4567-e89b-42d3-a456-426614174020',
        mg_public_donations_allocation_recipients([
            ['assignment_id' => '123e4567-e89b-42d3-a456-426614174030', 'quantity' => 4],
        ])
    );
} catch (RuntimeException $error) {
    $inventoryRejected = $error->getCode() === 409;
}
phase4_assert($inventoryRejected, 'Insufficient template inventory must be rejected.');

$pausedRejected = false;
try {
    mg_public_donations_allocation_preflight(
        $pdo,1,'community-impact','123e4567-e89b-42d3-a456-426614174020',
        mg_public_donations_allocation_recipients([
            ['assignment_id' => '123e4567-e89b-42d3-a456-426614174032', 'quantity' => 1],
        ])
    );
} catch (RuntimeException $error) {
    $pausedRejected = $error->getCode() === 409;
}
phase4_assert($pausedRejected, 'Paused assignments must be rejected.');

$pdo->exec('UPDATE campaigns SET quantity_limit=NULL WHERE id=10');
$pdo->exec('UPDATE reward_templates SET quantity_limit=NULL WHERE id=20');
$largeRecipients = mg_public_donations_allocation_recipients([
    ['assignment_id' => '123e4567-e89b-42d3-a456-426614174030', 'quantity' => 100],
]);
$largePreview = mg_public_donations_allocation_preflight(
    $pdo,1,'community-impact','123e4567-e89b-42d3-a456-426614174020',$largeRecipients
);
phase4_assert($largePreview['large_operation'] === true, '100 units must require large-operation confirmation.');
$largeRejected = false;
try {
    mg_public_donations_allocation_execute(
        $pdo,1,1,'community-impact','123e4567-e89b-42d3-a456-426614174020',$largeRecipients,
        'public-donation:mysql-large-0001',null,null,false
    );
} catch (RuntimeException $error) {
    $largeRejected = $error->getCode() === 409;
}
phase4_assert($largeRejected, 'Unconfirmed large operation must be rejected.');
phase4_assert(phase4_count($pdo, 'campaign_donation_operations') === $counts['operations'], 'Rejected large operation created an operation.');
phase4_assert(phase4_count($pdo, 'wallet_items') === $counts['wallet'], 'Rejected large operation created wallet items.');

$GLOBALS['phase4_bridge_fail_on'] = $GLOBALS['phase4_bridge_calls'] + 2;
$rollbackRejected = false;
try {
    mg_public_donations_allocation_execute(
        $pdo,1,1,'community-impact','123e4567-e89b-42d3-a456-426614174020',
        mg_public_donations_allocation_recipients([
            ['assignment_id' => '123e4567-e89b-42d3-a456-426614174030', 'quantity' => 2],
        ]),
        'public-donation:mysql-rollback-0001','Rollback test',null,false
    );
} catch (RuntimeException $error) {
    $rollbackRejected = str_contains($error->getMessage(), 'Simulated canonical bridge failure');
}
phase4_assert($rollbackRejected, 'Simulated bridge failure must surface.');
phase4_assert(phase4_count($pdo, 'campaign_donation_operations') === $counts['operations'], 'Failed allocation operation was not rolled back.');
phase4_assert(phase4_count($pdo, 'campaign_donation_batches') === $counts['batches'], 'Failed allocation batches were not rolled back.');
phase4_assert(phase4_count($pdo, 'campaign_donation_rewards') === $counts['rewards'], 'Failed allocation attributions were not rolled back.');
phase4_assert(phase4_count($pdo, 'wallet_items') === $counts['wallet'], 'Failed allocation wallet items were not rolled back.');
phase4_assert(phase4_count($pdo, 'pppm_items') === $counts['pppm'], 'Failed allocation PPPM items were not rolled back.');
phase4_assert(phase4_count($pdo, 'microgift_instances') === $counts['microgift'], 'Failed allocation Microgifts were not rolled back.');
phase4_assert((int)$pdo->query('SELECT issued_count FROM campaigns WHERE id=10')->fetchColumn() === 4, 'Failed allocation changed campaign inventory.');
phase4_assert((int)$pdo->query('SELECT issued_count FROM reward_templates WHERE id=20')->fetchColumn() === 5, 'Failed allocation changed template inventory.');
phase4_assert(count($GLOBALS['phase4_notifications']) === 2, 'Failed allocation created notifications.');

echo "Public Donations allocation MySQL lifecycle valid.\n";
