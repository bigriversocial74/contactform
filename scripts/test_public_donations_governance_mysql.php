<?php
declare(strict_types=1);

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MYSQL_PORT') ?: 3306);
$database = getenv('MYSQL_DATABASE') ?: 'microgifter_phase9';
$user = getenv('MYSQL_USER') ?: 'root';
$password = getenv('MYSQL_PASSWORD') ?: 'root';
$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

foreach (['campaign_donation_operations','campaign_donation_rewards','public_profiles','campaigns','users'] as $table) {
    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
}

$pdo->exec("CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,
    status VARCHAR(40) NOT NULL,
    display_name VARCHAR(180) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE public_profiles (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    status VARCHAR(40) NOT NULL,
    visibility VARCHAR(40) NOT NULL,
    display_name VARCHAR(180) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaigns (
    id BIGINT UNSIGNED PRIMARY KEY,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_type VARCHAR(80) NOT NULL,
    title VARCHAR(180) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_donation_rewards (
    id BIGINT UNSIGNED PRIMARY KEY,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    original_community_user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_donation_operations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    operation_kind VARCHAR(40) NOT NULL,
    status VARCHAR(40) NOT NULL,
    requested_quantity INT UNSIGNED NOT NULL,
    completed_quantity INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_governance_budget (merchant_user_id,operation_kind,created_at,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("INSERT INTO users VALUES
    (1,'active','Merchant One'),
    (2,'active','Visible Community'),
    (3,'disabled','Disabled Community'),
    (4,'active','Erased Profile Community'),
    (9,'active','Other Merchant'),
    (10,'active','Other Community')");
$pdo->exec("INSERT INTO public_profiles VALUES
    (1,'active','public','Merchant One'),
    (2,'active','public','Visible Community'),
    (3,'active','private','Disabled Community'),
    (9,'active','public','Other Merchant'),
    (10,'active','public','Other Community')");
$pdo->exec("INSERT INTO campaigns VALUES
    (101,1,'public_donation','Merchant One Support'),
    (201,9,'public_donation','Other Merchant Support'),
    (301,1,'newsletter_signup','Newsletter')");
$pdo->exec("INSERT INTO campaign_donation_rewards VALUES
    (1001,1,101,2,'allocated'),
    (1002,1,101,3,'allocated'),
    (1003,1,101,4,'recalled'),
    (1004,1,101,5,'allocated'),
    (2001,9,201,10,'allocated')");
$pdo->exec("INSERT INTO campaign_donation_operations
    (merchant_user_id,operation_kind,status,requested_quantity,completed_quantity,created_at) VALUES
    (1,'allocation','completed',4,4,NOW()),
    (1,'allocation','processing',2,0,NOW()),
    (1,'allocation','completed',100,100,DATE_SUB(NOW(),INTERVAL 2 HOUR)),
    (9,'allocation','completed',100,100,NOW()),
    (1,'recall','completed',2,2,NOW())");

require_once dirname(__DIR__) . '/includes/public-donations-governance.php';
require_once dirname(__DIR__) . '/includes/public-donations-governance-locks.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

putenv('MG_PUBLIC_DONATIONS_FEATURE_STATE=disabled');
$assert(!mg_public_donations_is_enabled_for(1, null), 'Disabled rollout must fail closed.');
putenv('MG_PUBLIC_DONATIONS_FEATURE_STATE=enabled');
$assert(mg_public_donations_is_enabled_for(1, null), 'Enabled rollout must allow the merchant.');
putenv('MG_PUBLIC_DONATIONS_FEATURE_STATE=selected_merchants');
putenv('MG_PUBLIC_DONATIONS_MERCHANT_IDS=1,5');
$assert(mg_public_donations_is_enabled_for(1, null), 'Selected merchant must be enabled.');
$assert(!mg_public_donations_is_enabled_for(2, null), 'Unselected merchant must remain disabled.');
putenv('MG_PUBLIC_DONATIONS_FEATURE_STATE=admin_only');
$assert(mg_public_donations_is_enabled_for(1, ['roles' => ['admin']]), 'Admin-only rollout must allow admin actor.');
$assert(!mg_public_donations_is_enabled_for(1, ['roles' => ['merchant']]), 'Admin-only rollout must reject merchant actor.');
putenv('MG_PUBLIC_DONATIONS_FEATURE_STATE=enabled');

$assert(mg_public_donations_governance_actor_active($pdo, 1), 'Active actor should pass.');
$assert(!mg_public_donations_governance_actor_active($pdo, 3), 'Disabled actor should fail.');
$assert(!mg_public_donations_governance_actor_active($pdo, 999), 'Missing actor should fail.');

putenv('MG_PUBLIC_DONATIONS_ALLOCATION_UNITS_PER_HOUR=10');
putenv('MG_PUBLIC_DONATIONS_RECALL_UNITS_PER_HOUR=5');
$pdo->beginTransaction();
$allocationBudget = mg_public_donations_governance_assert_hourly_budget($pdo, 1, 'allocation', 3);
$pdo->rollBack();
$assert($allocationBudget['used_units'] === 6, 'Allocation budget should count completed and processing units.');
$assert($allocationBudget['remaining_after'] === 1, 'Allocation remaining budget should reconcile.');

$blocked = false;
$pdo->beginTransaction();
try {
    mg_public_donations_governance_assert_hourly_budget($pdo, 1, 'allocation', 5);
} catch (RuntimeException $error) {
    $blocked = $error->getCode() === 429;
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
$assert($blocked, 'Allocation above the hourly budget must be rejected with 429.');

$pdo->beginTransaction();
$recallBudget = mg_public_donations_governance_assert_hourly_budget($pdo, 1, 'recall', 3);
$pdo->rollBack();
$assert($recallBudget['used_units'] === 2 && $recallBudget['remaining_after'] === 0, 'Recall budget should reconcile exactly.');

$lockName = mg_public_donations_governance_admit_operation($pdo, 1, 'allocation', 1);
$lockStmt = $pdo->prepare('SELECT IS_USED_LOCK(?)');
$lockStmt->execute([$lockName]);
$assert($lockStmt->fetchColumn() !== null, 'Merchant operation lock should remain held during lifecycle execution.');
mg_public_donations_governance_release_operation_lock($pdo, $lockName);
$freeStmt = $pdo->prepare('SELECT IS_FREE_LOCK(?)');
$freeStmt->execute([$lockName]);
$assert((int)$freeStmt->fetchColumn() === 1, 'Merchant operation lock should release after execution.');

$integrity = mg_public_donations_governance_integrity($pdo, 1);
$assert($integrity['reward_rows'] === 4, 'Integrity must retain all merchant attribution rows after account/profile changes.');
$assert($integrity['recalled_rows'] === 1, 'Integrity must retain recalled attribution.');
$assert($integrity['campaign_rows'] === 1, 'Integrity must retain campaign attribution.');
$assert($integrity['original_accounts'] === 4, 'Integrity should count original accounts without returning identities.');
$assert($integrity['unavailable_original_account_rows'] === 2, 'Disabled and erased-user rows should be counted as unavailable.');
$assert($integrity['aggregate_only_rows'] === 3, 'Private, erased-profile, and erased-user rows should be aggregate-only.');
$assert($integrity['identity_values_returned'] === false, 'Integrity response must contain counts only.');

$json = json_encode($integrity, JSON_THROW_ON_ERROR);
foreach (['Visible Community','Disabled Community','Erased Profile Community','Other Community'] as $identity) {
    $assert(!str_contains($json, $identity), $identity . ' must not appear in governance integrity output.');
}
$other = mg_public_donations_governance_integrity($pdo, 9);
$assert($other['reward_rows'] === 1 && $other['campaign_rows'] === 1, 'Other merchant should receive only its own integrity totals.');

$copy = mg_public_donations_governance_operational_copy();
$assert($copy['cash_donation'] === false && $copy['tax_deductible_charitable_contribution'] === false, 'Operational copy must reject cash and tax-deductible interpretations.');

echo json_encode([
    'ok' => true,
    'allocation_budget' => $allocationBudget,
    'recall_budget' => $recallBudget,
    'integrity' => $integrity,
    'privacy' => mg_public_donations_governance_privacy_contract(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
