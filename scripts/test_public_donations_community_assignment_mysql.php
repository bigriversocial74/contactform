<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }

$GLOBALS['phase3_notifications'] = [];
function mg_create_notification(PDO $pdo, int $userId, string $type, string $title, ?string $body = null, ?string $actionUrl = null, array $context = []): string
{
    $id = 'notification-' . (count($GLOBALS['phase3_notifications']) + 1);
    $GLOBALS['phase3_notifications'][] = compact('id', 'userId', 'type', 'title', 'body', 'actionUrl', 'context');
    return $id;
}
function mg_audit(string $action, string $entityType = 'system', array $metadata = [], ?int $userId = null): void {}

require_once dirname(__DIR__) . '/includes/public-donations-community-assignments.php';

function phase3_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = getenv('MYSQL_PORT') ?: '3306';
$db = getenv('MYSQL_DATABASE') ?: 'microgifter_phase3';
$user = getenv('MYSQL_USER') ?: 'root';
$pass = getenv('MYSQL_PASSWORD') ?: 'root';
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['wallet_items','campaign_community_assignments','campaigns','user_roles','roles','public_profiles','users'] as $table) {
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
    PRIMARY KEY(id),UNIQUE KEY uq_profile_public(public_id),UNIQUE KEY uq_profile_user(user_id),
    CONSTRAINT fk_profile_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
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
    PRIMARY KEY(user_id,role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaigns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    public_slug VARCHAR(120) NOT NULL,
    title VARCHAR(180) NOT NULL,
    campaign_type VARCHAR(80) NOT NULL,
    status ENUM('draft','active','paused','ended','archived') NOT NULL DEFAULT 'draft',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_campaign_public(public_id),UNIQUE KEY uq_campaign_slug(merchant_user_id,public_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE campaign_community_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    community_user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','paused','removed') NOT NULL DEFAULT 'active',
    public_display_status ENUM('pending','approved','declined','revoked') NOT NULL DEFAULT 'pending',
    decision_by_user_id BIGINT UNSIGNED NULL,
    decision_at DATETIME NULL,
    decision_note VARCHAR(500) NULL,
    added_by_user_id BIGINT UNSIGNED NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reactivated_at DATETIME NULL,
    paused_at DATETIME NULL,
    removed_at DATETIME NULL,
    last_allocated_at DATETIME NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),UNIQUE KEY uq_assignment_public(public_id),
    UNIQUE KEY uq_campaign_community_assignment(campaign_id,community_user_id),
    KEY idx_assignment_merchant(merchant_user_id,campaign_id,status),
    CONSTRAINT fk_assignment_campaign FOREIGN KEY(campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignment_user FOREIGN KEY(community_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE wallet_items (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(id)) ENGINE=InnoDB");

$pdo->exec("INSERT INTO users(id,full_name,display_name,status) VALUES
    (1,'Merchant Owner','Merchant Owner','active'),
    (2,'Community One','Community One','active'),
    (3,'Community Multi','Community Multi','active'),
    (4,'Disabled Community','Disabled Community','disabled'),
    (5,'Regular Customer','Regular Customer','active')");
$pdo->exec("INSERT INTO public_profiles(public_id,user_id,slug,display_name,avatar_url,location_label,visibility,status) VALUES
    ('pp_merchant',1,'merchant-owner','Merchant Owner',NULL,'Phoenix area','public','active'),
    ('pp_community_one',2,'community-one','Community One','/avatars/one.jpg','Phoenix area','public','active'),
    ('pp_community_multi',3,'community-multi','Community Multi','/avatars/multi.jpg','Tempe area','unlisted','active'),
    ('pp_disabled',4,'disabled-community','Disabled Community',NULL,'Mesa area','public','active'),
    ('pp_customer',5,'regular-customer','Regular Customer',NULL,'Scottsdale area','public','active')");
$pdo->exec("INSERT INTO roles(id,slug,name) VALUES
    (1,'merchant','Merchant'),(2,'community','Community'),(3,'customer','Customer'),
    (4,'creator','Creator'),(5,'admin','Admin'),(6,'super_admin','Super Admin')");
$pdo->exec("INSERT INTO user_roles(user_id,role_id) VALUES
    (1,1),(2,2),(2,3),(3,2),(3,1),(3,4),(3,5),(4,2),(5,3)");
$pdo->exec("INSERT INTO campaigns(id,public_id,merchant_user_id,public_slug,title,campaign_type,status)
    VALUES (10,'123e4567-e89b-42d3-a456-426614174010',1,'community-impact','Community Impact','public_donation','active')");

phase3_assert(mg_public_donations_assignment_schema_ready($pdo), 'Assignment schema should be ready.');
$campaign = mg_public_donations_assignment_campaign($pdo, 1, 'community-impact');
phase3_assert((int)$campaign['id'] === 10, 'Campaign lookup failed.');

$search = mg_public_donations_assignment_search($pdo, 1, 10, '', 50);
phase3_assert(count($search) === 2, 'Search must return exactly the two active Community users.');
$keys = array_column($search, 'community_account_id');
sort($keys);
phase3_assert($keys === ['pp_community_multi', 'pp_community_one'], 'Disabled and non-Community users must not appear.');
$multi = array_values(array_filter($search, static fn(array $row): bool => $row['community_account_id'] === 'pp_community_multi'))[0] ?? null;
phase3_assert(is_array($multi), 'Multi-role Community account missing.');
phase3_assert($multi['other_roles'] === ['Creator', 'Merchant'], 'Administrative roles must be filtered from public identity.');
phase3_assert($multi['community_badge'] === true, 'Community badge missing.');

$added = mg_public_donations_assignment_mutate($pdo, 1, 1, 'community-impact', 'add', 'pp_community_one');
phase3_assert($added['changed'] === true, 'First add must change state.');
phase3_assert(count($GLOBALS['phase3_notifications']) === 1, 'First add must create one notification.');
$assignmentId = (string)$added['assignment_id'];
phase3_assert($assignmentId !== '', 'Assignment public ID missing.');

$duplicate = mg_public_donations_assignment_mutate($pdo, 1, 1, 'community-impact', 'add', 'pp_community_one');
phase3_assert($duplicate['changed'] === false, 'Duplicate active add must be idempotent.');
phase3_assert(count($GLOBALS['phase3_notifications']) === 1, 'Duplicate active add must not notify again.');

$paused = mg_public_donations_assignment_mutate($pdo, 1, 1, 'community-impact', 'pause', '', $assignmentId);
phase3_assert($paused['changed'] === true, 'Pause must change active assignment.');
phase3_assert((string)$pdo->query("SELECT status FROM campaign_community_assignments WHERE public_id=" . $pdo->quote($assignmentId))->fetchColumn() === 'paused', 'Pause state not persisted.');

$readded = mg_public_donations_assignment_mutate($pdo, 1, 1, 'community-impact', 'add', 'pp_community_one');
phase3_assert($readded['changed'] === true, 'Re-adding paused assignment must reactivate it.');
phase3_assert(count($GLOBALS['phase3_notifications']) === 2, 'Reactivation through add must notify once.');

$removed = mg_public_donations_assignment_mutate($pdo, 1, 1, 'community-impact', 'remove', '', $assignmentId);
phase3_assert($removed['changed'] === true, 'Remove must change assignment.');
$reactivated = mg_public_donations_assignment_mutate($pdo, 1, 1, 'community-impact', 'reactivate', '', $assignmentId);
phase3_assert($reactivated['changed'] === true, 'Explicit reactivation must change removed assignment.');
phase3_assert(count($GLOBALS['phase3_notifications']) === 3, 'Explicit reactivation must notify once.');

$summary = mg_public_donations_assignment_summary($pdo, 1, 10);
phase3_assert($summary === ['total' => 1, 'active' => 1, 'paused' => 0, 'removed' => 0], 'Assignment summary is incorrect.');
$list = mg_public_donations_assignment_list($pdo, 1, 10, 'active', 100);
phase3_assert(count($list) === 1 && $list[0]['assignment']['status'] === 'active', 'Active assignment list is incorrect.');
phase3_assert((int)$pdo->query('SELECT COUNT(*) FROM wallet_items')->fetchColumn() === 0, 'Assignment lifecycle must not create wallet inventory.');

$pdo->exec("UPDATE campaigns SET status='ended' WHERE id=10");
$endedRejected = false;
try {
    mg_public_donations_assignment_mutate($pdo, 1, 1, 'community-impact', 'add', 'pp_community_multi');
} catch (RuntimeException $error) {
    $endedRejected = $error->getCode() === 409;
}
phase3_assert($endedRejected, 'Ended campaign must reject new assignments.');
phase3_assert((int)$pdo->query('SELECT COUNT(*) FROM campaign_community_assignments WHERE community_user_id=3')->fetchColumn() === 0, 'Rejected ended-campaign add must roll back.');
phase3_assert((int)$pdo->query('SELECT COUNT(*) FROM wallet_items')->fetchColumn() === 0, 'Rejected add must not affect wallet inventory.');

echo "Public Donations Community MySQL lifecycle valid.\n";
