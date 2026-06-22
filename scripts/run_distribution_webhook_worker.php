<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/distribution/_developer_webhooks.php';

$limit = 25;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with((string)$arg, '--limit=')) $limit = (int)substr((string)$arg, 8);
}
$workerId = 'cli-developer-webhook-worker:' . gethostname() . ':' . getmypid();
$result = mg_dev_webhook_run(mg_db(), max(1, min(100, $limit)), $workerId);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
