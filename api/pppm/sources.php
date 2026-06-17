<?php
declare(strict_types=1);

require_once __DIR__ . '/_pppm.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission('pppm.sources.manage');

if ($method === 'GET') {
    $stmt = mg_db()->prepare(
        'SELECT public_id, source_type, provider, name, status, created_at, updated_at
         FROM pppm_sources WHERE owner_user_id = ? ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute([(int) $user['id']]);
    mg_ok(['sources' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $sourceType = mg_pppm_text($input['source_type'] ?? '', 'source type', 80);
    $provider = mg_pppm_text($input['provider'] ?? '', 'provider', 120);
    $name = mg_pppm_text($input['name'] ?? '', 'name', 160);
    $publicId = mg_pppm_uuid();

    $stmt = mg_db()->prepare(
        "INSERT INTO pppm_sources (public_id, owner_user_id, source_type, provider, name, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())"
    );
    $stmt->execute([$publicId, (int) $user['id'], $sourceType, $provider, $name]);
    mg_audit('pppm.source_created', 'pppm_source', ['source_id' => $publicId], (int) $user['id']);
    mg_ok(['source_id' => $publicId], 'PPPM source created.', 201);
}

mg_fail('Method not allowed.', 405);
