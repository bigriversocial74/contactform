<?php
declare(strict_types=1);

require_once __DIR__ . '/_mcp_creator_campaign_bridge.php';
require_once __DIR__ . '/_mcp_creator_campaign_draft_bridge.php';
require_once dirname(__DIR__, 2) . '/includes/mcp-creator-campaign-playbooks.php';

function mg_mcp_creator_campaign_playbook_bridge_authenticate(
    PDO $pdo,
    string $rawBody,
    array $payload
): array {
    $context = mg_mcp_draft_bridge_authenticate($pdo, $rawBody, $payload);
    if ((string)($context['maximum_operation_class'] ?? '') !== 'draft'
        || (string)($context['client_maximum_operation_class'] ?? '') !== 'draft') {
        throw new MgMcpBridgeException(
            'Creator Campaign bounded playbooks require a draft-authority connection.',
            403,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_OPERATION_DENIED'
        );
    }
    if (!in_array((string)($context['workspace_type'] ?? ''), ['merchant', 'merchant_workspace'], true)
        || (int)($context['workspace_id'] ?? 0) < 1) {
        throw new MgMcpBridgeException(
            'Creator Campaign bounded playbooks require an authorized merchant workspace.',
            403,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_WORKSPACE_REQUIRED'
        );
    }
    return $context;
}

function mg_mcp_creator_campaign_playbook_bridge_dispatch(
    PDO $pdo,
    array $context,
    string $operation,
    array $arguments
): array {
    try {
        if ($operation !== 'creator_campaign_playbooks.run') {
            throw new MgMcpBridgeException(
                'Creator Campaign playbook bridge operation is not allowed.',
                404,
                'MCP_CREATOR_CAMPAIGN_PLAYBOOK_OPERATION_UNKNOWN'
            );
        }
        $toolName = mg_mcp_bridge_text($arguments['tool_name'] ?? '', 180, 'tool_name');
        $input = is_array($arguments['input'] ?? null) ? $arguments['input'] : [];
        return mg_mcp_creator_campaign_playbook_run($pdo, $context, $toolName, $input);
    } catch (MgMcpCreatorCampaignPlaybookException $error) {
        throw new MgMcpBridgeException($error->getMessage(), $error->httpStatus(), $error->errorCode());
    } catch (MgMcpDraftException $error) {
        throw new MgMcpBridgeException($error->getMessage(), $error->httpStatus(), $error->draftCode());
    } catch (MgMcpAutomationGrantException $error) {
        throw new MgMcpBridgeException($error->getMessage(), $error->httpStatus(), $error->errorCode());
    }
}
