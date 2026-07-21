<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/mcp-phase3b/bootstrap.php';
require_once dirname(__DIR__) . '/mcp-phase3b/prepare.php';
require_once dirname(__DIR__, 2) . '/includes/mcp-drafts/native-status.php';
function phase3c_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function phase3c_bootstrap(PDO $pdo): array
{
    $receipt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE event_type=?');
    $receipt->execute([mg_mcp_native_status_event_type()]);
    $fixture = phase3b_build_fixture($pdo);
    $prepared = phase3b_prepare_conversions($pdo, $fixture);
    $created = [];
    $creator = 'mg_mcp_conversion_' . 'create_native';
    foreach ((array)$prepared['conversions'] as $type=>$conversion) {
        $created[$type] = $creator($pdo, (array)$fixture['user'], (string)$conversion['id']);
    }
    return ['pdo'=>$pdo,'fixture'=>$fixture,'prepared'=>$prepared,'created'=>$created,'receipt_before'=>(int)$receipt->fetchColumn()];
}
