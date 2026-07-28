#!/usr/bin/env python3
from pathlib import Path

path = Path("api/homeserver/_operational_intelligence.php")
text = path.read_text(encoding="utf-8")

old_cursor_functions = r'''function mg_homeserver_operational_cursor_decode(?string $cursor): array
{
    if ($cursor === null || trim($cursor) === '') return ['updated_at' => '1970-01-01 00:00:00', 'id' => '0'];
    $decoded = mg_homeserver_base64url_decode(trim($cursor));
    if (!is_string($decoded)) mg_fail('Operational cursor is invalid.', 422);
    $value = json_decode($decoded, true);
    if (!is_array($value) || !isset($value['updated_at'], $value['id']) || strlen((string)$value['updated_at']) > 30 || strlen((string)$value['id']) > 190) mg_fail('Operational cursor is invalid.', 422);
    return ['updated_at' => (string)$value['updated_at'], 'id' => (string)$value['id']];
}

function mg_homeserver_operational_cursor_encode(string $updatedAt, string $id): string
{
    return mg_homeserver_base64url_encode(mg_homeserver_json(['updated_at' => $updatedAt, 'id' => $id]));
}
'''
new_cursor_functions = r'''function mg_homeserver_operational_cursor_decode(?string $cursor): array
{
    if ($cursor === null || trim($cursor) === '') return ['version' => 2, 'sources' => [], 'legacy' => null];
    $decoded = mg_homeserver_base64url_decode(trim($cursor));
    if (!is_string($decoded)) mg_fail('Operational cursor is invalid.', 422);
    $value = json_decode($decoded, true);
    if (!is_array($value)) mg_fail('Operational cursor is invalid.', 422);

    if (isset($value['sources']) && is_array($value['sources'])) {
        $sources = [];
        if (count($value['sources']) > 64) mg_fail('Operational cursor contains too many source positions.', 422);
        foreach ($value['sources'] as $sourceKey => $position) {
            if (!is_string($sourceKey) || strlen($sourceKey) < 1 || strlen($sourceKey) > 190 || !is_array($position)) {
                mg_fail('Operational cursor is invalid.', 422);
            }
            $updatedAt = (string)($position['updated_at'] ?? '');
            $id = (string)($position['id'] ?? '');
            if ($updatedAt === '' || strlen($updatedAt) > 30 || strlen($id) > 190) mg_fail('Operational cursor is invalid.', 422);
            $sources[$sourceKey] = ['updated_at' => $updatedAt, 'id' => $id];
        }
        return ['version' => 2, 'sources' => $sources, 'legacy' => null];
    }

    if (isset($value['updated_at'], $value['id']) && strlen((string)$value['updated_at']) <= 30 && strlen((string)$value['id']) <= 190) {
        return [
            'version' => 1,
            'sources' => [],
            'legacy' => ['updated_at' => (string)$value['updated_at'], 'id' => (string)$value['id']],
        ];
    }
    mg_fail('Operational cursor is invalid.', 422);
}

function mg_homeserver_operational_source_cursor(array $cursor, string $sourceKey): array
{
    $position = $cursor['sources'][$sourceKey] ?? $cursor['legacy'] ?? null;
    if (!is_array($position)) return ['updated_at' => '1970-01-01 00:00:00', 'id' => '0'];
    return ['updated_at' => (string)$position['updated_at'], 'id' => (string)$position['id']];
}

function mg_homeserver_operational_cursor_encode(array $sources): string
{
    ksort($sources);
    return mg_homeserver_base64url_encode(mg_homeserver_json(['version' => 2, 'sources' => $sources]));
}
'''
if old_cursor_functions in text:
    text = text.replace(old_cursor_functions, new_cursor_functions, 1)
elif new_cursor_functions not in text:
    raise SystemExit("operational cursor function anchor was not found")

old_setup = r'''    $cursor = $mode === 'snapshot' ? ['updated_at' => '1970-01-01 00:00:00', 'id' => '0'] : mg_homeserver_operational_cursor_decode($cursorBefore);
    $records = [];
    $lastUpdated = $cursor['updated_at'];
    $lastId = $cursor['id'];

    foreach ($definition['sources'] as $source) {
'''
new_setup = r'''    $cursor = $mode === 'snapshot'
        ? ['version' => 2, 'sources' => [], 'legacy' => null]
        : mg_homeserver_operational_cursor_decode($cursorBefore);
    $cursorSources = is_array($cursor['sources'] ?? null) ? $cursor['sources'] : [];
    $records = [];

    foreach ($definition['sources'] as $source) {
'''
if old_setup in text:
    text = text.replace(old_setup, new_setup, 1)
elif new_setup not in text:
    raise SystemExit("operational export cursor setup anchor was not found")

old_query = r'''        $remaining = $limit - count($records);
        $sql = 'SELECT ' . $quoted . ' FROM `' . $source['table'] . '` WHERE `' . $source['merchantColumn'] . '`=? AND (`' . $updatedColumn . '`>? OR (`' . $updatedColumn . '`=? AND CAST(`' . $source['idColumn'] . '` AS CHAR)>?)) ORDER BY `' . $updatedColumn . '` ASC, `' . $source['idColumn'] . '` ASC LIMIT ' . (int)$remaining;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$merchantId, $cursor['updated_at'], $cursor['updated_at'], $cursor['id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row = mg_homeserver_operational_filter_row($row, $grant);
            $sourceId = (string)($row['public_id'] ?? $row[$source['idColumn']] ?? '');
            $sourceUpdated = (string)($row[$updatedColumn] ?? gmdate('Y-m-d H:i:s'));
            $payloadJson = mg_homeserver_json($row);
            $revision = hash('sha256', $source['table'] . '|' . $sourceId . '|' . $sourceUpdated . '|' . $payloadJson);
            $records[] = [
                'source_object_type' => $source['table'],
                'source_object_id' => $sourceId,
                'source_revision' => $revision,
                'source_updated_at_utc' => gmdate(DATE_ATOM, strtotime($sourceUpdated) ?: time()),
                'payload' => $row,
                'payload_hash' => hash('sha256', $payloadJson),
            ];
            $lastUpdated = $sourceUpdated;
            $lastId = (string)($row[$source['idColumn']] ?? $sourceId);
        }
    }

    $cursorAfter = mg_homeserver_operational_cursor_encode($lastUpdated, $lastId);
'''
new_query = r'''        $remaining = $limit - count($records);
        $sourceKey = $source['table'] . '|' . $source['merchantColumn'] . '|' . $source['idColumn'] . '|' . $updatedColumn;
        $sourceCursor = mg_homeserver_operational_source_cursor($cursor, $sourceKey);
        $sql = 'SELECT ' . $quoted . ' FROM `' . $source['table'] . '` WHERE `' . $source['merchantColumn'] . '`=? AND (`' . $updatedColumn . '`>? OR (`' . $updatedColumn . '`=? AND CAST(`' . $source['idColumn'] . '` AS CHAR)>?)) ORDER BY `' . $updatedColumn . '` ASC, `' . $source['idColumn'] . '` ASC LIMIT ' . (int)$remaining;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$merchantId, $sourceCursor['updated_at'], $sourceCursor['updated_at'], $sourceCursor['id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rawRow) {
            $sourceId = (string)($rawRow['public_id'] ?? $rawRow[$source['idColumn']] ?? '');
            $sourceUpdated = (string)($rawRow[$updatedColumn] ?? gmdate('Y-m-d H:i:s'));
            $sourcePositionId = (string)($rawRow[$source['idColumn']] ?? $sourceId);
            $row = mg_homeserver_operational_filter_row($rawRow, $grant);
            $payloadJson = mg_homeserver_json($row);
            $revision = hash('sha256', $source['table'] . '|' . $sourceId . '|' . $sourceUpdated . '|' . $payloadJson);
            $records[] = [
                'source_object_type' => $source['table'],
                'source_object_id' => $sourceId,
                'source_revision' => $revision,
                'source_updated_at_utc' => gmdate(DATE_ATOM, strtotime($sourceUpdated) ?: time()),
                'payload' => $row,
                'payload_hash' => hash('sha256', $payloadJson),
            ];
            $cursorSources[$sourceKey] = ['updated_at' => $sourceUpdated, 'id' => $sourcePositionId];
        }
    }

    $cursorAfter = mg_homeserver_operational_cursor_encode($cursorSources);
'''
if old_query in text:
    text = text.replace(old_query, new_query, 1)
elif new_query not in text:
    raise SystemExit("operational export query anchor was not found")

path.write_text(text, encoding="utf-8", newline="\n")
print("Operational exports now use opaque per-source cursors without filtered metadata loss.")
