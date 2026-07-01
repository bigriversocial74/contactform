<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/admin/_system_sql_diagnostics.php';

$options = getopt('', ['json', 'sql', 'output:']);
$pdo = mg_db();
$diagnostics = mg_system_sql_diagnostics($pdo);

if (isset($options['sql'])) {
    $sql = (string)($diagnostics['repair_plan']['sql'] ?? '');
    $output = isset($options['output']) ? trim((string)$options['output']) : '';
    if ($output !== '') {
        file_put_contents($output, $sql);
        fwrite(STDOUT, "Repair SQL written to {$output}\n");
    } else {
        fwrite(STDOUT, $sql);
    }
    exit($diagnostics['counts']['critical_findings'] > 0 ? 2 : 0);
}

if (isset($options['json'])) {
    fwrite(STDOUT, json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($diagnostics['counts']['critical_findings'] > 0 ? 2 : 0);
}

$counts = $diagnostics['counts'];
fwrite(STDOUT, "Microgifter System SQL Diagnostics\n");
fwrite(STDOUT, "Status: " . strtoupper((string)$diagnostics['status']) . "\n");
fwrite(STDOUT, "Summary: " . (string)$diagnostics['summary'] . "\n");
fwrite(STDOUT, "Modules: {$counts['modules']} total, {$counts['healthy_modules']} healthy, {$counts['warning_modules']} warning, {$counts['critical_modules']} critical\n");
fwrite(STDOUT, "Findings: {$counts['findings']} total, {$counts['critical_findings']} critical, {$counts['warning_findings']} warning, {$counts['repairable_findings']} repairable\n\n");

foreach (array_slice($diagnostics['findings'], 0, 40) as $finding) {
    fwrite(STDOUT, '[' . strtoupper((string)$finding['severity']) . '] ' . (string)$finding['item'] . ' — ' . (string)$finding['message'] . "\n");
}

if (($counts['findings'] ?? 0) > 40) {
    fwrite(STDOUT, '... ' . ((int)$counts['findings'] - 40) . " more finding(s). Run with --json for full output.\n");
}

if (!empty($diagnostics['repair_plan']['available'])) {
    fwrite(STDOUT, "\nRepair SQL available. Run: php scripts/run_system_sql_diagnostics.php --sql --output=microgifter_system_sql_repair.sql\n");
}

exit($diagnostics['counts']['critical_findings'] > 0 ? 2 : 0);
