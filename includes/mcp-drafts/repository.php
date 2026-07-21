<?php
declare(strict_types=1);

function mg_mcp_draft_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    try {
        $decoded = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }
    return is_array($decoded) ? $decoded : [];
}

function mg_mcp_draft_event(PDO $pdo, int $draftId, string $eventType, ?int $actorUserId, ?int $connectionId, array $evidence = []): void
{
    $pdo->prepare(
        'INSERT INTO mcp_agent_draft_events
         (public_id,draft_id,event_type,actor_user_id,connection_id,evidence_json,created_at)
         VALUES (?,?,?,?,?,?,NOW())'
    )->execute([
        mg_public_uuid(),
        $draftId,
        $eventType,
        $actorUserId,
        $connectionId,
        $evidence === [] ? null : json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
}

function mg_mcp_draft_select_sql(string $where = ''): string
{
    return "SELECT d.*,c.public_id AS connection_public_id,c.display_name AS connection_name,
                   cl.public_id AS client_public_id,cl.client_key,cl.display_name AS client_name
            FROM mcp_agent_drafts d
            INNER JOIN mcp_connections c ON c.id=d.connection_id
            INNER JOIN mcp_clients cl ON cl.id=d.client_id
            $where";
}

function mg_mcp_draft_projection(array $row, bool $duplicate = false): array
{
    return [
        'id' => (string)$row['public_id'],
        'type' => (string)$row['draft_type'],
        'status' => (string)$row['status'],
        'title' => (string)$row['title'],
        'summary' => (string)$row['summary'],
        'payload' => mg_mcp_draft_json($row['payload_json'] ?? null),
        'risk_level' => (string)$row['risk_level'],
        'requested_reason' => (string)$row['requested_reason'],
        'workspace' => $row['workspace_type'] !== null ? [
            'type' => (string)$row['workspace_type'],
            'database_id' => $row['workspace_id'] !== null ? (int)$row['workspace_id'] : null,
        ] : null,
        'connection' => [
            'id' => (string)($row['connection_public_id'] ?? ''),
            'name' => (string)($row['connection_name'] ?? ''),
        ],
        'client' => [
            'id' => (string)($row['client_public_id'] ?? ''),
            'key' => (string)($row['client_key'] ?? ''),
            'name' => (string)($row['client_name'] ?? ''),
        ],
        'approval' => [
            'required' => true,
            'expires_at' => $row['approval_expires_at'] !== null ? (string)$row['approval_expires_at'] : null,
            'decided_at' => $row['decided_at'] !== null ? (string)$row['decided_at'] : null,
            'decision_reason' => $row['decision_reason'] !== null ? (string)$row['decision_reason'] : null,
        ],
        'execution' => [
            'enabled' => false,
            'status' => 'not_enabled',
            'next_step' => (string)$row['status'] === 'approved' ? 'manual_microgifter_follow_up' : 'owner_review',
        ],
        'duplicate' => $duplicate,
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function mg_mcp_draft_row_by_id(PDO $pdo, int $id, bool $lock = false): array
{
    $sql = mg_mcp_draft_select_sql('WHERE d.id=? LIMIT 1') . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgMcpDraftException('Draft not found.', 404, 'MCP_DRAFT_NOT_FOUND');
    return $row;
}

function mg_mcp_draft_expire(PDO $pdo, ?int $ownerUserId = null): int
{
    $where = "status='pending_review' AND approval_expires_at IS NOT NULL AND approval_expires_at<NOW()";
    $params = [];
    if ($ownerUserId !== null) {
        $where .= ' AND owner_user_id=?';
        $params[] = $ownerUserId;
    }
    $stmt = $pdo->prepare("SELECT id,connection_id FROM mcp_agent_drafts WHERE $where FOR UPDATE");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $pdo->prepare("UPDATE mcp_agent_drafts SET status='expired',updated_at=NOW() WHERE id=? AND status='pending_review'")
            ->execute([(int)$row['id']]);
        mg_mcp_draft_event($pdo, (int)$row['id'], 'expired', null, (int)$row['connection_id'], ['reason' => 'review_window_elapsed']);
    }
    return count($rows);
}

function mg_mcp_draft_create(PDO $pdo, array $context, array $input): array
{
    $type = mg_mcp_draft_type($input['type'] ?? '');
    mg_mcp_draft_require_context($context, $type);
    $title = mg_mcp_draft_text($input['title'] ?? '', 190, 'draft title');
    $summary = mg_mcp_draft_text($input['summary'] ?? '', 500, 'draft summary');
    $payload = mg_mcp_draft_validate_payload($type, $input['payload'] ?? []);
    $fingerprint = mg_mcp_draft_fingerprint($payload);
    $idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
    if (strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 190 || preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) !== 1) {
        throw new MgMcpDraftException('A valid idempotency key is required.', 422, 'MCP_DRAFT_IDEMPOTENCY_INVALID');
    }
    $sourceRequestId = mg_mcp_draft_uuid($input['source_request_id'] ?? '', 'source request');
    $risk = strtolower(trim((string)($input['risk_level'] ?? 'medium')));
    if (!in_array($risk, ['low', 'medium', 'high', 'critical'], true)) {
        throw new MgMcpDraftException('Invalid risk level.', 422, 'MCP_DRAFT_VALIDATION_FAILED');
    }
    $reason = mg_mcp_draft_multiline($input['requested_reason'] ?? 'External agent prepared a reviewable draft.', 1000, 'requested reason', true);
    $connectionId = (int)($context['connection_db_id'] ?? 0);
    $clientId = (int)($context['client_db_id'] ?? 0);
    $ownerUserId = (int)($context['user_id'] ?? 0);
    if ($connectionId < 1 || $clientId < 1 || $ownerUserId < 1) {
        throw new MgMcpDraftException('The MCP authority context is incomplete.', 503, 'MCP_DRAFT_AUTHORITY_UNAVAILABLE');
    }

    $pdo->beginTransaction();
    try {
        mg_mcp_draft_expire($pdo, $ownerUserId);
        $existingStmt = $pdo->prepare('SELECT id,draft_type,payload_fingerprint FROM mcp_agent_drafts WHERE connection_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
        $existingStmt->execute([$connectionId, $idempotencyKey]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if ((string)$existing['draft_type'] !== $type || !hash_equals((string)$existing['payload_fingerprint'], $fingerprint)) {
                throw new MgMcpDraftException('The idempotency key was already used for different draft content.', 409, 'MCP_DRAFT_IDEMPOTENCY_CONFLICT');
            }
            mg_mcp_draft_event($pdo, (int)$existing['id'], 'duplicate_returned', $ownerUserId, $connectionId, ['idempotency_key_hash' => hash('sha256', $idempotencyKey)]);
            $row = mg_mcp_draft_row_by_id($pdo, (int)$existing['id']);
            $pdo->commit();
            return mg_mcp_draft_projection($row, true);
        }

        $publicId = mg_public_uuid();
        $stmt = $pdo->prepare(
            "INSERT INTO mcp_agent_drafts
             (public_id,connection_id,client_id,owner_user_id,workspace_type,workspace_id,draft_type,status,title,summary,
              payload_json,payload_fingerprint,risk_level,idempotency_key,source_request_id,requested_reason,approval_expires_at,
              created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,'pending_review',?,?,?,?,?,?,?, ?,DATE_ADD(NOW(),INTERVAL 7 DAY),NOW(),NOW())"
        );
        $stmt->execute([
            $publicId,
            $connectionId,
            $clientId,
            $ownerUserId,
            $context['workspace_type'] !== null ? (string)$context['workspace_type'] : null,
            $context['workspace_id'] !== null ? (int)$context['workspace_id'] : null,
            $type,
            $title,
            $summary,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $fingerprint,
            $risk,
            $idempotencyKey,
            $sourceRequestId,
            $reason,
        ]);
        $draftId = (int)$pdo->lastInsertId();
        mg_mcp_draft_event($pdo, $draftId, 'created', $ownerUserId, $connectionId, [
            'draft_type' => $type,
            'scope' => mg_mcp_draft_scope($type),
            'payload_fingerprint' => $fingerprint,
            'execution_enabled' => false,
        ]);
        $row = mg_mcp_draft_row_by_id($pdo, $draftId);
        $pdo->commit();

        $metadata = ['draft_id' => $publicId, 'draft_type' => $type, 'connection_id' => (string)$context['connection_public_id'], 'execution_enabled' => false];
        mg_audit('mcp_agent_draft_created', 'mcp_agent_draft', $metadata, $ownerUserId);
        mg_event('mcp.agent_draft.created', $metadata, $ownerUserId);
        mg_security_log('info', 'mcp.agent_draft.created', 'External agent created a review-only draft.', $metadata, $ownerUserId);
        return mg_mcp_draft_projection($row);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException && (string)$error->getCode() === '23000') {
            throw new MgMcpDraftException('The draft request conflicts with an existing record.', 409, 'MCP_DRAFT_CONFLICT');
        }
        throw $error;
    }
}

function mg_mcp_draft_get_for_connection(PDO $pdo, array $context, string $publicId): array
{
    $publicId = mg_mcp_draft_uuid($publicId, 'draft');
    $stmt = $pdo->prepare(mg_mcp_draft_select_sql('WHERE d.public_id=? AND d.connection_id=? LIMIT 1'));
    $stmt->execute([$publicId, (int)$context['connection_db_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgMcpDraftException('Draft not found.', 404, 'MCP_DRAFT_NOT_FOUND');
    return mg_mcp_draft_projection($row);
}

function mg_mcp_draft_list_for_connection(PDO $pdo, array $context, array $input): array
{
    $limit = max(1, min((int)($input['limit'] ?? 20), 50));
    $type = trim((string)($input['type'] ?? ''));
    $status = trim((string)($input['status'] ?? ''));
    $cursor = max(0, (int)($input['cursor'] ?? 0));
    $where = ['d.connection_id=?'];
    $params = [(int)$context['connection_db_id']];
    if ($type !== '') {
        $where[] = 'd.draft_type=?';
        $params[] = mg_mcp_draft_type($type);
    }
    if ($status !== '') {
        if (!in_array($status, MG_MCP_DRAFT_STATUSES, true)) throw new MgMcpDraftException('Invalid draft status.', 422, 'MCP_DRAFT_VALIDATION_FAILED');
        $where[] = 'd.status=?';
        $params[] = $status;
    }
    if ($cursor > 0) {
        $where[] = 'd.id<?';
        $params[] = $cursor;
    }
    $params[] = $limit + 1;
    $stmt = $pdo->prepare(mg_mcp_draft_select_sql('WHERE ' . implode(' AND ', $where) . ' ORDER BY d.id DESC LIMIT ?'));
    foreach ($params as $index => $param) $stmt->bindValue($index + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($rows) > $limit;
    if ($hasMore) array_pop($rows);
    return [
        'items' => array_map('mg_mcp_draft_projection', $rows),
        'next_cursor' => $hasMore && $rows !== [] ? (string)end($rows)['id'] : null,
        'limit' => $limit,
    ];
}

function mg_mcp_draft_cancel_for_connection(PDO $pdo, array $context, string $publicId, string $reason): array
{
    $publicId = mg_mcp_draft_uuid($publicId, 'draft');
    $reason = mg_mcp_draft_multiline($reason !== '' ? $reason : 'External agent canceled the draft.', 1000, 'cancellation reason', true);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(mg_mcp_draft_select_sql('WHERE d.public_id=? AND d.connection_id=? LIMIT 1 FOR UPDATE'));
        $stmt->execute([$publicId, (int)$context['connection_db_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new MgMcpDraftException('Draft not found.', 404, 'MCP_DRAFT_NOT_FOUND');
        if ((string)$row['status'] === 'canceled') {
            $pdo->commit();
            return mg_mcp_draft_projection($row, true);
        }
        if ((string)$row['status'] !== 'pending_review') {
            throw new MgMcpDraftException('Only a pending draft can be canceled by the originating agent.', 409, 'MCP_DRAFT_STATE_CONFLICT');
        }
        $pdo->prepare("UPDATE mcp_agent_drafts SET status='canceled',decision_reason=?,canceled_at=NOW(),updated_at=NOW() WHERE id=? AND status='pending_review'")
            ->execute([$reason, (int)$row['id']]);
        mg_mcp_draft_event($pdo, (int)$row['id'], 'canceled', (int)$context['user_id'], (int)$context['connection_db_id'], ['reason' => $reason]);
        $updated = mg_mcp_draft_row_by_id($pdo, (int)$row['id']);
        $pdo->commit();
        return mg_mcp_draft_projection($updated);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_mcp_draft_list_for_owner(PDO $pdo, int $ownerUserId, array $filters = []): array
{
    $pdo->beginTransaction();
    try {
        mg_mcp_draft_expire($pdo, $ownerUserId);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $status = trim((string)($filters['status'] ?? ''));
    $type = trim((string)($filters['type'] ?? ''));
    $where = ['d.owner_user_id=?'];
    $params = [$ownerUserId];
    if ($status !== '') {
        if (!in_array($status, MG_MCP_DRAFT_STATUSES, true)) throw new MgMcpDraftException('Invalid draft status.');
        $where[] = 'd.status=?';
        $params[] = $status;
    }
    if ($type !== '') {
        $where[] = 'd.draft_type=?';
        $params[] = mg_mcp_draft_type($type);
    }
    $params[] = 100;
    $stmt = $pdo->prepare(mg_mcp_draft_select_sql('WHERE ' . implode(' AND ', $where) . ' ORDER BY d.id DESC LIMIT ?'));
    foreach ($params as $index => $param) $stmt->bindValue($index + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stmt->execute();
    return array_map('mg_mcp_draft_projection', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_mcp_draft_owner_decide(PDO $pdo, int $ownerUserId, string $publicId, string $decision, string $reason): array
{
    $publicId = mg_mcp_draft_uuid($publicId, 'draft');
    $decision = strtolower(trim($decision));
    if (!in_array($decision, ['approve', 'reject'], true)) throw new MgMcpDraftException('Invalid draft decision.');
    $reason = mg_mcp_draft_multiline($reason, 1000, 'decision reason', $decision === 'reject');
    $target = $decision === 'approve' ? 'approved' : 'rejected';
    $pdo->beginTransaction();
    try {
        mg_mcp_draft_expire($pdo, $ownerUserId);
        $stmt = $pdo->prepare(mg_mcp_draft_select_sql('WHERE d.public_id=? AND d.owner_user_id=? LIMIT 1 FOR UPDATE'));
        $stmt->execute([$publicId, $ownerUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new MgMcpDraftException('Draft not found.', 404, 'MCP_DRAFT_NOT_FOUND');
        if ((string)$row['status'] === $target) {
            $pdo->commit();
            return mg_mcp_draft_projection($row, true);
        }
        if ((string)$row['status'] !== 'pending_review') {
            throw new MgMcpDraftException('The draft is no longer available for review.', 409, 'MCP_DRAFT_STATE_CONFLICT');
        }
        $pdo->prepare('UPDATE mcp_agent_drafts SET status=?,decided_by_user_id=?,decision_reason=?,decided_at=NOW(),updated_at=NOW() WHERE id=? AND status=\'pending_review\'')
            ->execute([$target, $ownerUserId, $reason !== '' ? $reason : null, (int)$row['id']]);
        mg_mcp_draft_event($pdo, (int)$row['id'], $target, $ownerUserId, (int)$row['connection_id'], [
            'reason' => $reason,
            'execution_enabled' => false,
            'manual_follow_up_required' => $target === 'approved',
        ]);
        $updated = mg_mcp_draft_row_by_id($pdo, (int)$row['id']);
        $pdo->commit();

        $metadata = ['draft_id' => $publicId, 'draft_type' => (string)$row['draft_type'], 'decision' => $target, 'execution_enabled' => false];
        mg_audit('mcp_agent_draft_' . $target, 'mcp_agent_draft', $metadata, $ownerUserId);
        mg_event('mcp.agent_draft.' . $target, $metadata, $ownerUserId);
        return mg_mcp_draft_projection($updated);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_mcp_draft_events_for_owner(PDO $pdo, int $ownerUserId, string $publicId): array
{
    $publicId = mg_mcp_draft_uuid($publicId, 'draft');
    $stmt = $pdo->prepare(
        "SELECT e.event_type,e.evidence_json,e.created_at
         FROM mcp_agent_draft_events e
         INNER JOIN mcp_agent_drafts d ON d.id=e.draft_id
         WHERE d.public_id=? AND d.owner_user_id=? ORDER BY e.id ASC"
    );
    $stmt->execute([$publicId, $ownerUserId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($events === []) {
        $check = $pdo->prepare('SELECT 1 FROM mcp_agent_drafts WHERE public_id=? AND owner_user_id=? LIMIT 1');
        $check->execute([$publicId, $ownerUserId]);
        if (!$check->fetchColumn()) throw new MgMcpDraftException('Draft not found.', 404, 'MCP_DRAFT_NOT_FOUND');
    }
    return array_map(static fn(array $event): array => [
        'type' => (string)$event['event_type'],
        'evidence' => mg_mcp_draft_json($event['evidence_json'] ?? null),
        'created_at' => (string)$event['created_at'],
    ], $events);
}
