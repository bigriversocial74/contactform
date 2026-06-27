<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'examples/local-quest-rewards/program-builder.php',
    'examples/local-quest-rewards/admin-programs.php',
    'examples/local-quest-rewards/app.php',
];
function lqdv6_read(string $root, string $path): string
{
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
}
$checks = [];
foreach ($files as $file) $checks[] = ['name'=>'file:' . $file, 'ok'=>is_file($root . '/' . $file)];
$builder = lqdv6_read($root, 'examples/local-quest-rewards/program-builder.php');
$admin = lqdv6_read($root, 'examples/local-quest-rewards/admin-programs.php');
$app = lqdv6_read($root, 'examples/local-quest-rewards/app.php');
$checks[] = ['name'=>'builder helpers', 'ok'=>str_contains($builder, 'lqr_builder_default_settings') && str_contains($builder, 'lqr_builder_settings') && str_contains($builder, 'lqr_builder_save_settings') && str_contains($builder, 'lqr_builder_issue_gate')];
$checks[] = ['name'=>'disabled gate support', 'ok'=>str_contains($builder, 'This quest action is disabled in Program Admin') && str_contains($builder, 'This Distribution Program is disabled in Program Admin')];
$checks[] = ['name'=>'state persistence', 'ok'=>str_contains($app, "'merchant_programs' => []") && str_contains($app, "require_once __DIR__ . '/program-builder.php'")];
$checks[] = ['name'=>'issue path uses builder gate', 'ok'=>str_contains($app, 'lqr_builder_issue_gate($state') && str_contains($app, "'program_id' => (string)$") && str_contains($app, "'template_id' => (string)$") && str_contains($app, 'merchant_program_key')];
$checks[] = ['name'=>'editable admin controls', 'ok'=>str_contains($admin, 'save_program') && str_contains($admin, 'save_mapping') && str_contains($admin, 'seed_builder') && str_contains($admin, 'reset_builder')];
$checks[] = ['name'=>'mapping statuses', 'ok'=>str_contains($admin, 'mapped') && str_contains($admin, 'draft') && str_contains($admin, 'disabled') && str_contains($admin, 'Action → template mapping')];
$failed = array_values(array_filter($checks, static fn(array $check): bool => empty($check['ok'])));
$result = ['ok'=>count($failed)===0,'checks'=>$checks,'failed'=>$failed,'generated_at'=>gmdate('c')];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
