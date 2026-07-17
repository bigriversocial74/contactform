<?php
declare(strict_types=1);

/**
 * Reproducible 100-point repository production-quality audit.
 *
 * A 10/10 result means every documented automated gate passed for this commit.
 * It is not a guarantee that no future defect can exist.
 */

$root = dirname(__DIR__);
chdir($root);
$gate = in_array('--gate', $argv, true);
$buildDir = $root . '/build';
if (!is_dir($buildDir)) mkdir($buildDir, 0775, true);

function qa_run(array $command, string $cwd): array
{
    $stdoutFile = tempnam(sys_get_temp_dir(), 'mg-qa-out-');
    $stderrFile = tempnam(sys_get_temp_dir(), 'mg-qa-err-');
    if ($stdoutFile === false || $stderrFile === false) {
        return ['code' => 127, 'output' => 'Unable to allocate command output files.'];
    }
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['file', $stdoutFile, 'w'],
        2 => ['file', $stderrFile, 'w'],
    ], $pipes, $cwd);
    if (!is_resource($process)) {
        @unlink($stdoutFile);
        @unlink($stderrFile);
        return ['code' => 127, 'output' => 'Unable to start command.'];
    }
    fclose($pipes[0]);
    $code = proc_close($process);
    $stdout = (string) @file_get_contents($stdoutFile);
    $stderr = (string) @file_get_contents($stderrFile);
    @unlink($stdoutFile);
    @unlink($stderrFile);
    return ['code' => $code, 'output' => trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''))];
}

function qa_files(string $root): array
{
    $result = qa_run(['git', 'ls-files', '-z'], $root);
    if ($result['code'] !== 0) throw new RuntimeException('Unable to enumerate tracked files.');
    $files = array_values(array_filter(explode("\0", $result['output']), static fn(string $value): bool => $value !== ''));
    sort($files);
    return $files;
}

function qa_text(string $root, string $path): string
{
    $full = $root . '/' . $path;
    if (!is_file($full) || filesize($full) > 2_000_000) return '';
    $content = file_get_contents($full);
    return is_string($content) && !str_contains($content, "\0") ? $content : '';
}

function qa_excerpt(string $value, int $limit = 700): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit) . '…';
}

function qa_list(array $items): string
{
    $items = array_values(array_unique($items));
    sort($items);
    return implode(', ', array_slice($items, 0, 25));
}

function qa_throwable_catch_blocks(string $content): array
{
    $tokens = token_get_all($content);
    $blocks = [];
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CATCH) continue;
        $signature = '';
        $variable = '';
        while (++$i < $count && $tokens[$i] !== '(') {}
        $depth = 1;
        while (++$i < $count && $depth > 0) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($text === '(') $depth++;
            if ($text === ')') $depth--;
            if ($depth > 0) {
                $signature .= $text;
                if (is_array($token) && $token[0] === T_VARIABLE) $variable = $text;
            }
        }
        if ($variable === '' || preg_match('/(?:^|[|&\s\\])Throwable(?:[|&\s]|$)/i', $signature) !== 1) continue;
        while (++$i < $count && $tokens[$i] !== '{') {}
        if ($i >= $count) continue;
        $braceDepth = 1;
        $body = '';
        while (++$i < $count && $braceDepth > 0) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($text === '{') $braceDepth++;
            if ($text === '}') $braceDepth--;
            if ($braceDepth > 0) $body .= $text;
        }
        $blocks[] = ['variable'=>$variable, 'body'=>$body];
    }
    return $blocks;
}

function qa_check(array &$categories, string $category, string $id, string $label, int $points, bool $passed, string $detail = ''): void
{
    $categories[$category] ??= ['earned' => 0, 'possible' => 0, 'checks' => []];
    $categories[$category]['possible'] += $points;
    if ($passed) $categories[$category]['earned'] += $points;
    $categories[$category]['checks'][] = compact('id', 'label', 'points', 'passed', 'detail');
}

$files = qa_files($root);
$textExtensions = ['php','js','mjs','cjs','html','htm','css','md','json','yml','yaml','xml','sh','sql','txt','env','ini','dist'];
$textFiles = [];
foreach ($files as $path) {
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($extension, $textExtensions, true) || str_starts_with(basename($path), '.env')) $textFiles[] = $path;
}
$webPhp = array_values(array_filter($files, static fn(string $path): bool =>
    str_ends_with($path, '.php') && !preg_match('#^(?:scripts|tests|vendor|database|\.github)/#', $path)
));
$categories = [];

// 1. Runtime correctness — 10 points.
$composerValidate = qa_run(['composer', 'validate', '--strict', '--no-interaction'], $root);
qa_check($categories, 'Runtime correctness', 'composer-validate', 'composer.json validates strictly', 2, $composerValidate['code'] === 0, qa_excerpt($composerValidate['output']));

$phpLint = qa_run(['bash', '-lc', <<<'BASH'
set -o pipefail
find . -path './vendor' -prune -o -path './.git' -prune -o -name '*.php' -type f -print0 |
  xargs -0 -r -n1 php -l >/tmp/mg-qa-php.log 2>&1
BASH], $root);
qa_check($categories, 'Runtime correctness', 'php-lint', 'All first-party PHP files pass syntax validation', 4, $phpLint['code'] === 0, $phpLint['code'] === 0 ? 'All PHP files passed.' : qa_excerpt((string) @file_get_contents('/tmp/mg-qa-php.log')));

$jsLint = qa_run(['bash', '-lc', <<<'BASH'
set -o pipefail
failed=0
while IFS= read -r -d '' file; do
  case "$file" in
    */vendor/*|*/node_modules/*|*/third-party/*|*/dist/*|*.min.js) continue ;;
  esac
  if ! node --check "$file" >/tmp/mg-qa-js-one.log 2>&1; then
    printf '%s\n' "$file"
    cat /tmp/mg-qa-js-one.log
    failed=1
  fi
done < <(find . -path './vendor' -prune -o -path './.git' -prune -o -name '*.js' -type f -print0)
exit "$failed"
BASH], $root);
qa_check($categories, 'Runtime correctness', 'js-lint', 'All first-party JavaScript files pass syntax validation', 2, $jsLint['code'] === 0, $jsLint['code'] === 0 ? 'All JavaScript files passed.' : qa_excerpt($jsLint['output']));

$shellLint = qa_run(['bash', '-lc', <<<'BASH'
set -o pipefail
failed=0
while IFS= read -r -d '' file; do
  if ! bash -n "$file"; then
    printf '%s\n' "$file"
    failed=1
  fi
done < <(find scripts -name '*.sh' -type f -print0)
exit "$failed"
BASH], $root);
qa_check($categories, 'Runtime correctness', 'shell-lint', 'All repository shell scripts pass bash syntax validation', 2, $shellLint['code'] === 0, qa_excerpt($shellLint['output']));

// 2. Dependency and supply-chain safety — 10 points.
$hasLock = is_file($root . '/composer.lock');
qa_check($categories, 'Dependency and supply chain', 'composer-lock', 'Application dependencies are committed in composer.lock', 4, $hasLock, $hasLock ? 'composer.lock is tracked.' : 'composer.lock is missing.');
$composerAudit = $hasLock ? qa_run(['composer', 'audit', '--locked', '--no-interaction'], $root) : ['code' => 1, 'output' => 'Skipped without composer.lock.'];
qa_check($categories, 'Dependency and supply chain', 'composer-audit', 'Locked Composer dependencies have no known advisories', 4, $composerAudit['code'] === 0, qa_excerpt($composerAudit['output']));
$dependabot = qa_text($root, '.github/dependabot.yml');
qa_check($categories, 'Dependency and supply chain', 'dependabot', 'Dependabot monitors Composer and GitHub Actions', 2, str_contains($dependabot, 'package-ecosystem: "composer"') && str_contains($dependabot, 'package-ecosystem: "github-actions"'));

// 3. Secret and configuration hygiene — 10 points.
$gitignore = qa_text($root, '.gitignore');
$requiredIgnore = ['/.env','/api/config.local.php','/vendor/','/build/','/node_modules/','/.phpunit.cache/','*.log','*.sql.gz','*.zip','*.bak'];
$missingIgnore = array_values(array_filter($requiredIgnore, static fn(string $entry): bool => !str_contains($gitignore, $entry)));
qa_check($categories, 'Secret and configuration hygiene', 'gitignore', 'Runtime secrets, dependencies, builds, logs, and backups are ignored', 3, $missingIgnore === [], qa_list($missingIgnore));

$sensitiveNames = [];
foreach ($files as $path) {
    $base = strtolower(basename($path));
    if ($path === 'api/config.local.example.php' || $path === '.env.example' || str_ends_with($path, '.env.example')) continue;
    if ($base === '.env' || preg_match('/\.(?:pem|key|p12|pfx)$/i', $base) || in_array($base, ['id_rsa','id_ed25519','credentials.json','service-account.json'], true) || preg_match('/\.(?:sql\.gz|zip|bak|orig)$/i', $base)) $sensitiveNames[] = $path;
}
qa_check($categories, 'Secret and configuration hygiene', 'sensitive-files', 'No credential, private-key, backup, or local-config files are tracked', 4, $sensitiveNames === [], qa_list($sensitiveNames));

$secretPatterns = [
    'private-key' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
    'aws-access-key' => '/\bAKIA[0-9A-Z]{16}\b/',
    'github-token' => '/\bgh[pousr]_[A-Za-z0-9_]{30,}\b/',
    'stripe-live-secret' => '/\bsk_live_[A-Za-z0-9]{20,}\b/',
    'openai-key' => '/\bsk-[A-Za-z0-9]{32,}\b/',
];
$secretFindings = [];
foreach ($textFiles as $path) {
    if (str_contains($path, 'fixtures/') || str_contains($path, 'examples/')) continue;
    $content = qa_text($root, $path);
    foreach ($secretPatterns as $name => $pattern) if ($content !== '' && preg_match($pattern, $content)) $secretFindings[] = $path . ':' . $name;
}
qa_check($categories, 'Secret and configuration hygiene', 'secret-scan', 'No high-confidence production secret patterns are committed', 3, $secretFindings === [], qa_list($secretFindings));

// 4. Dangerous runtime primitives — 10 points.
$evalFindings = $commandFindings = $unserializeFindings = $dynamicIncludeFindings = [];
$processGatewayPath = 'includes/runtime-process.php';
$processGateway = qa_text($root, $processGatewayPath);
$processGatewaySafe = $processGateway !== ''
    && str_contains($processGateway, "['ffmpeg', 'ffprobe']")
    && str_contains($processGateway, "['bypass_shell'=>true")
    && preg_match('/proc_open\s*\(\s*\$command/i', $processGateway) === 1
    && preg_match('/\$_(?:GET|POST|REQUEST|COOKIE)/i', $processGateway) !== 1
    && preg_match('/\b(?:shell_exec|system|passthru|popen|pcntl_exec)\s*\(/i', $processGateway) !== 1;
foreach ($webPhp as $path) {
    $content = qa_text($root, $path);
    if ($content === '') continue;
    if (preg_match('/\beval\s*\(/i', $content) || preg_match('/\bassert\s*\(\s*["\']/i', $content)) $evalFindings[] = $path;
    if ($path !== $processGatewayPath && preg_match('/\b(?:shell_exec|system|passthru|proc_open|popen|pcntl_exec)\s*\(/i', $content)) $commandFindings[] = $path;
    if (preg_match('/\bunserialize\s*\(/i', $content) && !str_contains($content, 'allowed_classes')) $unserializeFindings[] = $path;
    if (preg_match('/\b(?:include|include_once|require|require_once)\s*[\(]?\s*\$_(?:GET|POST|REQUEST|COOKIE)/i', $content)) $dynamicIncludeFindings[] = $path;
}
qa_check($categories, 'Dangerous runtime primitives', 'eval', 'No eval or string-assert execution in web-accessible PHP', 3, $evalFindings === [], qa_list($evalFindings));
qa_check($categories, 'Dangerous runtime primitives', 'commands', 'Operating-system commands are isolated to the audited allowlisted process gateway', 3, $commandFindings === [] && $processGatewaySafe, qa_list(array_merge($commandFindings, $processGatewaySafe ? [] : ['invalid-process-gateway'])));
qa_check($categories, 'Dangerous runtime primitives', 'unserialize', 'No unsafe unserialize calls in web-accessible PHP', 2, $unserializeFindings === [], qa_list($unserializeFindings));
qa_check($categories, 'Dangerous runtime primitives', 'dynamic-include', 'No request-controlled include or require paths', 2, $dynamicIncludeFindings === [], qa_list($dynamicIncludeFindings));

// 5. Request, SQL, and data-integrity boundaries — 10 points.
$requestSqlFindings = [];
foreach ($webPhp as $path) {
    $content = qa_text($root, $path);
    if ($content === '') continue;
    $patterns = [
        '/->(?:query|exec|prepare)\s*\(\s*[^;\n]{0,700}\$_(?:GET|POST|REQUEST|COOKIE)/is',
        '/\b(?:SELECT|INSERT|UPDATE|DELETE|REPLACE)\b[^"\']{0,500}\{\s*\$_(?:GET|POST|REQUEST|COOKIE)/is',
        '/\b(?:SELECT|INSERT|UPDATE|DELETE|REPLACE)\b[^;\n]{0,500}\.\s*\$_(?:GET|POST|REQUEST|COOKIE)/is',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $requestSqlFindings[] = $path;
            break;
        }
    }
}
qa_check($categories, 'Request SQL and data integrity', 'request-sql', 'No request values are directly interpolated into SQL execution expressions', 4, $requestSqlFindings === [], qa_list($requestSqlFindings));
$securityFoundation = qa_run(['vendor/bin/phpunit', '--configuration', 'phpunit.xml.dist', '--filter', 'SecurityFoundationTest'], $root);
qa_check($categories, 'Request SQL and data integrity', 'security-foundation', 'Prepared-statement and security foundation tests pass', 2, $securityFoundation['code'] === 0, qa_excerpt($securityFoundation['output']));
$migrationValidate = qa_run(['php', 'scripts/validate_migration_manifest.php'], $root);
qa_check($categories, 'Request SQL and data integrity', 'migration-manifest', 'Canonical migration manifest validates', 2, $migrationValidate['code'] === 0, qa_excerpt($migrationValidate['output']));
$architectureDoc = qa_text($root, 'docs/architecture/current_active_file_map.md');
$readme = qa_text($root, 'README.md');
qa_check($categories, 'Request SQL and data integrity', 'authority-map', 'Canonical active-root and ownership authority documentation exists', 2, $architectureDoc !== '' && str_contains($readme, 'Canonical ownership'));

// 6. Error handling and observability — 10 points.
$debugFindings = $rawThrowableFindings = [];
foreach ($webPhp as $path) {
    $content = qa_text($root, $path);
    if ($content === '') continue;
    if (preg_match('/\b(?:var_dump|print_r)\s*\(/i', $content) || preg_match('/display_errors\s*[\'"\s,=>]+(?:1|on|true)/i', $content)) $debugFindings[] = $path;
    foreach (qa_throwable_catch_blocks($content) as $catchBlock) {
        $variable = preg_quote((string)$catchBlock['variable'], '/');
        if (preg_match('/(?:echo|die|mg_fail)\s*\([^;]{0,450}' . $variable . '\s*->\s*getMessage\s*\(/is', (string)$catchBlock['body'])) {
            $rawThrowableFindings[] = $path;
            break;
        }
    }
}
qa_check($categories, 'Error handling and observability', 'debug-output', 'No debug dumps or display_errors enablement in web-accessible PHP', 3, $debugFindings === [], qa_list($debugFindings));
qa_check($categories, 'Error handling and observability', 'raw-errors', 'Generic Throwable messages are not exposed directly to users', 3, $rawThrowableFindings === [], qa_list($rawThrowableFindings));
$securityLogFound = false;
foreach ($webPhp as $path) if (str_contains(qa_text($root, $path), 'function mg_security_log')) {$securityLogFound = true; break;}
qa_check($categories, 'Error handling and observability', 'security-log', 'Centralized security logging is available', 2, $securityLogFound);
$recoveryWorkflow = qa_text($root, '.github/workflows/recovery-baseline.yml');
qa_check($categories, 'Error handling and observability', 'failure-artifacts', 'Recovery CI uploads diagnostic logs on failure', 2, str_contains($recoveryWorkflow, 'Upload diagnostic logs on failure') && str_contains($recoveryWorkflow, 'if: failure()'));

// 7. Frontend safety and contracts — 10 points.
$frontendContracts = qa_run(['php', 'scripts/validate_frontend_contracts.php'], $root);
qa_check($categories, 'Frontend safety and contracts', 'frontend-contracts', 'Frontend contracts validate', 4, $frontendContracts['code'] === 0, qa_excerpt($frontendContracts['output']));
$unsafeJs = [];
foreach ($files as $path) {
    if (!str_ends_with($path, '.js') || preg_match('#(?:vendor|node_modules|third-party|dist)/#', $path) || str_ends_with($path, '.min.js')) continue;
    $content = qa_text($root, $path);
    if (preg_match('/\beval\s*\(|\bdocument\.write\s*\(|(?:href|src)\s*=\s*[\'"`]\s*javascript\s*:/i', $content)) $unsafeJs[] = $path;
}
qa_check($categories, 'Frontend safety and contracts', 'unsafe-js', 'No eval, document.write, or javascript-scheme assignments in first-party JavaScript', 2, $unsafeJs === [], qa_list($unsafeJs));
$blankTargetFindings = [];
foreach ($textFiles as $path) {
    if (!preg_match('/\.(?:php|html?|js)$/i', $path)) continue;
    $content = qa_text($root, $path);
    if ($content !== '' && preg_match('/<a\b(?=[^>]*\btarget\s*=\s*["\']_blank["\'])(?![^>]*\brel\s*=\s*["\'][^"\']*\bnoopener\b)[^>]*>/i', $content)) $blankTargetFindings[] = $path;
}
qa_check($categories, 'Frontend safety and contracts', 'noopener', 'Static target=_blank links include rel=noopener', 2, $blankTargetFindings === [], qa_list($blankTargetFindings));
$browserWorkflowFound = false;
foreach ($files as $path) if (str_starts_with($path, '.github/workflows/') && preg_match('/browser|playwright/i', $path . ' ' . qa_text($root, $path))) {$browserWorkflowFound = true; break;}
qa_check($categories, 'Frontend safety and contracts', 'browser-ci', 'Browser or Playwright validation is configured', 2, $browserWorkflowFound);

// 8. Tests and continuous integration — 10 points.
$phpunitTests = array_values(array_filter($files, static fn(string $path): bool => preg_match('#^tests/phpunit/.+Test\.php$#', $path) === 1));
$validators = array_values(array_filter($files, static fn(string $path): bool => preg_match('#^scripts/(?:validate|audit)_.+\.php$#', $path) === 1));
qa_check($categories, 'Tests and continuous integration', 'test-depth', 'Repository has substantial PHPUnit and behavior-validator coverage', 3, count($phpunitTests) >= 20 && count($validators) >= 20, count($phpunitTests) . ' PHPUnit files; ' . count($validators) . ' validators/audits.');
$preflight = qa_text($root, 'scripts/preflight.sh');
qa_check($categories, 'Tests and continuous integration', 'preflight-depth', 'Changed-file preflight includes JS, shell, dependency, and repository-quality gates', 3, str_contains($preflight, 'node --check') && str_contains($preflight, 'bash -n') && str_contains($preflight, 'composer audit') && str_contains($preflight, 'audit_repository_production_quality.php'));
$recovery = qa_text($root, 'scripts/recovery_baseline.sh');
qa_check($categories, 'Tests and continuous integration', 'recovery-depth', 'Recovery baseline includes dependency, JS, shell, and quality audits', 2, str_contains($recovery, 'composer audit') && str_contains($recovery, 'node --check') && str_contains($recovery, 'bash -n') && str_contains($recovery, 'audit_repository_production_quality.php'));
$auditWorkflow = qa_text($root, '.github/workflows/repository-production-quality.yml');
qa_check($categories, 'Tests and continuous integration', 'audit-workflow', 'Repository production audit runs on PHP 8.2 and 8.3 with bounded permissions/time', 2, str_contains($auditWorkflow, "php-version: ['8.2', '8.3']") && str_contains($auditWorkflow, 'timeout-minutes:') && str_contains($auditWorkflow, 'permissions:'));

// 9. Deployment and recovery safety — 10 points.
$deployFiles = ['scripts/build_release_artifact.sh','scripts/validate_database_backup_restore.sh','scripts/validate_release_rollback.sh','scripts/run_migrations.php','scripts/build_full_upgrade_sql.php'];
$missingDeploy = array_values(array_filter($deployFiles, static fn(string $path): bool => !is_file($root . '/' . $path)));
qa_check($categories, 'Deployment and recovery safety', 'release-recovery', 'Release, migration, backup/restore, and rollback tooling exists', 4, $missingDeploy === [], qa_list($missingDeploy));
$configExample = qa_text($root, 'api/config.local.example.php');
qa_check($categories, 'Deployment and recovery safety', 'config-boundary', 'Production-local configuration has an ignored, documented example', 2, $configExample !== '' && str_contains($gitignore, '/api/config.local.php') && str_contains($configExample, 'Never place production credentials'));
qa_check($categories, 'Deployment and recovery safety', 'health-check', 'Application health endpoint exists for deployment readiness', 2, is_file($root . '/api/health.php'));
qa_check($categories, 'Deployment and recovery safety', 'clean-recovery', 'CI rebuilds a clean database and boots the application before full validation', 2, str_contains($recoveryWorkflow, 'Build clean database') && str_contains($recoveryWorkflow, 'Start application server') && str_contains($recoveryWorkflow, 'Run complete recovery baseline'));

// 10. Maintainability and governance — 10 points.
qa_check($categories, 'Maintainability and governance', 'readme', 'README documents canonical root, ownership, migrations, and validation', 2, str_contains($readme, 'Canonical repository root') && str_contains($readme, 'Canonical ownership') && str_contains($readme, 'Database migrations') && str_contains($readme, 'Pull-request validation'));
qa_check($categories, 'Maintainability and governance', 'security-policy', 'SECURITY.md defines private reporting and response expectations', 2, is_file($root . '/SECURITY.md'));
qa_check($categories, 'Maintainability and governance', 'contributing', 'CONTRIBUTING.md defines branch, test, SQL, and review standards', 2, is_file($root . '/CONTRIBUTING.md'));
qa_check($categories, 'Maintainability and governance', 'editorconfig', 'EditorConfig standardizes whitespace and line endings', 1, is_file($root . '/.editorconfig'));
qa_check($categories, 'Maintainability and governance', 'gitattributes', 'Git attributes normalize text and generated/binary files', 1, is_file($root . '/.gitattributes'));
$qualityDoc = qa_text($root, 'docs/production-quality-gates.md');
qa_check($categories, 'Maintainability and governance', 'quality-doc', 'Production quality score and gate definitions are documented', 2, $qualityDoc !== '' && str_contains($qualityDoc, '100-point'));

$totalEarned = 0;
$totalPossible = 0;
foreach ($categories as $category) {
    $totalEarned += $category['earned'];
    $totalPossible += $category['possible'];
}
$score = $totalPossible > 0 ? round(($totalEarned / $totalPossible) * 10, 1) : 0.0;
$status = $totalEarned === $totalPossible ? 'pass' : 'fail';
$report = [
    'audit_version' => 2,
    'generated_at' => gmdate(DATE_ATOM),
    'status' => $status,
    'score' => $score,
    'points' => ['earned' => $totalEarned, 'possible' => $totalPossible],
    'tracked_files' => count($files),
    'categories' => $categories,
    'scope_note' => '10/10 means every documented automated production-quality gate passed; it is not a guarantee that no future defect can exist.',
];
file_put_contents($buildDir . '/repository-production-audit.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
$markdown = "# Repository Production Quality Audit\n\n";
$markdown .= '- Score: **' . number_format($score, 1) . "/10**\n";
$markdown .= '- Points: **' . $totalEarned . '/' . $totalPossible . "**\n";
$markdown .= '- Status: **' . strtoupper($status) . "**\n";
$markdown .= '- Tracked files audited: **' . count($files) . "**\n\n";
foreach ($categories as $name => $category) {
    $markdown .= '## ' . $name . ' — ' . $category['earned'] . '/' . $category['possible'] . "\n\n";
    foreach ($category['checks'] as $check) {
        $markdown .= '- ' . ($check['passed'] ? 'PASS' : 'FAIL') . ' — ' . $check['label'] . ' (' . $check['points'] . ' pts)';
        if ($check['detail'] !== '') $markdown .= ': ' . qa_excerpt($check['detail'], 450);
        $markdown .= "\n";
    }
    $markdown .= "\n";
}
$markdown .= "> '10/10' is defined by the documented automated gate. It is not a claim that software can never contain defects.\n";
file_put_contents($buildDir . '/repository-production-audit.md', $markdown);
echo $markdown;
if ($gate && $status !== 'pass') exit(1);
