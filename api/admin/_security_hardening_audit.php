<?php
declare(strict_types=1);

function mg_security_hardening_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1) return false;
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_security_hardening_columns(PDO $pdo, string $table): array
{
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1) return [];
    try {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        return array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
    } catch (Throwable) {
        return [];
    }
}

function mg_security_hardening_check(array &$checks, string $category, string $key, string $label, string $status, string $summary, array $evidence = [], array $recommendations = []): void
{
    if (!in_array($status, ['healthy','warning','critical','info'], true)) $status = 'warning';
    $checks[] = [
        'category' => $category,
        'key' => $key,
        'label' => $label,
        'status' => $status,
        'summary' => mb_substr($summary, 0, 300),
        'evidence' => $evidence,
        'recommendations' => array_values(array_map(static fn($item): string => mb_substr((string)$item, 0, 220), $recommendations)),
    ];
}

function mg_security_hardening_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((bool)mg_config_value('app', 'trust_proxy', false) && (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));
}

function mg_security_hardening_headers_present(): array
{
    $headers = [];
    foreach (headers_list() as $header) {
        $name = strtolower(trim(strtok($header, ':') ?: $header));
        if ($name !== '') $headers[$name] = $header;
    }
    return $headers;
}

function mg_security_hardening_schema_checks(PDO $pdo): array
{
    $checks = [];
    $required = [
        'security_logs' => ['id','severity','event_type','message','context_json','created_at'],
        'audit_logs' => ['id','action','entity_type','metadata_json','created_at'],
        'rate_limits' => ['id','action','identifier_hash','attempts','first_seen_at','last_seen_at','locked_until'],
        'user_sessions' => ['id','user_id','session_hash','last_seen_at','expires_at','revoked_at'],
        'users' => ['id','email','status'],
        'roles' => ['id','slug'],
        'permissions' => ['id','slug'],
        'role_permissions' => ['role_id','permission_id'],
        'user_roles' => ['user_id','role_id'],
    ];
    foreach ($required as $table => $columns) {
        if (!mg_security_hardening_table_exists($pdo, $table)) {
            mg_security_hardening_check($checks, 'database', 'table_' . $table, $table, 'critical', 'Required security table is missing.', ['table' => $table], ['Run the canonical migration set before relying on this control.']);
            continue;
        }
        $actual = mg_security_hardening_columns($pdo, $table);
        $missing = array_values(array_filter($columns, static fn(string $column): bool => empty($actual[$column])));
        mg_security_hardening_check(
            $checks,
            'database',
            'table_' . $table,
            $table,
            $missing === [] ? 'healthy' : 'critical',
            $missing === [] ? 'Required table and columns are present.' : 'Required security columns are missing.',
            ['table' => $table, 'missing_columns' => $missing],
            $missing === [] ? [] : ['Repair the listed columns before treating the security layer as complete.']
        );
    }
    return $checks;
}

function mg_security_hardening_grant_audit(PDO $pdo): array
{
    try {
        $currentUser = (string)$pdo->query('SELECT CURRENT_USER()')->fetchColumn();
        $rows = $pdo->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_COLUMN);
        $joined = strtoupper(implode("\n", array_map('strval', $rows)));
        $criticalFlags = [];
        foreach (['ALL PRIVILEGES','GRANT OPTION','SUPER','FILE','CREATE USER','SHUTDOWN','RELOAD'] as $privilege) {
            if (str_contains($joined, $privilege)) $criticalFlags[] = $privilege;
        }
        $reviewFlags = [];
        foreach (['DROP','ALTER','CREATE','INDEX','TRIGGER','EVENT'] as $privilege) {
            if (preg_match('/\b' . preg_quote($privilege, '/') . '\b/', $joined)) $reviewFlags[] = $privilege;
        }
        $status = $criticalFlags ? 'critical' : ($reviewFlags ? 'warning' : 'healthy');
        return [[
            'category' => 'database',
            'key' => 'db_privileges',
            'label' => 'Database user privileges',
            'status' => $status,
            'summary' => $criticalFlags ? 'Database user has high-risk privileges.' : ($reviewFlags ? 'Database user has migration-level privileges that should be reviewed for production.' : 'No high-risk database privileges were detected.'),
            'evidence' => ['current_user_hash' => hash('sha256', $currentUser), 'grant_count' => count($rows), 'high_risk_privileges' => $criticalFlags, 'review_privileges' => $reviewFlags],
            'recommendations' => $criticalFlags ? ['Use a least-privilege application DB user without ALL PRIVILEGES, GRANT OPTION, SUPER, FILE, CREATE USER, SHUTDOWN, or RELOAD.'] : ($reviewFlags ? ['Consider a separate migration DB user for DDL and a narrower runtime DB user for the application.'] : []),
        ]];
    } catch (Throwable $error) {
        return [[
            'category' => 'database',
            'key' => 'db_privileges',
            'label' => 'Database user privileges',
            'status' => 'warning',
            'summary' => 'Database grants could not be inspected from this host.',
            'evidence' => ['exception_class' => $error::class],
            'recommendations' => ['Manually confirm the production DB user follows least-privilege rules.'],
        ]];
    }
}

function mg_security_hardening_runtime_checks(): array
{
    $checks = [];
    $https = mg_security_hardening_is_https();
    mg_security_hardening_check($checks, 'runtime', 'https_detected', 'HTTPS detection', $https ? 'healthy' : 'warning', $https ? 'Request is detected as HTTPS.' : 'Request was not detected as HTTPS by PHP.', ['https' => $https, 'trust_proxy' => (bool)mg_config_value('app', 'trust_proxy', false)], ['Confirm HTTPS is enforced at the host/proxy and trust_proxy is configured only when needed.']);
    mg_security_hardening_check($checks, 'runtime', 'display_errors', 'Display errors', filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN) ? 'critical' : 'healthy', filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN) ? 'display_errors is enabled.' : 'display_errors is disabled.', ['display_errors' => ini_get('display_errors')], ['Disable display_errors in production; log errors server-side instead.']);
    mg_security_hardening_check($checks, 'runtime', 'log_errors', 'PHP error logging', filter_var(ini_get('log_errors'), FILTER_VALIDATE_BOOLEAN) ? 'healthy' : 'warning', filter_var(ini_get('log_errors'), FILTER_VALIDATE_BOOLEAN) ? 'PHP error logging is enabled.' : 'PHP error logging is not enabled.', ['log_errors' => ini_get('log_errors')], ['Enable log_errors for production visibility.']);
    mg_security_hardening_check($checks, 'runtime', 'expose_php', 'Expose PHP header', filter_var(ini_get('expose_php'), FILTER_VALIDATE_BOOLEAN) ? 'warning' : 'healthy', filter_var(ini_get('expose_php'), FILTER_VALIDATE_BOOLEAN) ? 'expose_php is enabled.' : 'expose_php is disabled.', ['expose_php' => ini_get('expose_php')], ['Set expose_php=Off in production.']);
    $session = session_get_cookie_params();
    mg_security_hardening_check($checks, 'session', 'cookie_httponly', 'Session HttpOnly cookie', !empty($session['httponly']) ? 'healthy' : 'critical', !empty($session['httponly']) ? 'Session cookie is HttpOnly.' : 'Session cookie is not HttpOnly.', ['httponly' => !empty($session['httponly'])], ['Set session.cookie_httponly=1.']);
    mg_security_hardening_check($checks, 'session', 'cookie_secure', 'Session Secure cookie', !empty($session['secure']) ? 'healthy' : ($https ? 'critical' : 'warning'), !empty($session['secure']) ? 'Session cookie is Secure.' : 'Session cookie is not marked Secure.', ['secure' => !empty($session['secure']), 'https' => $https], ['Set session.cookie_secure=1 once HTTPS is enforced.']);
    $sameSite = (string)($session['samesite'] ?? '');
    mg_security_hardening_check($checks, 'session', 'cookie_samesite', 'Session SameSite cookie', in_array(strtolower($sameSite), ['lax','strict'], true) ? 'healthy' : 'warning', $sameSite !== '' ? 'Session SameSite is configured.' : 'Session SameSite is not configured.', ['samesite' => $sameSite ?: null], ['Use SameSite=Lax or Strict for the application session cookie.']);
    mg_security_hardening_check($checks, 'session', 'strict_mode', 'Session strict mode', filter_var(ini_get('session.use_strict_mode'), FILTER_VALIDATE_BOOLEAN) ? 'healthy' : 'warning', filter_var(ini_get('session.use_strict_mode'), FILTER_VALIDATE_BOOLEAN) ? 'Session strict mode is enabled.' : 'Session strict mode is not enabled.', ['session.use_strict_mode' => ini_get('session.use_strict_mode')], ['Set session.use_strict_mode=1 to reject uninitialized session IDs.']);
    return $checks;
}

function mg_security_hardening_header_checks(): array
{
    $checks = [];
    $headers = mg_security_hardening_headers_present();
    $expected = [
        'x-content-type-options' => 'X-Content-Type-Options',
        'x-frame-options' => 'X-Frame-Options',
        'referrer-policy' => 'Referrer-Policy',
        'permissions-policy' => 'Permissions-Policy',
        'content-security-policy' => 'Content-Security-Policy',
        'cache-control' => 'Cache-Control',
    ];
    foreach ($expected as $key => $label) {
        mg_security_hardening_check($checks, 'headers', $key, $label, isset($headers[$key]) ? 'healthy' : 'critical', isset($headers[$key]) ? $label . ' is present on this API response.' : $label . ' is missing on this API response.', ['present' => isset($headers[$key])], ['Ensure mg_apply_api_security_headers() runs before direct API responses.']);
    }
    $hstsPresent = isset($headers['strict-transport-security']);
    mg_security_hardening_check($checks, 'headers', 'strict_transport_security', 'Strict-Transport-Security', $hstsPresent ? 'healthy' : (mg_security_hardening_is_https() ? 'warning' : 'info'), $hstsPresent ? 'HSTS is present.' : 'HSTS is not present on this response.', ['present' => $hstsPresent], ['Enable HSTS only after HTTPS is stable for the domain and subdomains.']);
    return $checks;
}

function mg_security_hardening_file_checks(): array
{
    $checks = [];
    $root = dirname(__DIR__, 2);
    $config = dirname(__DIR__) . '/config.php';
    $configExists = is_file($config);
    $configWritableWorld = $configExists && ((fileperms($config) & 0x0002) !== 0);
    mg_security_hardening_check($checks, 'files', 'api_config_file', 'API config file', $configExists && !$configWritableWorld ? 'healthy' : 'critical', !$configExists ? 'api/config.php is missing.' : ($configWritableWorld ? 'api/config.php is world-writable.' : 'api/config.php exists and is not world-writable.'), ['exists' => $configExists, 'world_writable' => $configWritableWorld], ['Keep config files non-world-writable and outside public download paths when possible.']);
    $risky = ['.env','phpinfo.php','debug.log','api/config.php.bak','api/config.php~','config.php.bak','config.php~'];
    foreach ($risky as $relative) {
        $path = $root . '/' . $relative;
        mg_security_hardening_check($checks, 'files', 'risky_' . preg_replace('/[^a-z0-9]+/i', '_', $relative), $relative, is_file($path) ? 'critical' : 'healthy', is_file($path) ? 'Risky public-root file exists.' : 'Risky public-root file was not found.', ['relative_path' => $relative, 'exists' => is_file($path)], ['Remove backup, env, debug, and phpinfo files from the deployed web root.']);
    }
    foreach (['database','docs','scripts','tests'] as $dir) {
        $path = $root . '/' . $dir;
        mg_security_hardening_check($checks, 'files', 'public_dir_' . $dir, $dir . ' directory', is_dir($path) ? 'warning' : 'healthy', is_dir($path) ? $dir . ' exists under the deploy root; verify it is not browsable/downloadable.' : $dir . ' directory is not present under deploy root.', ['exists' => is_dir($path)], ['Block direct web access to operational folders with server config or move them outside web root.']);
    }
    return $checks;
}

function mg_security_hardening_recent_events(PDO $pdo): array
{
    if (!mg_security_hardening_table_exists($pdo, 'security_logs')) return [];
    try {
        $stmt = $pdo->query("SELECT severity,event_type,message,created_at FROM security_logs WHERE severity IN ('warning','error','critical') ORDER BY created_at DESC,id DESC LIMIT 20");
        return array_map(static fn(array $row): array => [
            'severity' => (string)$row['severity'],
            'event_type' => (string)$row['event_type'],
            'message' => mb_substr((string)$row['message'], 0, 180),
            'created_at' => $row['created_at'] ?? null,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable) {
        return [];
    }
}

function mg_security_hardening_audit(PDO $pdo): array
{
    $checks = [];
    array_push($checks, ...mg_security_hardening_runtime_checks());
    array_push($checks, ...mg_security_hardening_header_checks());
    array_push($checks, ...mg_security_hardening_schema_checks($pdo));
    array_push($checks, ...mg_security_hardening_grant_audit($pdo));
    array_push($checks, ...mg_security_hardening_file_checks());

    $critical = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'critical'));
    $warning = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'warning'));
    $healthy = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'healthy'));
    $status = $critical > 0 ? 'critical' : ($warning > 0 ? 'warning' : 'healthy');
    $categories = [];
    foreach ($checks as $check) {
        $category = (string)$check['category'];
        if (!isset($categories[$category])) $categories[$category] = ['key' => $category, 'label' => ucwords(str_replace('_', ' ', $category)), 'healthy' => 0, 'warning' => 0, 'critical' => 0, 'info' => 0, 'status' => 'healthy'];
        $categories[$category][$check['status']]++;
        if ($check['status'] === 'critical') $categories[$category]['status'] = 'critical';
        elseif ($check['status'] === 'warning' && $categories[$category]['status'] !== 'critical') $categories[$category]['status'] = 'warning';
    }
    return [
        'status' => $status,
        'summary' => $status === 'healthy' ? 'Security hardening audit passed all automated checks.' : ($critical > 0 ? $critical . ' critical hardening issue(s) need attention.' : $warning . ' hardening warning(s) need review.'),
        'counts' => ['checks' => count($checks), 'healthy' => $healthy, 'warning' => $warning, 'critical' => $critical, 'info' => count($checks) - $healthy - $warning - $critical],
        'categories' => array_values($categories),
        'checks' => $checks,
        'recent_security_events' => mg_security_hardening_recent_events($pdo),
        'generated_at' => gmdate('c'),
        'request_id' => function_exists('mg_request_id') ? mg_request_id() : null,
    ];
}
