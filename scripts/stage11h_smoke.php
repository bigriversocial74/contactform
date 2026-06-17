<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';

$pdo = mg_db();

$column = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
);
$column->execute(['merchant_locations', 'claim_code']);
if ((int)$column->fetchColumn() !== 1) {
    throw new RuntimeException('Missing Stage 11H column: merchant_locations.claim_code');
}

$index = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?'
);

$workspaceColumn = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
);
$workspaceColumn->execute(['merchant_locations', 'workspace_id']);
if ((int)$workspaceColumn->fetchColumn() === 1) {
    $index->execute(['merchant_locations', 'uq_merchant_locations_workspace_claim_code']);
    if ((int)$index->fetchColumn() < 1) {
        throw new RuntimeException('Missing Stage 11H workspace claim-code index.');
    }
}

$merchantColumn = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
);
$merchantColumn->execute(['merchant_locations', 'merchant_user_id']);
if ((int)$merchantColumn->fetchColumn() === 1) {
    $index->execute(['merchant_locations', 'uq_merchant_locations_merchant_claim_code']);
    if ((int)$index->fetchColumn() < 1) {
        throw new RuntimeException('Missing Stage 11H merchant claim-code index.');
    }
}

$migration = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration_key=?');
$migration->execute(['stage_11h_backend_hardening']);
if ((int)$migration->fetchColumn() !== 1) {
    throw new RuntimeException('Stage 11H migration record is missing.');
}

fwrite(STDOUT, "Stage 11H backend hardening smoke validation passed.\n");
