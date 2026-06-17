<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';

$pdo = mg_db();

$table = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
);
$column = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
);
$index = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?'
);
$constraint = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?'
);

$table->execute(['tip_events']);
if ((int)$table->fetchColumn() !== 1) {
    throw new RuntimeException('Missing Stage 12A table: tip_events');
}

$requiredTipColumns = [
    'recipient_wallet_owner_type',
    'recipient_wallet_owner_user_id',
    'provider_key',
    'payment_intent_id',
    'request_fingerprint',
    'target_snapshot_json',
    'settled_at',
    'failed_at',
    'disputed_at',
    'refunded_at',
];
foreach ($requiredTipColumns as $name) {
    $column->execute(['tips', $name]);
    if ((int)$column->fetchColumn() !== 1) {
        throw new RuntimeException("Missing Stage 12A tips column: {$name}");
    }
}

foreach (['source_type', 'source_reference'] as $name) {
    $column->execute(['payment_intents', $name]);
    if ((int)$column->fetchColumn() !== 1) {
        throw new RuntimeException("Missing Stage 12A payment_intents column: {$name}");
    }
}

$orderNullable = $pdo->prepare(
    "SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_intents' AND COLUMN_NAME='order_id'"
);
$orderNullable->execute();
if ((string)$orderNullable->fetchColumn() !== 'YES') {
    throw new RuntimeException('Stage 12A requires payment_intents.order_id to allow non-order payment sources.');
}

foreach ([
    ['payment_intents', 'idx_payment_intents_source'],
    ['tips', 'idx_tips_recipient_wallet_status'],
    ['tips', 'idx_tips_payment_intent'],
    ['tip_events', 'uq_tip_events_tip_idempotency'],
] as [$tableName, $indexName]) {
    $index->execute([$tableName, $indexName]);
    if ((int)$index->fetchColumn() < 1) {
        throw new RuntimeException("Missing Stage 12A index: {$indexName}");
    }
}

foreach ([
    ['tips', 'fk_tips_recipient_wallet_owner'],
    ['tips', 'fk_tips_payment_intent'],
    ['tip_events', 'fk_tip_events_tip'],
] as [$tableName, $constraintName]) {
    $constraint->execute([$tableName, $constraintName]);
    if ((int)$constraint->fetchColumn() !== 1) {
        throw new RuntimeException("Missing Stage 12A constraint: {$constraintName}");
    }
}

foreach (['amount_cents', 'currency', 'metadata_json'] as $name) {
    $column->execute(['tip_reversals', $name]);
    if ((int)$column->fetchColumn() !== 1) {
        throw new RuntimeException("Missing Stage 12A tip_reversals column: {$name}");
    }
}

$statusColumn = $pdo->prepare(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tips' AND COLUMN_NAME='status'"
);
$statusColumn->execute();
$statusType = (string)$statusColumn->fetchColumn();
foreach (['requires_action', 'processing', 'disputed', 'refunded'] as $state) {
    if (!str_contains($statusType, "'{$state}'")) {
        throw new RuntimeException("Missing Stage 12A tip status: {$state}");
    }
}

$migration = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration_key=?');
$migration->execute(['stage_12a_tip_financial_integrity']);
if ((int)$migration->fetchColumn() !== 1) {
    throw new RuntimeException('Stage 12A migration record is missing.');
}

fwrite(STDOUT, "Stage 12A tip financial-integrity smoke validation passed.\n");
