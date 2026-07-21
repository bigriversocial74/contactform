<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
require_once __DIR__ . '/mcp-phase3c/bootstrap.php';
require_once __DIR__ . '/mcp-phase3c/initial-observation.php';
require_once __DIR__ . '/mcp-phase3c/final-verification.php';
try {
    $state = phase3c_bootstrap(mg_db());
    phase3c_initial_observation($state);
    phase3c_final_verification($state);
    echo "MCP native draft status Phase 3C executable flow passed.\n";
} catch (Throwable $error) {
    if (isset($state['pdo']) && $state['pdo'] instanceof PDO && $state['pdo']->inTransaction()) $state['pdo']->rollBack();
    fwrite(STDERR, 'MCP Phase 3C test failed: ' . $error->getMessage() . "\n");
    exit(1);
}
