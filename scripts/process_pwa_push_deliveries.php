<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/includes/pwa-push.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This worker must be run from CLI.\n");
    exit(1);
}

$limit = isset($argv[1]) && is_numeric($argv[1]) ? (int)$argv[1] : (int)mg_config_value('delivery', 'pwa_push_batch_size', 25);
$result = mg_pwa_push_send_pending(mg_db(), max(1, min(250, $limit)));
fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
