<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$options = getopt('', ['output::']);
$outputPath = isset($options['output']) && is_string($options['output']) ? trim($options['output']) : '';
$config = require dirname(__DIR__) . '/api/config.php';
$db = $config['db'];
if (($user = getenv('MG_MIGRATION_DB_USER')) !== false && $user !== '') $db['user'] = $user;
if (($pass = getenv('MG_MIGRATION_DB_PASS')) !== false) $db['pass'] = $pass;
$database = (string)($db['name'] ?? '');
if ($database === '' || preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) throw new RuntimeException('A safe MG_DB_NAME is required.');

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $database, $db['charset']),
    (string)$db['user'], (string)$db['pass'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
);

$scalar = static function (string $sql, array $params = []) use ($pdo): mixed {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
};
$columnCount = static fn(string $table, string $column): int => (int)$scalar(
    'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',
    [$table, $column]
);
$indexCount = static fn(string $table, string $index): int => (int)$scalar(
    'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',
    [$table, $index]
);

$checks = [
    'checkout_intent_column_absent'=>$columnCount('checkout_sessions','payment_intent_id')===0,
    'conversation_key_absent'=>$columnCount('message_threads','conversation_key')===0,
    'platform_credentials_table_absent'=>(int)$scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_platform_credentials'")===0,
    'application_fee_column_absent'=>$columnCount('payment_intents','application_fee_cents')===0,
    'legacy_thread_unique_present'=>$indexCount('message_threads','uq_message_threads_microgift_instance')===1,
    'moderation_trigger_present'=>(int)$scalar("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='trg_catalog_assets_review_state'")===1,
];
foreach ($checks as $name=>$passed) if (!$passed) throw new RuntimeException("Pre-bundle check failed: {$name}");

$pdo->beginTransaction();
try {
    $userStmt=$pdo->prepare("INSERT INTO users (email,password_hash,full_name,display_name,status,created_at,updated_at) VALUES (?,?,?,?, 'active',NOW(),NOW())");
    $userStmt->execute(['bundle-buyer@example.test',password_hash('bundle-buyer',PASSWORD_DEFAULT),'Bundle Buyer','Bundle Buyer']);
    $buyerId=(int)$pdo->lastInsertId();
    $userStmt->execute(['bundle-merchant@example.test',password_hash('bundle-merchant',PASSWORD_DEFAULT),'Bundle Merchant','Bundle Merchant']);
    $merchantId=(int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO commerce_orders (public_id,buyer_user_id,merchant_user_id,currency,subtotal_cents,platform_fee_cents,total_cents,payment_status,fulfillment_status,source_type,source_reference,idempotency_key,metadata_json,created_at,updated_at) VALUES (?,?,?,'USD',2500,375,2500,'unpaid','pending','checkout','bundle-validation','bundle-validation-order',JSON_OBJECT('fixture','production_bundle'),DATE_SUB(NOW(),INTERVAL 2 MINUTE),NOW())")
        ->execute(['40000000-0000-4000-8000-000000000001',$buyerId,$merchantId]);
    $orderId=(int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO payment_intents (public_id,order_id,provider_key,provider_intent_reference,amount_cents,currency,status,capture_method,idempotency_key,created_at,updated_at) VALUES (?,?,'stripe','pi_bundle_validation',2500,'USD','created','automatic','bundle-validation-intent',DATE_SUB(NOW(),INTERVAL 90 SECOND),NOW())")
        ->execute(['40000000-0000-4000-8000-000000000002',$orderId]);
    $pdo->prepare("INSERT INTO checkout_sessions (public_id,order_id,provider_key,provider_session_reference,status,success_url,cancel_url,expires_at,created_at,updated_at) VALUES (?,?,'stripe','cs_bundle_validation','open','/checkout-success.php','/cart.php',DATE_ADD(NOW(),INTERVAL 30 MINUTE),DATE_SUB(NOW(),INTERVAL 1 MINUTE),NOW())")
        ->execute(['40000000-0000-4000-8000-000000000003',$orderId]);
    $pdo->prepare("INSERT INTO payment_provider_accounts (public_id,merchant_user_id,provider_key,provider_account_reference,mode,status,charges_enabled,payouts_enabled,capabilities_json,created_at,updated_at) VALUES (?,?,'stripe','acct_bundle_validation','test','active',1,1,JSON_OBJECT('card_payments','active','transfers','active'),NOW(),NOW())")
        ->execute(['40000000-0000-4000-8000-000000000004',$merchantId]);
    $pdo->prepare('INSERT INTO message_threads (public_id,gift_id,created_by_user_id,subject,created_at,updated_at) VALUES (?,NULL,?,?,NOW(),NOW())')
        ->execute(['40000000-0000-4000-8000-000000000005',$buyerId,'Legacy bundle conversation']);
    $pdo->prepare("INSERT INTO catalog_assets (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,checksum_sha256,status,metadata_json,created_at,updated_at) VALUES (?,?,'image','private_local','bundle-validation/asset.jpg','asset.jpg','image/jpeg',128,REPEAT('a',64),'ready',JSON_OBJECT('fixture','production_bundle'),NOW(),NOW())")
        ->execute(['40000000-0000-4000-8000-000000000006',$merchantId]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}

$fingerprint=hash('sha256',implode('|',[
    (string)$scalar("SELECT COUNT(*) FROM users WHERE email IN ('bundle-buyer@example.test','bundle-merchant@example.test')"),
    (string)$scalar("SELECT COUNT(*) FROM checkout_sessions WHERE public_id='40000000-0000-4000-8000-000000000003'"),
    (string)$scalar("SELECT COUNT(*) FROM message_threads WHERE public_id='40000000-0000-4000-8000-000000000005'"),
    (string)$scalar("SELECT status FROM catalog_assets WHERE public_id='40000000-0000-4000-8000-000000000006'"),
]));
$report=['status'=>'passed','database'=>$database,'checks'=>$checks,'fixture_fingerprint'=>$fingerprint,'completed_at'=>gmdate('c')];
$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
if ($outputPath!=='') {
    $directory=dirname($outputPath);
    if (!is_dir($directory)&&!mkdir($directory,0770,true)&&!is_dir($directory)) throw new RuntimeException('Unable to create evidence directory.');
    if (file_put_contents($outputPath,$json)===false) throw new RuntimeException('Unable to write fixture evidence.');
}
fwrite(STDOUT,$json);
