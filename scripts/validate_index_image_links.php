<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$index = $root . '/index.php';
$code = is_file($index) ? (string) file_get_contents($index) : '';

$refs = [];
preg_match_all('/src=["\'](\/images\/[^"\']+)["\']/i', $code, $srcMatches);
preg_match_all('/url\(["\']?(\/images\/[^"\')]+)["\']?\)/i', $code, $urlMatches);

foreach (($srcMatches[1] ?? []) as $ref) $refs[] = $ref;
foreach (($urlMatches[1] ?? []) as $ref) $refs[] = $ref;
$refs = array_values(array_unique($refs));

$missing = [];
foreach ($refs as $ref) {
    $path = $root . $ref;
    if (!is_file($path)) {
        $missing[] = $ref;
    }
}

$result = [
    'ok' => count($missing) === 0,
    'checked' => $refs,
    'missing' => $missing,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
