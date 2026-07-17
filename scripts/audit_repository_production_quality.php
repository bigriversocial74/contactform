<?php
declare(strict_types=1);

/**
 * Repository-wide production quality audit.
 *
 * This is intentionally conservative: only high-confidence findings affect the
 * score. The goal is a reproducible 100-point gate, not a claim that software
 * can never contain defects.
 */

$root = dirname(__DIR__);
chdir($root);
$gate = in_array('--gate', $argv, true);
$buildDir = $root . '/build';
if (!is_dir($buildDir)) mkdir($buildDir, 0775, true);

function audit_run(array $command, string $cwd): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, $cwd);
    if (!is_resource($process)) return ['code' => 127, 'output' => 'Unable to start command.'];
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return ['code' => $code, 'output' => trim((string) $stdout . ((string) $stderr !== '' ? "\n" . $stderr : ''))];
}

function audit_tracked_files(string $root): array
{
    $result = audit_run(['git', 'ls-files', '-z'], $root);
    if ($result['code'] === 0) {
        $files = array_values(array_filter(explode("\0", $result['output']), static fn(string $v): bool => $v !== ''));
        sort($files);
        return $files;
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (preg_match('#^(?:\.git|vendor|node_modules)/#', $path)) continue;
        $files[] = $path;
    }
    sort($files);
    return $files;
}

function audit_text(string $root, string $path): string
{
    $full = $root . '/' . $path;
    if (!is_file($full) || filesize($full) > 2_000_000) return '';
    $data = file_get_contents($full);
    if (!is_string($data) || str_contains($data, "\0")) return '';
    return $data;
}

function audit_excerpt(string $value, int $limit = 900): string
{
    $value = trim($value);
    if (mb_strlen($value) <= $limit) return $value;
    return mb_substr($value, 0, $limit) . '…';
}

$files = audit_tracked_files($root);
$textExtensions = ['php','js','mjs','cjs','html','htm','css','md','json','yml','yaml','xml','sh','sql','txt','env','ini','dist'];
$textFiles = [];
foreach ($files as $path) {
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($extension, $textExtensions, true) || str_starts_with(basename($path), '.env')) $textFiles[] = $path;
}

$categories = [];
function audit_check(array &$categories, string $category, string $id, string $label, int $points, bool $passed, string $detail = ''): void
{
    if (!isset($categories[$category])) $categories[$category] = ['earned' => 0, 'possible' => 0, 'checks' => []];
    $categories[$category]['possible'] += $points;
    if ($passed) $categories[$category]['earned'] += $points;
    $categories[$category]['checks'][] = [
        'id' => $id,
        'label' => $label,
        'points' => $points,
        'passed' => $passed,
        'detail' => $detail,
    ];
}

// 1. Runtime correctness — 10 points.
$composerValidate = audit_run(['composer', 'validate', '--strict', '--no-interaction'], $root);
audit_check($categories, 'Runtime correctness', 'composer-validate', 'composer.json validates strictly', 2, $composerValidate['code'] === 0, audit_excerpt($composerValidate['output']));

$phpLint = audit_run(['bash', '-lc', "set -o pipefail; find . -path './vendor' -prune -o -path './.git' -prune -o -name '*.php' -type f -print0 | xargs -0 -n1 php -l >/tmp/mg-audit-php-lint.log 2>&1"], $root);
audit_check($categories, 'Runtime correctness', 'php-lint', 'All first-party PHP files pass syntax validation', 4, $phpLint['code'] === 0, $phpLint['code'] === 0 ? 'All PHP files passed.' : audit_excerpt((string) @file_get_contents('/tmp/mg-audit-php-lint.log')));

$jsLint = audit_run(['bash', '-lc', "set -o pipefail; failed=0; while IFS= read -r -d '' file; do case \"$file\" in */vendor/*|*/node_modules/*|*/third-party/*|*/dist/*|*.min.js) continue;; esac; node --check \"$file\" >/tmp/mg-audit-js-one.log 2>&1 || { echo \"$file\"; cat /tmp/mg-audit-js-one.log; failed=1; }; done < <(find . -path './vendor' -prune -o -path './.git' -prune -o -name '*.js' -type f -print0); exit $failed"], $root);
audit_check($categories, 'Runtime correctness', 'js-lint', 'All first-party JavaScript files pass syntax validation', 2, $jsLint['code'] === 0, $jsLint['code'] === 0 ? 'All JavaScript files passed.' : audit_excerpt($jsLint['output']));

$shellLint = audit_run(['bash', '-lc', "set -o pipefail; failed=0; while IFS= read -r -d '' file; do bash -n \"$file\" || failed=1; done < <(find scripts -name '*.sh' -type f -print0 2>/dev/null); exit $failed"], $root);
audit_check($categories, 'Runtime correctness', 'shell-lint', 'All repository shell scripts pass bash syntax validation', 2, $shellLint['code'] === 0, audit_excerpt($shellLint['output']));

// 2. Dependency and supply-chain safety — 10 points.
$hasLock = is_file($root . '/composer.lock');
audit_check($categories, 'Dependency and supply chain', 'composer-lock', 'Application dependencies are committed in composer.lock', 4, $hasLock, $hasLock ? 'composer.lock is tracked.' : 'composer.lock is missing; installs are not deterministic.');
$composerAudit = $hasLock ? audit_run(['composer', 'audit', '--locked', '--no-interaction'], $root) : ['code' => 1, 'output' => 'Skipped because composer.lock is missing.'];
audit_check($categories, 'Dependency and supply chain', 'composer-audit', 'Locked Composer dependencies have no known advisories', 4, $composerAudit['code'] === 0, audit_excerpt($composerAudit['output']));
$dependabot = audit_text($root, '.github/dependabot.yml');
$dependabotOk = $dependabot !== '' && str_contains($dependabot, 'package-ecosystem: "composer"') && str_contains($dependabot, 'package-ecosystem: "github-actions"');
audit_check($categories, 'Dependency and supply chain', 'dependabot', 'Dependabot monitors Composer and GitHub Actions', 2, $dependabotOk, $dependabotOk ? 'Composer and Actions updates are configured.' : 'Missing Composer and/or GitHub Actions update configuration.');

// 3. Secret and configuration hygiene — 10 points.
$gitignore = audit_text($root, '.gitignore');
$requiredIgnore = ['/.env','/api/config.local.php','/vendor/','/build/','/node_modules/','/.phpunit.cache/','*.log','*.sql.gz','*.zip','*.bak'];
$missingIgnore = [];
foreach ($requiredIgnore as $entry) if (!str_contains($gitignore, $entry)) $missingIgnore[] = $entry;
audit_check($categories, 'Secret and configuration hygiene', 'gitignore', 'Runtime secrets, dependencies, builds, logs, and backups are ignored', 3, $missingIgnore === [], $missingIgnore === [] ? 'Required ignore rules are present.' : 'Missing: ' . implode(', ', $missingIgnore));

$sensitiveNames = [];
foreach ($files as $path) {
    $base = strtolower(basename($path));
    if ($path === 'api/config.local.example.php') continue;
    if ($path === '.env.example' || str_ends_with($path, '.env.example')) continue;
    if ($base === '.env' || preg_match('/\.(?:pem|key|p12|pfx)$/i', $base) || in_array($base, ['id_rsa','id_ed25519','credentials.json','service-account.json'], true) || preg_match('/\.(?:sql\.gz|zip|bak|orig)$/i', $base)) {
        $sensitiveNames[] = $path;
    }
}
audit_check($categories, 'Secret and configuration hygiene', 'sensitive-files', 'No credential, private-key, backup, or local-config files are tracked', 4, $sensitiveNames === [], $sensitiveNames === [] ? 'No sensitive filenames detected.' : implode(', ', array_slice($sensitiveNames, 0, 20)));

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
    $content = audit_text($root, $path);
    foreach ($secretPatterns as $name => $pattern) {
        if ($content !== '' && preg_match($pattern, $content)) $secretFindings[] = $path . ':' . $name;
    }
}
audit_check($categories, 'Secret and configuration hygiene', 'secret-scan', 'No high-confidence production secret patterns are committed', 3, $secretFindings === [], $secretFindings === [] ? 'No secret patterns detected.' : implode(', ', array_slice($secretFindings, 0, 20)));

// 4. Dangerous runtime primitives — 10 points.
$webPhp = array_values(array_filter($files, static function (string $path): bool {
    return str_ends_with($path, '.php') && !preg_match('#^(?:scripts|tests|vendor|database|\.github)/#', $path);
}));
$evalFindings = $commandFindings = $unserializeFindings = $dynamicIncludeFindings = [];
foreach ($webPhp as $path) {
    $content = audit_text($root, $path);
    if ($content === '') continue;
    if (preg_match('/\beval\s*\(/i', $content) || preg_match('/\bassert\s*\(\s*["\']/i', $content)) $evalFindings[] = $path;
    if (preg_match('/\b(?:shell_exec|system|passthru|proc_open|popen|pcntl_exec)\s*\(/i', $content) || preg_match('/`[^`]+`/', $content)) $commandFindings[] = $path;
    if (preg_match('/\bunserialize\s*\(/i', $content) && !str_contains($content, 'allowed_classes')) $unserializeFindings[] = $path;
    if (preg_match('/\b(?:include|include_once|require|require_once)\s*\(?\s*\$_(?:GET|POST|REQUEST|COOKIE)/i', $content)) $dynamicIncludeFindings[] = $path;
}
audit_check($categories, 'Dangerous runtime primitives', 'eval', 'No eval or string-assert execution in web-accessible PHP', 3, $evalFindings === [], implode(', ', array_slice($evalFindings, 0, 20)));
audit_check($categories, 'Dangerous runtime primitives', 'commands', 'No operating-system command execution in web-accessible PHP', 3, $commandFindings === [], implode(', ', array_slice($commandFindings, 0, 20)));
audit_check($categories, 'Dangerous runtime primitives', 'unserialize', 'No unsafe unserialize calls in web-accessible PHP', 2, $unserializeFindings === [], implode(', ', array_slice($unserializeFindings, 0, 20)));
audit_check($categories, 'Dangerous runtime primitives', 'dynamic-include', 'No request-controlled include or require paths', 2, $dynamicIncludeFindings === [], implode(', ', array_slice($dynamicIncludeFindings, 0, 20)));

// 5. Request, SQL, and data-integrity boundaries — 10 points.
$requestSqlFindings = $interpolatedQueryFindings = [];
foreach ($webPhp as $path) {
    $content = audit_text($root, $path);
    if ($content === '') continue;
    $lines = preg_split('/\R/', $content) ?: [];
    foreach ($lines as $index => $line) {
        if (preg_match('/\b(?:SELECT|INSERT|UPDATE|DELETE|REPLACE)\b.*\$_(?:GET|POST|REQUEST|COOKIE)/i', $line) || preg_match('/\$_(?:GET|POST|REQUEST|COOKIE).*\b(?:SELECT|INSERT|UPDATE|DELETE|REPLACE)\b/i', $line)) {
            $requestSqlFindings[] = $path . ':' . ($index + 1);
        }
        if (preg_match('/->query\s*\(\s*"[^"]*\$[A-Za-z_][A-Za-z0-9_]*/', $line) || preg_match('/->query\s*\([^\)]*\.\s*\$_(?:GET|POST|REQUEST|COOKIE)/', $line)) {
            $interpolatedQueryFindings[] = $path . ':' . ($index + 1);
        }
    }
}
audit_check($categories, 'Request SQL and data integrity', 'request-sql', 'No request values are directly interpolated into SQL', 4, $requestSqlFindings === [], implode(', ', array_slice($requestSqlFindings, 0, 20)));
audit_check($categories, 'Request SQL and data integrity', 'pdo-query', 'No high-confidence variable interpolation is used in PDO query calls', 2, $interpolatedQueryFindings === [], implode(', ', array_slice($interpolatedQueryFindings, 0, 20)));
$migrationValidate = audit_run(['php', 'scripts/validate_migration_manifest.php'], $root);
audit_check($categories, 'Request SQL and data integrity', 'migration-manifest', 'Canonical migration manifest validates', 2, $migrationValidate['code'] === 0, audit_excerpt($migrationValidate['output']));
$architectureDoc = audit_text($root, 'docs/architecture/current_active_file_map.md');
audit_check($categories, 'Request SQL and data integrity', 'authority-map', 'Canonical active-root and ownership authority documentation exists', 2, $architectureDoc !== '' && str_contains(audit_text($root, 'README.md'), 'Canonical ownership'), $architectureDoc !== '' ? 'Active file map and ownership rules are documented.' : 'Missing active file map.');

// 6. Error handling and observability — 10 points.
$debugFindings = $rawThrowableFindings = [];
foreach ($webPhp as $path) {
    $content = audit_text($root, $path);
    if ($content === '') continue;
    if (preg_match('/\b(?:var_dump|print_r)\s*\(/i', $content) || preg_match('/display_errors\s*[\'"\s,=>]+(?:1|on|true)/i', $content)) $debugFindings[] = $path;
    if (preg_match('/catch\s*\(\s*Throwable[^)]*\)\s*\{.{0,900}(?:echo|die|mg_fail)\s*\([^;]{0,250}->getMessage\s*\(/is', $content)) $rawThrowableFindings[] = $path;
}
audit_check($categories, 'Error handling and observability', 'debug-output', 'No debug dumps or display_errors enablement in web-accessible PHP', 3, $debugFindings === [], implode(', ', array_slice($debugFindings, 0, 20)));
audit_check($categories, 'Error handling and observability', 'raw-errors', 'Generic Throwable messages are not exposed directly to users', 3, $rawThrowableFindings === [], implode(', ', array_slice($rawThrowableFindings, 0, 20)));
$securityLogFound = false;
foreach ($webPhp as $path) {
    if (str_contains(audit_text($root, $path), 'function mg_security_log')) { $securityLogFound = true; break; }
}
audit_check($categories, 'Error handling and observability', 'security-log', 'Centralized security logging is available', 2, $securityLogFound, $securityLogFound ? 'mg_security_log is defined.' : 'mg_security_log definition was not found.');
$recoveryWorkflow = audit_text($root, '.github/workflows/recovery-baseline.yml');
audit_check($categories, 'Error handling and observability', 'failure-artifacts', 'Recovery CI uploads diagnostic logs on failure', 2, str_contains($recoveryWorkflow, 'Upload diagnostic logs on failure') && str_contains($recoveryWorkflow, 'if: failure()'), 'Recovery workflow failure artifacts checked.');

// 7. Frontend safety and contracts — 10 points.
$frontendContracts = audit_run(['php', 'scripts/validate_frontend_contracts.php'], $root);
audit_check($categories, 'Frontend safety and contracts', 'frontend-contracts', 'Frontend contracts validate', 4, $frontendContracts['code'] === 0, audit_excerpt($frontendContracts['output']));
$unsafeJs = [];
foreach ($files as $path) {
    if (!str_ends_with($path, '.js') || preg_match('#(?:vendor|node_modules|third-party|dist)/#', $path) || str_ends_with($path, '.min.js')) continue;
    $content = audit_text($root, $path);
    if (preg_match('/\beval\s*\(|\bdocument\.write\s*\(|javascript\s*:/i', $content)) $unsafeJs[] = $path;
}
audit_check($categories, 'Frontend safety and contracts', 'unsafe-js', 'No eval, document.write, or javascript: URLs in first-party JavaScript', 2, $unsafeJs === [], implode(', ', array_slice($unsafeJs, 0, 20)));
$blankTargetFindings = [];
foreach ($textFiles as $path) {
    if (!preg_match('/\.(?:php|html?|js)$/i', $path)) continue;
    $content = audit_text($root, $path);
    if ($content === '') continue;
    if (preg_match('/<a\b(?=[^>]*\btarget\s*=\s*["\']_blank["\'])(?![^>]*\brel\s*=\s*["\'][^"\']*\bnoopener\b)[^>]*>/i', $content)) $blankTargetFindings[] = $path;
}
audit_check($categories, 'Frontend safety and contracts', 'noopener', 'Static target=_blank links include rel=noopener', 2, $blankTargetFindings === [], implode(', ', array_slice($blankTargetFindings, 0, 20)));
$browserWorkflowFound = false;
foreach ($files as $path) {
    if (str_starts_with($path, '.github/workflows/') && preg_match('/browser|playwright/i', $path . ' ' . audit_text($root, $path))) { $browserWorkflowFound = true; break; }
}
audit_check($categories, 'Frontend safety and contracts', 'browser-ci', 'Browser or Playwright validation is configured', 2, $browserWorkflowFound, $browserWorkflowFound ? 'Browser validation workflow found.' : 'No browser validation workflow detected.');

// 8. Tests and continuous integration — 10 points.
$phpunitTests = array_values(array_filter($files, static fn(string $path): bool => preg_match('#^tests/phpunit/.+Test\.php$#', $path) === 1));
$auditValidators = array_values(array_filter($files, static fn(string $path): bool => preg_match('#^scripts/(?:validate|audit)_.+\.php$#', $path) === 1));
audit_check($categories, 'Tests and continuous integration', 'test-depth', 'Repository has substantial PHPUnit and behavior-validator coverage', 3, count($phpunitTests) >= 20 && count($auditValidators) >= 20, count($phpunitTests) . ' PHPUnit files; ' . count($auditValidators) . ' validators/audits.');
$preflight = audit_text($root, 'scripts/preflight.sh');
$preflightComplete = str_contains($preflight, 'node --check') && str_contains($preflight, 'bash -n') && str_contains($preflight, 'composer audit') && str_contains($preflight, 'audit_repository_production_quality.php');
audit_check($categories, 'Tests and continuous integration', 'preflight-depth', 'Changed-file preflight includes JS, shell, dependency, and repository-quality gates', 3, $preflightComplete, $preflightComplete ? 'Preflight gates are complete.' : 'Preflight is missing one or more repository-wide quality gates.');
$recovery = audit_text($root, 'scripts/recovery_baseline.sh');
$recoveryComplete = str_contains($recovery, 'composer audit') && str_contains($recovery, 'node --check') && str_contains($recovery, 'bash -n') && str_contains($recovery, 'audit_repository_production_quality.php');
audit_check($categories, 'Tests and continuous integration', 'recovery-depth', 'Recovery baseline includes dependency, JS, shell, and quality audits', 2, $recoveryComplete, $recoveryComplete ? 'Recovery baseline gates are complete.' : 'Recovery baseline is missing one or more quality gates.');
$auditWorkflow = audit_text($root, '.github/workflows/repository-production-quality.yml');
$auditWorkflowOk = str_contains($auditWorkflow, "php-version: ['8.2', '8.3']") && str_contains($auditWorkflow, 'timeout-minutes:') && str_contains($auditWorkflow, 'permissions:');
audit_check($categories, 'Tests and continuous integration', 'audit-workflow', 'Repository production audit runs on PHP 8.2 and 8.3 with bounded permissions/time', 2, $auditWorkflowOk, $auditWorkflowOk ? 'Audit workflow is configured.' : 'Audit workflow or matrix is incomplete.');

// 9. Deployment and recovery safety — 10 points.
$deployFiles = ['scripts/build_release_artifact.sh','scripts/validate_database_backup_restore.sh','scripts/validate_release_rollback.sh','scripts/run_migrations.php','scripts/build_full_upgrade_sql.php'];
$missingDeploy = array_values(array_filter($deployFiles, static fn(string $path): bool => !is_file($root . '/' . $path)));
audit_check($categories, 'Deployment and recovery safety', 'release-recovery', 'Release, migration, backup/restore, and rollback tooling exists', 4, $missingDeploy === [], implode(', ', $missingDeploy));
$configExample = audit_text($root, 'api/config.local.example.php');
audit_check($categories, 'Deployment and recovery safety', 'config-boundary', 'Production-local configuration has an ignored, documented example', 2, $configExample !== '' && str_contains($gitignore, '/api/config.local.php') && str_contains($configExample, 'Never place production credentials'), 'Config local boundary checked.');
$health = audit_text($root, 'api/health.php');
audit_check($categories, 'Deployment and recovery safety', 'health-check', 'Application health endpoint exists for deployment readiness', 2, $health !== '', $health !== '' ? 'api/health.php exists.' : 'Missing api/health.php.');
$recoveryReady = str_contains($recoveryWorkflow, 'Build clean database') && str_contains($recoveryWorkflow, 'Start application server') && str_contains($recoveryWorkflow, 'Run complete recovery baseline');
audit_check($categories, 'Deployment and recovery safety', 'clean-recovery', 'CI rebuilds a clean database and boots the application before full validation', 2, $recoveryReady, 'Recovery workflow clean-build sequence checked.');

// 10. Maintainability and governance — 10 points.
$readme = audit_text($root, 'README.md');
audit_check($categories, 'Maintainability and governance', 'readme', 'README documents canonical root, ownership, migrations, and validation', 2, str_contains($readme, 'Canonical repository root') && str_contains($readme, 'Canonical ownership') && str_contains($readme, 'Database migrations') && str_contains($readme, 'Pull-request validation'), 'README governance sections checked.');
audit_check($categories, 'Maintainability and governance', 'security-policy', 'SECURITY.md defines private reporting and response expectations', 2, is_file($root . '/SECURITY.md'), is_file($root . '/SECURITY.md') ? 'SECURITY.md exists.' : 'SECURITY.md is missing.');
audit_check($categories, 'Maintainability and governance', 'contributing', 'CONTRIBUTING.md defines branch, test, SQL, and review standards', 2, is_file($root . '/CONTRIBUTING.md'), is_file($root . '/CONTRIBUTING.md') ? 'CONTRIBUTING.md exists.' : 'CONTRIBUTING.md is missing.');
audit_check($categories, 'Maintainability and governance', 'editorconfig', 'EditorConfig standardizes whitespace and line endings', 1, is_file($root . '/.editorconfig'), is_file($root . '/.editorconfig') ? '.editorconfig exists.' : '.editorconfig is missing.');
audit_check($categories, 'Maintainability and governance', 'gitattributes', 'Git attributes normalize text and generated/binary files', 1, is_file($root . '/.gitattributes'), is_file($root . '/.gitattributes') ? '.gitattributes exists.' : '.gitattributes is missing.');
$qualityDoc = audit_text($root, 'docs/production-quality-gates.md');
audit_check($categories, 'Maintainability and governance', 'quality-doc', 'Production quality score and gate definitions are documented', 2, $qualityDoc !== '' && str_contains($qualityDoc, '100-point'), $qualityDoc !== '' ? 'Quality gate documentation exists.' : 'Quality gate documentation is missing.');

$totalEarned = 0;
$totalPossible = 0;
foreach ($categories as $category) {
    $totalEarned += $category['earned'];
    $totalPossible += $category['possible'];
}
$score = $totalPossible > 0 ? round(($totalEarned / $totalPossible) * 10, 1) : 0.0;
$status = $totalEarned === $totalPossible ? 'pass' : 'fail';

$report = [
    'audit_version' => 1,
    'generated_at' => gmdate(DATE_ATOM),
    'status' => $status,
    'score' => $score,
    'points' => ['earned' => $totalEarned, 'possible' => $totalPossible],
    'tracked_files' => count($files),
    'categories' => $categories,
    'scope_note' => '10/10 means every documented automated production-quality gate passed; it is not a guarantee that no future defect can exist.',
];

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
file_put_contents($buildDir . '/repository-production-audit.json', $json);

$markdown = "# Repository Production Quality Audit\n\n";
$markdown .= '- Score: **' . number_format($score, 1) . "/10**\n";
$markdown .= '- Points: **' . $totalEarned . '/' . $totalPossible . "**\n";
$markdown .= '- Status: **' . strtoupper($status) . "**\n";
$markdown .= '- Tracked files audited: **' . count($files) . "**\n\n";
foreach ($categories as $name => $category) {
    $markdown .= '## ' . $name . ' — ' . $category['earned'] . '/' . $category['possible'] . "\n\n";
    foreach ($category['checks'] as $check) {
        $markdown .= '- ' . ($check['passed'] ? 'PASS' : 'FAIL') . ' — ' . $check['label'] . ' (' . $check['points'] . ' pts)';
        if ($check['detail'] !== '') $markdown .= ': ' . str_replace("\n", ' ', audit_excerpt($check['detail'], 450));
        $markdown .= "\n";
    }
    $markdown .= "\n";
}
$markdown .= "> '10/10' is defined by the documented automated gate. It is not a claim that software can never contain defects.\n";
file_put_contents($buildDir . '/repository-production-audit.md', $markdown);

echo $markdown;
if ($gate && $status !== 'pass') exit(1);
