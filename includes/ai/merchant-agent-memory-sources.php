<?php
declare(strict_types=1);

function mg_agent_memory_source_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function mg_agent_memory_source_clean(mixed $value, int $max = 240): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    return mb_substr($text, 0, $max);
}

function mg_agent_memory_source_table_ready(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'merchant_agent_memory_sources'");
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_agent_memory_chunk_table_ready(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'merchant_agent_memory_chunks'");
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_agent_memory_source_public(array $row): array
{
    return [
        'id' => (string)($row['public_id'] ?? ''),
        'source_type' => (string)($row['source_type'] ?? 'other'),
        'source_status' => (string)($row['source_status'] ?? 'uploaded'),
        'title' => (string)($row['title'] ?? 'Memory source'),
        'original_filename' => $row['original_filename'] ?? null,
        'source_url' => $row['source_url'] ?? null,
        'mime_type' => $row['mime_type'] ?? null,
        'byte_size' => (int)($row['byte_size'] ?? 0),
        'summary' => $row['summary'] ?? null,
        'error_message' => $row['error_message'] ?? null,
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

function mg_agent_memory_sources(PDO $pdo, int $merchantId, int $limit = 30): array
{
    if (!mg_agent_memory_source_table_ready($pdo)) return [];
    $stmt = $pdo->prepare('SELECT * FROM merchant_agent_memory_sources WHERE merchant_user_id=? AND archived_at IS NULL ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)));
    $stmt->execute([$merchantId]);
    return array_map('mg_agent_memory_source_public', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_agent_memory_source_allowed_mime(string $mime, string $name): ?array
{
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $map = [
        'application/pdf' => ['pdf', 'pdf', 26214400],
        'application/msword' => ['doc', 'doc', 26214400],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx', 'docx', 26214400],
        'text/plain' => ['txt', 'txt', 5242880],
        'text/markdown' => ['md', 'md', 5242880],
        'text/csv' => ['csv', 'csv', 5242880],
        'application/json' => ['json', 'json', 5242880],
    ];
    if (isset($map[$mime])) return $map[$mime];
    if (in_array($extension, ['md','markdown'], true)) return ['md', 'md', 5242880];
    if ($extension === 'csv') return ['csv', 'csv', 5242880];
    if ($extension === 'txt') return ['txt', 'txt', 5242880];
    if ($extension === 'docx') return ['docx', 'docx', 26214400];
    if ($extension === 'doc') return ['doc', 'doc', 26214400];
    if ($extension === 'pdf') return ['pdf', 'pdf', 26214400];
    return null;
}

function mg_agent_memory_source_insert(PDO $pdo, int $merchantId, int $createdByUserId, array $values): array
{
    if (!mg_agent_memory_source_table_ready($pdo)) mg_fail('Memory source tables are not installed yet. Apply the SQL migration first.', 500);
    $publicId = mg_agent_memory_source_uuid();
    $stmt = $pdo->prepare("INSERT INTO merchant_agent_memory_sources
        (public_id,merchant_user_id,created_by_user_id,source_type,source_status,title,original_filename,source_url,storage_provider,storage_key,mime_type,byte_size,checksum_sha256,summary,error_message,metadata_json,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $stmt->execute([
        $publicId,
        $merchantId,
        $createdByUserId,
        $values['source_type'] ?? 'other',
        $values['source_status'] ?? 'uploaded',
        mg_agent_memory_source_clean($values['title'] ?? 'Memory source', 180),
        $values['original_filename'] ?? null,
        $values['source_url'] ?? null,
        $values['storage_provider'] ?? null,
        $values['storage_key'] ?? null,
        $values['mime_type'] ?? null,
        (int)($values['byte_size'] ?? 0),
        $values['checksum_sha256'] ?? null,
        $values['summary'] ?? null,
        $values['error_message'] ?? null,
        isset($values['metadata']) ? json_encode($values['metadata'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
    ]);
    $stmt = $pdo->prepare('SELECT * FROM merchant_agent_memory_sources WHERE public_id=? LIMIT 1');
    $stmt->execute([$publicId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function mg_agent_memory_source_chunk_text(string $text, int $chunkSize = 3000): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') return [];
    $chunks = [];
    $offset = 0;
    $length = mb_strlen($text);
    while ($offset < $length && count($chunks) < 80) {
        $chunks[] = mb_substr($text, $offset, $chunkSize);
        $offset += $chunkSize;
    }
    return $chunks;
}

function mg_agent_memory_source_insert_chunks(PDO $pdo, int $sourceId, int $merchantId, array $chunks): void
{
    if (!mg_agent_memory_chunk_table_ready($pdo) || $chunks === []) return;
    $stmt = $pdo->prepare('INSERT INTO merchant_agent_memory_chunks (public_id,source_id,merchant_user_id,chunk_index,heading,section_label,chunk_text,token_estimate,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
    foreach (array_values($chunks) as $index => $chunk) {
        $text = trim((string)$chunk);
        if ($text === '') continue;
        $stmt->execute([
            mg_agent_memory_source_uuid(),
            $sourceId,
            $merchantId,
            $index,
            null,
            null,
            $text,
            max(1, (int)ceil(mb_strlen($text) / 4)),
            null,
        ]);
    }
}

function mg_agent_memory_source_upload(PDO $pdo, int $merchantId, int $userId, array $file, array $input): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) mg_fail('The memory file upload failed.', 422);
    if (!is_uploaded_file((string)$file['tmp_name'])) mg_fail('Invalid uploaded memory file.', 422);
    $original = basename((string)($file['name'] ?? 'memory-source'));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file((string)$file['tmp_name']);
    $allowed = mg_agent_memory_source_allowed_mime($mime, $original);
    if ($allowed === null) mg_fail('Unsupported memory file. Upload PDF, Word, TXT, Markdown, CSV, or JSON.', 422);
    [$sourceType, $extension, $maxBytes] = $allowed;
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > $maxBytes) mg_fail('The memory file is too large.', 422);
    $sourceId = mg_agent_memory_source_uuid();
    $relativeKey = 'agent-memory/' . $merchantId . '/' . $sourceId . '.' . $extension;
    $storageRoot = dirname(__DIR__, 2) . '/storage/private';
    $directory = $storageRoot . '/agent-memory/' . $merchantId;
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) mg_fail('Unable to prepare secure memory storage.', 500);
    $destination = $storageRoot . '/' . $relativeKey;
    if (!move_uploaded_file((string)$file['tmp_name'], $destination)) mg_fail('Unable to store memory file.', 500);
    @chmod($destination, 0600);
    $checksum = hash_file('sha256', $destination);
    $title = mg_agent_memory_source_clean($input['title'] ?? pathinfo($original, PATHINFO_FILENAME), 180) ?: $original;
    $status = in_array($sourceType, ['txt','md','csv','json'], true) ? 'ready' : 'uploaded';
    $summary = in_array($sourceType, ['txt','md','csv','json'], true)
        ? 'Text source added to memory.'
        : 'Document uploaded. Text extraction can be processed by a future memory job.';
    $row = mg_agent_memory_source_insert($pdo, $merchantId, $userId, [
        'source_type' => $sourceType,
        'source_status' => $status,
        'title' => $title,
        'original_filename' => $original,
        'storage_provider' => 'private_local',
        'storage_key' => $relativeKey,
        'mime_type' => $mime,
        'byte_size' => $size,
        'checksum_sha256' => $checksum,
        'summary' => $summary,
        'metadata' => ['original_extension' => strtolower(pathinfo($original, PATHINFO_EXTENSION)), 'source' => 'merchant_agent_memory_upload'],
    ]);
    if (in_array($sourceType, ['txt','md','csv','json'], true)) {
        $text = (string)@file_get_contents($destination);
        mg_agent_memory_source_insert_chunks($pdo, (int)$row['id'], $merchantId, mg_agent_memory_source_chunk_text($text));
    }
    return mg_agent_memory_source_public($row);
}

function mg_agent_memory_source_add_website(PDO $pdo, int $merchantId, int $userId, array $input): array
{
    $raw = trim((string)($input['url'] ?? $input['source_url'] ?? ''));
    if ($raw === '') mg_fail('Enter a website URL.', 422);
    $url = filter_var($raw, FILTER_VALIDATE_URL);
    if (!$url || !preg_match('#^https?://#i', (string)$url)) mg_fail('Enter a valid http or https website URL.', 422);
    $host = parse_url((string)$url, PHP_URL_HOST);
    if (!$host || in_array(strtolower((string)$host), ['localhost','127.0.0.1','0.0.0.0'], true)) mg_fail('That website URL is not allowed.', 422);
    $title = mg_agent_memory_source_clean($input['title'] ?? $host, 180) ?: (string)$host;
    $row = mg_agent_memory_source_insert($pdo, $merchantId, $userId, [
        'source_type' => 'website',
        'source_status' => 'queued',
        'title' => $title,
        'source_url' => (string)$url,
        'summary' => 'Website queued for memory scan.',
        'metadata' => ['source' => 'merchant_agent_website_memory', 'host' => $host],
    ]);
    return mg_agent_memory_source_public($row);
}

function mg_agent_memory_source_prompt_context(PDO $pdo, int $merchantId): array
{
    $sources = mg_agent_memory_sources($pdo, $merchantId, 12);
    return [
        'sources' => $sources,
        'guidance' => 'Use ready memory chunks when available. Uploaded PDF/DOC/DOCX sources may be awaiting text extraction, so do not invent document details that are not in chunks or summaries.',
    ];
}
