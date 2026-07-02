<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';
require_once __DIR__ . '/_system_health_security.php';
require_once __DIR__ . '/_security_hardening_audit.php';

function mg_admin_security_hardening_recount(array &$data): void
{
    $checks = is_array($data['checks'] ?? null) ? $data['checks'] : [];
    $critical = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'critical'));
    $warning = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'warning'));
    $healthy = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'healthy'));
    $categories = [];
    foreach ($checks as $check) {
        $category = (string)($check['category'] ?? 'general');
        if (!isset($categories[$category])) {
            $categories[$category] = ['key' => $category, 'label' => ucwords(str_replace('_', ' ', $category)), 'healthy' => 0, 'warning' => 0, 'critical' => 0, 'info' => 0, 'status' => 'healthy'];
        }
        $status = in_array((string)($check['status'] ?? ''), ['healthy','warning','critical','info'], true) ? (string)$check['status'] : 'warning';
        $categories[$category][$status]++;
        if ($status === 'critical') $categories[$category]['status'] = 'critical';
        elseif ($status === 'warning' && $categories[$category]['status'] !== 'critical') $categories[$category]['status'] = 'warning';
    }
    $data['status'] = $critical > 0 ? 'critical' : ($warning > 0 ? 'warning' : 'healthy');
    $data['summary'] = $data['status'] === 'healthy' ? 'Security hardening audit passed all automated checks.' : ($critical > 0 ? $critical . ' critical hardening issue(s) need attention.' : $warning . ' hardening warning(s) need review.');
    $data['counts'] = ['checks' => count($checks), 'healthy' => $healthy, 'warning' => $warning, 'critical' => $critical, 'info' => count($checks) - $healthy - $warning - $critical];
    $data['categories'] = array_values($categories);
}

function mg_admin_security_hardening_apply_webroot_protection_context(array $data): array
{
    $root = dirname(__DIR__, 2);
    $protectedDirs = ['database','docs','scripts','tests'];
    $rootHtaccess = is_file($root . '/.htaccess') ? (string)@file_get_contents($root . '/.htaccess') : '';
    foreach ($data['checks'] as &$check) {
        $key = (string)($check['key'] ?? '');
        if (!str_starts_with($key, 'public_dir_')) {
            continue;
        }
        $dir = substr($key, strlen('public_dir_'));
        if (!in_array($dir, $protectedDirs, true)) {
            continue;
        }
        $folderHtaccess = $root . '/' . $dir . '/.htaccess';
        $hasFolderDeny = is_file($folderHtaccess);
        $hasRootDeny = $rootHtaccess !== '' && (str_contains($rootHtaccess, 'RewriteRule ^' . $dir . '/') || str_contains($rootHtaccess, 'REQUEST_URI} !^/('));
        if ($hasFolderDeny || $hasRootDeny) {
            $check['status'] = 'healthy';
            $check['summary'] = $dir . ' exists under the deploy root, but direct web access is blocked by deploy protection rules.';
            $check['evidence']['protected_by_root_htaccess'] = $hasRootDeny;
            $check['evidence']['protected_by_folder_htaccess'] = $hasFolderDeny;
            $check['recommendations'] = [];
        }
    }
    unset($check);
    mg_admin_security_hardening_recount($data);
    return $data;
}

mg_require_method('GET');
$user = mg_admin_system_health_require_user();
mg_admin_system_health_require_security_auditor($user);

try {
    mg_rate_limit('admin.security_hardening_audit.read', 'user:' . (int)$user['id'], 12, 60);
    $data = mg_admin_security_hardening_apply_webroot_protection_context(mg_security_hardening_audit(mg_db()));
    $data['access'] = ['restricted_to_super_admin' => true];
    mg_security_log('info', 'admin.security_hardening_audit.viewed', 'Security hardening audit viewed.', [
        'status' => $data['status'],
        'critical' => $data['counts']['critical'] ?? 0,
        'warning' => $data['counts']['warning'] ?? 0,
    ], (int)$user['id']);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.security_hardening_audit.failed', 'Security hardening audit request failed.', [
        'exception_class' => $error::class,
        'message' => mb_substr($error->getMessage(), 0, 240),
    ], (int)$user['id']);
    mg_fail('Unable to run security hardening audit.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
mg_ok($data, 'Security hardening audit loaded.');
