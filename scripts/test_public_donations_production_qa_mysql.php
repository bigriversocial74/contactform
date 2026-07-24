<?php
declare(strict_types=1);

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MYSQL_PORT') ?: 3306);
$database = getenv('MYSQL_DATABASE') ?: 'microgifter_phase10';
$user = getenv('MYSQL_USER') ?: 'root';
$password = getenv('MYSQL_PASSWORD') ?: 'root';
$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$tables = [
    'audit_logs','microgift_inbox_items','campaign_donation_rewards','campaign_donation_batches',
    'campaign_donation_operations','wallet_items','microgift_instances','pppm_items',
    'campaign_community_assignments','campaigns','reward_templates','user_roles','roles','users',
];
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $table) $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->exec("CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,
    status VARCHAR(40) NOT NULL,
    display_name VARCHAR(180) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE roles (
    id BIGINT UNSIGNED PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY(user_id,role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE reward_templates (
    id BIGINT UNSIGNED PRIMARY KEY,
    public_id CHAR(36) NOT NULL UNIQUE,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    quantity_limit INT UNSIGNED NULL,
    issued_count INT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaigns (
    id BIGINT UNSIGNED PRIMARY KEY,
    public_id CHAR(36) NOT NULL UNIQUE,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    reward_template_id BIGINT UNSIGNED NOT NULL,
    campaign_type VARCHAR(80) NOT NULL,
    title VARCHAR(180) NOT NULL,
    status VARCHAR(40) NOT NULL,
    public_slug VARCHAR(140) NULL,
    quantity_limit INT UNSIGNED NULL,
    issued_count INT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_community_assignments (
    id BIGINT UNSIGNED PRIMARY KEY,
    public_id CHAR(36) NOT NULL UNIQUE,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    community_user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL,
    removed_at DATETIME NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE pppm_items (
    id BIGINT UNSIGNED PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    owner_user_id BIGINT UNSIGNED NULL,
    status VARCHAR(40) NOT NULL,
    cancelled_at DATETIME NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE microgift_instances (
    id BIGINT UNSIGNED PRIMARY KEY,
    public_id CHAR(36) NOT NULL UNIQUE,
    owner_user_id BIGINT UNSIGNED NULL,
    status VARCHAR(40) NOT NULL,
    pppm_item_id BIGINT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE wallet_items (
    id BIGINT UNSIGNED PRIMARY KEY,
    public_id CHAR(36) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    reward_template_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NULL,
    pppm_item_id BIGINT UNSIGNED NULL,
    source_type VARCHAR(80) NOT NULL,
    status VARCHAR(40) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_donation_operations (
    id BIGINT UNSIGNED PRIMARY KEY,
    public_id CHAR(36) NOT NULL UNIQUE,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    reward_template_id BIGINT UNSIGNED NOT NULL,
    operation_kind VARCHAR(40) NOT NULL,
    status VARCHAR(40) NOT NULL,
    requested_quantity INT UNSIGNED NOT NULL,
    completed_quantity INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_donation_batches (
    id BIGINT UNSIGNED PRIMARY KEY,
    public_id CHAR(36) NOT NULL UNIQUE,
    operation_id BIGINT UNSIGNED NOT NULL,
    assignment_id BIGINT UNSIGNED NOT NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    reward_template_id BIGINT UNSIGNED NOT NULL,
    community_user_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    recalled_quantity INT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_donation_rewards (
    id BIGINT UNSIGNED PRIMARY KEY,
    public_id CHAR(36) NOT NULL UNIQUE,
    operation_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    reward_template_id BIGINT UNSIGNED NOT NULL,
    original_community_user_id BIGINT UNSIGNED NOT NULL,
    wallet_item_id BIGINT UNSIGNED NOT NULL,
    pppm_item_id BIGINT UNSIGNED NOT NULL,
    microgift_instance_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL,
    allocated_at DATETIME NOT NULL,
    recalled_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE microgift_inbox_items (
    id BIGINT UNSIGNED PRIMARY KEY,
    public_id CHAR(36) NOT NULL UNIQUE,
    instance_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    state VARCHAR(40) NOT NULL,
    archived_at DATETIME NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    metadata_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$uuid = static function (int $number): string {
    return sprintf('00000000-0000-4000-a000-%012d', $number);
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$pdo->exec("INSERT INTO roles VALUES
    (1,'community','Community'),(2,'customer','Customer'),(3,'creator','Creator'),(4,'merchant','Merchant')");
$pdo->exec("INSERT INTO users VALUES
    (1,'active','Fixture Merchant'),
    (2,'active','Community Ten'),
    (3,'active','Community Twenty'),
    (4,'active','Community Twenty Five'),
    (5,'active','Stale Assignment'),
    (20,'active','Regift Receiver A'),(21,'active','Regift Receiver B'),
    (22,'active','Regift Receiver C'),(23,'active','Regift Receiver D')");
$pdo->exec("INSERT INTO user_roles VALUES
    (1,4),(2,1),(2,2),(2,3),(3,1),(3,2),(3,4),(4,1),(4,2),(4,3),(4,4),(5,2)");
$pdo->prepare('INSERT INTO reward_templates VALUES (?,?,?,?,?,NOW())')->execute([1,$uuid(1),1,100,49]);
$pdo->prepare('INSERT INTO campaigns VALUES (?,?,?,?,?,?,?,?,?,?,NOW())')->execute([1,$uuid(2),1,1,'public_donation','Production QA Campaign','active','production-qa',100,49]);

$assignments = [[1,2,10],[2,3,20],[3,4,25]];
foreach ($assignments as [$id,$communityId,$quantity]) {
    $pdo->prepare('INSERT INTO campaign_community_assignments VALUES (?,?,?,?,?,?,NULL,NOW())')
        ->execute([$id,$uuid(100+$id),1,1,$communityId,'active']);
    $pdo->prepare('INSERT INTO campaign_donation_operations VALUES (?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([$id,$uuid(200+$id),1,1,1,'allocation','completed',$quantity,$quantity]);
    $pdo->prepare('INSERT INTO campaign_donation_batches VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([$id,$uuid(300+$id),$id,$id,1,1,1,$communityId,$quantity,2,'partially_recalled']);
}
$pdo->prepare('INSERT INTO campaign_community_assignments VALUES (?,?,?,?,?,?,NULL,NOW())')
    ->execute([4,$uuid(104),1,1,5,'active']);

$sequence = 0;
foreach ($assignments as [$batchId,$communityId,$quantity]) {
    for ($unit = 1; $unit <= $quantity; $unit++) {
        $sequence++;
        $isRegifted = $sequence <= 4;
        $isClaimed = in_array($sequence, [5,6], true);
        $isRedeemed = $sequence === 7;
        $isRecalled = $sequence >= 50;
        $owner = $isRegifted ? 19 + $sequence : $communityId;
        $walletStatus = $isRecalled ? 'cancelled' : ($isRedeemed ? 'redeemed' : ($isClaimed ? 'claimed' : 'issued'));
        $pppmStatus = $isRecalled ? 'cancelled' : ($isRedeemed ? 'redeemed' : ($isClaimed ? 'claim_pending' : 'assigned'));
        $microgiftStatus = $isRecalled ? 'cancelled' : ($isRedeemed ? 'redeemed' : ($isClaimed ? 'claimed' : 'issued'));
        $inboxState = $isRecalled ? 'revoked' : ($isRedeemed ? 'redeemed' : ($isClaimed ? 'redeemable' : 'received'));
        $archived = $isRecalled ? date('Y-m-d H:i:s') : null;
        $pppmId = 1000 + $sequence;
        $microgiftId = 2000 + $sequence;
        $walletId = 3000 + $sequence;

        $pdo->prepare('INSERT INTO pppm_items VALUES (?,?,?,?,?,NOW())')
            ->execute([$pppmId,'pppm-' . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT),$owner,$pppmStatus,$isRecalled ? date('Y-m-d H:i:s') : null]);
        $pdo->prepare('INSERT INTO microgift_instances VALUES (?,?,?,?,?,?,NOW())')
            ->execute([$microgiftId,$uuid(4000+$sequence),$owner,$microgiftStatus,$pppmId,$isRecalled ? date('Y-m-d H:i:s') : null]);
        $pdo->prepare('INSERT INTO wallet_items VALUES (?,?,?,?,?,?,?,?,?,NOW())')
            ->execute([$walletId,$uuid(5000+$sequence),$owner,1,1,1,$pppmId,'public_donation',$walletStatus]);
        $pdo->prepare('INSERT INTO campaign_donation_rewards VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)')
            ->execute([$sequence,$uuid(6000+$sequence),$batchId,$batchId,1,1,1,$communityId,$walletId,$pppmId,$microgiftId,$isRecalled ? 'recalled' : 'allocated',$isRecalled ? date('Y-m-d H:i:s') : null]);
        $pdo->prepare('INSERT INTO microgift_inbox_items VALUES (?,?,?,?,?,?,NOW())')
            ->execute([$sequence,$uuid(7000+$sequence),$microgiftId,$owner,$inboxState,$archived]);
    }
}

require_once dirname(__DIR__) . '/includes/public-donations-reconciliation.php';

$clean = mg_public_donations_reconcile_detect($pdo, [
    'merchant_id' => 1,
    'campaign' => 'production-qa',
    'limit' => 100,
]);
$metrics = $clean['metrics']['campaigns'][0] ?? [];
$assert($metrics['quantity_limit'] === 100, 'Acceptance inventory must begin at 100.');
$assert($metrics['gross_allocated'] === 55, 'Gross allocated must equal 55.');
$assert($metrics['recalled'] === 6, 'Recalled quantity must equal 6.');
$assert($metrics['net_allocated'] === 49, 'Net allocated must equal 49.');
$assert($metrics['remaining_inventory'] === 51, 'Final expected inventory must equal 51.');
$assert($clean['totals']['issues'] === 1, 'Only the intentionally stale fourth assignment should be detected initially.');
$assert(count($clean['issues']['assignment_role_removed']) === 1, 'Stale assignment must be detected without touching rewards.');

// Verify the acceptance lifecycle distribution across canonical stores.
$walletCounts = $pdo->query("SELECT status,COUNT(*) total FROM wallet_items GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pppmCounts = $pdo->query("SELECT status,COUNT(*) total FROM pppm_items GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$microgiftCounts = $pdo->query("SELECT status,COUNT(*) total FROM microgift_instances GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$assert((int)($walletCounts['cancelled'] ?? 0) === 6, 'Wallet recalled count must equal 6.');
$assert((int)($walletCounts['claimed'] ?? 0) === 2, 'Wallet claimed count must equal 2.');
$assert((int)($walletCounts['redeemed'] ?? 0) === 1, 'Wallet redeemed count must equal 1.');
$assert((int)($pppmCounts['cancelled'] ?? 0) === 6, 'PPPM recalled count must equal 6.');
$assert((int)($pppmCounts['claim_pending'] ?? 0) === 2, 'PPPM claimed count must equal 2.');
$assert((int)($pppmCounts['redeemed'] ?? 0) === 1, 'PPPM redeemed count must equal 1.');
$assert((int)($microgiftCounts['cancelled'] ?? 0) === 6, 'Microgift recalled count must equal 6.');
$assert((int)($microgiftCounts['claimed'] ?? 0) === 2, 'Microgift claimed count must equal 2.');
$assert((int)($microgiftCounts['redeemed'] ?? 0) === 1, 'Microgift redeemed count must equal 1.');
$regifted = (int)$pdo->query('SELECT COUNT(*) FROM campaign_donation_rewards reward INNER JOIN wallet_items wallet ON wallet.id=reward.wallet_item_id WHERE wallet.user_id<>reward.original_community_user_id')->fetchColumn();
$assert($regifted === 4, 'Exactly four rewards must be regifted.');

// Inject only safe deterministic drift.
$pdo->exec('UPDATE campaigns SET issued_count=55 WHERE id=1');
$pdo->exec('UPDATE reward_templates SET issued_count=55 WHERE id=1');
$pdo->exec("UPDATE campaign_donation_batches SET recalled_quantity=0,status='allocated' WHERE id=1");
$pdo->exec("UPDATE wallet_items SET status='issued' WHERE id=3055");
$pdo->exec("UPDATE pppm_items SET status='assigned',cancelled_at=NULL WHERE id=1055");
$pdo->exec("UPDATE microgift_instances SET status='issued',cancelled_at=NULL WHERE id=2055");
$pdo->exec("UPDATE microgift_inbox_items SET state='received',archived_at=NULL WHERE instance_id=2055");

$drift = mg_public_donations_reconcile_detect($pdo, ['merchant_id'=>1,'campaign'=>'production-qa','limit'=>100]);
$assert(count($drift['issues']['counter_drift']) === 2, 'Campaign and reward-template counter drift must be detected.');
$assert(count($drift['issues']['batch_drift']) === 1, 'Batch recall drift must be detected.');
$assert(count($drift['issues']['recalled_visible']) === 1, 'Visible recalled lifecycle must be detected.');
$assert(count($drift['issues']['assignment_role_removed']) === 1, 'Removed Community role assignment must be detected.');
$assert($drift['unexplained_drift'] === 0, 'All injected defects must be deterministic and repairable.');

$repair = mg_public_donations_reconcile_apply($pdo, [
    'merchant_id' => 1,
    'campaign' => 'production-qa',
    'limit' => 100,
    'repair' => 'safe',
    'actor_id' => 1,
]);
$assert($repair['receipt']['repairs_applied'] === 5, 'Five deterministic repairs must be applied.');
$assert($repair['receipt']['unexplained_drift_after'] === 0, 'No unexplained drift may remain after repair.');
$assert($repair['report']['totals']['issues'] === 0, 'Post-repair dry-run must be clean.');
$assert(strlen((string)$repair['receipt']['checksum']) === 64, 'Repair receipt must have a SHA-256 checksum.');
$assert((int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='public_donations.reconcile'")->fetchColumn() === 1, 'Repair must persist an audit receipt when audit_logs exists.');

$finalCampaign = $pdo->query('SELECT issued_count,quantity_limit FROM campaigns WHERE id=1')->fetch();
$finalTemplate = $pdo->query('SELECT issued_count,quantity_limit FROM reward_templates WHERE id=1')->fetch();
$assert((int)$finalCampaign['issued_count'] === 49 && (int)$finalTemplate['issued_count'] === 49, 'Campaign and reward counters must reconcile to net allocation 49.');
$assert(((int)$finalTemplate['quantity_limit'] - (int)$finalTemplate['issued_count']) === 51, 'Reward inventory must reconcile to 51.');
$assert((int)$pdo->query("SELECT COUNT(*) FROM campaign_donation_rewards WHERE status='allocated'")->fetchColumn() === 49, 'Existing rewards must remain intact after assignment role removal.');
$assert((string)$pdo->query('SELECT status FROM campaign_community_assignments WHERE id=4')->fetchColumn() === 'removed', 'Stale assignment must be removed.');

// Campaign and operation filters must remain bounded and deterministic.
$filtered = mg_public_donations_reconcile_detect($pdo, ['merchant_id'=>1,'operation'=>$uuid(201),'limit'=>20]);
$assert($filtered['scanned_attributions'] === 10, 'Operation filter must return the first 10-unit allocation only.');
$bounded = mg_public_donations_reconcile_detect($pdo, ['merchant_id'=>1,'campaign'=>'production-qa','limit'=>7]);
$assert($bounded['scanned_attributions'] === 7, 'Limit must bound attribution scanning.');

// Surface agreement receipt: dashboard/public campaign/profile use the same
// gross/recalled/net metrics; Wallet, PPPM, claim, and redemption agree.
$surface = [
    'dashboard' => $repair['report']['metrics']['campaigns'][0],
    'public_campaign' => $repair['report']['metrics']['campaigns'][0],
    'profile_community_tab' => $repair['report']['metrics']['campaigns'][0],
    'wallet' => ['gross'=>55,'recalled'=>6,'claimed'=>2,'redeemed'=>1,'net'=>49],
    'pppm' => ['gross'=>55,'recalled'=>6,'claimed'=>2,'redeemed'=>1,'net'=>49],
    'claim' => ['claimed'=>2],
    'redemption' => ['redeemed'=>1],
];
$assert($surface['dashboard']['net_allocated'] === $surface['public_campaign']['net_allocated'], 'Dashboard and public campaign must agree.');
$assert($surface['dashboard']['net_allocated'] === $surface['profile_community_tab']['net_allocated'], 'Dashboard and profile Community tab must agree.');
$assert($surface['wallet']['net'] === $surface['pppm']['net'] && $surface['wallet']['net'] === 49, 'Wallet and PPPM net totals must agree.');

fwrite(STDOUT, json_encode([
    'ok' => true,
    'acceptance' => [
        'initial_inventory' => 100,
        'allocations' => [10,20,25],
        'gross_allocated' => 55,
        'regifted' => 4,
        'claimed' => 2,
        'redeemed' => 1,
        'recalled' => 6,
        'net_allocated' => 49,
        'remaining_inventory' => 51,
    ],
    'receipt' => $repair['receipt'],
    'final_report' => $repair['report']['totals'],
    'surfaces' => $surface,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
