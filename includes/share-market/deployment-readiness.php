<?php
declare(strict_types=1);

if (!function_exists('mg_share_market_deploy_check')) {
    function mg_share_market_deploy_check(string $key, string $label, bool $passed, string $message, array $extra = []): array
    {
        return array_merge(['key'=>$key,'label'=>$label,'passed'=>$passed,'message'=>$message], $extra);
    }
}

if (!function_exists('mg_share_market_deploy_score')) {
    function mg_share_market_deploy_score(array $checks): int
    {
        if (!$checks) return 0;
        $passed = 0;
        foreach ($checks as $check) if (!empty($check['passed'])) $passed++;
        return (int)round(($passed / count($checks)) * 100);
    }
}

if (!function_exists('mg_share_market_deploy_file_checks')) {
    function mg_share_market_deploy_file_checks(string $root, array $paths): array
    {
        $checks = [];
        foreach ($paths as $path) {
            $exists = is_file(rtrim($root, '/') . '/' . ltrim($path, '/'));
            $checks[] = mg_share_market_deploy_check('file:' . $path, $path, $exists, $exists ? 'File is present.' : 'File is missing.', ['type'=>'file','path'=>$path]);
        }
        return $checks;
    }
}

if (!function_exists('mg_share_market_deploy_table_checks')) {
    function mg_share_market_deploy_table_checks(PDO $pdo, array $tables): array
    {
        $checks = [];
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        foreach ($tables as $table) {
            $stmt->execute([$table]);
            $exists = (bool)$stmt->fetchColumn();
            $checks[] = mg_share_market_deploy_check('table:' . $table, $table, $exists, $exists ? 'Table is installed.' : 'Table is missing.', ['type'=>'table','table'=>$table]);
        }
        return $checks;
    }
}

if (!function_exists('mg_share_market_deploy_permission_checks')) {
    function mg_share_market_deploy_permission_checks(PDO $pdo, array $permissions): array
    {
        $checks = [];
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=?');
        foreach ($permissions as $slug) {
            $stmt->execute([$slug]);
            $exists = (int)$stmt->fetchColumn() > 0;
            $checks[] = mg_share_market_deploy_check('permission:' . $slug, $slug, $exists, $exists ? 'Permission is installed.' : 'Permission is missing.', ['type'=>'permission','permission'=>$slug]);
        }
        return $checks;
    }
}

if (!function_exists('mg_share_market_deployment_readiness')) {
    function mg_share_market_deployment_readiness(PDO $pdo, string $root): array
    {
        $tables = [
            'share_market_enrollments','share_market_approval_requests','share_market_execution_attempts','share_market_execution_preflight_snapshots','share_market_execution_operator_signoffs','share_market_legal_release_evidence','share_market_execution_rollback_evidence','share_market_idempotency_reservations','share_market_evidence_candidates','share_market_evidence_acknowledgements','share_market_handoff_archives'
        ];
        $permissions = ['share_market.admin','share_market.participate','share_market.review'];
        $files = [
            'account-share-market-admin.php','account-share-market-approvals.php','account-share-market-execution-audit.php','account-share-market-candidate-packet.php','account-share-market-candidate-comparison.php','account-share-market-operations-handoff.php',
            'api/admin/share-market/evidence-readiness.php','api/admin/share-market/evidence-export.php','api/admin/share-market/evidence-candidates.php','api/admin/share-market/evidence-acknowledgements.php','api/admin/share-market/preflight-handoff.php','api/admin/share-market/handoff-archives.php','api/admin/share-market/operations-handoff-packet.php',
            'assets/js/share-market-execution-audit.js','assets/js/share-market-operations-handoff.js','assets/js/share-market-operations-handoff-links.js'
        ];
        $checks = array_merge(
            mg_share_market_deploy_table_checks($pdo, $tables),
            mg_share_market_deploy_permission_checks($pdo, $permissions),
            mg_share_market_deploy_file_checks($root, $files)
        );
        $missing = array_values(array_filter($checks, static fn(array $check): bool => empty($check['passed'])));
        return [
            'readiness_version' => 'phase_23_deployment_readiness_v1',
            'generated_at' => gmdate('c'),
            'score' => mg_share_market_deploy_score($checks),
            'ready' => count($missing) === 0,
            'checks' => $checks,
            'missing' => $missing,
            'summary' => [
                'total_checks' => count($checks),
                'passed_checks' => count($checks) - count($missing),
                'missing_checks' => count($missing),
                'table_checks' => count($tables),
                'permission_checks' => count($permissions),
                'file_checks' => count($files),
            ],
            'domain_mutations_performed' => false,
        ];
    }
}
