<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'adapter'=>$root . '/includes/ai/merchant-contact-workspace-review-actions.php',
    'endpoint'=>$root . '/api/merchant/agent-approval-action.php',
    'followups'=>$root . '/api/merchant/crm-followup-tasks.php',
    'messages'=>$root . '/includes/merchant-agent-messages.php',
];
$content = [];
foreach ($paths as $key=>$path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
    $content[$key] = (string)file_get_contents($path);
}

$contactAdapterPosition = strpos($content['endpoint'], 'mg_contact_workspace_review_is_payload');
$genericAdapterPosition = strpos($content['endpoint'], 'mg_ai_plan_review_item');
$checks = [
    'approval endpoint loads the Contact Action Center adapter' =>
        str_contains($content['endpoint'], 'merchant-contact-workspace-review-actions.php'),
    'workspace owner is resolved separately from the authenticated actor' =>
        str_contains($content['endpoint'], '$workspace = mg_merchant_ensure_workspace')
        && str_contains($content['endpoint'], '$merchantOwnerId')
        && str_contains($content['endpoint'], '\'_merchant_owner_id\'=>$merchantOwnerId'),
    'Contact Action Center payloads route before generic execution' =>
        $contactAdapterPosition !== false && $genericAdapterPosition !== false && $contactAdapterPosition < $genericAdapterPosition,
    'adapter accepts only v1.1 message and follow-up payloads' =>
        str_contains($content['adapter'], "'merchant_contact_action_center_v1_1'")
        && str_contains($content['adapter'], "['message','followup']")
        && str_contains($content['adapter'], 'mg_merchant_contact_action_center_uuid'),
    'approval revalidates canonical contact ownership under a row lock' =>
        str_contains($content['adapter'], 'FROM merchant_crm_contacts WHERE merchant_user_id=? AND public_id=?')
        && str_contains($content['adapter'], 'merged_into_contact_id IS NULL')
        && str_contains($content['adapter'], 'FOR UPDATE'),
    'campaign contact resolution remains workspace scoped' =>
        str_contains($content['adapter'], 'FROM campaign_contacts cc')
        && str_contains($content['adapter'], 'cc.merchant_user_id=?')
        && str_contains($content['adapter'], 'cc.user_id=?')
        && str_contains($content['adapter'], 'LOWER(cc.email)=?'),
    'approved follow-up creates the canonical task shape' =>
        str_contains($content['adapter'], "'crm.followup.created'")
        && str_contains($content['adapter'], '\'note\'=>$note')
        && str_contains($content['adapter'], '\'due_at\'=>$dueAt')
        && str_contains($content['adapter'], "'status'=>'open'")
        && str_contains($content['adapter'], '\'task_type\'=>$taskType')
        && str_contains($content['adapter'], '\'priority\'=>$priority'),
    'follow-up output matches the current task loader' =>
        str_contains($content['followups'], "ce.event_type='crm.followup.created'")
        && str_contains($content['followups'], "JSON_EXTRACT(ce.event_context_json,'$.due_at')")
        && str_contains($content['adapter'], 'contact_id,event_type,event_context_json'),
    'approved message creates the Agent Messages draft shape' =>
        str_contains($content['adapter'], "'crm.agent.message.draft.created'")
        && str_contains($content['adapter'], '\'message_draft_id\'=>$messageDraftId')
        && str_contains($content['adapter'], '\'draft_body\'=>$body')
        && str_contains($content['adapter'], '\'message_body\'=>$body')
        && str_contains($content['adapter'], "'send_directly'=>false"),
    'message output matches the Agent Messages outbox' =>
        str_contains($content['messages'], "'crm.agent.message.draft.created'")
        && str_contains($content['messages'], "['draft_body']")
        && str_contains($content['messages'], "['message_draft_id']"),
    'both approved resource types enter the canonical CRM timeline' =>
        substr_count($content['adapter'], 'mg_merchant_crm_record_event') >= 2
        && str_contains($content['adapter'], "'source_type'=>'merchant_contact_action_center_v1_1'"),
    'approval is transactional and updates the plan after resource creation' =>
        str_contains($content['adapter'], '$pdo->beginTransaction()')
        && str_contains($content['adapter'], "UPDATE ai_merchant_plan_items SET status='executed'")
        && str_contains($content['adapter'], 'mg_ai_plan_update_parent_status')
        && str_contains($content['adapter'], '$pdo->commit()')
        && str_contains($content['adapter'], '$pdo->rollBack()'),
    'approved message remains an editable unsent draft' =>
        str_contains($content['adapter'], 'Sending still requires the Agent Messages send action')
        && str_contains($content['adapter'], "'send_directly'=>false"),
    'approval events retain actor and owner boundaries' =>
        str_contains($content['adapter'], '\'merchant_owner_id\'=>$merchantOwnerId')
        && str_contains($content['adapter'], '\'decided_by_user_id\'=>$actorId')
        && str_contains($content['adapter'], "mg_audit('merchant.contact_workspace_review_approved'"),
    'reject and defer continue through the established plan review path' =>
        str_contains($content['endpoint'], "'reject' => 'reject'")
        && str_contains($content['endpoint'], "'defer' => 'defer'")
        && str_contains($content['endpoint'], 'mg_ai_plan_review_item'),
    'adapter introduces no schema mutation' =>
        !str_contains($content['adapter'], 'CREATE TABLE')
        && !str_contains($content['adapter'], 'ALTER TABLE'),
];

$failed = [];
foreach ($checks as $name=>$passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}
if ($failed !== []) {
    fwrite(STDERR, "\nContact workspace approval adapter validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}
echo "\nContact workspace approval adapter contract: " . count($checks) . '/' . count($checks) . ".\n";
