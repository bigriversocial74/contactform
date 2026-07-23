<?php
declare(strict_types=1);

function mg_mcp_creator_campaign_playbook_campaign_preparation(
    PDO $pdo,
    array $context,
    array $input
): array {
    $campaignPublicId = mg_mcp_creator_campaign_playbook_public_id(
        $input['campaign_id'] ?? '',
        'campaign_id',
        false
    );
    $proposal = mg_mcp_creator_campaign_playbook_json($input['proposal'] ?? [], 'campaign proposal');
    $campaignValues = is_array($proposal['campaign'] ?? null) ? $proposal['campaign'] : [];
    if ($campaignValues === []) {
        throw new MgMcpCreatorCampaignPlaybookException('Campaign proposal values are required.');
    }

    $current = null;
    $campaign = null;
    if ($campaignPublicId !== '') {
        $current = mg_mcp_creator_campaign_playbook_campaign($pdo, $context, $campaignPublicId);
        $campaign = mg_creator_campaign_repository_by_public_id(
            $pdo,
            $campaignPublicId,
            (int)$context['workspace_id']
        );
    }

    try {
        $campaignProposal = mg_mcp_creator_campaign_proposal_values(
            $pdo,
            $context,
            $campaignPublicId === '' ? 'draft.create' : 'draft.update',
            $campaignValues,
            $campaign
        );
        $productProposal = [];
        if (is_array($proposal['products'] ?? null) && $proposal['products'] !== []) {
            $productProposal = mg_mcp_creator_campaign_proposal_values(
                $pdo,
                $context,
                'products.propose',
                ['products' => $proposal['products']],
                $campaign
            );
        }
        $eligibilityProposal = [];
        if (is_array($proposal['eligibility_rules'] ?? null) && $proposal['eligibility_rules'] !== []) {
            $eligibilityProposal = mg_mcp_creator_campaign_proposal_values(
                $pdo,
                $context,
                'eligibility.propose',
                ['rules' => $proposal['eligibility_rules']],
                $campaign
            );
        }
        $deliverableProposal = [];
        if (is_array($proposal['deliverables'] ?? null) && $proposal['deliverables'] !== []) {
            $deliverableProposal = mg_mcp_creator_campaign_proposal_values(
                $pdo,
                $context,
                'deliverables.propose',
                ['deliverables' => $proposal['deliverables']],
                $campaign
            );
        }
        $compensationProposal = [];
        if (is_array($proposal['compensation_rules'] ?? null) && $proposal['compensation_rules'] !== []) {
            $compensationProposal = mg_mcp_creator_campaign_proposal_values(
                $pdo,
                $context,
                'compensation.propose',
                ['rules' => $proposal['compensation_rules']],
                $campaign
            );
        }
    } catch (MgMcpDraftException $error) {
        throw new MgMcpCreatorCampaignPlaybookException(
            $error->getMessage(),
            $error->httpStatus(),
            $error->draftCode()
        );
    } catch (MgMcpBridgeException $error) {
        throw new MgMcpCreatorCampaignPlaybookException(
            $error->getMessage(),
            $error->httpStatus(),
            $error->bridgeCode()
        );
    }

    $productIds = [];
    foreach ((array)($productProposal['products'] ?? []) as $product) {
        if (!empty($product['product_id'])) {
            $productIds[] = (string)$product['product_id'];
        }
    }
    $catalogItems = $productIds !== []
        ? mg_mcp_creator_campaign_playbook_catalog_items($pdo, $context, $productIds)
        : [];

    $validation = null;
    if ($campaignPublicId !== '') {
        $validation = mg_mcp_creator_campaign_playbook_read(
            $pdo,
            $context,
            'creator_campaigns.validate',
            ['campaign_id' => $campaignPublicId]
        );
    }

    $sections = [
        'campaign' => $campaignProposal !== [],
        'products' => $productProposal !== [],
        'eligibility' => $eligibilityProposal !== [],
        'deliverables' => $deliverableProposal !== [],
        'compensation' => $compensationProposal !== [],
    ];
    $missing = [];
    foreach ($sections as $section => $present) {
        if (!$present && $section !== 'products') {
            $missing[] = $section;
        }
    }

    return [
        'mode' => $campaignPublicId === '' ? 'new_campaign' : 'campaign_update',
        'campaign_id' => $campaignPublicId !== '' ? $campaignPublicId : null,
        'current' => $current,
        'catalog_items' => $catalogItems,
        'proposal' => [
            'campaign' => $campaignProposal,
            'products' => $productProposal['products'] ?? [],
            'eligibility_rules' => $eligibilityProposal['rules'] ?? [],
            'deliverables' => $deliverableProposal['deliverables'] ?? [],
            'compensation_rules' => $compensationProposal['rules'] ?? [],
        ],
        'native_validation' => $validation,
        'readiness' => [
            'status' => $missing === [] ? 'approval_ready' : 'incomplete',
            'present_sections' => array_keys(array_filter($sections)),
            'missing_sections' => $missing,
            'publication_enabled' => false,
            'canonical_mutation_enabled' => false,
        ],
    ];
}
