<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }

require_once __DIR__ . '/mcp-phase3b/bootstrap.php';
require_once __DIR__ . '/mcp-phase3b/verify-native.php';
require_once __DIR__ . '/mcp-phase3b/prepare.php';
require_once __DIR__ . '/mcp-phase3b/finish.php';

try {
    $pdo = mg_db();
    $fixture = phase3b_build_fixture($pdo);
    phase3b_finish_conversions($pdo, $fixture, phase3b_prepare_conversions($pdo, $fixture));
    echo "MCP approved-draft conversion Phase 3B executable flow passed.\n";
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'MCP Phase 3B test failed: ' . $error->getMessage() . "\n");
    exit(1);
}
