<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function validation_file(string $root, string $path): string
{
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
    return $content;
}

function validation_contains(string $content, string $needle, string $message): void
{
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function validation_excludes(string $content, string $needle, string $message): void
{
    if (str_contains($content, $needle)) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$merchantView = validation_file($root, 'includes/merchant-hosted-games-view.php');
$adminView = validation_file($root, 'admin/hosted-games.php');
$merchantPage = validation_file($root, 'merchant-games.php');
$resolver = validation_file($root, 'includes/hosted-game-program-integration.php');
$merchantEndpoint = validation_file($root, 'api/merchant/hosted-game-integration.php');
$adminEndpoint = validation_file($root, 'api/admin/hosted-game-integration.php');
$merchantJs = validation_file($root, 'assets/js/merchant-hosted-games-program-only.js');
$adminJs = validation_file($root, 'assets/js/admin-hosted-games-program-only.js');

foreach ([$merchantView, $adminView] as $view) {
    validation_contains($view, 'Distribution integration', 'Hosted Games must label the program-only integration section.');
    validation_contains($view, 'name="program_id" required', 'Hosted Games must expose the Distribution Program selector.');
    validation_excludes($view, '<label>Campaign<select', 'Campaign must not be exposed as a separate Hosted Games selector.');
    validation_excludes($view, '<label>Program reward<select', 'Reward must not be exposed as a separate Hosted Games selector.');
}

validation_contains($merchantPage, 'merchant-hosted-games-program-only.js', 'Merchant Hosted Games must load the program-only controller.');
validation_contains($adminView, 'admin-hosted-games-program-only.js', 'Admin Hosted Games must load the program-only controller.');

validation_contains($resolver, "metadata['campaign_ids']", 'The resolver must read campaign IDs from Distribution Program metadata.');
validation_contains($resolver, 'distribution_program_products', 'The resolver must derive reward inventory from the Distribution Program.');
validation_contains($resolver, 'count($campaignIds) !== 1', 'Hosted-game programs must reject ambiguous campaign selection.');
validation_contains($resolver, 'count($rewards) !== 1', 'Hosted-game programs must reject ambiguous reward inventory.');

foreach ([$merchantEndpoint, $adminEndpoint] as $endpoint) {
    validation_contains($endpoint, '($input[\'program_id\']', 'The integration endpoint must accept the Distribution Program ID.');
    validation_excludes($endpoint, '($input[\'campaign_id\']', 'The integration endpoint must not trust a browser campaign ID.');
    validation_excludes($endpoint, '($input[\'reward_template_id\']', 'The integration endpoint must not trust a browser reward ID.');
    validation_contains($endpoint, 'mg_hosted_game_resolve_program_integration', 'The integration endpoint must resolve program relationships server-side.');
}

validation_contains($merchantJs, '/api/merchant/hosted-game-integration.php', 'Merchant controller must use the program-only endpoint.');
validation_contains($adminJs, '/api/admin/hosted-game-integration.php', 'Admin controller must use the program-only endpoint.');
validation_contains($merchantJs, 'stopImmediatePropagation', 'Merchant controller must replace the legacy multi-selector submit handler.');
validation_contains($adminJs, 'stopImmediatePropagation', 'Admin controller must replace the legacy multi-selector submit handler.');

echo "Hosted Games program-only integration validation passed.\n";
