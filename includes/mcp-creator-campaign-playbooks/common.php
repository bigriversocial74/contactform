<?php
declare(strict_types=1);

function mg_mcp_creator_campaign_playbook_require_scope(array $context, string $scope): void
{
    if (!in_array($scope, array_map('strval', (array)($context['scopes'] ?? [])), true)) {
        throw new MgMcpCreatorCampaignPlaybookException(
            'A required Creator Campaign playbook scope has been revoked.',
            403,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_SCOPE_DENIED'
        );
    }
}

function mg_mcp_creator_campaign_playbook_read(
    PDO $pdo,
    array $context,
    string $operation,
    array $arguments
): array {
    try {
        return mg_mcp_creator_campaign_bridge_dispatch($pdo, $context, $operation, $arguments);
    } catch (MgMcpBridgeException $error) {
        throw new MgMcpCreatorCampaignPlaybookException(
            $error->getMessage(),
            $error->httpStatus(),
            $error->bridgeCode()
        );
    }
}

function mg_mcp_creator_campaign_playbook_campaign(PDO $pdo, array $context, string $campaignPublicId): array
{
    $result = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.get', [
        'campaign_id' => $campaignPublicId,
    ]);
    if (!is_array($result['campaign'] ?? null)) {
        throw new MgMcpCreatorCampaignPlaybookException(
            'Creator Campaign could not be loaded.',
            404,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_CAMPAIGN_NOT_FOUND'
        );
    }
    return $result;
}

function mg_mcp_creator_campaign_playbook_find(array $items, string $publicId, string $label): array
{
    foreach ($items as $item) {
        if (is_array($item) && (string)($item['id'] ?? '') === $publicId) {
            return $item;
        }
    }
    throw new MgMcpCreatorCampaignPlaybookException(
        $label . ' was not found in the authorized campaign scope.',
        404,
        'MCP_CREATOR_CAMPAIGN_PLAYBOOK_RESOURCE_NOT_FOUND'
    );
}

function mg_mcp_creator_campaign_playbook_list_item(
    PDO $pdo,
    array $context,
    string $operation,
    string $campaignPublicId,
    string $publicId,
    string $label
): array {
    $result = mg_mcp_creator_campaign_playbook_read($pdo, $context, $operation, [
        'campaign_id' => $campaignPublicId,
        'limit' => 100,
    ]);
    return mg_mcp_creator_campaign_playbook_find((array)($result['items'] ?? []), $publicId, $label);
}

function mg_mcp_creator_campaign_playbook_catalog_items(PDO $pdo, array $context, array $productIds): array
{
    mg_mcp_creator_campaign_playbook_require_scope($context, 'catalog:read');
    $items = [];
    foreach ($productIds as $productId) {
        $productId = mg_mcp_creator_campaign_playbook_public_id($productId, 'product_id');
        try {
            $items[] = mg_mcp_bridge_catalog_item($pdo, ['product_id' => $productId]);
        } catch (MgMcpBridgeException $error) {
            throw new MgMcpCreatorCampaignPlaybookException(
                $error->getMessage(),
                $error->httpStatus(),
                $error->bridgeCode()
            );
        }
    }
    return $items;
}

function mg_mcp_creator_campaign_playbook_creator_candidates(PDO $pdo, array $context, array $candidates): array
{
    if (count($candidates) > 25) {
        throw new MgMcpCreatorCampaignPlaybookException(
            'Creator outreach may include at most 25 candidates.',
            422,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_VALIDATION_FAILED'
        );
    }
    $workspaceId = (int)($context['workspace_id'] ?? 0);
    $verified = [];
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            throw new MgMcpCreatorCampaignPlaybookException('Invalid Creator candidate.');
        }
        $creatorProfileId = mg_mcp_creator_campaign_playbook_public_id(
            $candidate['creator_profile_id'] ?? '',
            'creator_profile_id'
        );
        $stmt = $pdo->prepare(
            "SELECT cp.public_id,COALESCE(cp.display_name,u.display_name,u.full_name,'Creator') creator_name,
                    cp.bio,cp.status,
                    EXISTS(SELECT 1 FROM creator_campaign_participants p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id WHERE p.creator_profile_id=cp.id AND cc.workspace_id=? AND p.status NOT IN ('removed','declined')) AS existing_merchant_relationship
             FROM creator_profiles cp
             INNER JOIN users u ON u.id=cp.user_id AND u.status='active'
             INNER JOIN user_model_assignments uma ON uma.user_id=u.id AND uma.status='active'
             INNER JOIN user_models um ON um.id=uma.user_model_id AND um.code='creator'
             WHERE cp.public_id=? AND cp.status='active' LIMIT 1"
        );
        $stmt->execute([$workspaceId, $creatorProfileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new MgMcpCreatorCampaignPlaybookException(
                'Only existing approved Microgifter Creators may be selected.',
                422,
                'MCP_CREATOR_CAMPAIGN_PLAYBOOK_CREATOR_INELIGIBLE'
            );
        }
        $fitScore = (int)($candidate['fit_score'] ?? 0);
        if ($fitScore < 0 || $fitScore > 100) {
            throw new MgMcpCreatorCampaignPlaybookException('Creator fit score must be between 0 and 100.');
        }
        $verified[] = [
            'creator_profile_id' => (string)$row['public_id'],
            'creator_name' => (string)$row['creator_name'],
            'bio' => $row['bio'] ?? null,
            'existing_merchant_relationship' => !empty($row['existing_merchant_relationship']),
            'fit_score' => $fitScore,
            'rationale' => mg_mcp_creator_campaign_playbook_text($candidate['rationale'] ?? '', 1, 2000, 'Candidate rationale'),
            'invitation_message' => mg_mcp_creator_campaign_playbook_text($candidate['invitation_message'] ?? '', 1, 8000, 'Invitation message'),
        ];
    }
    usort($verified, static fn(array $a, array $b): int => $b['fit_score'] <=> $a['fit_score']);
    return $verified;
}

function mg_mcp_creator_campaign_playbook_string_list(mixed $value, string $field, int $maximum = 50): array
{
    if ($value === null) {
        return [];
    }
    if (!is_array($value) || count($value) > $maximum) {
        throw new MgMcpCreatorCampaignPlaybookException('Invalid ' . $field . '.');
    }
    $items = [];
    foreach ($value as $item) {
        $text = mg_mcp_creator_campaign_playbook_text($item, 1, 2000, $field . ' item');
        $items[] = $text;
    }
    return array_values(array_unique($items));
}
