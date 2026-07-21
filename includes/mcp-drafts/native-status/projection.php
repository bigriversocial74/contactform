<?php
declare(strict_types=1);
function mg_mcp_native_status_projection(array $row, ?array $resolved = null, ?array $receipt = null, bool $changed = false): array
{
    $conversionId = isset($row['id']) && $row['id'] !== null ? (string)($row['public_id'] ?? '') : null;
    $conversionStatus = $conversionId === null || $conversionId === '' ? 'not_prepared' : (string)$row['status'];
    $nativeCreated = in_array($conversionStatus, ['created','opened'], true) && !empty($row['native_public_id']);
    $resolved ??= $nativeCreated ? ['state'=>'unknown','class'=>'unknown','updated_at'=>null,'details'=>[]] : ['state'=>'not_created','class'=>'not_created','updated_at'=>null,'details'=>[]];
    return [
        'draft_id'=>(string)$row['draft_public_id'],
        'draft_type'=>(string)$row['draft_type'],
        'conversion'=>[
            'id'=>$conversionId,
            'status'=>$conversionStatus,
            'type'=>isset($row['conversion_type']) && $row['conversion_type'] !== null ? (string)$row['conversion_type'] : null,
        ],
        'native'=>[
            'exists'=>$nativeCreated && (string)$resolved['class'] !== 'missing',
            'id'=>$nativeCreated ? (string)$row['native_public_id'] : null,
            'url'=>$nativeCreated ? mg_mcp_native_status_safe_url($row['native_url'] ?? null) : null,
            'state'=>(string)$resolved['state'],
            'state_class'=>(string)$resolved['class'],
            'updated_at'=>$resolved['updated_at'],
            'details'=>(array)$resolved['details'],
        ],
        'observation'=>[
            'receipt_id'=>$receipt && !empty($receipt['id']) ? 'event:' . (string)$receipt['id'] : null,
            'changed'=>$changed,
            'observed_at'=>$receipt['created_at'] ?? null,
        ],
        'execution'=>['enabled'=>false,'status'=>'read_only_status'],
    ];
}
function mg_mcp_native_status_observe_row(PDO $pdo, array $row, string $observerType, ?int $connectionId, ?int $actorUserId): array
{
    if (empty($row['id']) || !in_array((string)$row['status'], ['created','opened'], true) || empty($row['native_public_id'])) return mg_mcp_native_status_projection($row);
    $resolved = mg_mcp_native_status_resolve($pdo, $row);
    $recorded = mg_mcp_native_status_record($pdo, $row, $resolved, $observerType, $connectionId, $actorUserId);
    return mg_mcp_native_status_projection($row, $resolved, $recorded['receipt'], (bool)$recorded['changed']);
}
