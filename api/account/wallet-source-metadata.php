<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

try {
    if (!function_exists('mg_store_table_exists')) {
        require_once dirname(__DIR__) . '/store/_canvas.php';
    }
    if (!mg_store_table_exists($pdo, 'wallet_items')) {
        mg_ok(['items'=>[], 'schema_ready'=>false]);
    }

    $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));
    $stmt = $pdo->prepare(
        "SELECT public_id,metadata_json,updated_at
         FROM wallet_items
         WHERE user_id=? AND status<>'cancelled'
         ORDER BY updated_at DESC,id DESC
         LIMIT {$limit}"
    );
    $stmt->execute([(int)$user['id']]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $metadata = [];
        $raw = trim((string)($row['metadata_json'] ?? ''));
        if ($raw !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) $metadata = $decoded;
            } catch (Throwable) {}
        }
        $sourceSystem = trim((string)($metadata['source_system'] ?? ''));
        $sourceLabel = trim((string)($metadata['source_label'] ?? ''));
        $sourceDetail = trim((string)($metadata['source_detail'] ?? $metadata['campaign_title'] ?? ''));
        $sourceReference = trim((string)($metadata['source_reference'] ?? $metadata['store_session_id'] ?? $metadata['campaign_id'] ?? ''));
        if ($sourceSystem === '' && $sourceLabel === '' && $sourceDetail === '') continue;
        $items[] = [
            'action_item_id' => 'wallet-' . (string)$row['public_id'],
            'wallet_item_id' => (string)$row['public_id'],
            'source_system' => $sourceSystem,
            'source_type' => trim((string)($metadata['source_type'] ?? $sourceSystem)),
            'source_label' => $sourceLabel !== '' ? $sourceLabel : ($sourceSystem !== '' ? ucwords(str_replace(['_','-'], ' ', $sourceSystem)) : 'Microgifter'),
            'source_detail' => $sourceDetail,
            'source_reference' => $sourceReference,
        ];
    }
    mg_ok(['items'=>$items, 'schema_ready'=>true]);
} catch (Throwable $error) {
    mg_security_log('error', 'wallet.source_metadata_failed', 'Wallet source metadata lookup failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_ok(['items'=>[], 'schema_ready'=>false]);
}
