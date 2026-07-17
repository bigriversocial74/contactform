<?php
declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

function diagnostic_run(array $command, string $cwd): array
{
    $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $cwd, null, ['bypass_shell'=>true]);
    if (!is_resource($process)) return ['code'=>127,'output'=>'Unable to start command.'];
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code'=>proc_close($process),'output'=>(string)$stdout.(string)$stderr];
}

function diagnostic_catch_blocks(string $content): array
{
    $tokens = token_get_all($content);
    $blocks = [];
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CATCH) continue;
        $signature = '';
        $variable = '';
        while (++$i < $count && $tokens[$i] !== '(') {}
        $depth = 1;
        while (++$i < $count && $depth > 0) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($text === '(') $depth++;
            if ($text === ')') $depth--;
            if ($depth > 0) {
                $signature .= $text;
                if (is_array($token) && $token[0] === T_VARIABLE) $variable = $text;
            }
        }
        while (++$i < $count && $tokens[$i] !== '{') {}
        if ($i >= $count) continue;
        $braceDepth = 1;
        $body = '';
        while (++$i < $count && $braceDepth > 0) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($text === '{') $braceDepth++;
            if ($text === '}') $braceDepth--;
            if ($braceDepth > 0) $body .= $text;
        }
        $blocks[] = ['signature'=>trim($signature),'variable'=>$variable,'body'=>$body];
    }
    return $blocks;
}

$result = diagnostic_run(['git','ls-files','*.php'], $root);
if ($result['code'] !== 0) throw new RuntimeException('Unable to enumerate PHP files.');
$files = array_values(array_filter(preg_split('/\R/', trim($result['output'])) ?: []));
$findings = [];
$allThrowable = [];
foreach ($files as $path) {
    if (preg_match('#^(?:scripts|tests|vendor|database|\.github)/#', $path)) continue;
    $content = @file_get_contents($root.'/'.$path);
    if (!is_string($content)) continue;
    foreach (diagnostic_catch_blocks($content) as $index => $block) {
        $isThrowable = preg_match('/(?:^|[|&\s\\\\])Throwable(?:[|&\s]|$)/i', $block['signature']) === 1;
        if (!$isThrowable) continue;
        $variable = preg_quote((string)$block['variable'], '/');
        $pattern = '/(?:echo|die|mg_fail)\s*\([^;]{0,450}' . $variable . '\s*->\s*getMessage\s*\(/is';
        $matched = preg_match($pattern, (string)$block['body'], $match) === 1;
        $entry = [
            'path'=>$path,
            'catch_index'=>$index,
            'signature'=>$block['signature'],
            'variable'=>$block['variable'],
            'matched'=>$matched,
            'match'=>$matched ? preg_replace('/\s+/', ' ', trim((string)$match[0])) : '',
            'body_excerpt'=>mb_substr(preg_replace('/\s+/', ' ', trim((string)$block['body'])) ?? '', 0, 900),
        ];
        $allThrowable[] = $entry;
        if ($matched) $findings[] = $entry;
    }
}

$report = [
    'generated_at'=>gmdate(DATE_ATOM),
    'throwable_catches'=>count($allThrowable),
    'matched_findings'=>count($findings),
    'findings'=>$findings,
    'all_throwable_catches'=>$allThrowable,
];
@mkdir($root.'/build', 0775, true);
file_put_contents($root.'/build/raw-throwable-diagnostics.json', json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
echo 'Throwable catches: '.count($allThrowable).PHP_EOL;
echo 'Matched findings: '.count($findings).PHP_EOL;
foreach ($findings as $finding) echo $finding['path'].' :: '.$finding['signature'].' :: '.$finding['match'].PHP_EOL;
