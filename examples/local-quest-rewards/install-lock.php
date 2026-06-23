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
        'installed_at' => gmdate('c'),
        'message' => 'Local Quest installer is locked. Remove install.php or create .install-unlock temporarily to re-run setup.',
    ];
    file_put_contents(lqi_lock_path(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function lqi_lock_screen(): void
{
    http_response_code(403);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Installer locked</title><style>body{margin:0;background:#071225;color:#f5f9ff;font-family:Arial,sans-serif}.wrap{width:min(760px,92%);margin:0 auto;padding:54px 0}.card{background:#0d1b2f;border:1px solid #24415f;border-radius:18px;padding:20px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 14px;border-radius:12px;background:#172b47;color:#f5f9ff;border:1px solid #24415f;font-weight:900;text-decoration:none}p{color:#9db3cc}</style></head><body><main class="wrap"><section class="card"><h1>Installer locked</h1><p>This starter app already appears to be installed. For safety, the web installer should not run again from a public URL.</p><p>To re-run setup intentionally, create a temporary <code>.install-unlock</code> file in this app folder, complete setup, then remove the unlock file and protect or delete <code>install.php</code>.</p><p><a class="btn" href="admin.php">Open admin</a> <a class="btn" href="cover.php">Open app</a></p></section></main></body></html>';
    exit;
}

function lqi_guard_installer(): void
{
    if (lqi_is_installed() && !lqi_is_unlocked()) lqi_lock_screen();
}
