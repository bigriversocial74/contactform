<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/includes/public-donations-reconciliation.php';

$usage = static function (): string {
    return <<<'TEXT'
Public Donations reconciliation

Usage:
  php scripts/reconcile_public_donations.php --merchant=ID [options]

Options:
  --dry-run                     Explicit audit-only mode (also the default).
  --repair=MODE[,MODE]          Explicit safe repair modes:
                                 counters,batch_totals,recalled_visibility,assignments
                                 or safe for all deterministic repair modes.
  --campaign=PUBLIC_ID|SLUG     Restrict to one campaign.
  --operation=PUBLIC_ID         Restrict to one allocation/recall operation.
  --limit=1..1000               Maximum attribution and issue rows per category.
  --actor=USER_ID               Optional actor for the audit receipt.
  --json                        Emit compact JSON instead of pretty JSON.
  --help                        Show this help.

Safety:
  Dry-run is the default. Missing attribution, missing canonical links, and
  ownership mismatches are always report-only. Repair mode never creates or
  reassigns ownership records.
TEXT;
};

$options = getopt('', [
    'merchant:',
    'dry-run',
    'repair::',
    'campaign::',
    'operation::',
    'limit::',
    'actor::',
    'json',
    'help',
]);

if (isset($options['help'])) {
    fwrite(STDOUT, $usage() . PHP_EOL);
    exit(0);
}

try {
    $repair = $options['repair'] ?? null;
    if (array_key_exists('dry-run', $options) && $repair !== null && trim((string)$repair) !== '') {
        throw new InvalidArgumentException('--dry-run cannot be combined with --repair.');
    }

    $merchant = filter_var($options['merchant'] ?? null, FILTER_VALIDATE_INT);
    if ($merchant === false || $merchant < 1) {
        throw new InvalidArgumentException('--merchant must be a positive integer.');
    }
    $actor = filter_var($options['actor'] ?? null, FILTER_VALIDATE_INT);

    $result = mg_public_donations_reconcile_apply(mg_db(), [
        'merchant_id' => (int)$merchant,
        'campaign' => $options['campaign'] ?? null,
        'operation' => $options['operation'] ?? null,
        'limit' => $options['limit'] ?? 100,
        'repair' => $repair,
        'actor_id' => $actor !== false && $actor !== null ? (int)$actor : null,
    ]);

    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if (!array_key_exists('json', $options)) $flags |= JSON_PRETTY_PRINT;
    fwrite(STDOUT, json_encode($result, $flags | JSON_THROW_ON_ERROR) . PHP_EOL);

    // Report-only findings are operationally important, but a dry run remains a
    // successful command. CI and deployment tooling may inspect these fields.
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'error' => $error->getMessage(),
        'usage' => $usage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
