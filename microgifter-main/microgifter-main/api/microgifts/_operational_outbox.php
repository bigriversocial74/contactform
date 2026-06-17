<?php
declare(strict_types=1);

function mg_claim_operational_outbox(PDO $pdo,string $topic,string $aggregateType,string $aggregatePublicId,array $payload): string
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Operational outbox writes must occur inside the owning domain transaction.');
    }

    $publicId = function_exists('mg_microgift_uuid')
        ? mg_microgift_uuid()
        : sprintf('%s-%s-%s-%s-%s',bin2hex(random_bytes(4)),bin2hex(random_bytes(2)),bin2hex(random_bytes(2)),bin2hex(random_bytes(2)),bin2hex(random_bytes(6)));

    $stmt = $pdo->prepare(
        "INSERT INTO microgift_operational_outbox
        (public_id,topic,aggregate_type,aggregate_public_id,payload_json,status,available_at,created_at,updated_at)
        VALUES (?,?,?,?,?,'pending',NOW(),NOW(),NOW())"
    );
    $stmt->execute([
        $publicId,
        $topic,
        $aggregateType,
        $aggregatePublicId,
        json_encode($payload,JSON_THROW_ON_ERROR),
    ]);

    return $publicId;
}
