<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$root = dirname(__DIR__);
$commands = [
    'composer migrate',
    'php scripts/run_stage3_delivery.php',
    'php scripts/run_stage4_product_assets.php',
    'php scripts/run_stage4b_builder.php',
    'php scripts/run_stage4c_feed_stream.php',
    'php scripts/stage4d.php',
    'php scripts/stage4e.php',
    'php scripts/stage4f.php',
    'php scripts/stage5a.php',
    'php scripts/stage5c.php',
    'php scripts/stage5d.php',
    'php scripts/stage5e.php',
    'php scripts/stage5f.php',
    'php scripts/stage5g.php',
    'php scripts/stage5h.php',
    'php scripts/stage5i.php',
    'php scripts/stage5j.php',
    'php scripts/stage7b.php',
    'php scripts/stage8b.php',
    'php scripts/stage9b.php',
    'php scripts/stage9d.php',
    'php scripts/stage9e3_smoke.php',
];

$manifest = [
    'name' => 'Stage 9E-3 Early Install Upgrade Manifest',
    'deployment_style' => 'zip_upload_extract_over_existing_stage1_install',
    'preserve_tables' => ['users', 'roles', 'permissions', 'role_permissions', 'sessions'],
    'backup_required_before_upload' => true,
    'safe_for_context' => 'existing Stage 1 install with only test/admin accounts and no Stage 2-9 production data',
    'commands' => $commands,
    'notes' => [
        'Upload and extract the latest repo files over the existing codebase.',
        'Do not drop or recreate the database because existing login/account rows should be preserved.',
        'Run the commands from the project root after updating environment/config values.',
        'If a command fails, stop and inspect the error before running later commands.',
    ],
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
