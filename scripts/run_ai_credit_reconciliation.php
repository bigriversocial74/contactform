<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/ai/ai-credit-reconciliation.php';

$options = getopt('', ['days::','provider::','user-id::','trigger::']);
$days = max(1, min(365, (int)($options['days'] ?? 30)));
$provider = mg_ai_credit_provider_key($options['provider'] ?? 'anthropic');
$userId = isset($options['user-id']) ? max(0, (int)$options['user-id']) : 0;
$trigger = mg_ai_reconciliation_text($options['trigger'] ?? 'scheduled', 40) ?: 'scheduled';

try {
    $summary = mg_ai_reconciliation_run(mg_db(), [
        'days'=>$days,
        'provider_key'=>$provider,
        'user_id'=>$userId > 0 ? $userId : null,
        'trigger_source'=>$trigger,
    ]);
    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, json_encode([
        'status'=>'failed',
        'message'=>$error->getMessage(),
        'exception_class'=>$error::class,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
