<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$options = getopt('', ['phase:', 'output::']);
$phase = isset($options['phase']) && is_string($options['phase']) ? strtolower(trim($options['phase'])) : '';
$outputPath = isset($options['output']) && is_string($options['output']) ? trim($options['output']) : '';
if (!in_array($phase, ['before', 'after'], true)) {
    throw new InvalidArgumentException('Use --phase=before or --phase=after.');
}

$config = require dirname(__DIR__) . '/api/config.php';
$db = $config['db'];
$migrationUser = getenv('MG_MIGRATION_DB_USER');
$migrationPass = getenv('MG_MIGRATION_DB_PASS');
if (is_string($migrationUser) && $migrationUser !== '') $db['user'] = $migrationUser;
if (is_string($migrationPass)) $db['pass'] = $migrationPass;

$database = (string)($db['name'] ?? '');
if ($database === '' || preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
    throw new RuntimeException('A safe MG_DB_NAME is required.');
}

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $database, $db['charset']),
    (string)$db['user'],
    (string)$db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);

function mg_bundle_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function mg_bundle_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function mg_bundle_schema_count(PDO $pdo, string $table, string $column): int
{
    return (int)mg_bundle_scalar($pdo,
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',
        [$table, $column]
    );
}

function mg_bundle_index_count(PDO $pdo, string $table, string $index): int
{
    return (int)mg_bundle_scalar($pdo,
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',
        [$table, $index]
    );
}

function mg_bundle_seed(PDO $pdo): void
{
    $pdo->beginTransaction();
    try {
        $insertUser = $pdo->prepare('INSERT INTO users (email,password_hash,full_name,display_name,status,created_at,updated_at) VALUES (?,?,?,?,\'active\',NOW(),NOW())');
        $insertUser->execute(['bundle-buyer@example.test', password_hash('bundle-buyer', PASSWORD_DEFAULT), 'Bundle Buyer', 'Bundle Buyer']);
        $buyerId = (int)$pdo->lastInsertId();
        $insertUser->execute(['bundle-merchant@example.test', password_hash('bundle-merchant', PASSWORD_DEFAULT), 'Bundle Merchant', 'Bundle Merchant']);
        $merchantId = (int)$pdo->lastInsertId();

        $pdo->prepare("INSERT INTO commerce_orders
            (public_id,buyer_user_id,merchant_user_id,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,payment_status,fulfillment_status,source_type,source_reference,idempotency_key,metadata_json,created_at,updated_at)
            VALUES (?,?,?,?,2500,0,0,375,2500,'unpaid','pending','checkout','bundle-validation','bundle-validation-order',JSON_OBJECT('fixture','production_bundle'),DATE_SUB(NOW(),INTERVAL 2 MINUTE),DATE_SUB(NOW(),INTERVAL 2 MINUTE))")
            ->execute(['40000000-0000-4000-8000-000000000001', $buyerId, $merchantId, 'USD']);
        $orderId = (int)$pdo->lastInsertId();

        $pdo->prepare("INSERT INTO payment_intents
            (public_id,order_id,provider_key,provider_intent_reference,amount_cents,currency,status,capture_method,idempotency_key,created_at,updated_at)
            VALUES (?,?, 'stripe','pi_bundle_validation',2500,'USD','created','automatic','bundle-validation-intent',DATE_SUB(NOW(),INTERVAL 90 SECOND),DATE_SUB(NOW(),INTERVAL 90 SECOND))")
            ->execute(['40000000-0000-4000-8000-000000000002', $orderId]);

        $pdo->prepare("INSERT INTO checkout_sessions
            (public_id,order_id,provider_key,provider_session_reference,status,success_url,cancel_url,expires_at,created_at,updated_at)
            VALUES (?,?, 'stripe','cs_bundle_validation','open','/checkout-success.php','/cart.php',DATE_ADD(NOW(),INTERVAL 30 MINUTE),DATE_SUB(NOW(),INTERVAL 1 MINUTE),DATE_SUB(NOW(),INTERVAL 1 MINUTE))")
            ->execute(['40000000-0000-4000-8000-000000000003', $orderId]);

        $pdo->prepare("INSERT INTO payment_provider_accounts
            (public_id,merchant_user_id,provider_key,provider_account_reference,mode,status,charges_enabled,payouts_enabled,capabilities_json,created_at,updated_at)
            VALUES (?,?,'stripe','acct_bundle_validation','test','active',1,1,JSON_OBJECT('card_payments','active','transfers','active'),NOW(),NOW())")
            ->execute(['40000000-0000-4000-8000-000000000004', $merchantId]);

        $pdo->prepare('INSERT INTO message_threads (public_id,gift_id,created_by_user_id,subject,created_at,updated_at) VALUES (?,NULL,?,?,NOW(),NOW())')
            ->execute(['40000000-0000-4000-8000-000000000005', $buyerId, 'Legacy bundle conversation']);

        $pdo->prepare("INSERT INTO catalog_assets
            (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,checksum_sha256,status,metadata_json,created_at,updated_at)
            VALUES (?,?,'image','private_local','bundle-validation/asset.jpg','asset.jpg','image/jpeg',128,REPEAT('a',64),'ready',JSON_OBJECT('fixture','production_bundle'),NOW(),NOW())")
            ->execute(['40000000-0000-4000-8000-000000000006', $merchantId]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

$checks = [];
if ($phase === 'before') {
    $checks['checkout_intent_column_absent'] = mg_bundle_schema_count($pdo, 'checkout_sessions', 'payment_intent_id') === 0;
    $checks['conversation_key_absent'] = mg_bundle_schema_count($pdo, 'message_threads', 'conversation_key') === 0;
    $checks['platform_credentials_table_absent'] = (int)mg_bundle_scalar($pdo, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_platform_credentials'") === 0;
    $checks['application_fee_column_absent'] = mg_bundle_schema_count($pdo, 'payment_intents', 'application_fee_cents') === 0;
    $checks['legacy_thread_unique_present'] = mg_bundle_index_count($pdo, 'message_threads', 'uq_message_threads_microgift_instance') === 1;
    $checks['moderation_trigger_present'] = (int)mg_bundle_scalar($pdo, "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='trg_catalog_assets_review_state'") === 1;
    foreach ($checks as $name => $passed) mg_bundle_assert($passed, "Pre-bundle check failed: {$name}");
    mg_bundle_seed($pdo);
} else {
    $checks['migration_markers'] = (int)mg_bundle_scalar($pdo, "SELECT COUNT(*) FROM schema_migrations WHERE migration_key IN ('stage_v1c_checkout_session_intent_authority','stage_v1d_transfer_conversations','stage_v1f_stripe_payments','stage_v1_release_trigger_portability')") === 4;
    $checks['checkout_intent_column'] = mg_bundle_schema_count($pdo, 'checkout_sessions', 'payment_intent_id') === 1;
    $checks['checkout_intent_index'] = mg_bundle_index_count($pdo, 'checkout_sessions', 'idx_checkout_sessions_payment_intent') === 1;
    $checks['checkout_intent_foreign_key'] = (int)mg_bundle_scalar($pdo, "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='checkout_sessions' AND CONSTRAINT_NAME='fk_checkout_sessions_payment_intent' AND CONSTRAINT_TYPE='FOREIGN KEY'") === 1;
    $checks['checkout_backfill'] = (int)mg_bundle_scalar($pdo, "SELECT COUNT(*) FROM checkout_sessions cs INNER JOIN payment_intents pi ON pi.id=cs.payment_intent_id WHERE cs.public_id='40000000-0000-4000-8000-000000000003' AND pi.public_id='40000000-0000-4000-8000-000000000002'") === 1;
    $checks['conversation_backfill'] = (string)mg_bundle_scalar($pdo, "SELECT conversation_key FROM message_threads WHERE public_id='40000000-0000-4000-8000-000000000005'") === 'legacy:40000000-0000-4000-8000-000000000005';
    $checks['legacy_thread_unique_removed'] = mg_bundle_index_count($pdo, 'message_threads', 'uq_message_threads_microgift_instance') === 0;
    $checks['conversation_unique_present'] = mg_bundle_index_count($pdo, 'message_threads', 'uq_message_threads_microgift_conversation') === 2;
    $checks['platform_credentials_table'] = (int)mg_bundle_scalar($pdo, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_platform_credentials'") === 1;
    $checks['payment_defaults'] = (string)mg_bundle_scalar($pdo, "SELECT CONCAT(application_fee_cents,':',COALESCE(destination_account_reference,'NULL')) FROM payment_intents WHERE public_id='40000000-0000-4000-8000-000000000002'") === '0:NULL';
    $checks['provider_defaults'] = (string)mg_bundle_scalar($pdo, "SELECT CONCAT(details_submitted,':',onboarding_status,':',COALESCE(JSON_LENGTH(requirements_due_json),0),':',COALESCE(last_synced_at,'NULL')) FROM payment_provider_accounts WHERE public_id='40000000-0000-4000-8000-000000000004'") === '0:not_started:0:NULL';
    $checks['moderation_trigger'] = (int)mg_bundle_scalar($pdo, "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='trg_catalog_assets_review_state'") === 1;
    foreach ($checks as $name => $passed) mg_bundle_assert($passed, "Post-bundle check failed: {$name}");

    $pdo->exec("UPDATE catalog_assets SET moderation_status='blocked' WHERE public_id='40000000-0000-4000-8000-000000000006'");
    mg_bundle_assert((string)mg_bundle_scalar($pdo, "SELECT status FROM catalog_assets WHERE public_id='40000000-0000-4000-8000-000000000006'") === 'quarantined', 'Moderation trigger did not quarantine blocked media.');
    $pdo->exec("UPDATE catalog_assets SET moderation_status='clear' WHERE public_id='40000000-0000-4000-8000-000000000006'");
    mg_bundle_assert((string)mg_bundle_scalar($pdo, "SELECT status FROM catalog_assets WHERE public_id='40000000-0000-4000-8000-000000000006'") === 'ready', 'Moderation trigger did not restore cleared media.');
}

$fingerprint = (string)mg_bundle_scalar($pdo, "SELECT SHA2(CONCAT_WS('|',
    (SELECT COUNT(*) FROM users WHERE email IN ('bundle-buyer@example.test','bundle-merchant@example.test')),
    COALESCE((SELECT payment_intent_id FROM checkout_sessions WHERE public_id='40000000-0000-4000-8000-000000000003'),'missing'),
    COALESCE((SELECT conversation_key FROM message_threads WHERE public_id='40000000-0000-4000-8000-000000000005'),'missing'),
    (SELECT status FROM catalog_assets WHERE public_id='40000000-0000-4000-8000-000000000006')
),256)");

$report = ['status'=>'passed','phase'=>$phase,'database'=>$database,'checks'=>$checks,'fixture_fingerprint'=>$fingerprint,'completed_at'=>gmdate('c')];
$encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
if ($outputPath !== '') {
    $directory = dirname($outputPath);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Unable to create evidence directory.');
    if (file_put_contents($outputPath, $encoded) === false) throw new RuntimeException('Unable to write state evidence.');
}
fwrite(STDOUT, $encoded);
