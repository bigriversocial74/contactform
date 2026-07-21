<?php
declare(strict_types=1);

function mg_mcp_conversion_create_native(PDO $pdo, array $user, string $conversionPublicId): array
{
    $ownerUserId = (int)($user['id'] ?? 0);
    if ($ownerUserId < 1) throw new MgMcpDraftException('Authentication is required.', 401, 'MCP_CONVERSION_AUTH_REQUIRED');

    $pdo->beginTransaction();
    try {
        $conversion = mg_mcp_conversion_row_for_owner($pdo, $ownerUserId, $conversionPublicId, true);
        if (in_array((string)$conversion['status'], ['created', 'opened'], true)) {
            mg_mcp_conversion_event($pdo, (int)$conversion['id'], 'duplicate_returned', $ownerUserId, [
                'status' => (string)$conversion['status'],
                'native_public_id' => (string)$conversion['native_public_id'],
            ]);
            $pdo->commit();
            return mg_mcp_conversion_projection($conversion, true);
        }
        if ((string)$conversion['status'] !== 'prepared') {
            throw new MgMcpDraftException(
                'The conversion is no longer available.',
                409,
                'MCP_CONVERSION_STATE_CONFLICT'
            );
        }
        if ((string)$conversion['draft_status'] !== 'approved') {
            throw new MgMcpDraftException(
                'The source draft is no longer approved.',
                409,
                'MCP_CONVERSION_DRAFT_NOT_APPROVED'
            );
        }

        $draftType = (string)$conversion['draft_type'];
        $result = match ($draftType) {
            'gift' => mg_mcp_conversion_create_gift($pdo, $user, $conversion),
            'campaign' => mg_mcp_conversion_create_campaign($pdo, $user, $conversion),
            'reward' => mg_mcp_conversion_create_reward($pdo, $user, $conversion),
            'message' => mg_mcp_conversion_create_message($pdo, $user, $conversion),
            default => throw new MgMcpDraftException('Unsupported conversion type.', 422, 'MCP_CONVERSION_TYPE_UNSUPPORTED'),
        };

        $nativeUrl = (string)$result['native_url'];
        if (!str_starts_with($nativeUrl, '/') || str_starts_with($nativeUrl, '//') || strlen($nativeUrl) > 700) {
            throw new MgMcpDraftException('The conversion destination is invalid.', 500, 'MCP_CONVERSION_TARGET_INVALID');
        }
        $pdo->prepare(
            "UPDATE mcp_agent_draft_conversions
             SET status='created',native_public_id=?,native_url=?,native_created_at=NOW(),updated_at=NOW()
             WHERE id=? AND status='prepared'"
        )->execute([
            (string)$result['native_public_id'],
            $nativeUrl,
            (int)$conversion['id'],
        ]);
        mg_mcp_conversion_event($pdo, (int)$conversion['id'], 'native_created', $ownerUserId, [
            'draft_type' => $draftType,
            'native_public_id' => (string)$result['native_public_id'],
            'execution_enabled' => false,
            'evidence' => (array)($result['evidence'] ?? []),
        ]);
        $updated = mg_mcp_conversion_row_by_id($pdo, (int)$conversion['id']);
        $pdo->commit();

        $metadata = [
            'draft_id' => (string)$conversion['draft_public_id'],
            'conversion_id' => (string)$conversion['public_id'],
            'draft_type' => $draftType,
            'native_public_id' => (string)$result['native_public_id'],
            'execution_enabled' => false,
        ];
        mg_audit('mcp_agent_draft_native_created', 'mcp_agent_draft_conversion', $metadata, $ownerUserId);
        mg_event('mcp.agent_draft.native_created', $metadata, $ownerUserId);
        return mg_mcp_conversion_projection($updated);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_mcp_conversion_cancel(PDO $pdo, array $user, string $conversionPublicId): array
{
    $ownerUserId = (int)($user['id'] ?? 0);
    $pdo->beginTransaction();
    try {
        $row = mg_mcp_conversion_row_for_owner($pdo, $ownerUserId, $conversionPublicId, true);
        if ((string)$row['status'] === 'canceled') {
            $pdo->commit();
            return mg_mcp_conversion_projection($row, true);
        }
        if ((string)$row['status'] !== 'prepared') {
            throw new MgMcpDraftException(
                'Only a prepared conversion can be canceled.',
                409,
                'MCP_CONVERSION_STATE_CONFLICT'
            );
        }
        $pdo->prepare("UPDATE mcp_agent_draft_conversions SET status='canceled',canceled_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([(int)$row['id']]);
        mg_mcp_conversion_event($pdo, (int)$row['id'], 'canceled', $ownerUserId, [
            'native_draft_created' => false,
        ]);
        $updated = mg_mcp_conversion_row_by_id($pdo, (int)$row['id']);
        $pdo->commit();
        return mg_mcp_conversion_projection($updated);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_mcp_conversion_mark_opened(PDO $pdo, array $user, string $conversionPublicId): array
{
    $ownerUserId = (int)($user['id'] ?? 0);
    $pdo->beginTransaction();
    try {
        $row = mg_mcp_conversion_row_for_owner($pdo, $ownerUserId, $conversionPublicId, true);
        if (!in_array((string)$row['status'], ['created', 'opened'], true) || empty($row['native_url'])) {
            throw new MgMcpDraftException('The native draft is not available.', 409, 'MCP_CONVERSION_STATE_CONFLICT');
        }
        if ((string)$row['status'] === 'created') {
            $pdo->prepare("UPDATE mcp_agent_draft_conversions SET status='opened',opened_at=NOW(),updated_at=NOW() WHERE id=?")
                ->execute([(int)$row['id']]);
            mg_mcp_conversion_event($pdo, (int)$row['id'], 'opened', $ownerUserId, [
                'native_public_id' => (string)$row['native_public_id'],
            ]);
            $row = mg_mcp_conversion_row_by_id($pdo, (int)$row['id']);
        }
        $pdo->commit();
        return mg_mcp_conversion_projection($row);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_mcp_conversion_attach_to_drafts(PDO $pdo, int $ownerUserId, array $drafts): array
{
    if ($drafts === []) return [];
    $ids = array_values(array_filter(array_map(static fn(array $draft): string => (string)($draft['id'] ?? ''), $drafts)));
    if ($ids === []) return $drafts;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        mg_mcp_conversion_select_sql(
            "WHERE cv.owner_user_id=? AND d.public_id IN ($placeholders) ORDER BY cv.id DESC"
        )
    );
    $stmt->execute(array_merge([$ownerUserId], $ids));
    $byDraft = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $draftId = (string)$row['draft_public_id'];
        if (!isset($byDraft[$draftId])) $byDraft[$draftId] = mg_mcp_conversion_projection($row);
    }
    foreach ($drafts as &$draft) {
        $draft['conversion'] = $byDraft[(string)$draft['id']] ?? null;
    }
    unset($draft);
    return $drafts;
}

function mg_mcp_conversion_events_for_owner(PDO $pdo, int $ownerUserId, string $conversionPublicId): array
{
    $conversionPublicId = mg_mcp_draft_uuid($conversionPublicId, 'conversion');
    $stmt = $pdo->prepare(
        "SELECT e.event_type,e.evidence_json,e.created_at
         FROM mcp_agent_draft_conversion_events e
         INNER JOIN mcp_agent_draft_conversions cv ON cv.id=e.conversion_id
         WHERE cv.public_id=? AND cv.owner_user_id=?
         ORDER BY e.id ASC"
    );
    $stmt->execute([$conversionPublicId, $ownerUserId]);
    return array_map(static fn(array $row): array => [
        'type' => (string)$row['event_type'],
        'evidence' => mg_mcp_conversion_json($row['evidence_json'] ?? null),
        'created_at' => (string)$row['created_at'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}
