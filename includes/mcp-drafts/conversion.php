<?php
declare(strict_types=1);

const MG_MCP_CONVERSION_STATUSES = ['prepared', 'created', 'opened', 'canceled'];
const MG_MCP_CONVERSION_TYPE_BY_DRAFT = [
    'gift' => 'gift_draft',
    'campaign' => 'campaign_draft',
    'reward' => 'reward_template_draft',
    'message' => 'message_draft',
];
const MG_MCP_CONVERSION_PERMISSION_BY_DRAFT = [
    'gift' => 'gift.create',
    'campaign' => 'merchant.campaigns.manage',
    'reward' => 'merchant.reward_templates.manage',
    'message' => 'merchant.campaigns.manage',
];

function mg_mcp_conversion_json(mixed $value): array
{
    return mg_mcp_draft_json($value);
}

function mg_mcp_conversion_user_has_permission(array $user, string $permission, ?array $workspace = null): bool
{
    $roles = is_array($user['roles'] ?? null) ? array_map('strval', $user['roles']) : [];
    if (in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) return true;
    $permissions = is_array($user['permissions'] ?? null) ? array_map('strval', $user['permissions']) : [];
    if (in_array($permission, $permissions, true)) return true;
    if ($workspace !== null && function_exists('mg_workspace_role_allows_permission')) {
        return mg_workspace_role_allows_permission([
            'workspace_role' => (string)($workspace['role_key'] ?? ''),
        ], $permission);
    }
    return function_exists('mg_has_permission')
        && (int)($user['id'] ?? 0) === (int)(mg_current_user()['id'] ?? 0)
        && mg_has_permission($permission);
}

function mg_mcp_conversion_require_permission(array $user, string $draftType, ?array $workspace = null): void
{
    $permission = MG_MCP_CONVERSION_PERMISSION_BY_DRAFT[$draftType] ?? '';
    if ($permission === '' || !mg_mcp_conversion_user_has_permission($user, $permission, $workspace)) {
        throw new MgMcpDraftException(
            'Your account is not authorized to create this Microgifter draft.',
            403,
            'MCP_CONVERSION_PERMISSION_DENIED'
        );
    }
}

function mg_mcp_conversion_event(
    PDO $pdo,
    int $conversionId,
    string $eventType,
    ?int $actorUserId,
    array $evidence = []
): void {
    $pdo->prepare(
        'INSERT INTO mcp_agent_draft_conversion_events
         (public_id,conversion_id,event_type,actor_user_id,evidence_json,created_at)
         VALUES (?,?,?,?,?,NOW())'
    )->execute([
        mg_public_uuid(),
        $conversionId,
        $eventType,
        $actorUserId,
        $evidence === [] ? null : json_encode(
            $evidence,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ),
    ]);
}

function mg_mcp_conversion_projection(array $row, bool $duplicate = false): array
{
    $status = (string)$row['status'];
    $url = trim((string)($row['native_url'] ?? ''));
    if ($url !== '' && (!str_starts_with($url, '/') || str_starts_with($url, '//'))) $url = '';
    return [
        'id' => (string)$row['public_id'],
        'draft_id' => (string)($row['draft_public_id'] ?? ''),
        'draft_type' => (string)($row['draft_type'] ?? ''),
        'conversion_type' => (string)$row['conversion_type'],
        'status' => $status,
        'native_public_id' => $row['native_public_id'] !== null ? (string)$row['native_public_id'] : null,
        'native_url' => $url !== '' ? $url : null,
        'snapshot' => mg_mcp_conversion_json($row['snapshot_json'] ?? null),
        'actions' => [
            'can_create_native_draft' => $status === 'prepared',
            'can_open' => in_array($status, ['created', 'opened'], true) && $url !== '',
            'can_cancel' => $status === 'prepared',
        ],
        'execution' => [
            'enabled' => false,
            'status' => 'native_draft_only',
        ],
        'duplicate' => $duplicate,
        'prepared_at' => (string)$row['prepared_at'],
        'native_created_at' => $row['native_created_at'] !== null ? (string)$row['native_created_at'] : null,
        'opened_at' => $row['opened_at'] !== null ? (string)$row['opened_at'] : null,
        'canceled_at' => $row['canceled_at'] !== null ? (string)$row['canceled_at'] : null,
        'updated_at' => (string)$row['updated_at'],
    ];
}

function mg_mcp_conversion_select_sql(string $where = ''): string
{
    return "SELECT cv.*,d.public_id AS draft_public_id,d.draft_type,d.status AS draft_status,d.title,d.summary,
                   d.payload_json,d.workspace_type,d.workspace_id,d.payload_fingerprint
            FROM mcp_agent_draft_conversions cv
            INNER JOIN mcp_agent_drafts d ON d.id=cv.draft_id
            $where";
}

function mg_mcp_conversion_row_by_id(PDO $pdo, int $id, bool $lock = false): array
{
    $sql = mg_mcp_conversion_select_sql('WHERE cv.id=? LIMIT 1') . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgMcpDraftException('Conversion not found.', 404, 'MCP_CONVERSION_NOT_FOUND');
    return $row;
}

function mg_mcp_conversion_row_for_owner(PDO $pdo, int $ownerUserId, string $publicId, bool $lock = false): array
{
    $publicId = mg_mcp_draft_uuid($publicId, 'conversion');
    $sql = mg_mcp_conversion_select_sql('WHERE cv.public_id=? AND cv.owner_user_id=? LIMIT 1') . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId, $ownerUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgMcpDraftException('Conversion not found.', 404, 'MCP_CONVERSION_NOT_FOUND');
    return $row;
}

function mg_mcp_conversion_draft_for_owner(PDO $pdo, int $ownerUserId, string $draftPublicId, bool $lock = false): array
{
    $draftPublicId = mg_mcp_draft_uuid($draftPublicId, 'draft');
    $sql = mg_mcp_draft_select_sql('WHERE d.public_id=? AND d.owner_user_id=? LIMIT 1') . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$draftPublicId, $ownerUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgMcpDraftException('Draft not found.', 404, 'MCP_DRAFT_NOT_FOUND');
    return $row;
}

function mg_mcp_conversion_workspace(PDO $pdo, array $draft, int $actorUserId): array
{
    if (!in_array((string)($draft['workspace_type'] ?? ''), ['merchant', 'merchant_workspace'], true)) {
        throw new MgMcpDraftException('A merchant workspace is required.', 403, 'MCP_CONVERSION_WORKSPACE_REQUIRED');
    }
    $workspaceId = (int)($draft['workspace_id'] ?? 0);
    if ($workspaceId < 1) {
        throw new MgMcpDraftException('The merchant workspace is unavailable.', 403, 'MCP_CONVERSION_WORKSPACE_REQUIRED');
    }
    $stmt = $pdo->prepare(
        "SELECT mw.id,mw.public_id,mw.merchant_user_id,mw.display_name,mw.status,
                COALESCE(mt.role_key,'owner') AS role_key
         FROM merchant_workspaces mw
         LEFT JOIN merchant_team_members mt
           ON mt.workspace_id=mw.id AND mt.user_id=? AND mt.status='active'
         WHERE mw.id=? AND (mw.merchant_user_id=? OR mt.id IS NOT NULL)
           AND mw.status NOT IN ('suspended','archived')
         LIMIT 1"
    );
    $stmt->execute([$actorUserId, $workspaceId, $actorUserId]);
    $workspace = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$workspace) {
        throw new MgMcpDraftException(
            'You no longer have access to the merchant workspace.',
            403,
            'MCP_CONVERSION_WORKSPACE_DENIED'
        );
    }
    return $workspace;
}

function mg_mcp_conversion_require_merchant_package(
    PDO $pdo,
    array $user,
    array $workspace,
    ?string $limitKey = null,
    int $usage = 0
): array {
    $roles = is_array($user['roles'] ?? null) ? array_map('strval', $user['roles']) : [];
    if (in_array('admin', $roles, true) || in_array('super_admin', $roles, true)) {
        $context = mg_package_entitlement_admin_context($user);
    } else {
        $ownerId = (int)($workspace['merchant_user_id'] ?? 0);
        $subscription = $ownerId > 0 ? mg_package_entitlement_subscription_row($pdo, $ownerId) : null;
        if (!$subscription || !mg_package_entitlement_subscription_is_active($subscription)) {
            throw new MgMcpDraftException(
                'Merchant draft conversion requires an active merchant package.',
                403,
                'MCP_CONVERSION_MERCHANT_ACCESS_REQUIRED'
            );
        }
        $context = mg_package_entitlement_from_subscription(
            $subscription,
            $user,
            (int)($user['id'] ?? 0) === $ownerId ? 'direct_subscription' : 'workspace_subscription',
            $ownerId,
            [
                'workspace_id' => (int)$workspace['id'],
                'workspace_public_id' => (string)$workspace['public_id'],
                'workspace_owner_user_id' => $ownerId,
                'role_key' => (string)($workspace['role_key'] ?? 'owner'),
            ]
        );
    }
    if (empty($context['merchant_access'])) {
        throw new MgMcpDraftException(
            'Merchant draft conversion requires an active merchant package.',
            403,
            'MCP_CONVERSION_MERCHANT_ACCESS_REQUIRED'
        );
    }
    if ($limitKey !== null && !mg_package_limit_allows_create($context, $limitKey, $usage)) {
        throw new MgMcpDraftException(
            'The current merchant package limit has been reached.',
            402,
            'MCP_CONVERSION_PACKAGE_LIMIT'
        );
    }
    return $context;
}

function mg_mcp_conversion_prepare(PDO $pdo, array $user, string $draftPublicId): array
{
    $ownerUserId = (int)($user['id'] ?? 0);
    if ($ownerUserId < 1) throw new MgMcpDraftException('Authentication is required.', 401, 'MCP_CONVERSION_AUTH_REQUIRED');

    $pdo->beginTransaction();
    try {
        $draft = mg_mcp_conversion_draft_for_owner($pdo, $ownerUserId, $draftPublicId, true);
        if ((string)$draft['status'] !== 'approved') {
            throw new MgMcpDraftException(
                'Only an approved draft can be prepared for conversion.',
                409,
                'MCP_CONVERSION_DRAFT_NOT_APPROVED'
            );
        }
        $draftType = (string)$draft['draft_type'];
        $workspace = in_array($draftType, ['campaign', 'reward', 'message'], true)
            ? mg_mcp_conversion_workspace($pdo, $draft, $ownerUserId)
            : null;
        mg_mcp_conversion_require_permission($user, $draftType, $workspace);

        $existingStmt = $pdo->prepare('SELECT id FROM mcp_agent_draft_conversions WHERE draft_id=? LIMIT 1 FOR UPDATE');
        $existingStmt->execute([(int)$draft['id']]);
        $existingId = (int)($existingStmt->fetchColumn() ?: 0);
        if ($existingId > 0) {
            $row = mg_mcp_conversion_row_by_id($pdo, $existingId);
            if ((string)$row['status'] === 'canceled') {
                $pdo->prepare(
                    "UPDATE mcp_agent_draft_conversions
                     SET status='prepared',canceled_at=NULL,prepared_at=NOW(),updated_at=NOW()
                     WHERE id=? AND status='canceled'"
                )->execute([$existingId]);
                mg_mcp_conversion_event($pdo, $existingId, 'prepared', $ownerUserId, [
                    'reprepared' => true,
                    'execution_enabled' => false,
                ]);
                $row = mg_mcp_conversion_row_by_id($pdo, $existingId);
                $pdo->commit();
                return mg_mcp_conversion_projection($row);
            }
            mg_mcp_conversion_event($pdo, $existingId, 'duplicate_returned', $ownerUserId, [
                'status' => (string)$row['status'],
            ]);
            $pdo->commit();
            return mg_mcp_conversion_projection($row, true);
        }

        $snapshot = [
            'source_draft_id' => (string)$draft['public_id'],
            'draft_type' => $draftType,
            'title' => (string)$draft['title'],
            'summary' => (string)$draft['summary'],
            'payload' => mg_mcp_draft_json($draft['payload_json'] ?? null),
            'workspace_type' => $draft['workspace_type'] !== null ? (string)$draft['workspace_type'] : null,
            'workspace_id' => $draft['workspace_id'] !== null ? (int)$draft['workspace_id'] : null,
            'payload_fingerprint' => (string)$draft['payload_fingerprint'],
            'execution_enabled' => false,
        ];
        $publicId = mg_public_uuid();
        $conversionType = MG_MCP_CONVERSION_TYPE_BY_DRAFT[$draftType] ?? throw new MgMcpDraftException('Unsupported conversion type.');
        $pdo->prepare(
            "INSERT INTO mcp_agent_draft_conversions
             (public_id,draft_id,owner_user_id,conversion_type,status,snapshot_json,prepared_at,created_at,updated_at)
             VALUES (?,?,?,?,'prepared',?,NOW(),NOW(),NOW())"
        )->execute([
            $publicId,
            (int)$draft['id'],
            $ownerUserId,
            $conversionType,
            json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $conversionId = (int)$pdo->lastInsertId();
        mg_mcp_conversion_event($pdo, $conversionId, 'prepared', $ownerUserId, [
            'draft_type' => $draftType,
            'execution_enabled' => false,
        ]);
        $row = mg_mcp_conversion_row_by_id($pdo, $conversionId);
        $pdo->commit();

        $metadata = [
            'draft_id' => (string)$draft['public_id'],
            'conversion_id' => $publicId,
            'draft_type' => $draftType,
            'execution_enabled' => false,
        ];
        mg_audit('mcp_agent_draft_conversion_prepared', 'mcp_agent_draft_conversion', $metadata, $ownerUserId);
        mg_event('mcp.agent_draft.conversion.prepared', $metadata, $ownerUserId);
        return mg_mcp_conversion_projection($row);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
