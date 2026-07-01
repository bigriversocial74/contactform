<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$options = getopt('', ['file:', 'output::']);
$file = isset($options['file']) && is_string($options['file']) ? trim($options['file']) : '';
$output = isset($options['output']) && is_string($options['output']) ? trim($options['output']) : '';
if ($file === '' || !is_file($file)) {
    throw new InvalidArgumentException('Use --file=/path/to/file.sql.');
}

$config = require dirname(__DIR__) . '/api/config.php';
$db = $config['db'];
if (($user = getenv('MG_MIGRATION_DB_USER')) !== false && $user !== '') {
    $db['user'] = $user;
}
if (($pass = getenv('MG_MIGRATION_DB_PASS')) !== false) {
    $db['pass'] = $pass;
}

$database = (string)($db['name'] ?? '');
if ($database === '' || preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
    throw new RuntimeException('A safe MG_DB_NAME is required.');
}

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $database, $db['charset']),
    (string)$db['user'],
    (string)$db['pass'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$sql = file_get_contents($file);
if (!is_string($sql) || trim($sql) === '') {
    throw new RuntimeException('SQL file is empty.');
}
$sql = str_replace(["\r\n", "\r"], "\n", $sql);

function mg_find_sql_delimiter(string $buffer, string $delimiter): ?int
{
    $length = strlen($buffer);
    $delimiterLength = strlen($delimiter);
    $quote = null;
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $buffer[$i];
        $next = $i + 1 < $length ? $buffer[$i + 1] : '';

        if ($lineComment) {
            if ($char === "\n") $lineComment = false;
            continue;
        }
        if ($blockComment) {
            if ($char === '*' && $next === '/') {
                $blockComment = false;
                $i++;
            }
            continue;
        }
        if ($quote !== null) {
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($char === $quote) {
                if ($next === $quote) {
                    $i++;
                    continue;
                }
                $quote = null;
            }
            continue;
        }

        if ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($buffer[$i + 2]))) {
            $lineComment = true;
            $i++;
            continue;
        }
        if ($char === '#') {
            $lineComment = true;
            continue;
        }
        if ($char === '/' && $next === '*') {
            $blockComment = true;
            $i++;
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }
        if (substr($buffer, $i, $delimiterLength) === $delimiter) {
            return $i;
        }
    }

    return null;
}

function mg_sql_has_executable_content(string $statement): bool
{
    $withoutBlocks = preg_replace('#/\*.*?\*/#s', '', $statement) ?? $statement;
    $withoutLines = preg_replace('/^\s*(?:--\s|#).*$/m', '', $withoutBlocks) ?? $withoutBlocks;
    return trim($withoutLines) !== '';
}

$delimiter = ';';
$buffer = '';
$statementCount = 0;
$startedAt = gmdate('c');

foreach (explode("\n", $sql) as $line) {
    if (trim($buffer) === '' && preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $match) === 1) {
        $delimiter = $match[1];
        continue;
    }

    $buffer .= $line . "\n";
    while (($position = mg_find_sql_delimiter($buffer, $delimiter)) !== null) {
        $statement = substr($buffer, 0, $position);
        $buffer = substr($buffer, $position + strlen($delimiter));
        if (!mg_sql_has_executable_content($statement)) continue;
        $pdo->exec(trim($statement));
        $statementCount++;
    }
}

if (mg_sql_has_executable_content($buffer)) {
    $pdo->exec(trim($buffer));
    $statementCount++;
}

$report = [
    'status' => 'passed',
    'database' => $database,
    'file' => basename($file),
    'sha256' => hash('sha256', $sql),
    'statement_count' => $statementCount,
    'started_at' => $startedAt,
    'completed_at' => gmdate('c'),
];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

if ($output !== '') {
    $directory = dirname($output);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create SQL import evidence directory.');
    }
    if (file_put_contents($output, $json) === false) {
        throw new RuntimeException('Unable to write SQL import evidence.');
    }
}

fwrite(STDOUT, $json);
