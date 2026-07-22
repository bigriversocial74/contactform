<?php
declare(strict_types=1);

$user = mg_require_auth();
$pdo = mg_db();
$notice = '';
$errorMessage = '';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        if (!mg_verify_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new MgMcpDraftException('Your session expired. Refresh the page and try again.', 419, 'MCP_DRAFT_CSRF_FAILED');
        }
        $action = strtolower(trim((string)($_POST['action'] ?? 'decision')));
        if ($action === 'decision') {
            $draft = mg_mcp_draft_owner_decide(
                $pdo,
                (int)$user['id'],
                (string)($_POST['draft_id'] ?? ''),
                (string)($_POST['decision'] ?? ''),
                (string)($_POST['reason'] ?? '')
            );
            $notice = (string)$draft['status'] === 'approved'
                ? 'Draft approved. Prepare conversion only when you are ready to create an inactive Microgifter draft.'
                : 'Draft rejected. No Microgifter action was performed.';
        } elseif ($action === 'prepare_conversion') {
            $sourceDraft = mg_mcp_conversion_draft_for_owner(
                $pdo,
                (int)$user['id'],
                (string)($_POST['draft_id'] ?? '')
            );
            $sourcePayload = mg_mcp_draft_json($sourceDraft['payload_json'] ?? null);
            if (!empty($sourcePayload['creator_campaign_proposal'])) {
                throw new MgMcpDraftException(
                    'Creator Campaign proposals remain review evidence only until the approval-gated canonical-action phase.',
                    409,
                    'MCP_CREATOR_CAMPAIGN_PROPOSAL_CONVERSION_DISABLED'
                );
            }
            $conversion = mg_mcp_conversion_prepare($pdo, $user, (string)($_POST['draft_id'] ?? ''));
            $notice = $conversion['duplicate']
                ? 'The conversion was already prepared.'
                : 'Conversion prepared. Review the destination before creating the inactive Microgifter draft.';
        } elseif ($action === 'create_native') {
            $conversion = mg_mcp_conversion_create_native($pdo, $user, (string)($_POST['conversion_id'] ?? ''));
            $notice = $conversion['duplicate']
                ? 'The inactive Microgifter draft already exists.'
                : 'Inactive Microgifter draft created. Nothing was made live or executed.';
        } elseif ($action === 'cancel_conversion') {
            mg_mcp_conversion_cancel($pdo, $user, (string)($_POST['conversion_id'] ?? ''));
            $notice = 'Conversion canceled before any native draft was created.';
        } else {
            throw new MgMcpDraftException('Unknown draft action.', 422, 'MCP_DRAFT_ACTION_INVALID');
        }
    } catch (MgMcpDraftException $error) {
        $errorMessage = $error->getMessage();
    } catch (Throwable $error) {
        mg_security_log('error', 'mcp.agent_draft.owner_action_failed', 'Agent draft owner action failed.', [
            'exception_class' => $error::class,
            'exception_message' => mb_substr($error->getMessage(), 0, 500),
        ], (int)$user['id']);
        $errorMessage = 'The draft action could not be completed.';
    }
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$typeFilter = trim((string)($_GET['type'] ?? ''));
try {
    $drafts = mg_mcp_draft_list_for_owner($pdo, (int)$user['id'], [
        'status' => $statusFilter,
        'type' => $typeFilter,
    ]);
    $drafts = mg_mcp_conversion_attach_to_drafts($pdo, (int)$user['id'], $drafts);
} catch (MgMcpDraftException $error) {
    $drafts = [];
    $errorMessage = $error->getMessage();
}

$counts = array_fill_keys(MG_MCP_DRAFT_STATUSES, 0);
foreach ($drafts as $draft) $counts[(string)$draft['status']] = ($counts[(string)$draft['status']] ?? 0) + 1;

$conversionLabel = static function (array $conversion): string {
    return match ((string)($conversion['conversion_type'] ?? '')) {
        'gift_draft' => 'private gift draft',
        'campaign_draft' => 'CRM campaign draft',
        'reward_template_draft' => 'reward template draft',
        'message_draft' => 'merchant message draft',
        default => 'Microgifter draft',
    };
};

$page_title = 'Agent Drafts | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'agent-drafts';
$can_merchant_nav = true;
$page_body_class = 'mg-agent-drafts-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/mcp-drafts.css?v=20260722-phase13b',
];
require dirname(__DIR__) . '/header.php';
require __DIR__ . '/account-page-phase3b-view.php';
require dirname(__DIR__) . '/footer.php';
