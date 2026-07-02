<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/admin/_security_hardening_audit.php';

$options = getopt('', ['json']);
$report = mg_security_hardening_audit(mg_db());

if (isset($options['json'])) {
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(($report['counts']['critical'] ?? 0) > 0 ? 2 : 0);
}

$counts = $report['counts'];
fwrite(STDOUT, "Microgifter Security Hardening Audit\n");
fwrite(STDOUT, "Status: " . strtoupper((string)$report['status']) . "\n");
fwrite(STDOUT, "Summary: " . (string)$report['summary'] . "\n");
fwrite(STDOUT, "Checks: {$counts['checks']} total, {$counts['healthy']} healthy, {$counts['warning']} warning, {$counts['critical']} critical\n\n");

$items = array_values(array_filter($report['checks'], static fn(array $check): bool => in_array($check['status'] ?? '', ['critical','warning'], true)));
if ($items === []) {
    fwrite(STDOUT, "No critical or warning hardening items were detected.\n");
} else {
    foreach (array_slice($items, 0, 60) as $check) {
        fwrite(STDOUT, '[' . strtoupper((string)$check['status']) . '] ' . (string)$check['label'] . ' — ' . (string)$check['summary'] . "\n");
        foreach (array_slice($check['recommendations'] ?? [], 0, 2) as $recommendation) {
            fwrite(STDOUT, '  - ' . (string)$recommendation . "\n");
        }
    }
}

exit(($report['counts']['critical'] ?? 0) > 0 ? 2 : 0);
