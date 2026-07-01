<?php
declare(strict_types=1);

function mg_agent_memory_source_private_path(string $storageKey): ?string
{
    $storageKey = trim($storageKey);
    if ($storageKey === '' || str_contains($storageKey, "\0")) return null;
    $storageKey = ltrim(str_replace('\\', '/', $storageKey), '/');
    if (!str_starts_with($storageKey, 'agent-memory/') || str_contains($storageKey, '../')) return null;
    $root = dirname(__DIR__, 2) . '/storage/private';
    $path = $root . '/' . $storageKey;
    $rootReal = realpath($root);
    $fileReal = realpath($path);
    if (!$rootReal || !$fileReal || !str_starts_with($fileReal, $rootReal . DIRECTORY_SEPARATOR)) return null;
    return is_file($fileReal) ? $fileReal : null;
}

function mg_agent_memory_source_command_exists(string $command): bool
{
    $command = trim($command);
    if ($command === '' || preg_match('/[^A-Za-z0-9_.-]/', $command)) return false;
    $lookup = stripos(PHP_OS_FAMILY, 'Windows') === 0 ? 'where' : 'command -v';
    $output = [];
    $code = 1;
    @exec($lookup . ' ' . escapeshellarg($command) . ' 2>/dev/null', $output, $code);
    return $code === 0 && $output !== [];
}

function mg_agent_memory_source_run_text_command(string $command): string
{
    $output = [];
    $code = 1;
    @exec($command . ' 2>&1', $output, $code);
    $text = trim(implode("\n", $output));
    if ($code !== 0) {
        throw new RuntimeException(mb_substr($text !== '' ? $text : 'Document extraction command failed.', 0, 420));
    }
    return $text;
}

function mg_agent_memory_source_extract_docx_xml_text(string $xml): string
{
    $xml = preg_replace('/<w:tab\b[^>]*\/>/i', "\t", $xml) ?? $xml;
    $xml = preg_replace('/<w:br\b[^>]*\/>/i', "\n", $xml) ?? $xml;
    $xml = preg_replace('/<\/w:p>/i', "\n", $xml) ?? $xml;
    $xml = preg_replace('/<\/w:tr>/i', "\n", $xml) ?? $xml;
    $xml = preg_replace('/<\/w:tc>/i', "\t", $xml) ?? $xml;
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    return trim(preg_replace('/[ \t]+/u', ' ', preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text) ?? $text);
}

function mg_agent_memory_source_extract_docx_text(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('DOCX extraction requires the PHP ZipArchive extension on the server.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open DOCX memory source.');
    }
    $parts = ['word/document.xml'];
    for ($i = 1; $i <= 12; $i++) {
        $parts[] = 'word/header' . $i . '.xml';
        $parts[] = 'word/footer' . $i . '.xml';
    }
    $parts[] = 'word/footnotes.xml';
    $parts[] = 'word/endnotes.xml';
    $texts = [];
    foreach ($parts as $part) {
        $xml = $zip->getFromName($part);
        if (is_string($xml) && $xml !== '') {
            $texts[] = mg_agent_memory_source_extract_docx_xml_text($xml);
        }
    }
    $zip->close();
    return ['text' => trim(implode("\n\n", array_filter($texts))), 'engine' => 'php_ziparchive_docx'];
}

function mg_agent_memory_source_extract_pdf_text(string $path): array
{
    if (!mg_agent_memory_source_command_exists('pdftotext')) {
        throw new RuntimeException('PDF extraction requires Poppler pdftotext on the server.');
    }
    $text = mg_agent_memory_source_run_text_command('pdftotext -layout -enc UTF-8 ' . escapeshellarg($path) . ' -');
    return ['text' => $text, 'engine' => 'poppler_pdftotext'];
}

function mg_agent_memory_source_extract_doc_text(string $path): array
{
    if (mg_agent_memory_source_command_exists('antiword')) {
        return ['text' => mg_agent_memory_source_run_text_command('antiword ' . escapeshellarg($path)), 'engine' => 'antiword'];
    }
    if (mg_agent_memory_source_command_exists('catdoc')) {
        return ['text' => mg_agent_memory_source_run_text_command('catdoc ' . escapeshellarg($path)), 'engine' => 'catdoc'];
    }
    throw new RuntimeException('DOC extraction requires antiword or catdoc on the server. Convert to DOCX or TXT if those tools are unavailable.');
}

function mg_agent_memory_source_extract_text(array $source, string $path): array
{
    $type = strtolower((string)($source['source_type'] ?? ''));
    if ($type === 'docx') return mg_agent_memory_source_extract_docx_text($path);
    if ($type === 'pdf') return mg_agent_memory_source_extract_pdf_text($path);
    if ($type === 'doc') return mg_agent_memory_source_extract_doc_text($path);
    if (in_array($type, ['txt','md','csv','json'], true)) {
        return ['text' => (string)@file_get_contents($path), 'engine' => 'plain_text'];
    }
    throw new RuntimeException('Unsupported document type for extraction.');
}

function mg_agent_memory_source_metadata(array $source): array
{
    $raw = trim((string)($source['metadata_json'] ?? ''));
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_agent_memory_source_update_result(PDO $pdo, int $sourceId, int $merchantId, string $status, ?string $summary, ?string $error, array $metadata = []): void
{
    $stmt = $pdo->prepare('UPDATE merchant_agent_memory_sources SET source_status=?, summary=?, error_message=?, metadata_json=?, updated_at=NOW() WHERE id=? AND merchant_user_id=? LIMIT 1');
    $stmt->execute([
        $status,
        $summary,
        $error !== null ? mg_agent_memory_source_clean($error, 500) : null,
        $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        $sourceId,
        $merchantId,
    ]);
}

function mg_agent_memory_source_reload(PDO $pdo, int $sourceId, int $merchantId): array
{
    $stmt = $pdo->prepare('SELECT * FROM merchant_agent_memory_sources WHERE id=? AND merchant_user_id=? LIMIT 1');
    $stmt->execute([$sourceId, $merchantId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function mg_agent_memory_source_delete_chunks(PDO $pdo, int $sourceId, int $merchantId): void
{
    if (!mg_agent_memory_chunk_table_ready($pdo)) return;
    $stmt = $pdo->prepare('DELETE FROM merchant_agent_memory_chunks WHERE source_id=? AND merchant_user_id=?');
    $stmt->execute([$sourceId, $merchantId]);
}

function mg_agent_memory_source_process_one(PDO $pdo, array $source): array
{
    $sourceId = (int)$source['id'];
    $merchantId = (int)$source['merchant_user_id'];
    $metadata = mg_agent_memory_source_metadata($source);
    mg_agent_memory_source_update_result($pdo, $sourceId, $merchantId, 'processing', 'Document text extraction is running.', null, array_merge($metadata, ['processing_started_at' => gmdate('c')]));

    try {
        $path = mg_agent_memory_source_private_path((string)($source['storage_key'] ?? ''));
        if (!$path) {
            throw new RuntimeException('Private memory file is missing or not readable.');
        }
        $extracted = mg_agent_memory_source_extract_text($source, $path);
        $text = trim(preg_replace('/\s+/u', ' ', (string)($extracted['text'] ?? '')) ?? (string)($extracted['text'] ?? ''));
        if ($text === '') {
            throw new RuntimeException('No readable text could be extracted from this document.');
        }
        $chunks = mg_agent_memory_source_chunk_text($text);
        if ($chunks === []) {
            throw new RuntimeException('No memory chunks could be created from this document.');
        }
        $pdo->beginTransaction();
        mg_agent_memory_source_delete_chunks($pdo, $sourceId, $merchantId);
        mg_agent_memory_source_insert_chunks($pdo, $sourceId, $merchantId, $chunks);
        $newMetadata = array_merge($metadata, [
            'extraction_engine' => (string)($extracted['engine'] ?? 'unknown'),
            'extracted_at' => gmdate('c'),
            'extracted_characters' => mb_strlen($text),
            'chunk_count' => count($chunks),
        ]);
        mg_agent_memory_source_update_result($pdo, $sourceId, $merchantId, 'ready', 'Document text extracted into ' . count($chunks) . ' memory chunk' . (count($chunks) === 1 ? '.' : 's.'), null, $newMetadata);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $metadata['extraction_failed_at'] = gmdate('c');
        mg_agent_memory_source_update_result($pdo, $sourceId, $merchantId, 'failed', 'Document text extraction failed.', $e->getMessage(), $metadata);
    }

    return mg_agent_memory_source_public(mg_agent_memory_source_reload($pdo, $sourceId, $merchantId));
}

function mg_agent_memory_source_process_pending(PDO $pdo, int $merchantId, ?string $publicId = null, int $limit = 1, bool $force = false): array
{
    if (!mg_agent_memory_source_table_ready($pdo) || !mg_agent_memory_chunk_table_ready($pdo)) return ['processed' => [], 'remaining' => 0];
    $limit = max(1, min(10, $limit));
    $types = "'pdf','doc','docx'";
    if ($publicId !== null && $publicId !== '') {
        $statusSql = $force ? '' : " AND source_status IN ('uploaded','queued','failed')";
        $stmt = $pdo->prepare("SELECT * FROM merchant_agent_memory_sources WHERE public_id=? AND merchant_user_id=? AND archived_at IS NULL AND source_type IN ({$types}){$statusSql} LIMIT 1");
        $stmt->execute([$publicId, $merchantId]);
    } else {
        $statusSql = $force ? "source_status IN ('uploaded','queued','failed')" : "source_status IN ('uploaded','queued')";
        $stmt = $pdo->prepare("SELECT * FROM merchant_agent_memory_sources WHERE merchant_user_id=? AND archived_at IS NULL AND source_type IN ({$types}) AND {$statusSql} ORDER BY id ASC LIMIT {$limit}");
        $stmt->execute([$merchantId]);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $processed = [];
    foreach ($rows as $row) {
        $processed[] = mg_agent_memory_source_process_one($pdo, $row);
    }
    $remainingStmt = $pdo->prepare("SELECT COUNT(*) FROM merchant_agent_memory_sources WHERE merchant_user_id=? AND archived_at IS NULL AND source_type IN ({$types}) AND source_status IN ('uploaded','queued')");
    $remainingStmt->execute([$merchantId]);
    return ['processed' => $processed, 'remaining' => (int)$remainingStmt->fetchColumn()];
}

function mg_agent_memory_ready_chunks(PDO $pdo, int $merchantId, int $limit = 12): array
{
    if (!mg_agent_memory_source_table_ready($pdo) || !mg_agent_memory_chunk_table_ready($pdo)) return [];
    $limit = max(1, min(24, $limit));
    $stmt = $pdo->prepare("SELECT c.*, s.public_id source_public_id, s.title source_title, s.source_type
        FROM merchant_agent_memory_chunks c
        INNER JOIN merchant_agent_memory_sources s ON s.id=c.source_id
        WHERE c.merchant_user_id=? AND s.merchant_user_id=? AND s.source_status='ready' AND s.archived_at IS NULL
        ORDER BY s.updated_at DESC, s.id DESC, c.chunk_index ASC
        LIMIT {$limit}");
    $stmt->execute([$merchantId, $merchantId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return array_map(static function (array $row): array {
        $text = trim((string)($row['chunk_text'] ?? ''));
        return [
            'source_id' => (string)($row['source_public_id'] ?? ''),
            'source_title' => (string)($row['source_title'] ?? 'Memory source'),
            'source_type' => (string)($row['source_type'] ?? 'other'),
            'chunk_index' => (int)($row['chunk_index'] ?? 0),
            'text' => mb_substr($text, 0, 1200),
        ];
    }, $rows);
}
