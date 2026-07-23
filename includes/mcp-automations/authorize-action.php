<?php
declare(strict_types=1);

function mg_mcp_automation_authorize_grant_action(
    PDO $pdo,
    string $connectionPublicId,
    string $grantPublicId,
    string $toolName,
    string $operationClass,
    string $riskLevel,
    int $proposedAmountCents = 0,
    int $proposedQuantity = 0,
    array $targetContext = [],
    ?int $excludeRunId = null
): array {
    if (!mg_mcp_automation_schema_ready($pdo)) {
        throw new MgMcpAutomationGrantException('Automation authority is unavailable.', 503, 'MCP_AUTOMATION_SCHEMA_MISSING');
    }
    $stmt = $pdo->prepare(
        "SELECT g.*,c.public_id AS connection_public_id,c.status AS connection_status,c.expires_at AS connection_expires_at,
                c.maximum_operation_class AS connection_maximum_operation_class,cl.status AS client_status,
                cl.maximum_operation_class AS client_maximum_operation_class,mw.status AS workspace_status
         FROM mcp_automation_grants g
         INNER JOIN mcp_connections c ON c.id=g.connection_id
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         LEFT JOIN merchant_workspaces mw ON g.workspace_type IN ('merchant','merchant_workspace') AND mw.id=g.workspace_id
         WHERE g.public_id=? AND c.public_id=? LIMIT 1"
    );
    $stmt->execute([$grantPublicId, $connectionPublicId]);
    $grant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$grant || (string)$grant['status'] !== 'active') {
        throw new MgMcpAutomationGrantException('No active automation grant authorizes this request.', 403, 'MCP_AUTOMATION_GRANT_INACTIVE');
    }
    mg_mcp_automation_assert_grant_activatable($pdo, $grant);
    if (!in_array($toolName, mg_mcp_automation_json_list($grant['allowed_tools_json']), true)) {
        throw new MgMcpAutomationGrantException('The requested tool is outside the grant.', 403, 'MCP_AUTOMATION_TOOL_DENIED');
    }
    if (mg_mcp_automation_operation_rank($operationClass) > mg_mcp_automation_operation_rank((string)$grant['maximum_operation_class'])) {
        throw new MgMcpAutomationGrantException('The requested operation exceeds the grant ceiling.', 403, 'MCP_AUTOMATION_OPERATION_DENIED');
    }
    $riskRank = ['low' => 10, 'medium' => 20, 'high' => 30, 'critical' => 40];
    if (($riskRank[$riskLevel] ?? 1000) > ($riskRank[(string)$grant['risk_ceiling']] ?? 0)) {
        throw new MgMcpAutomationGrantException('The requested risk level exceeds the grant ceiling.', 403, 'MCP_AUTOMATION_RISK_DENIED');
    }
    if ($grant['per_run_amount_limit_cents'] !== null && $proposedAmountCents > (int)$grant['per_run_amount_limit_cents']) {
        throw new MgMcpAutomationGrantException('The requested amount exceeds the per-run grant limit.', 403, 'MCP_AUTOMATION_AMOUNT_LIMIT');
    }
    if ($grant['per_run_quantity_limit'] !== null && $proposedQuantity > (int)$grant['per_run_quantity_limit']) {
        throw new MgMcpAutomationGrantException('The requested quantity exceeds the per-run grant limit.', 403, 'MCP_AUTOMATION_QUANTITY_LIMIT');
    }
    if ($grant['minimum_frequency_seconds'] !== null && $grant['last_used_at'] !== null) {
        $nextAllowed = strtotime((string)$grant['last_used_at']) + (int)$grant['minimum_frequency_seconds'];
        if ($nextAllowed > time()) {
            throw new MgMcpAutomationGrantException('The grant frequency limit has not elapsed.', 409, 'MCP_AUTOMATION_FREQUENCY_LIMIT');
        }
    }
    $usage = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN ar.created_at>=UTC_DATE() AND ar.status IN ('succeeded','reconciled') THEN ar.amount_cents ELSE 0 END),0) AS daily_amount,
            COALESCE(SUM(CASE WHEN ar.status IN ('succeeded','reconciled') THEN ar.amount_cents ELSE 0 END),0) AS lifetime_amount,
            COALESCE(SUM(CASE WHEN ar.created_at>=UTC_DATE() AND ar.status IN ('succeeded','reconciled') THEN ar.quantity ELSE 0 END),0) AS daily_quantity,
            COALESCE(SUM(CASE WHEN ar.status IN ('succeeded','reconciled') THEN ar.quantity ELSE 0 END),0) AS lifetime_quantity
         FROM mcp_action_receipts ar WHERE ar.grant_id=?"
    );
    $usage->execute([(int)$grant['id']]);
    $used = $usage->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($grant['daily_amount_limit_cents'] !== null && ((int)($used['daily_amount'] ?? 0) + $proposedAmountCents) > (int)$grant['daily_amount_limit_cents']) {
        throw new MgMcpAutomationGrantException('The grant daily amount limit would be exceeded.', 403, 'MCP_AUTOMATION_DAILY_AMOUNT_LIMIT');
    }
    if ($grant['lifetime_amount_limit_cents'] !== null && ((int)($used['lifetime_amount'] ?? 0) + $proposedAmountCents) > (int)$grant['lifetime_amount_limit_cents']) {
        throw new MgMcpAutomationGrantException('The grant lifetime amount limit would be exceeded.', 403, 'MCP_AUTOMATION_LIFETIME_AMOUNT_LIMIT');
    }
    if ($grant['daily_quantity_limit'] !== null && ((int)($used['daily_quantity'] ?? 0) + $proposedQuantity) > (int)$grant['daily_quantity_limit']) {
        throw new MgMcpAutomationGrantException('The grant daily quantity limit would be exceeded.', 403, 'MCP_AUTOMATION_DAILY_QUANTITY_LIMIT');
    }
    if ($grant['lifetime_quantity_limit'] !== null && ((int)($used['lifetime_quantity'] ?? 0) + $proposedQuantity) > (int)$grant['lifetime_quantity_limit']) {
        throw new MgMcpAutomationGrantException('The grant lifetime quantity limit would be exceeded.', 403, 'MCP_AUTOMATION_LIFETIME_QUANTITY_LIMIT');
    }
    $targetPolicy = mg_mcp_automation_json_object($grant['target_policy_json']);
    foreach (['product_id' => 'allowed_product_ids', 'campaign_id' => 'allowed_campaign_ids', 'reward_template_id' => 'allowed_reward_template_ids'] as $inputKey => $policyKey) {
        $targetId = strtolower(trim((string)($targetContext[$inputKey] ?? '')));
        $allowedIds = is_array($targetPolicy[$policyKey] ?? null) ? array_map('strval', $targetPolicy[$policyKey]) : [];
        if ($targetId === '') {
            continue;
        }
        if ($inputKey === 'product_id' && $allowedIds === [] && empty($targetPolicy['allow_all_published_catalog'])) {
            throw new MgMcpAutomationGrantException('The grant does not authorize unrestricted catalog targets.', 403, 'MCP_AUTOMATION_TARGET_DENIED');
        }
        if ($allowedIds !== [] && !in_array($targetId, $allowedIds, true)) {
            throw new MgMcpAutomationGrantException('The requested target is outside the grant target policy.', 403, 'MCP_AUTOMATION_TARGET_DENIED');
        }
    }
    if (!empty($targetPolicy['allow_existing_contacts_only'])
        && array_key_exists('recipient_is_existing_contact', $targetContext)
        && $targetContext['recipient_is_existing_contact'] !== true) {
        throw new MgMcpAutomationGrantException('The grant allows existing authorized contacts only.', 403, 'MCP_AUTOMATION_RECIPIENT_DENIED');
    }
    $concurrencySql = "SELECT COUNT(*) FROM mcp_automation_runs
                       WHERE grant_id=? AND status IN ('queued','evaluating','waiting_for_approval','approved','executing')";
    $concurrencyParams = [(int)$grant['id']];
    if ($excludeRunId !== null && $excludeRunId > 0) {
        $concurrencySql .= ' AND id<>?';
        $concurrencyParams[] = $excludeRunId;
    }
    $concurrency = $pdo->prepare($concurrencySql);
    $concurrency->execute($concurrencyParams);
    if ((int)$concurrency->fetchColumn() >= (int)$grant['maximum_concurrent_runs']) {
        throw new MgMcpAutomationGrantException('The grant concurrency limit has been reached.', 409, 'MCP_AUTOMATION_CONCURRENCY_LIMIT');
    }

    return [
        'grant_id' => (string)$grant['public_id'],
        'connection_id' => (string)$grant['connection_public_id'],
        'revocation_version' => (int)$grant['revocation_version'],
        'maximum_operation_class' => (string)$grant['maximum_operation_class'],
        'approval_policy' => (string)$grant['approval_policy'],
        'risk_ceiling' => (string)$grant['risk_ceiling'],
        'execution_enabled' => $operationClass === 'approval_gated',
    ];
}
