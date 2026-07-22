<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/creator-campaigns.php';

const MG_MCP_CREATOR_CAMPAIGN_PROPOSAL_SCOPE_BY_KIND = [
    'draft.create' => 'creator_campaigns:draft',
    'draft.update' => 'creator_campaigns:draft',
    'products.propose' => 'creator_campaign_products:draft',
    'eligibility.propose' => 'creator_campaign_eligibility:draft',
    'deliverables.propose' => 'creator_campaign_deliverables:draft',
    'compensation.propose' => 'creator_campaign_compensation:draft',
    'attribution.propose' => 'creator_campaign_attribution:draft',
    'budget.propose' => 'creator_campaign_budget:draft',
    'rights.propose' => 'creator_campaign_rights:draft',
    'terms.propose' => 'creator_campaign_terms:draft',
    'invitation.draft' => 'creator_campaign_invitations:draft',
    'message.draft' => 'creator_campaign_messages:draft',
    'submission_feedback.draft' => 'creator_campaign_submission_feedback:draft',
];

const MG_MCP_CREATOR_CAMPAIGN_PROPOSAL_RISK_FLOOR = [
    'draft.create' => 'medium',
    'draft.update' => 'medium',
    'products.propose' => 'medium',
    'eligibility.propose' => 'medium',
    'deliverables.propose' => 'medium',
    'compensation.propose' => 'high',
    'attribution.propose' => 'medium',
    'budget.propose' => 'high',
    'rights.propose' => 'high',
    'terms.propose' => 'high',
    'invitation.draft' => 'medium',
    'message.draft' => 'medium',
    'submission_feedback.draft' => 'medium',
];

function mg_mcp_creator_campaign_proposal_requested(array $arguments): bool
{
    $payload = is_array($arguments['payload'] ?? null) ? $arguments['payload'] : [];
    return !empty($payload['creator_campaign_proposal']);
}

function mg_mcp_creator_campaign_proposal_rank(string $risk): int
{
    return match ($risk) {
        'low' => 10,
        'medium' => 20,
        'high' => 30,
        'critical' => 40,
        default => 0,
    };
}

function mg_mcp_creator_campaign_proposal_enum(
    mixed $value,
    array $allowed,
    string $label,
    bool $required = true,
    ?string $default = null
): ?string {
    $text = strtolower(trim((string)$value));
    if ($text === '' && $default !== null) $text = $default;
    if ($text === '' && !$required) return null;
    if (!in_array($text, $allowed, true)) {
        throw new MgMcpDraftException('Invalid ' . $label . '.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
    }
    return $text;
}

function mg_mcp_creator_campaign_proposal_bool(mixed $value, bool $default = false): bool
{
    if ($value === null || $value === '') return $default;
    if (is_bool($value)) return $value;
    if (in_array($value, [1, '1', 'true', 'yes', 'on'], true)) return true;
    if (in_array($value, [0, '0', 'false', 'no', 'off'], true)) return false;
    throw new MgMcpDraftException('Invalid boolean proposal value.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
}

function mg_mcp_creator_campaign_proposal_json(mixed $value, string $label, int $maximumBytes = 60000): array
{
    if (!is_array($value)) {
        throw new MgMcpDraftException('Invalid ' . $label . '.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
    }
    try {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new MgMcpDraftException('Invalid ' . $label . '.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
    }
    if (strlen($json) > $maximumBytes) {
        throw new MgMcpDraftException(ucfirst($label) . ' is too large.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_TOO_LARGE');
    }
    return $value;
}

function mg_mcp_creator_campaign_proposal_id(mixed $value, string $label, bool $required = true): string
{
    return mg_mcp_creator_campaign_uuid($value, $label, $required);
}

function mg_mcp_creator_campaign_proposal_require_context(array $context, string $kind): string
{
    $scope = MG_MCP_CREATOR_CAMPAIGN_PROPOSAL_SCOPE_BY_KIND[$kind] ?? '';
    if ($scope === '') {
        throw new MgMcpDraftException('Unsupported Creator Campaign proposal.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_UNSUPPORTED');
    }
    if (mg_mcp_draft_operation_rank((string)($context['maximum_operation_class'] ?? 'read')) < mg_mcp_draft_operation_rank('draft')) {
        throw new MgMcpDraftException('The MCP connection is not authorized to create proposals.', 403, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_OPERATION_DENIED');
    }
    if (!in_array($scope, array_map('strval', (array)($context['scopes'] ?? [])), true)) {
        throw new MgMcpDraftException('Required Creator Campaign proposal scope is not granted.', 403, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_SCOPE_DENIED');
    }
    if (!in_array((string)($context['workspace_type'] ?? ''), ['merchant', 'merchant_workspace'], true)
        || (int)($context['workspace_id'] ?? 0) < 1) {
        throw new MgMcpDraftException('An authorized merchant workspace is required.', 403, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_WORKSPACE_REQUIRED');
    }
    return $scope;
}

function mg_mcp_creator_campaign_proposal_campaign(PDO $pdo, array $context, mixed $campaignPublicId, bool $required = true): ?array
{
    $publicId = mg_mcp_creator_campaign_proposal_id($campaignPublicId, 'campaign_id', $required);
    if ($publicId === '') return null;
    try {
        return mg_creator_campaign_repository_by_public_id($pdo, $publicId, (int)$context['workspace_id']);
    } catch (RuntimeException) {
        throw new MgMcpDraftException('Creator Campaign was not found in the authorized merchant workspace.', 404, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_CAMPAIGN_NOT_FOUND');
    }
}

function mg_mcp_creator_campaign_proposal_product(PDO $pdo, int $workspaceId, array $input): array
{
    $productPublicId = mg_mcp_creator_campaign_proposal_id($input['product_id'] ?? '', 'product_id');
    $stmt = $pdo->prepare('SELECT id FROM catalog_products WHERE public_id=? LIMIT 1');
    $stmt->execute([$productPublicId]);
    $productId = (int)($stmt->fetchColumn() ?: 0);
    if ($productId < 1) throw new MgMcpDraftException('Product was not found.', 404, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_PRODUCT_NOT_FOUND');

    $versionPublicId = mg_mcp_creator_campaign_proposal_id($input['version_id'] ?? '', 'version_id', false);
    $versionId = null;
    if ($versionPublicId !== '') {
        $versionStmt = $pdo->prepare('SELECT id FROM catalog_product_versions WHERE public_id=? AND product_id=? LIMIT 1');
        $versionStmt->execute([$versionPublicId, $productId]);
        $versionId = (int)($versionStmt->fetchColumn() ?: 0);
        if ($versionId < 1) throw new MgMcpDraftException('Product version was not found.', 404, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VERSION_NOT_FOUND');
    }
    try {
        mg_creator_campaign_repository_assert_product_owned($pdo, $workspaceId, $productId, $versionId);
    } catch (DomainException $error) {
        throw new MgMcpDraftException($error->getMessage(), 403, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_PRODUCT_DENIED');
    }

    $currency = strtoupper(mg_mcp_draft_text($input['currency'] ?? '', 3, 'currency', false));
    if ($currency !== '' && preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
        throw new MgMcpDraftException('Invalid currency.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
    }
    return array_filter([
        'product_id' => $productPublicId,
        'version_id' => $versionPublicId !== '' ? $versionPublicId : null,
        'relationship_type' => mg_mcp_creator_campaign_proposal_enum($input['relationship_type'] ?? 'featured', ['primary','featured','commissionable','excluded','creator_compensation'], 'product relationship', true, 'featured'),
        'sort_order' => mg_mcp_draft_integer($input['sort_order'] ?? null, 0, 10000, 'sort order', 0),
        'value_minor' => mg_mcp_draft_integer($input['value_minor'] ?? null, 0, 1000000000, 'product value'),
        'currency' => $currency !== '' ? $currency : null,
    ], static fn(mixed $value): bool => $value !== null && $value !== '');
}

function mg_mcp_creator_campaign_proposal_submission(PDO $pdo, array $context, mixed $submissionPublicId): array
{
    $publicId = mg_mcp_creator_campaign_proposal_id($submissionPublicId, 'submission_id');
    $stmt = $pdo->prepare('SELECT s.id,s.public_id,s.campaign_id,cc.public_id campaign_public_id FROM creator_campaign_submissions s INNER JOIN creator_campaigns cc ON cc.id=s.campaign_id WHERE s.public_id=? AND cc.workspace_id=? LIMIT 1');
    $stmt->execute([$publicId, (int)$context['workspace_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgMcpDraftException('Submission was not found in this merchant workspace.', 404, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_SUBMISSION_NOT_FOUND');
    return $row;
}

function mg_mcp_creator_campaign_proposal_participant(PDO $pdo, array $context, array $campaign, mixed $participantPublicId, bool $required = false): ?array
{
    $publicId = mg_mcp_creator_campaign_proposal_id($participantPublicId, 'participant_id', $required);
    if ($publicId === '') return null;
    $stmt = $pdo->prepare('SELECT public_id,status FROM creator_campaign_participants WHERE public_id=? AND campaign_id=? LIMIT 1');
    $stmt->execute([$publicId, (int)$campaign['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgMcpDraftException('Participant was not found in this campaign.', 404, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_PARTICIPANT_NOT_FOUND');
    return $row;
}

function mg_mcp_creator_campaign_proposal_creator(PDO $pdo, mixed $creatorProfilePublicId): array
{
    $publicId = mg_mcp_creator_campaign_proposal_id($creatorProfilePublicId, 'creator_profile_id');
    $stmt = $pdo->prepare("SELECT cp.public_id,cp.status FROM creator_profiles cp INNER JOIN users u ON u.id=cp.user_id AND u.status='active' INNER JOIN user_model_assignments uma ON uma.user_id=u.id AND uma.status='active' INNER JOIN user_models um ON um.id=uma.user_model_id AND um.code='creator' WHERE cp.public_id=? AND cp.status='active' LIMIT 1");
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgMcpDraftException('Creator profile is unavailable.', 404, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_CREATOR_NOT_FOUND');
    return $row;
}

require_once __DIR__ . '/_mcp_creator_campaign_draft_values.php';
require_once __DIR__ . '/_mcp_creator_campaign_draft_service.php';
