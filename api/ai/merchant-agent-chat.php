<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-automation-controls.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-credit-response.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-chat-memory.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-crm-search.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-crm-contact-context.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-contact-action-center.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-contact-workspace-v1-1.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-crm-contact-chat.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-thread-delete.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-snapshot.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-ai-report.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-ai-incident-alerts.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-admin-limits.php';

function mg_agent_chat_admin_operator(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $role = 'super_' . 'admin';
    return in_array($role, $roles, true);
}

function mg_agent_chat_contact_state(PDO $pdo, int $merchantOwnerId, int $actorId, array $state, array $input = []): array
{
    $state = mg_merchant_contact_action_center_attach_state($pdo, $merchantOwnerId, $actorId, $state, $input);
    return mg_merchant_contact_workspace_attach_state($pdo, $merchantOwnerId, $actorId, $state);
}

function mg_agent_chat_access_state(PDO $pdo, array $user, array $context, array $state): array
{
    return mg_merchant_agent_state_with_access($pdo, $user, $context, $state);
}

function mg_agent_chat_access_response(PDO $pdo, array $user, array $context, array $response): array
{
    if (is_array($response['state'] ?? null)) {
        $response['state'] = mg_agent_chat_access_state($pdo, $user, $context, $response['state']);
    }
    return array_merge($response, mg_merchant_agent_ai_last_result($pdo, $user, $context));
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pdo = mg_db();
$access = mg_merchant_agent_require_owner_access($pdo);
$user = $access['user'];
$packageContext = $access['context'];
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$actorId = (int)$user['id'];
$merchantOwnerId = max(1, (int)($workspace['merchant_user_id'] ?? $actorId));
if ($merchantOwnerId !== $actorId) mg_fail('This Merchant Agent build is available to the merchant workspace owner only.', 403, ['scope'=>'merchant_owner_required']);

if ($method === 'GET') {
    $state = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, mg_ai_chat_public_state($pdo, $actorId));
    $state['memory'] = mg_agent_memory_summary($pdo, $actorId);
    $state['memory_sources'] = mg_agent_memory_sources($pdo, $actorId, 30);
    $state['agent_autonomy'] = mg_agent_autonomy_for_merchant($pdo, $actorId);
    $state['admin_operator_available'] = mg_agent_chat_admin_operator($user);
    $state['admin_ai_limits'] = mg_agent_admin_limit_public($pdo, $actorId);
    mg_ok(mg_agent_chat_access_state($pdo, $user, $packageContext, $state));
}

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action'] ?? 'send_message')));
    if ($action === 'send_message' && mg_merchant_ai_report_is_keyword($input['message'] ?? '')) $action = 'ai_report';
    if ($action === 'send_message' && mg_merchant_agent_crm_search_is_query($input['message'] ?? '')) $action = 'crm_search';
    $localActions = ['save_agent_profile','save_memory_profile','create_thread','save_thread','archive_thread','clear_thread','rename_thread','load_thread','delete_thread','select_contact','clear_contact','contact_note','contact_review_draft'];

    if (!in_array($action, array_merge(['send_message','snapshot','ai_report','crm_search','contact_action'], $localActions), true)) {
        mg_fail('Unknown merchant agent chat action.', 422);
    }

    $input['_merchant_owner_id'] = $merchantOwnerId;
    $thread = mg_agent_thread_by_id($pdo, $actorId, mg_ai_chat_clean($input['thread_id'] ?? '', 80));
    $threadId = (string)($thread['id'] ?? '');
    $hasExplicitMention = $action === 'send_message' && mg_merchant_agent_crm_has_mentions($input['message'] ?? '');
    $selectedContact = mg_merchant_contact_action_center_find_contact($pdo, $merchantOwnerId, $actorId, $threadId, $input);
    if ($selectedContact && !$hasExplicitMention) {
        $input['selected_contact_id'] = (string)($selectedContact['id'] ?? '');
        $input['selected_contact_mention'] = (string)($selectedContact['mention'] ?? '');
    } elseif ($hasExplicitMention) {
        unset($input['selected_contact_id'], $input['selected_contact_mention'], $input['contact_id'], $input['contact_mention']);
    }
    $contactAware = $action === 'send_message' && ($hasExplicitMention || $selectedContact !== null);
    if ($contactAware || in_array($action, ['snapshot','crm_search','contact_action','select_contact','clear_contact','contact_note','contact_review_draft'], true)) {
        mg_merchant_agent_require_owner_permission($user, 'merchant.campaigns.view');
    }
    if ($action === 'contact_note') mg_merchant_agent_require_owner_permission($user, 'merchant.campaigns.manage');

    if ($action === 'ai_report') {
        mg_rate_limit('merchant.agent.ai_report.chat', 'user:' . $actorId, 30, 60);
        $input['message'] = trim((string)($input['message'] ?? 'AI Report')) ?: 'AI Report';
        $reportMode = mg_merchant_ai_report_mode($input['message']);
        $response = $reportMode === 'alerts' && mg_ai_reconciliation_schema_ready($pdo)
            ? mg_merchant_ai_incident_alert_chat_response($pdo, $user, $input)
            : mg_merchant_ai_report_chat_response($pdo, $user, $packageContext, $input);
        if (is_array($response['state'] ?? null)) $response['state'] = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, $response['state'], $input);
        mg_ok(mg_agent_chat_access_response($pdo, $user, $packageContext, $response), 'AI credit report generated.', 201);
    }

    if ($action === 'crm_search') {
        mg_rate_limit('merchant.agent.crm_search.chat', 'user:' . $actorId, 90, 60);
        $response = mg_merchant_agent_crm_search_response($pdo, $user, $input);
        mg_ok(mg_agent_chat_access_response($pdo, $user, $packageContext, $response), 'Merchant CRM search completed.', 201);
    }

    if ($action === 'select_contact') {
        if (!$selectedContact) mg_fail('Choose a valid CRM contact from this merchant workspace.', 404);
        if ($threadId === '') {
            $thread = mg_agent_active_thread($pdo, $actorId);
            $threadId = (string)($thread['id'] ?? '');
        }
        mg_merchant_contact_action_center_record_selection($pdo, $actorId, $threadId, $selectedContact);
        $input['selected_contact_id'] = (string)$selectedContact['id'];
        $input['selected_contact_mention'] = (string)($selectedContact['mention'] ?? '');
        $state = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, mg_ai_chat_public_state($pdo, $actorId), $input);
        mg_ok(mg_agent_chat_access_response($pdo, $user, $packageContext, ['state'=>$state,'contact_action_center'=>$state['contact_action_center'] ?? null]), 'CRM contact selected.');
    }

    if ($action === 'clear_contact') {
        if ($threadId === '') {
            $thread = mg_agent_active_thread($pdo, $actorId);
            $threadId = (string)($thread['id'] ?? '');
        }
        mg_merchant_contact_action_center_record_selection($pdo, $actorId, $threadId, null);
        unset($input['selected_contact_id'], $input['selected_contact_mention'], $input['contact_id'], $input['contact_mention']);
        $state = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, mg_ai_chat_public_state($pdo, $actorId));
        mg_ok(mg_agent_chat_access_response($pdo, $user, $packageContext, ['state'=>$state,'contact_action_center'=>$state['contact_action_center'] ?? null]), 'Selected CRM contact cleared.');
    }

    if ($action === 'contact_note') {
        if (!$selectedContact) mg_fail('Select a CRM contact before adding a note.', 422);
        $note = mg_merchant_contact_workspace_add_note($pdo, $merchantOwnerId, $actorId, $selectedContact, $input);
        $state = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, mg_ai_chat_public_state($pdo, $actorId), $input);
        mg_ok(mg_agent_chat_access_response($pdo, $user, $packageContext, ['note'=>$note,'state'=>$state,'contact_action_center'=>$state['contact_action_center'] ?? null]), 'CRM note added.', 201);
    }

    if ($action === 'contact_review_draft') {
        if (!$selectedContact) mg_fail('Select a CRM contact before preparing a review item.', 422);
        mg_merchant_agent_require_owner_permission($user, 'merchant.ai.review');
        mg_agent_autonomy_require_for_merchant($pdo, $actorId, 'review_queue', 'Contact Action Center review item creation');
        if (strtolower(trim((string)($input['draft_kind'] ?? ''))) === 'message') {
            mg_agent_autonomy_require_for_merchant($pdo, $actorId, 'messages', 'Contact Action Center message draft creation');
        }
        mg_agent_admin_limit_enforce_default($pdo, $actorId);
        $draft = mg_merchant_contact_workspace_create_review_draft($pdo, $user, $merchantOwnerId, $actorId, $threadId, $selectedContact, $input);
        $state = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, mg_ai_chat_public_state($pdo, $actorId), $input);
        mg_ok(mg_agent_chat_access_response($pdo, $user, $packageContext, ['draft'=>$draft,'state'=>$state,'contact_action_center'=>$state['contact_action_center'] ?? null]), !empty($draft['duplicate']) ? 'This draft is already in Agent Review.' : 'Contact draft added to Agent Review.', 201);
    }

    if ($action === 'snapshot' || ($action === 'send_message' && mg_merchant_snapshot_is_keyword($input['message'] ?? ''))) {
        mg_merchant_agent_require_owner_permission($user, 'merchant.campaigns.view');
        $input['message'] = trim((string)($input['message'] ?? 'snapshot')) ?: 'snapshot';
        $response = mg_merchant_snapshot_chat_response($pdo, $user, $input);
        if (is_array($response['state'] ?? null)) $response['state'] = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, $response['state'], $input);
        mg_ok(mg_agent_chat_access_response($pdo, $user, $packageContext, $response), 'Current merchant database snapshot generated.', 201);
    }

    if ($action === 'save_agent_profile') {
        $profile = mg_agent_save_profile($pdo, $actorId, $input);
        $state = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, mg_ai_chat_public_state($pdo, $actorId), $input);
        mg_ok(mg_agent_chat_access_response($pdo, $user, $packageContext, ['agent_profile'=>$profile,'state'=>$state]), 'Agent profile saved.');
    }

    if ($action === 'save_memory_profile') {
        $memory = mg_agent_memory_profile_save($pdo, $actorId, $actorId, $input);
        $state = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, mg_ai_chat_public_state($pdo, $actorId), $input);
        $state['memory'] = mg_agent_memory_summary($pdo, $actorId);
        $state['memory_sources'] = mg_agent_memory_sources($pdo, $actorId, 30);
        mg_ok(mg_agent_chat_access_response($pdo, $user, $packageContext, ['memory'=>$memory,'state'=>$state]), 'Merchant memory saved.');
    }

    if ($action === 'create_thread') {
        $thread = mg_agent_create_thread($pdo, $actorId, ['title'=>$input['title'] ?? 'Current chat'], true);
        $state = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, mg_ai_chat_public_state($pdo, $actorId));
        mg_ok(mg_agent_chat_access_response($pdo, $user, $packageContext, ['active_thread'=>$thread,'state'=>$state]), 'New agent chat created.');
    }

    if (in_array($action, ['save_thread','archive_thread','clear_thread','rename_thread','load_thread','delete_thread'], true)) {
        $requestedThreadId = mg_ai_chat_clean($input['thread_id'] ?? '', 80);
        $targetThread = mg_agent_thread_by_id($pdo, $actorId, $requestedThreadId);
        $targetThreadId = (string)($targetThread['id'] ?? '');
        if ($action === 'delete_thread') {
            $response = mg_merchant_agent_delete_thread($pdo, $actorId, $targetThreadId);
            if (is_array($response['state'] ?? null)) $response['state'] = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, $response['state']);
            mg_ok(mg_agent_chat_access_response($pdo, $user, $packageContext, $response), 'Merchant Agent chat deleted.');
        }
        if ($action === 'load_thread') {
            if ($targetThreadId !== '' && mg_agent_table_exists($pdo, 'merchant_agent_threads')) {
                $pdo->prepare("UPDATE merchant_agent_threads SET status='active',archived_at=NULL,updated_at=NOW() WHERE merchant_user_id=? AND public_id=?")->execute([$actorId, $targetThreadId]);
            }
        } else {
            $map = ['save_thread'=>'save','archive_thread'=>'archive','clear_thread'=>'clear','rename_thread'=>'rename'];
            mg_agent_thread_action($pdo, $actorId, $targetThreadId, $map[$action], $input);
            if ($action === 'clear_thread' && $targetThreadId !== '') mg_merchant_contact_action_center_record_selection($pdo, $actorId, $targetThreadId, null);
        }
        $state = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, mg_ai_chat_public_state($pdo, $actorId));
        mg_ok(mg_agent_chat_access_response($pdo, $user, $packageContext, ['state'=>$state]), 'Agent thread updated.');
    }

    if ($action === 'contact_action') {
        if (!$selectedContact) mg_fail('Select a CRM contact before running a contact action.', 422);
        $contactAction = strtolower(trim((string)($input['contact_action'] ?? $input['action_key'] ?? '')));
        $prompt = mg_merchant_contact_action_center_prompt($contactAction, (string)($selectedContact['mention'] ?? ''));
        $input['message'] = $prompt['message'];
        $input['scope'] = 'crm';
        $input['mode'] = 'review';
        $input['output_type'] = $prompt['output_type'];
        $input['approval_mode'] = $prompt['approval_mode'];
        $input['selected_contact_id'] = (string)$selectedContact['id'];
        $input['selected_contact_mention'] = (string)($selectedContact['mention'] ?? '');
    }

    $approvalMode = strtolower(trim((string)($input['approval_mode'] ?? 'advisory')));
    $outputType = strtolower(trim((string)($input['output_type'] ?? 'action_plan')));
    $agentMode = strtolower(trim((string)($input['mode'] ?? 'advisor')));
    $adminOperator = $approvalMode === 'admin_operator';

    if ($adminOperator) {
        if (!mg_agent_chat_admin_operator($user)) mg_fail('Admin operator mode is not available for this account.', 403);
        $input['mode'] = 'execute_plan';
        $input['approval_mode'] = 'review_queue';
        $input['admin_operator'] = true;
        $input['admin_autonomy_override'] = true;
        $agentMode = 'execute_plan';
        $approvalMode = 'review_queue';
    }

    if (!$adminOperator && ($approvalMode === 'review_queue' || $outputType === 'admin_recommendation')) {
        mg_agent_autonomy_require_for_merchant($pdo, $actorId, 'review_queue', 'Agent Review queue card creation');
    }
    if (!$adminOperator && $outputType === 'message_draft') {
        mg_agent_autonomy_require_for_merchant($pdo, $actorId, 'messages', 'agent message draft creation');
    }
    if (!$adminOperator && $agentMode === 'execute_plan') {
        mg_agent_autonomy_require_for_merchant($pdo, $actorId, 'review_queue', 'plan preparation');
    }
    mg_agent_admin_limit_enforce_default($pdo, $actorId);

    $sourceType = ($contactAware || $action === 'contact_action') ? 'merchant_agent_crm_contact_chat' : 'merchant_agent_chat';
    mg_merchant_agent_ai_begin_call($pdo, $user, $packageContext, $sourceType, [
        'action'=>$action,
        'thread_id'=>$threadId,
        'contact_aware'=>$contactAware || $action === 'contact_action',
        'merchant_owner_id'=>$actorId,
    ]);
    try {
        $response = ($contactAware || $action === 'contact_action')
            ? mg_merchant_agent_crm_contact_chat_response($pdo, $user, $input)
            : mg_ai_chat_send_with_memory($pdo, $user, $input);
    } finally {
        mg_merchant_agent_ai_end_call();
    }
    if (is_array($response['state'] ?? null)) $response['state'] = mg_agent_chat_contact_state($pdo, $merchantOwnerId, $actorId, $response['state'], $input);
    $response = mg_agent_chat_access_response($pdo, $user, $packageContext, $response);
    mg_ok($response, $adminOperator ? 'Admin operator agent plan created.' : (($contactAware || $action === 'contact_action') ? 'Contact-aware Merchant Agent reply created.' : 'Merchant agent reply created.'), 201);
}

mg_fail('Method not allowed.', 405);