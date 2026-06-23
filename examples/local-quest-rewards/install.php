<?php
declare(strict_types=1);

session_start();

function lqi_h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function lqi_token(): string { if (empty($_SESSION['lqi_csrf'])) $_SESSION['lqi_csrf'] = bin2hex(random_bytes(32)); return (string)$_SESSION['lqi_csrf']; }
function lqi_post(string $key, string $default = ''): string { return trim((string)($_POST[$key] ?? $default)); }
function lqi_secret(string $key): string { return (string)($_POST[$key] ?? ''); }
function lqi_bool(string $key): bool { return !empty($_POST[$key]); }
function lqi_check_token(): void { if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !hash_equals((string)($_SESSION['lqi_csrf'] ?? ''), (string)($_POST['_lqi_csrf'] ?? ''))) throw new RuntimeException('Install session expired. Refresh and try again.'); }
function lqi_config_path(): string { return __DIR__ . '/config.php'; }
function lqi_schema_paths(): array { return [__DIR__ . '/database/local_quest_rewards.sql', __DIR__ . '/database/local_quest_admin_auth.sql']; }
function lqi_required_tables(): array { return ['lqr_admin_users','lqr_users','lqr_link_states','lqr_quests','lqr_quest_completions','lqr_rewards','lqr_reward_claims','lqr_admin_audit_events','lqr_events','lqr_app_state','lqr_admin_password_resets']; }
function lqi_pdo(string $host, string $db, string $user, string $secret, bool $withDb): PDO
{
    $dsn = $withDb ? "mysql:host={$host};dbname={$db};charset=utf8mb4" : "mysql:host={$host};charset=utf8mb4";
    return new PDO($dsn, $user, $secret, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
}
function lqi_run_sql_file(PDO $pdo, string $path): void
{
    if (!is_file($path)) throw new RuntimeException('Schema file missing: ' . basename($path));
    $sql = (string)file_get_contents($path);
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
    foreach ($statements ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement === '' || str_starts_with($statement, '--')) continue;
        $pdo->exec($statement);
    }
}
function lqi_write_config(array $values): void
{
    $config = [
        'app_name' => $values['app_name'],
        'app_public_url' => $values['app_public_url'],
        'base_url' => $values['base_url'],
        'api_key' => $values['api_key'],
        'default_program_id' => $values['default_program_id'],
        'default_template_id' => $values['default_template_id'],
        'webhook_secret' => $values['webhook_secret'],
        'mode' => $values['mode'],
        'allow_sandbox_shortcut' => $values['allow_sandbox_shortcut'],
        'security' => [
            'session_name' => 'LQRSESSID',
            'session_timeout_minutes' => 60,
            'csrf_field' => '_lqr_csrf',
            'csrf_ttl_minutes' => 120,
            'signed_code_ttl_minutes' => 15,
            'signed_code_secret' => $values['signed_code_secret'],
        ],
        'admin' => [
            'username' => $values['owner_username'],
            'email' => $values['owner_email'],
            'password' => '',
            'password_hash' => $values['owner_hash'],
            'bootstrap_enabled' => false,
            'reset_token_ttl_minutes' => 30,
        ],
        'storage' => [
            'dsn' => "mysql:host={$values['db_host']};dbname={$values['db_name']};charset=utf8mb4",
            'username' => $values['db_user'],
            'password' => $values['db_secret'],
            'options' => [],
        ],
    ];
    $content = "<?php\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents(lqi_config_path(), $content, LOCK_EX) === false) throw new RuntimeException('Could not write config.php. Check folder permissions.');
}
function lqi_seed_owner(PDO $pdo, string $username, string $email, string $hash): void
{
    $publicId = 'admin_' . substr(hash('sha256', strtolower($username) . '|local-quest-owner'), 0, 16);
    $stmt = $pdo->prepare("INSERT INTO lqr_admin_users (public_id,username,email,password_hash,display_name,role_key,status,created_at,updated_at) VALUES (?,?,?,?,?,'owner','active',NOW(),NOW()) ON DUPLICATE KEY UPDATE email=VALUES(email),password_hash=VALUES(password_hash),role_key='owner',status='active',updated_at=NOW()");
    $stmt->execute([$publicId, strtolower($username), strtolower($email), $hash, 'Owner']);
}
function lqi_diagnostics(?array $posted = null): array
{
    $checks = [];
    $add = static function(string $name, bool $ok, string $detail) use (&$checks): void { $checks[] = ['name'=>$name,'ok'=>$ok,'detail'=>$detail]; };
    $add('PHP version', version_compare(PHP_VERSION, '8.1.0', '>='), 'Current: ' . PHP_VERSION . ' / Required: 8.1+');
    $add('PDO extension', extension_loaded('pdo'), extension_loaded('pdo') ? 'PDO is loaded.' : 'PDO is missing.');
    $add('PDO MySQL driver', in_array('mysql', PDO::getAvailableDrivers(), true), 'Available drivers: ' . implode(', ', PDO::getAvailableDrivers()));
    $add('HTTP client', function_exists('curl_init') || (bool)ini_get('allow_url_fopen'), function_exists('curl_init') ? 'cURL is available.' : 'Stream fallback check.');
    if ($posted) {
        try {
            $pdo = lqi_pdo($posted['db_host'], $posted['db_name'], $posted['db_user'], $posted['db_secret'], true);
            $missing = [];
            foreach (lqi_required_tables() as $table) { $stmt = $pdo->prepare('SHOW TABLES LIKE ?'); $stmt->execute([$table]); if (!$stmt->fetchColumn()) $missing[] = $table; }
            $add('Database connection', true, 'Connected.');
            $add('Schema tables', empty($missing), empty($missing) ? 'Required tables are present.' : 'Missing: ' . implode(', ', $missing));
        } catch (Throwable $e) { $add('Database connection', false, $e->getMessage()); }
    }
    return $checks;
}

$message = '';
$error = '';
$checks = lqi_diagnostics();
$defaults = [
    'app_name'=>'Local Quest Rewards', 'app_public_url'=>'http://127.0.0.1:8090', 'base_url'=>'https://microgifter.com', 'mode'=>'test', 'allow_sandbox_shortcut'=>'1',
    'db_host'=>'127.0.0.1', 'db_name'=>'local_quest_rewards', 'db_user'=>'', 'api_key'=>'', 'default_program_id'=>'', 'default_template_id'=>'', 'webhook_secret'=>'', 'signed_code_secret'=>'', 'owner_username'=>'admin', 'owner_email'=>'admin@example.test'
];
$values = array_merge($defaults, $_POST);

try {
    lqi_check_token();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && lqi_post('action') === 'install') {
        $dbHost = lqi_post('db_host');
        $dbName = lqi_post('db_name');
        $dbUser = lqi_post('db_user');
        $dbSecret = lqi_secret('db_secret');
        $ownerSecret = lqi_secret('owner_secret');
        $ownerSecretConfirm = lqi_secret('owner_secret_confirm');
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) throw new RuntimeException('Database name may only contain letters, numbers, and underscores.');
        if ($dbHost === '' || $dbUser === '') throw new RuntimeException('Database host and user are required.');
        if (strlen($ownerSecret) < 12) throw new RuntimeException('Owner secret must be at least 12 characters.');
        if ($ownerSecret !== $ownerSecretConfirm) throw new RuntimeException('Owner secret confirmation does not match.');
        foreach (['app_public_url','base_url','api_key','default_program_id','default_template_id','webhook_secret'] as $required) if (lqi_post($required) === '') throw new RuntimeException($required . ' is required.');
        $signedSecret = lqi_post('signed_code_secret') ?: bin2hex(random_bytes(32));
        $rootPdo = lqi_pdo($dbHost, '', $dbUser, $dbSecret, false);
        $rootPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo = lqi_pdo($dbHost, $dbName, $dbUser, $dbSecret, true);
        foreach (lqi_schema_paths() as $schemaPath) lqi_run_sql_file($pdo, $schemaPath);
        $ownerHash = password_hash($ownerSecret, PASSWORD_DEFAULT);
        lqi_seed_owner($pdo, lqi_post('owner_username'), lqi_post('owner_email'), $ownerHash);
        lqi_write_config([
            'app_name'=>lqi_post('app_name'), 'app_public_url'=>rtrim(lqi_post('app_public_url'), '/'), 'base_url'=>rtrim(lqi_post('base_url'), '/'), 'api_key'=>lqi_secret('api_key'), 'default_program_id'=>lqi_post('default_program_id'), 'default_template_id'=>lqi_post('default_template_id'), 'webhook_secret'=>lqi_secret('webhook_secret'), 'mode'=>lqi_post('mode') ?: 'test', 'allow_sandbox_shortcut'=>lqi_bool('allow_sandbox_shortcut'), 'signed_code_secret'=>$signedSecret, 'db_host'=>$dbHost, 'db_name'=>$dbName, 'db_user'=>$dbUser, 'db_secret'=>$dbSecret, 'owner_username'=>lqi_post('owner_username'), 'owner_email'=>lqi_post('owner_email'), 'owner_hash'=>$ownerHash
        ]);
        $message = 'Install complete. config.php was written, schema was applied, and the first owner admin was created.';
        $checks = lqi_diagnostics(['db_host'=>$dbHost,'db_name'=>$dbName,'db_user'=>$dbUser,'db_secret'=>$dbSecret]);
    }
} catch (Throwable $e) { $error = $e->getMessage(); }

?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Local Quest Installer</title><style>body{margin:0;background:#071225;color:#f5f9ff;font-family:Arial,sans-serif}.wrap{width:min(1080px,94%);margin:0 auto;padding:36px 0}.card{background:#0d1b2f;border:1px solid #24415f;border-radius:18px;padding:18px;margin:12px 0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.row{display:grid;grid-template-columns:230px 90px 1fr;gap:12px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.1)}label{display:block;margin-top:10px;font-size:12px;font-weight:800;color:#c8dbef}input,select{width:100%;min-height:38px;margin-top:5px;border-radius:10px;border:1px solid #24415f;background:#07192d;color:#f5f9ff;padding:6px 9px}.ok{color:#4ade80}.bad{color:#fb7185}.notice{padding:10px 12px;border-radius:12px;background:#10243d}.error{background:rgba(251,113,133,.16);color:#ffd7df}.btn,button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 14px;border-radius:12px;background:#4ade80;color:#062113;border:0;font-weight:900;text-decoration:none;cursor:pointer}p{color:#9db3cc}@media(max-width:820px){.grid,.row{display:block}}</style></head><body><main class="wrap"><h1>Local Quest Installer</h1><p>Enter database, Microgifter API, app, and first owner admin settings. Remove or protect this file after deployment.</p><?php if($message):?><p class="notice"><?= lqi_h($message) ?></p><?php endif;?><?php if($error):?><p class="notice error"><?= lqi_h($error) ?></p><?php endif;?><section class="card"><h2>Checks</h2><?php foreach($checks as $check):?><div class="row"><strong><?= lqi_h($check['name']) ?></strong><strong class="<?= $check['ok']?'ok':'bad' ?>"><?= $check['ok']?'PASS':'FAIL' ?></strong><span><?= lqi_h($check['detail']) ?></span></div><?php endforeach;?></section><form method="post" class="card"><input type="hidden" name="_lqi_csrf" value="<?= lqi_h(lqi_token()) ?>"><h2>Setup</h2><div class="grid"><div><h3>Database</h3><label>Host<input name="db_host" value="<?= lqi_h((string)$values['db_host']) ?>" required></label><label>Database name<input name="db_name" value="<?= lqi_h((string)$values['db_name']) ?>" required></label><label>Database user<input name="db_user" value="<?= lqi_h((string)$values['db_user']) ?>" required></label><label>Database secret<input type="password" name="db_secret"></label></div><div><h3>Owner admin</h3><label>Username<input name="owner_username" value="<?= lqi_h((string)$values['owner_username']) ?>" required></label><label>Email<input type="email" name="owner_email" value="<?= lqi_h((string)$values['owner_email']) ?>" required></label><label>Owner secret<input type="password" name="owner_secret" minlength="12" required></label><label>Confirm owner secret<input type="password" name="owner_secret_confirm" minlength="12" required></label></div></div><div class="grid"><div><h3>App</h3><label>App name<input name="app_name" value="<?= lqi_h((string)$values['app_name']) ?>" required></label><label>App public URL<input name="app_public_url" value="<?= lqi_h((string)$values['app_public_url']) ?>" required></label><label>Mode<select name="mode"><option value="test" <?= ($values['mode']??'')==='test'?'selected':'' ?>>test</option><option value="live" <?= ($values['mode']??'')==='live'?'selected':'' ?>>live</option></select></label><label><input type="checkbox" name="allow_sandbox_shortcut" value="1" <?= !empty($values['allow_sandbox_shortcut'])?'checked':'' ?>> Allow sandbox shortcut</label></div><div><h3>Microgifter</h3><label>Base URL<input name="base_url" value="<?= lqi_h((string)$values['base_url']) ?>" required></label><label>Developer API key<input type="password" name="api_key" value="<?= lqi_h((string)$values['api_key']) ?>" required></label><label>Program ID<input name="default_program_id" value="<?= lqi_h((string)$values['default_program_id']) ?>" required></label><label>Template ID<input name="default_template_id" value="<?= lqi_h((string)$values['default_template_id']) ?>" required></label><label>Webhook signing value<input type="password" name="webhook_secret" value="<?= lqi_h((string)$values['webhook_secret']) ?>" required></label><label>Local signed-code secret <small>(optional; generated if blank)</small><input type="password" name="signed_code_secret" value="<?= lqi_h((string)$values['signed_code_secret']) ?>"></label></div></div><p><button name="action" value="install">Install / Update foundation</button></p></form><p><a class="btn" href="cover.php">Open app</a> <a class="btn" href="admin.php">Open admin</a></p></main></body></html>
