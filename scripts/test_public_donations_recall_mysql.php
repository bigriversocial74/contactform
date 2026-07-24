<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }

$GLOBALS['phase5_notifications'] = [];
$GLOBALS['phase5_lifecycle_calls'] = 0;
$GLOBALS['phase5_fail_on_lifecycle_call'] = 0;

function mg_pppm_record_event(PDO $pdo, array $item, string $eventType, ?string $fromStatus, ?string $toStatus, ?int $actorUserId, ?int $sourceEventId, array $metadata = []): void
{
    $pdo->prepare('INSERT INTO pppm_item_events (item_id,event_type,from_status,to_status,actor_user_id,metadata_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([(int)$item['id'],$eventType,$fromStatus,$toStatus,$actorUserId,json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

function mg_microgift_apply_lifecycle(PDO $pdo, array $instance, string $action, string $sourceType, string $sourceReference, string $key, ?int $actor, string $reason = ''): array
{
    $GLOBALS['phase5_lifecycle_calls']++;
    if ((int)$GLOBALS['phase5_fail_on_lifecycle_call'] > 0
        && (int)$GLOBALS['phase5_lifecycle_calls'] === (int)$GLOBALS['phase5_fail_on_lifecycle_call']) {
        throw new RuntimeException('Simulated recall lifecycle failure.');
    }
    $existing = $pdo->prepare('SELECT public_id,to_status FROM microgift_lifecycle_actions WHERE idempotency_key=? LIMIT 1');
    $existing->execute([$key]);
    if ($row = $existing->fetch(PDO::FETCH_ASSOC)) return ['action_id'=>(string)$row['public_id'],'status'=>(string)$row['to_status'],'duplicate'=>true];
    if ($action !== 'cancel') throw new RuntimeException('Unexpected lifecycle action.');
    $publicId = 'recall-action-' . str_pad((string)$GLOBALS['phase5_lifecycle_calls'], 22, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT INTO microgift_lifecycle_actions (public_id,instance_id,action_type,from_status,to_status,source_type,source_reference,idempotency_key,actor_user_id,reason,created_at) VALUES (?,?,? ,?,'cancelled',?,?,?,?,?,NOW())")
        ->execute([$publicId,(int)$instance['id'],$action,(string)$instance['status'],$sourceType,$sourceReference,$key,$actor,$reason]);
    $pdo->prepare("UPDATE microgift_instances SET status='cancelled',cancelled_at=NOW(),updated_at=NOW() WHERE id=?")
        ->execute([(int)$instance['id']]);
    return ['action_id'=>$publicId,'status'=>'cancelled','duplicate'=>false];
}

function mg_action_center_project_lifecycle(PDO $pdo, array $instance, array $context = []): array
{
    $pdo->prepare("UPDATE microgift_inbox_items SET state='revoked',updated_at=NOW() WHERE instance_id=?")
        ->execute([(int)$instance['id']]);
    return ['recipient_item_id'=>(string)($pdo->query('SELECT public_id FROM microgift_inbox_items WHERE instance_id=' . (int)$instance['id'] . ' LIMIT 1')->fetchColumn() ?: '')];
}

function mg_create_notification(PDO $pdo, int $userId, string $type, string $title, ?string $body = null, ?string $actionUrl = null, array $context = []): string
{
    $id = 'notification-' . (count($GLOBALS['phase5_notifications']) + 1);
    $GLOBALS['phase5_notifications'][] = compact('id','userId','type','title','body','actionUrl','context');
    return $id;
}

require_once dirname(__DIR__) . '/includes/public-donations-recall.php';

function phase5_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase5_count(PDO $pdo, string $table, string $where = '1=1'): int
{
    return (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $where)->fetchColumn();
}

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = getenv('MYSQL_PORT') ?: '3306';
$db = getenv('MYSQL_DATABASE') ?: 'microgifter_phase5';
$user = getenv('MYSQL_USER') ?: 'root';
$pass = getenv('MYSQL_PASSWORD') ?: 'root';
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ([
    'campaign_events','microgift_inbox_items','microgift_lifecycle_actions','microgift_instances',
    'pppm_item_events','pppm_items','wallet_items','campaign_donation_rewards','campaign_donation_batches',
    'campaign_donation_operations','campaign_community_assignments','reward_templates','campaigns','public_profiles','users'
] as $table) $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->exec("CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,full_name VARCHAR(160) NOT NULL,display_name VARCHAR(160) NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',PRIMARY KEY(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE public_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id VARCHAR(80) NOT NULL,user_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(120) NOT NULL,display_name VARCHAR(160) NOT NULL,PRIMARY KEY(id),UNIQUE KEY uq_profile_user(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaigns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,
    public_slug VARCHAR(140) NULL,title VARCHAR(180) NOT NULL,campaign_type ENUM('public_donation') NOT NULL,
    status ENUM('draft','active','paused','ended','archived') NOT NULL DEFAULT 'active',quantity_limit INT UNSIGNED NULL,
    issued_count INT UNSIGNED NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_campaign_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE reward_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'active',
    value_amount_cents INT UNSIGNED NOT NULL DEFAULT 0,currency CHAR(3) NOT NULL DEFAULT 'USD',quantity_limit INT UNSIGNED NULL,
    issued_count INT UNSIGNED NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_template_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_community_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,community_user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','paused','removed') NOT NULL DEFAULT 'active',PRIMARY KEY(id),UNIQUE KEY uq_assignment_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_donation_operations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,reward_template_id BIGINT UNSIGNED NOT NULL,
    operation_kind ENUM('allocation','recall') NOT NULL,
    operation_mode ENUM('single','same_quantity','custom_quantity','partial_recall') NOT NULL,
    status ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',idempotency_key VARCHAR(190) NOT NULL,
    request_hash CHAR(64) NOT NULL,recipient_count INT UNSIGNED NOT NULL DEFAULT 0,requested_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    completed_quantity INT UNSIGNED NOT NULL DEFAULT 0,inventory_before INT UNSIGNED NULL,inventory_after INT UNSIGNED NULL,
    total_stated_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,currency CHAR(3) NOT NULL DEFAULT 'USD',
    confirmation_level ENUM('standard','large_operation') NOT NULL DEFAULT 'standard',message VARCHAR(1000) NULL,
    internal_note VARCHAR(2000) NULL,error_code VARCHAR(120) NULL,error_message VARCHAR(1000) NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_operation_public(public_id),UNIQUE KEY uq_operation_idem(merchant_user_id,idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_donation_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,operation_id BIGINT UNSIGNED NOT NULL,
    assignment_id BIGINT UNSIGNED NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NOT NULL,
    reward_template_id BIGINT UNSIGNED NOT NULL,community_user_id BIGINT UNSIGNED NOT NULL,quantity INT UNSIGNED NOT NULL,
    recalled_quantity INT UNSIGNED NOT NULL DEFAULT 0,stated_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',status ENUM('allocated','partially_recalled','recalled') NOT NULL DEFAULT 'allocated',
    message VARCHAR(1000) NULL,created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_batch_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE wallet_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,user_id BIGINT UNSIGNED NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,reward_template_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NULL,
    status ENUM('issued','viewed','claimed','redeemed','expired','cancelled') NOT NULL DEFAULT 'issued',
    claimed_at DATETIME NULL,redeemed_at DATETIME NULL,expires_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_wallet_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE pppm_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id VARCHAR(80) NOT NULL,owner_user_id BIGINT UNSIGNED NOT NULL,
    recipient_user_id BIGINT UNSIGNED NULL,status ENUM('assigned','sent','delivered','viewed','claim_pending','verified','redeemed','expired','cancelled','voided','refunded') NOT NULL,
    expires_at DATETIME NULL,cancelled_at DATETIME NULL,version_no INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_pppm_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE pppm_item_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,item_id BIGINT UNSIGNED NOT NULL,event_type VARCHAR(120) NOT NULL,
    from_status VARCHAR(40) NULL,to_status VARCHAR(40) NULL,actor_user_id BIGINT UNSIGNED NULL,metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE microgift_instances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id VARCHAR(80) NOT NULL,template_id BIGINT UNSIGNED NOT NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,recipient_user_id BIGINT UNSIGNED NULL,
    status ENUM('issued','delivered','claim_pending','claimed','redeemable','redeemed','expired','cancelled','revoked','replaced') NOT NULL,
    claimed_at DATETIME NULL,redeemed_at DATETIME NULL,expires_at DATETIME NULL,cancelled_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_microgift_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE microgift_lifecycle_actions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id VARCHAR(80) NOT NULL,instance_id BIGINT UNSIGNED NOT NULL,
    action_type VARCHAR(40) NOT NULL,from_status VARCHAR(40) NOT NULL,to_status VARCHAR(40) NOT NULL,
    source_type VARCHAR(80) NOT NULL,source_reference VARCHAR(190) NOT NULL,idempotency_key VARCHAR(190) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,reason VARCHAR(500) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_lifecycle_idem(idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE microgift_inbox_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id VARCHAR(80) NOT NULL,instance_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,folder VARCHAR(40) NOT NULL DEFAULT 'inbox',state VARCHAR(40) NOT NULL DEFAULT 'claimable',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_inbox_instance_user(instance_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_donation_rewards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,operation_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NOT NULL,
    reward_template_id BIGINT UNSIGNED NOT NULL,original_community_user_id BIGINT UNSIGNED NOT NULL,
    wallet_item_id BIGINT UNSIGNED NOT NULL,pppm_item_id BIGINT UNSIGNED NOT NULL,microgift_instance_id BIGINT UNSIGNED NOT NULL,
    allocation_sequence INT UNSIGNED NOT NULL,reward_title_snapshot VARCHAR(180) NOT NULL,value_cents_snapshot INT UNSIGNED NOT NULL DEFAULT 0,
    currency_snapshot CHAR(3) NOT NULL DEFAULT 'USD',status ENUM('allocated','recalled') NOT NULL DEFAULT 'allocated',
    allocated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,recalled_at DATETIME NULL,recalled_by_user_id BIGINT UNSIGNED NULL,
    recall_reason VARCHAR(500) NULL,metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_reward_public(public_id),UNIQUE KEY uq_reward_wallet(wallet_item_id),UNIQUE KEY uq_reward_sequence(batch_id,allocation_sequence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,wallet_item_id BIGINT UNSIGNED NULL,contact_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(120) NOT NULL,event_context_json JSON NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_campaign_event_public(public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("INSERT INTO users(id,full_name,display_name,status) VALUES
    (1,'Merchant Owner','Merchant Owner','active'),(2,'Community Original','Community Original','active'),(3,'Downstream Recipient','Downstream Recipient','active')");
$pdo->exec("INSERT INTO public_profiles(public_id,user_id,slug,display_name) VALUES
    ('profile-merchant',1,'merchant','Merchant Owner'),('profile-community',2,'community-original','Community Original'),('profile-downstream',3,'downstream','Downstream Recipient')");
$pdo->exec("INSERT INTO campaigns(id,public_id,merchant_user_id,public_slug,title,campaign_type,status,quantity_limit,issued_count) VALUES
    (10,'123e4567-e89b-42d3-a456-426614174010',1,'community-impact','Community Impact','public_donation','active',20,7)");
$pdo->exec("INSERT INTO reward_templates(id,public_id,merchant_user_id,title,status,value_amount_cents,currency,quantity_limit,issued_count) VALUES
    (20,'123e4567-e89b-42d3-a456-426614174020',1,'Community Meal','active',2500,'USD',20,7)");
$pdo->exec("INSERT INTO campaign_community_assignments(id,public_id,merchant_user_id,campaign_id,community_user_id,status) VALUES
    (30,'123e4567-e89b-42d3-a456-426614174030',1,10,2,'active')");
$pdo->exec("INSERT INTO campaign_donation_operations(id,public_id,merchant_user_id,campaign_id,reward_template_id,operation_kind,operation_mode,status,idempotency_key,request_hash,recipient_count,requested_quantity,completed_quantity,total_stated_value_cents,currency,created_by_user_id,completed_at) VALUES
    (40,'123e4567-e89b-42d3-a456-426614174040',1,10,20,'allocation','single','completed','allocation-seed-0001',REPEAT('a',64),1,8,8,20000,'USD',1,NOW()),
    (41,'123e4567-e89b-42d3-a456-426614174041',1,10,20,'allocation','single','completed','allocation-seed-0002',REPEAT('b',64),1,2,2,5000,'USD',1,NOW())");
$pdo->exec("INSERT INTO campaign_donation_batches(id,public_id,operation_id,assignment_id,merchant_user_id,campaign_id,reward_template_id,community_user_id,quantity,recalled_quantity,stated_value_cents,currency,status,created_by_user_id) VALUES
    (50,'123e4567-e89b-42d3-a456-426614174050',40,30,1,10,20,2,8,1,20000,'USD','partially_recalled',1),
    (51,'123e4567-e89b-42d3-a456-426614174051',41,30,1,10,20,2,2,0,5000,'USD','allocated',1)");

for ($index = 1; $index <= 10; $index++) {
    $walletId = 100 + $index;
    $pppmId = 200 + $index;
    $microgiftId = 300 + $index;
    $batchId = $index <= 8 ? 50 : 51;
    $sequence = $index <= 8 ? $index : $index - 8;
    $walletUser = 2;
    $walletStatus = 'issued';
    $walletClaimed = null;
    $walletRedeemed = null;
    $walletExpires = null;
    $pppmOwner = 2;
    $pppmStatus = 'delivered';
    $pppmExpires = null;
    $microgiftOwner = 2;
    $microgiftStatus = 'delivered';
    $microgiftClaimed = null;
    $microgiftRedeemed = null;
    $microgiftExpires = null;
    $rewardStatus = 'allocated';
    $recalledAt = null;
    $recalledBy = null;
    $recallReason = null;

    if ($index === 3) { $walletUser = 3; $pppmOwner = 3; $microgiftOwner = 3; }
    if ($index === 4) { $walletStatus = 'claimed'; $walletClaimed = '2026-07-24 10:00:00'; $pppmStatus = 'verified'; $microgiftStatus = 'claimed'; $microgiftClaimed = '2026-07-24 10:00:00'; }
    if ($index === 5) { $walletStatus = 'redeemed'; $walletRedeemed = '2026-07-24 11:00:00'; $pppmStatus = 'redeemed'; $microgiftStatus = 'redeemed'; $microgiftRedeemed = '2026-07-24 11:00:00'; }
    if ($index === 6) { $walletStatus = 'expired'; $pppmStatus = 'expired'; $microgiftStatus = 'expired'; }
    if ($index === 7) { $walletStatus = 'cancelled'; $pppmStatus = 'cancelled'; $microgiftStatus = 'cancelled'; }
    if ($index === 8) { $walletStatus = 'cancelled'; $pppmStatus = 'cancelled'; $microgiftStatus = 'cancelled'; $rewardStatus = 'recalled'; $recalledAt = '2026-07-24 09:00:00'; $recalledBy = 1; $recallReason = 'Earlier correction'; }

    $walletPublic = sprintf('123e4567-e89b-42d3-a456-%012d', $walletId);
    $rewardPublic = sprintf('223e4567-e89b-42d3-a456-%012d', 500 + $index);
    $pdo->prepare('INSERT INTO wallet_items(id,public_id,user_id,merchant_user_id,reward_template_id,campaign_id,status,claimed_at,redeemed_at,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$walletId,$walletPublic,$walletUser,1,20,10,$walletStatus,$walletClaimed,$walletRedeemed,$walletExpires]);
    $pdo->prepare('INSERT INTO pppm_items(id,public_id,owner_user_id,recipient_user_id,status,expires_at) VALUES (?,?,?,?,?,?)')
        ->execute([$pppmId,'pppm-' . $pppmId,$pppmOwner,2,$pppmStatus,$pppmExpires]);
    $pdo->prepare('INSERT INTO microgift_instances(id,public_id,template_id,owner_user_id,recipient_user_id,status,claimed_at,redeemed_at,expires_at) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$microgiftId,'microgift-' . $microgiftId,20,$microgiftOwner,2,$microgiftStatus,$microgiftClaimed,$microgiftRedeemed,$microgiftExpires]);
    $pdo->prepare('INSERT INTO microgift_inbox_items(public_id,instance_id,user_id,folder,state) VALUES (?,?,?,?,?)')
        ->execute(['inbox-' . $microgiftId,$microgiftId,$microgiftOwner,'inbox',in_array($microgiftStatus,['claimed','redeemed'],true)?'redeemable':'claimable']);
    $pdo->prepare('INSERT INTO campaign_donation_rewards(public_id,operation_id,batch_id,merchant_user_id,campaign_id,reward_template_id,original_community_user_id,wallet_item_id,pppm_item_id,microgift_instance_id,allocation_sequence,reward_title_snapshot,value_cents_snapshot,currency_snapshot,status,recalled_at,recalled_by_user_id,recall_reason) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$rewardPublic,$batchId === 50 ? 40 : 41,$batchId,1,10,20,2,$walletId,$pppmId,$microgiftId,$sequence,'Community Meal',2500,'USD',$rewardStatus,$recalledAt,$recalledBy,$recallReason]);
}

phase5_assert(mg_public_donations_recall_schema_ready($pdo), 'Phase 5 schema should be ready.');
$preview = mg_public_donations_recall_preview($pdo, 1, '123e4567-e89b-42d3-a456-426614174050');
phase5_assert($preview['counts']['original'] === 8, 'Original count is incorrect.');
phase5_assert($preview['counts']['recallable'] === 2, 'Recallable count is incorrect.');
phase5_assert($preview['counts']['regifted'] === 1, 'Regifted count is incorrect.');
phase5_assert($preview['counts']['claimed'] === 1, 'Claimed count is incorrect.');
phase5_assert($preview['counts']['redeemed'] === 1, 'Redeemed count is incorrect.');
phase5_assert($preview['counts']['expired'] === 1, 'Expired count is incorrect.');
phase5_assert($preview['counts']['cancelled'] === 1, 'Cancelled count is incorrect.');
phase5_assert($preview['counts']['already_recalled'] === 1, 'Already recalled count is incorrect.');
phase5_assert($preview['maximum_recall_quantity'] === 2, 'Maximum recall quantity is incorrect.');

$idempotency = 'public-donation-recall:mysql-0001';
$recall = mg_public_donations_recall_execute($pdo,1,1,'123e4567-e89b-42d3-a456-426614174050',1,'Inventory correction',$idempotency);
phase5_assert($recall['duplicate'] === false && $recall['completed_quantity'] === 1, 'First recall did not complete.');
phase5_assert((string)$pdo->query('SELECT status FROM wallet_items WHERE id=101')->fetchColumn() === 'cancelled', 'Selected wallet item was not cancelled.');
phase5_assert((string)$pdo->query('SELECT status FROM pppm_items WHERE id=201')->fetchColumn() === 'cancelled', 'Selected PPPM item was not cancelled.');
phase5_assert((string)$pdo->query('SELECT status FROM microgift_instances WHERE id=301')->fetchColumn() === 'cancelled', 'Selected Microgift was not cancelled.');
phase5_assert((string)$pdo->query('SELECT state FROM microgift_inbox_items WHERE instance_id=301')->fetchColumn() === 'revoked', 'Inbox projection was not revoked.');
phase5_assert((string)$pdo->query('SELECT status FROM campaign_donation_rewards WHERE id=1')->fetchColumn() === 'recalled', 'Donation attribution was not recalled.');
phase5_assert((int)$pdo->query('SELECT recalled_quantity FROM campaign_donation_batches WHERE id=50')->fetchColumn() === 2, 'Batch recalled quantity is incorrect.');
phase5_assert((string)$pdo->query('SELECT status FROM campaign_donation_batches WHERE id=50')->fetchColumn() === 'partially_recalled', 'Batch status is incorrect.');
phase5_assert((int)$pdo->query('SELECT issued_count FROM campaigns WHERE id=10')->fetchColumn() === 6, 'Campaign inventory was not restored.');
phase5_assert((int)$pdo->query('SELECT issued_count FROM reward_templates WHERE id=20')->fetchColumn() === 6, 'Template inventory was not restored.');
phase5_assert(phase5_count($pdo, 'campaign_donation_operations', "operation_kind='recall'") === 1, 'One recall operation is required.');
phase5_assert(phase5_count($pdo, 'pppm_item_events') === 1, 'PPPM recall event is required.');
phase5_assert(phase5_count($pdo, 'microgift_lifecycle_actions') === 1, 'Microgift lifecycle action is required.');
phase5_assert(phase5_count($pdo, 'campaign_events', "event_type='public_donations.recall.completed'") === 1, 'Campaign recall event is required.');
phase5_assert(count($GLOBALS['phase5_notifications']) === 1, 'One recall notification is required.');
phase5_assert((int)$pdo->query('SELECT user_id FROM wallet_items WHERE id=103')->fetchColumn() === 3, 'Downstream wallet owner was changed.');
phase5_assert((string)$pdo->query('SELECT status FROM wallet_items WHERE id=103')->fetchColumn() === 'issued', 'Downstream wallet status was changed.');
phase5_assert((int)$pdo->query('SELECT owner_user_id FROM pppm_items WHERE id=203')->fetchColumn() === 3, 'Downstream PPPM owner was changed.');
phase5_assert((int)$pdo->query('SELECT owner_user_id FROM microgift_instances WHERE id=303')->fetchColumn() === 3, 'Downstream Microgift owner was changed.');

$counts = [
    'operations'=>phase5_count($pdo, 'campaign_donation_operations'),
    'events'=>phase5_count($pdo, 'campaign_events'),
    'notifications'=>count($GLOBALS['phase5_notifications']),
];
$duplicate = mg_public_donations_recall_execute($pdo,1,1,'123e4567-e89b-42d3-a456-426614174050',1,'Inventory correction',$idempotency);
phase5_assert($duplicate['duplicate'] === true, 'Recall retry must be idempotent.');
phase5_assert(phase5_count($pdo, 'campaign_donation_operations') === $counts['operations'], 'Duplicate recall created an operation.');
phase5_assert(phase5_count($pdo, 'campaign_events') === $counts['events'], 'Duplicate recall created an event.');
phase5_assert(count($GLOBALS['phase5_notifications']) === $counts['notifications'], 'Duplicate recall created a notification.');

$hashConflict = false;
try {
    mg_public_donations_recall_execute($pdo,1,1,'123e4567-e89b-42d3-a456-426614174050',1,'Different reason',$idempotency);
} catch (RuntimeException $error) { $hashConflict = $error->getCode() === 409; }
phase5_assert($hashConflict, 'Recall idempotency hash conflict must fail.');

$tooMany = false;
try {
    mg_public_donations_recall_execute($pdo,1,1,'123e4567-e89b-42d3-a456-426614174050',2,'Too many','public-donation-recall:mysql-0002');
} catch (RuntimeException $error) { $tooMany = $error->getCode() === 409; }
phase5_assert($tooMany, 'Recall quantity above current eligibility must fail.');
phase5_assert((string)$pdo->query('SELECT status FROM wallet_items WHERE id=102')->fetchColumn() === 'issued', 'Rejected recall changed the remaining eligible unit.');

$campaignBeforeRollback = (int)$pdo->query('SELECT issued_count FROM campaigns WHERE id=10')->fetchColumn();
$templateBeforeRollback = (int)$pdo->query('SELECT issued_count FROM reward_templates WHERE id=20')->fetchColumn();
$GLOBALS['phase5_fail_on_lifecycle_call'] = (int)$GLOBALS['phase5_lifecycle_calls'] + 2;
$rollback = false;
try {
    mg_public_donations_recall_execute($pdo,1,1,'123e4567-e89b-42d3-a456-426614174051',2,'Rollback test','public-donation-recall:mysql-rollback');
} catch (RuntimeException $error) { $rollback = str_contains($error->getMessage(), 'Simulated recall lifecycle failure'); }
phase5_assert($rollback, 'Simulated lifecycle failure must surface.');
phase5_assert((string)$pdo->query('SELECT status FROM wallet_items WHERE id=109')->fetchColumn() === 'issued', 'Rollback did not restore first wallet item.');
phase5_assert((string)$pdo->query('SELECT status FROM pppm_items WHERE id=209')->fetchColumn() === 'delivered', 'Rollback did not restore first PPPM item.');
phase5_assert((string)$pdo->query('SELECT status FROM microgift_instances WHERE id=309')->fetchColumn() === 'delivered', 'Rollback did not restore first Microgift.');
phase5_assert((string)$pdo->query('SELECT status FROM campaign_donation_rewards WHERE id=9')->fetchColumn() === 'allocated', 'Rollback did not restore first attribution.');
phase5_assert((int)$pdo->query('SELECT recalled_quantity FROM campaign_donation_batches WHERE id=51')->fetchColumn() === 0, 'Rollback changed batch recall totals.');
phase5_assert((int)$pdo->query('SELECT issued_count FROM campaigns WHERE id=10')->fetchColumn() === $campaignBeforeRollback, 'Rollback changed campaign inventory.');
phase5_assert((int)$pdo->query('SELECT issued_count FROM reward_templates WHERE id=20')->fetchColumn() === $templateBeforeRollback, 'Rollback changed template inventory.');
phase5_assert(phase5_count($pdo, 'campaign_donation_operations', "idempotency_key='public-donation-recall:mysql-rollback'") === 0, 'Failed recall operation was not rolled back.');
phase5_assert(count($GLOBALS['phase5_notifications']) === 1, 'Failed recall created a notification.');

echo "Public Donations recall MySQL lifecycle valid.\n";
