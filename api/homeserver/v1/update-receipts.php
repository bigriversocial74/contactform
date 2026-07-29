<?php
declare(strict_types=1);

require_once __DIR__ . '/_contract.php';
mg_hs_v1_require_route('/api/homeserver/v1/updates/receipts');
mg_require_method('POST');

$context = mg_hs_v1_require_device('device-heartbeat.v1');
$pdo = $context['pdo'];
$connection = mg_hs_v1_connection_by_public($pdo, (string)$context['connection']['public_id']);
$payload = $context['payload'];
$receipts = $payload['receipts'] ?? null;
if (!is_array($receipts)) $receipts = [$payload];
if ($receipts === [] || count($receipts) > MG_HOMESERVER_V1_MAX_RECEIPTS) {
    mg_hs_v1_fail('microgifter_request_invalid', 'The update receipt batch is invalid.', 422);
}

$safeReceipts = [];
foreach ($receipts as $receipt) {
    if (!is_array($receipt)) continue;
    $safeReceipts[] = [
        'receipt_id' => mg_hs_v1_optional_string($receipt['receipt_id'] ?? $receipt['authorization_id'] ?? $receipt['update_id'] ?? null, 190, 'receipt identity'),
        'update_id' => mg_hs_v1_optional_string($receipt['update_id'] ?? null, 190, 'update identity'),
        'version' => mg_hs_v1_optional_string($receipt['version'] ?? null, 40, 'update version'),
        'disposition' => mg_hs_v1_optional_string($receipt['disposition'] ?? $receipt['result_state'] ?? $receipt['status'] ?? null, 80, 'receipt disposition'),
    ];
}

mg_hs_v1_record_receipt($pdo, $connection, 'update.receipts_delegated_to_vp3', 'success', $context['request_id'], null, null, 'vp3_software_authority', [
    'receipt_count' => count($safeReceipts),
    'software_authority' => 'vp3',
]);

mg_hs_v1_ok([
    'accepted' => false,
    'delegated' => true,
    'software_authority' => 'vp3',
    'receipts' => array_map(static fn(array $receipt): array => [
        'receipt_id' => $receipt['receipt_id'],
        'accepted' => false,
        'delegated' => true,
    ], $safeReceipts),
], 'VP3 receives HomeServer software update receipts.');
