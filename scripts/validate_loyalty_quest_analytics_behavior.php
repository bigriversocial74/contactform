<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/loyalty-quest-analytics.php';
require_once dirname(__DIR__) . '/includes/loyalty-quest-analytics-accuracy.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DB_PORT') ?: 3306);
$name = getenv('DB_NAME') ?: 'microgifter_test';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

foreach (['message_delivery_jobs','message_events','wallet_items','loyalty_quest_evidence','loyalty_quest_participations','campaign_contacts','campaigns'] as $table) {
    $pdo->exec("DROP TABLE IF EXISTS {$table}");
}

$schema = [
    "CREATE TABLE campaigns (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        public_id CHAR(36) NOT NULL,
        public_slug VARCHAR(190) NULL,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        campaign_type VARCHAR(80) NOT NULL,
        title VARCHAR(180) NOT NULL,
        status VARCHAR(30) NOT NULL,
        starts_at DATETIME NULL,
        ends_at DATETIME NULL,
        rules_json JSON NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )",
    "CREATE TABLE campaign_contacts (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        public_id CHAR(36) NOT NULL,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        campaign_id BIGINT UNSIGNED NOT NULL,
        email VARCHAR(190) NOT NULL,
        name VARCHAR(190) NULL,
        source VARCHAR(80) NULL,
        opt_in_status VARCHAR(30) NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )",
    "CREATE TABLE loyalty_quest_participations (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        public_id CHAR(36) NOT NULL,
        campaign_id BIGINT UNSIGNED NOT NULL,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        participant_user_id BIGINT UNSIGNED NOT NULL,
        contact_id BIGINT UNSIGNED NULL,
        status VARCHAR(30) NOT NULL,
        progress_count INT NOT NULL,
        required_count INT NOT NULL,
        completion_percent INT NOT NULL,
        joined_at DATETIME NOT NULL,
        started_at DATETIME NULL,
        submitted_at DATETIME NULL,
        reviewed_at DATETIME NULL,
        completed_at DATETIME NULL,
        cancelled_at DATETIME NULL,
        last_activity_at DATETIME NOT NULL,
        metadata_json JSON NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL
    )",
    "CREATE TABLE loyalty_quest_evidence (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        public_id CHAR(36) NOT NULL,
        participation_id BIGINT UNSIGNED NOT NULL,
        campaign_id BIGINT UNSIGNED NOT NULL,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        participant_user_id BIGINT UNSIGNED NOT NULL,
        evidence_type VARCHAR(40) NOT NULL,
        status VARCHAR(30) NOT NULL,
        distance_meters DECIMAL(10,2) NULL,
        verified_at DATETIME NULL,
        rejected_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL
    )",
    "CREATE TABLE wallet_items (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        public_id CHAR(36) NOT NULL,
        user_id BIGINT UNSIGNED NULL,
        merchant_user_id BIGINT UNSIGNED NOT NULL,
        campaign_id BIGINT UNSIGNED NULL,
        source_type VARCHAR(80) NOT NULL,
        status VARCHAR(30) NOT NULL,
        value_cents_snapshot INT NOT NULL,
        currency_snapshot CHAR(3) NOT NULL,
        title_snapshot VARCHAR(180) NOT NULL,
        issued_at DATETIME NOT NULL,
        claimed_at DATETIME NULL,
        redeemed_at DATETIME NULL,
        expires_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )",
    "CREATE TABLE message_events (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        event_type VARCHAR(100) NOT NULL
    )",
    "CREATE TABLE message_delivery_jobs (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        message_event_id BIGINT UNSIGNED NOT NULL,
        merchant_user_id BIGINT UNSIGNED NULL,
        campaign_id BIGINT UNSIGNED NULL,
        status VARCHAR(30) NOT NULL,
        created_at DATETIME NOT NULL
    )",
];
foreach ($schema as $statement) {
    $pdo->exec($statement);
}

$ago = static fn(int $days): string => gmdate('Y-m-d H:i:s', time() - ($days * 86400));
$ahead = static fn(int $days): string => gmdate('Y-m-d H:i:s', time() + ($days * 86400));
$now = gmdate('Y-m-d H:i:s');
$merchantCampaign = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$otherCampaign = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

$campaignInsert = $pdo->prepare(
    'INSERT INTO campaigns
     (public_id,public_slug,merchant_user_id,campaign_type,title,status,starts_at,ends_at,rules_json,created_at,updated_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
);
$campaignInsert->execute([$merchantCampaign,'merchant-quest',1,'loyalty_quest','Merchant Quest','active',$ago(10),$ahead(10),'{}',$ago(10),$now]);
$campaignInsert->execute([$otherCampaign,'other-quest',2,'loyalty_quest','Other Quest','active',$ago(10),$ahead(10),'{}',$ago(10),$now]);

$contactInsert = $pdo->prepare(
    'INSERT INTO campaign_contacts
     (public_id,merchant_user_id,campaign_id,email,name,source,opt_in_status,created_at,updated_at)
     VALUES (?,?,?,?,?,?,?,?,?)'
);
$contactInsert->execute(['cccccccc-cccc-4ccc-8ccc-ccccccccccc1',1,1,'private-one@example.test','Private One','website_embed','opted_in',$ago(8),$now]);
$contactInsert->execute(['cccccccc-cccc-4ccc-8ccc-ccccccccccc2',1,1,'private-two@example.test','Private Two','website_embed','opted_in',$ago(8),$now]);
$contactInsert->execute(['dddddddd-dddd-4ddd-8ddd-dddddddddddd',2,2,'other@example.test','Other Merchant Contact','qr','opted_in',$ago(8),$now]);

$participationInsert = $pdo->prepare(
    'INSERT INTO loyalty_quest_participations
     (public_id,campaign_id,merchant_user_id,participant_user_id,contact_id,status,progress_count,required_count,completion_percent,
      joined_at,started_at,submitted_at,reviewed_at,completed_at,last_activity_at,metadata_json,created_at,updated_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
$participationInsert->execute([
    'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee1',1,1,101,1,'completed',1,1,100,
    $ago(5),$ago(5),$ago(3),$ago(2),$ago(2),$ago(2),'{"joined_from":"website_embed"}',$ago(5),$now,
]);
$participationInsert->execute([
    'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee2',1,1,102,2,'in_progress',0,1,0,
    $ago(4),$ago(4),null,null,null,$ago(4),'{"joined_from":"website_embed"}',$ago(4),$now,
]);
$participationInsert->execute([
    'ffffffff-ffff-4fff-8fff-ffffffffffff',2,2,201,3,'completed',1,1,100,
    $ago(5),$ago(5),$ago(4),$ago(3),$ago(3),$ago(3),'{"joined_from":"qr"}',$ago(5),$now,
]);

$evidenceInsert = $pdo->prepare(
    'INSERT INTO loyalty_quest_evidence
     (public_id,participation_id,campaign_id,merchant_user_id,participant_user_id,evidence_type,status,distance_meters,verified_at,created_at,updated_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
);
for ($i = 1; $i <= 4; $i++) {
    $evidenceInsert->execute([
        sprintf('11111111-1111-4111-8111-%012d',$i),1,1,1,101,'geolocation','verified',20 + $i,$ago(2),$ago(3),$now,
    ]);
}

$walletInsert = $pdo->prepare(
    'INSERT INTO wallet_items
     (public_id,user_id,merchant_user_id,campaign_id,source_type,status,value_cents_snapshot,currency_snapshot,title_snapshot,
      issued_at,claimed_at,redeemed_at,created_at,updated_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
$walletInsert->execute(['22222222-2222-4222-8222-222222222221',101,1,1,'loyalty_quest','redeemed',2500,'USD','USD Reward',$ago(4),$ago(3),$ago(1),$ago(4),$now]);
$walletInsert->execute(['22222222-2222-4222-8222-222222222222',102,1,1,'loyalty_quest','issued',1000,'EUR','EUR Reward',$ago(3),null,null,$ago(3),$now]);
$walletInsert->execute(['33333333-3333-4333-8333-333333333333',201,2,2,'loyalty_quest','redeemed',9999,'USD','Other Reward',$ago(4),$ago(3),$ago(2),$ago(4),$now]);

$eventInsert = $pdo->prepare('INSERT INTO message_events (event_type) VALUES (?)');
foreach (['loyalty_quest.quest_invitation','loyalty_quest.reward_delivered','loyalty_quest.quest_expiring','loyalty_quest.reward_delivered'] as $eventType) {
    $eventInsert->execute([$eventType]);
}
$deliveryInsert = $pdo->prepare(
    'INSERT INTO message_delivery_jobs (message_event_id,merchant_user_id,campaign_id,status,created_at) VALUES (?,?,?,?,?)'
);
$deliveryInsert->execute([1,1,1,'delivered',$ago(3)]);
$deliveryInsert->execute([2,1,1,'failed',$ago(2)]);
$deliveryInsert->execute([3,1,1,'queued',$ago(1)]);
$deliveryInsert->execute([4,2,2,'delivered',$ago(1)]);

$report = mg_lqa_apply_accuracy($pdo,1,mg_lqa_report($pdo,1,30,''));
$checks = [];
$check = static function(string $name,bool $ok,mixed $actual=null) use (&$checks): void {
    $checks[] = ['name'=>$name,'ok'=>$ok,'actual'=>$actual];
};

$check('merchant isolation',count($report['campaigns'])===1 && $report['campaigns'][0]['id']===$merchantCampaign,array_column($report['campaigns'],'id'));
$check('participant cohort',($report['summary']['participants']??0)===2,$report['summary']['participants']??null);
$check('completion cohort',($report['summary']['completed']??0)===1,$report['summary']['completed']??null);
$check(
    'actual redemption date',
    array_reduce(
        $report['trend'],
        static fn(bool $carry,array $row): bool => $carry || ($row['date']===gmdate('Y-m-d',strtotime('-1 day')) && $row['redeemed']===1),
        false
    ),
    $report['trend']
);
$check('currency separation',count($report['value_by_currency'])===2,array_column($report['value_by_currency'],'currency'));
$check('mixed campaign currency',!empty($report['campaigns'][0]['mixed_currency']),$report['campaigns'][0]['currency']??null);
$check('no combined currency total',!array_key_exists('redeemed_value_cents',$report['summary']),array_keys($report['summary']));
$privacyValue = $report['verification'][0]['avg_distance_meters'] ?? null;
$check(
    'geolocation privacy threshold',
    array_key_exists('avg_distance_meters',$report['verification'][0]) && $privacyValue===null,
    $privacyValue
);
$check('delivery success excludes pending',abs((float)$report['delivery']['success_rate']-50.0)<0.01,$report['delivery']['success_rate']);
$check('source attribution',($report['sources'][0]['source']??'')==='website_embed',$report['sources']);
$json = json_encode($report,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
$check('no participant PII',!str_contains($json,'private-one@example.test')&&!str_contains($json,'Private One')&&!str_contains($json,'participant_user_id'));
$invalid = false;
try {
    mg_lqa_campaign_ref('not-a-uuid');
} catch (InvalidArgumentException) {
    $invalid = true;
}
$check('invalid campaign rejected',$invalid);

$failed = array_values(array_filter($checks,static fn(array $row): bool => !$row['ok']));
echo json_encode(['ok'=>$failed===[],'checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed===[] ? 0 : 1);
