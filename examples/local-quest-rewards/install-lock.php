<?php
declare(strict_types=1);

function lqi_lock_path(): string
{
    return __DIR__ . '/.installed.lock';
}

function lqi_unlock_path(): string
{
    return __DIR__ . '/.install-unlock';
}

function lqi_is_installed(): bool
{
    return is_file(__DIR__ . '/config.php') || is_file(lqi_lock_path());
}

function lqi_is_unlocked(): bool
{
    return is_file(lqi_unlock_path());
}

function lqi_write_lock(): void
{
    $payload = [
        'schema_version' => defined('LQI_SCHEMA_VERSION') ? LQI_SCHEMA_VERSION : 'unknown',
        'installed_at' => gmdate('c'),
        'message' => 'Local Quest installer is locked. Create .install-unlock only for intentional maintenance and remove it immediately afterward.',
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || file_put_contents(lqi_lock_path(), $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Installation completed but the installer lock could not be written. Protect or remove install.php immediately.');
    }
    @chmod(lqi_lock_path(), 0600);
}

function lqi_lock_screen(): void
{
    http_response_code(403);
    $hasConfig = is_file(__DIR__ . '/config.php');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Installer locked</title><style>body{margin:0;background:#f4f8f5;color:#14211b;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{width:min(760px,92%);margin:0 auto;padding:70px 0}.card{background:#fff;border:1px solid #dfe7e2;border-radius:26px;padding:32px;box-shadow:0 22px 70px rgba(25,54,40,.12)}.mark{width:48px;height:48px;border-radius:15px;display:grid;place-items:center;background:#155f44;color:#fff;font-weight:950}h1{font-size:42px;letter-spacing:-.06em;margin:22px 0 10px}p{color:#66736c;line-height:1.65}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 16px;border-radius:13px;background:#155f44;color:#fff;font-weight:900;text-decoration:none}.btn.soft{background:#fff;color:#14211b;border:1px solid #dfe7e2}code{background:#eef4f0;padding:2px 6px;border-radius:6px}</style></head><body><main class="wrap"><section class="card"><span class="mark">LQ</span><h1>Installer locked.</h1><p>This application already appears to be installed. Public reruns are blocked to protect the database, owner account, API credentials, and configuration.</p><p>For intentional maintenance, create a temporary <code>.install-unlock</code> file in this folder, complete the work, and remove the file immediately. Keep <code>install.php</code> deleted or server-protected on live deployments.</p><div class="actions">' . ($hasConfig ? '<a class="btn" href="cover.php">Open public site</a><a class="btn soft" href="admin-credentials.php">Admin sign in</a>' : '') . '</div></section></main></body></html>';
    exit;
}

function lqi_guard_installer(): void
{
    if (lqi_is_installed() && !lqi_is_unlocked()) lqi_lock_screen();
}
