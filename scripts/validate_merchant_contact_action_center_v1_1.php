<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'service'=>$root . '/includes/ai/merchant-agent-contact-workspace-v1-1.php',
    'api'=>$root . '/api/ai/merchant-agent-chat.php',
    'view'=>$root . '/includes/merchant-agent-chat-view.php',
    'page'=>$root . '/merchant-agent-chat.php',
    'js'=>$root . '/assets/js/merchant-agent-contact-workspace-v1-1.js',
    'css'=>$root . '/assets/css/merchant-agent-contact-workspace-v1-1.css',
    'docs'=>$root . '/docs/merchant-contact-action-center-v1-1.md',
];
$content = [];
foreach ($paths as $key=>$path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
    $content[$key] = (string)file_get_contents($path);
}

$cssPosition = strpos($content['page'], 'merchant-agent-contact-workspace-v1-1.css?v=1.1.0');
$baseCssPosition = strpos($content['page'], 'merchant-agent-contact-action-center.css?v=1.0.0');
$jsPosition = strpos($content['page'], 'merchant-agent-contact-workspace-v1-1.js?v=1.1.0');
$baseJsPosition = strpos($content['page'], 'merchant-agent-contact-action-center.js?v=1.0.0');
$chatJsPosition = strpos($content['page'], 'merchant-agent-chat.js?v=2.4.0');

$checks = [
    'workspace service extends the selected contact contract' =>
        str_contains($content['service'], "require_once __DIR__ . '/merchant-agent-contact-action-center.php'")
        && str_contains($content['service'], 'MG_MERCHANT_CONTACT_WORKSPACE_VERSION')
        && str_contains($content['service'], 'mg_merchant_contact_workspace_attach_state'),
    'contact writes remain scoped to the merchant owner and canonical CRM row' =>
        str_contains($content['service'], 'FROM merchant_crm_contacts WHERE merchant_user_id=? AND public_id=?')
        && str_contains($content['service'], 'merged_into_contact_id IS NULL')
        && str_contains($content['service'], 'mg_merchant_contact_workspace_contact_row'),
    'internal notes use the existing CRM note table and authenticated author' =>
        str_contains($content['service'], 'INSERT INTO merchant_crm_notes')
        && str_contains($content['service'], 'author_user_id')
        && str_contains($content['service'], 'merchant_internal')
        && str_contains($content['service'], 'mg_merchant_crm_record_event'),
    'note action records a CRM event and audit without invoking Claude' =>
        str_contains($content['service'], "'crm.note.added'")
        && str_contains($content['service'], "mg_audit('merchant.agent.contact_note_added'")
        && !str_contains($content['service'], 'mg_anthropic_'),
    'review draft supports deterministic message and follow-up action keys' =>
        str_contains($content['service'], "'create_message_draft'")
        && str_contains($content['service'], "'create_crm_followup_task'")
        && str_contains($content['service'], "'draft_kind'=>'message'")
        && str_contains($content['service'], "'draft_kind'=>'followup'"),
    'message channels are constrained to the approved editor set' =>
        str_contains($content['service'], "['email','sms','crm_message','social_dm']")
        && str_contains($content['view'], 'data-contact-draft-channel="email"')
        && str_contains($content['view'], 'data-contact-draft-channel="social_dm"'),
    'follow-up task types and priorities are server validated' =>
        str_contains($content['service'], "['call','email','reward_reminder','campaign_invite','customer_service']")
        && str_contains($content['service'], "['low','medium','high']")
        && str_contains($content['service'], 'Choose a valid follow-up due date.'),
    'drafts are idempotent inside the actor-owned Agent Review queue' =>
        str_contains($content['service'], "JSON_UNQUOTE(JSON_EXTRACT(i.suggested_payload_json,'$.idempotency_key'))=?")
        && str_contains($content['service'], 'p.merchant_user_id=?')
        && str_contains($content['js'], "newKey('contact-followup')")
        && str_contains($content['js'], "newKey('contact-message')"),
    'review items reuse the existing chat-card bridge and queue' =>
        str_contains($content['service'], 'mg_ai_chat_record_message')
        && str_contains($content['service'], 'mg_ai_chat_bridge_to_review')
        && str_contains($content['service'], '/merchant-agent-approvals.php'),
    'draft payloads explicitly prohibit direct message and task execution' =>
        str_contains($content['service'], "'send_directly'=>false")
        && str_contains($content['service'], "'create_directly'=>false")
        && str_contains($content['service'], "'approval_required'=>true"),
    'review status is scoped by actor and selected contact' =>
        str_contains($content['service'], 'mg_merchant_contact_workspace_review_status')
        && str_contains($content['service'], "JSON_UNQUOTE(JSON_EXTRACT(i.suggested_payload_json,'$.crm_contact_id'))=?")
        && str_contains($content['service'], "JSON_UNQUOTE(JSON_EXTRACT(i.suggested_payload_json,'$.source'))='merchant_contact_action_center_v1_1'"),
    'review status exposes waiting approved rejected deferred failed and executed states' =>
        str_contains($content['service'], "'executed'=>'Executed'")
        && str_contains($content['service'], "'approved'=>'Approved'")
        && str_contains($content['service'], "'rejected'=>'Rejected'")
        && str_contains($content['service'], "'deferred'=>'Deferred'")
        && str_contains($content['service'], "'failed'=>'Failed'"),
    'API attaches v1.1 after the v1 selected-contact state' =>
        str_contains($content['api'], 'mg_merchant_contact_action_center_attach_state')
        && str_contains($content['api'], 'mg_merchant_contact_workspace_attach_state')
        && strpos($content['api'], 'mg_merchant_contact_action_center_attach_state') < strpos($content['api'], 'mg_merchant_contact_workspace_attach_state'),
    'API exposes only scoped note and review-draft workspace actions' =>
        str_contains($content['api'], "'contact_note'")
        && str_contains($content['api'], "'contact_review_draft'")
        && str_contains($content['api'], 'mg_merchant_contact_workspace_add_note')
        && str_contains($content['api'], 'mg_merchant_contact_workspace_create_review_draft'),
    'note action requires merchant CRM management permission' =>
        str_contains($content['api'], "'contact_note' => 'merchant.campaigns.manage'")
        && str_contains($content['api'], "mg_merchant_require_permission('merchant.campaigns.view')"),
    'review drafts require plan review CRM and autonomy boundaries' =>
        str_contains($content['api'], "'contact_review_draft' => 'merchant.ai.plan'")
        && str_contains($content['api'], "mg_merchant_require_permission('merchant.ai.review')")
        && str_contains($content['api'], 'mg_agent_autonomy_require_for_merchant($pdo, $actorId, \'review_queue\'')
        && str_contains($content['api'], 'mg_agent_autonomy_require_for_merchant($pdo, $actorId, \'messages\'')
        && str_contains($content['api'], 'mg_agent_admin_limit_enforce_default'),
    'all workspace writes remain behind CSRF validation' =>
        str_contains($content['api'], 'mg_require_csrf_for_write($input)')
        && strpos($content['api'], 'mg_require_csrf_for_write($input)') < strpos($content['api'], 'if ($action === \'contact_note\')'),
    'workspace presents timeline notes follow-up draft and review tabs' =>
        str_contains($content['view'], 'data-contact-workspace-tab="timeline"')
        && str_contains($content['view'], 'data-contact-workspace-tab="notes"')
        && str_contains($content['view'], 'data-contact-workspace-tab="followup"')
        && str_contains($content['view'], 'data-contact-workspace-tab="draft"')
        && str_contains($content['view'], 'data-contact-workspace-tab="review"'),
    'timeline includes all requested filters' =>
        str_contains($content['view'], 'data-contact-timeline-filter="purchases"')
        && str_contains($content['view'], 'data-contact-timeline-filter="rewards"')
        && str_contains($content['view'], 'data-contact-timeline-filter="messages"')
        && str_contains($content['view'], 'data-contact-timeline-filter="campaigns"')
        && str_contains($content['view'], 'data-contact-timeline-filter="tasks_notes"'),
    'workspace controls do not introduce a nested HTML form' =>
        substr_count($content['view'], '<form') === 1
        && str_contains($content['view'], 'data-contact-note-save')
        && str_contains($content['view'], 'data-contact-followup-review')
        && str_contains($content['view'], 'data-contact-draft-review'),
    'browser runtime posts through the protected Merchant Agent endpoint only' =>
        substr_count($content['js'], "Microgifter.post('/api/ai/merchant-agent-chat.php'") === 3
        && !str_contains($content['js'], '/api/merchant/crm-message.php')
        && !str_contains($content['js'], '/api/merchant/crm-followup.php')
        && !str_contains($content['js'], 'send_customer_message'),
    'browser runtime supports tab filter channel and state rehydration' =>
        str_contains($content['js'], 'function setTab')
        && str_contains($content['js'], 'function setFilter')
        && str_contains($content['js'], 'activityCategory')
        && str_contains($content['js'], "document.addEventListener('mg:merchant-agent:state'")
        && str_contains($content['js'], "document.addEventListener('mg:merchant-agent:apply-state'"),
    'workspace assets load after v1 and before the main chat runtime' =>
        $cssPosition !== false && $baseCssPosition !== false && $baseCssPosition < $cssPosition
        && $jsPosition !== false && $baseJsPosition !== false && $chatJsPosition !== false
        && $baseJsPosition < $jsPosition && $jsPosition < $chatJsPosition,
    'responsive styles preserve desktop and mobile workspace layouts' =>
        str_contains($content['css'], '@media(max-width:820px)')
        && str_contains($content['css'], '@media(max-width:560px)')
        && str_contains($content['css'], '.mg-contact-followup-fields')
        && str_contains($content['css'], '.mg-contact-review-state'),
    'documentation states review-first and no schema migration' =>
        str_contains($content['docs'], 'does not create a task directly')
        && str_contains($content['docs'], 'The draft is not sent')
        && str_contains($content['docs'], 'No SQL migration is required.'),
    'feature introduces no schema mutation' =>
        !str_contains($content['service'], 'CREATE TABLE')
        && !str_contains($content['service'], 'ALTER TABLE')
        && !str_contains($content['api'], 'CREATE TABLE')
        && !str_contains($content['api'], 'ALTER TABLE'),
];

$failed = [];
foreach ($checks as $name=>$passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}
if ($failed !== []) {
    fwrite(STDERR, "\nMerchant Contact Action Center v1.1 validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}
$total = count($checks);
echo "\nMerchant Contact Action Center v1.1 contract: {$total}/{$total}.\n";
