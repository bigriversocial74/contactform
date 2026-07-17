<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    if (!is_file($path)) throw new RuntimeException('Missing required file: ' . $relative);
    $content = file_get_contents($path);
    if (!is_string($content) || trim($content) === '') throw new RuntimeException('Empty required file: ' . $relative);
    return $content;
};

$expect = static function (bool $condition, string $label) use (&$failures, &$passes): void {
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if ($condition) $passes++; else $failures[] = $label;
};

$slice = static function (string $content, string $start, string $end): string {
    $startAt = strpos($content, $start);
    if ($startAt === false) return '';
    $endAt = strpos($content, $end, $startAt + strlen($start));
    if ($endAt === false) return substr($content, $startAt);
    return substr($content, $startAt, $endAt - $startAt);
};

try {
    $page = $read('merchant-agent-chat.php');
    $view = $read('includes/merchant-agent-chat-view.php');
    $api = $read('api/ai/merchant-agent-chat.php');
    $service = $read('includes/ai/merchant-agent-contact-action-center.php');
    $context = $read('includes/ai/merchant-agent-crm-contact-context.php');
    $chat = $read('includes/ai/merchant-agent-crm-contact-chat.php');
    $delete = $read('includes/ai/merchant-agent-thread-delete.php');
    $runtime = $read('assets/js/merchant-agent-contact-action-center.js');
    $bridge = $read('assets/js/merchant-agent-contact-action-center-select-bridge.js');
    $css = $read('assets/css/merchant-agent-contact-action-center.css');
    $docs = $read('docs/merchant-contact-action-center-v1.md');

    $assetOrder = [
        strpos($page, 'merchant-agent-crm-mention-search.js?v=1.1.0'),
        strpos($page, 'merchant-agent-contact-action-center.js?v=1.0.0'),
        strpos($page, 'merchant-agent-contact-action-center-select-bridge.js?v=1.0.0'),
        strpos($page, 'merchant-agent-chat.js?v=2.4.0'),
    ];
    $publicContact = $slice($service, "'contact'=>[", "'metrics'=>[");
    $promptSanitizer = $slice($chat, 'function mg_merchant_agent_contact_action_prompt_context', 'function mg_merchant_agent_crm_contact_chat_response');

    $checks = [
        'Contact Action Center assets load in the required transport order' =>
            !in_array(false, $assetOrder, true) && $assetOrder === array_values($assetOrder) && $assetOrder[0] < $assetOrder[1] && $assetOrder[1] < $assetOrder[2] && $assetOrder[2] < $assetOrder[3]
            && str_contains($page, 'merchant-agent-contact-action-center.css?v=1.0.0'),
        'Composer contains a persistent selected-contact chip and expandable action center' =>
            str_contains($view, 'data-merchant-contact-action-center')
            && str_contains($view, 'data-contact-center-toggle')
            && str_contains($view, 'data-contact-center-body')
            && str_contains($view, 'data-contact-center-clear'),
        'Selected public contact ID and mention are carried through hidden form state' =>
            str_contains($view, 'data-contact-center-id') && str_contains($view, 'data-contact-center-mention'),
        'Action Center includes metrics activity campaign follow-up and navigation surfaces' =>
            str_contains($view, 'data-contact-center-metrics')
            && str_contains($view, 'data-contact-center-activity')
            && str_contains($view, 'data-contact-center-followups')
            && str_contains($view, 'data-contact-center-profile')
            && str_contains($view, 'data-contact-center-timeline'),
        'Server contract is explicitly versioned' =>
            str_contains($service, 'MG_MERCHANT_CONTACT_ACTION_CENTER_VERSION = 1')
            && str_contains($service, "'contract_version'=>MG_MERCHANT_CONTACT_ACTION_CENTER_VERSION"),
        'Thread selection lookup uses validated JSON and exact thread identity' =>
            str_contains($service, 'JSON_VALID(event_context_json)=1')
            && str_contains($service, "JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.thread_public_id'))=?"),
        'Selection persistence uses dedicated selected and cleared event types' =>
            str_contains($service, 'merchant.agent_chat.contact_selected')
            && str_contains($service, 'merchant.agent_chat.contact_cleared')
            && str_contains($service, "'source'=>'merchant_contact_action_center'"),
        'Selection audit uses the strict four-argument audit contract' =>
            str_contains($service, "'merchant_crm_contact'")
            && str_contains($service, "['thread_id'=>\$threadId,'crm_contact_id'=>")
            && str_contains($service, '$actorId'),
        'Canonical and campaign contact reads remain merchant-owner scoped' =>
            substr_count($service, 'merchant_user_id=?') >= 7
            && str_contains($service, 'mg_merchant_crm_search_contacts_by_ids($pdo, $merchantOwnerId'),
        'Public contact summary excludes email phone and database IDs' =>
            $publicContact !== ''
            && !str_contains($publicContact, 'primary_email')
            && !str_contains($publicContact, 'primary_phone')
            && !str_contains($publicContact, 'database_id')
            && str_contains($publicContact, "'mention'=>")
            && str_contains($publicContact, "'name'=>"),
        'Contact metrics cover purchases rewards campaigns messages notes and follow-ups' =>
            str_contains($service, "'purchase_value_cents'")
            && str_contains($service, "'rewards_issued'")
            && str_contains($service, "'rewards_claimed'")
            && str_contains($service, "'rewards_redeemed'")
            && str_contains($service, "'messages'")
            && str_contains($service, "'notes'")
            && str_contains($service, "'open_followups'"),
        'Recent activity normalizes CRM events messages notes and follow-up tasks' =>
            str_contains($service, 'function mg_merchant_contact_action_center_activity')
            && str_contains($service, "'type'=>'message'")
            && str_contains($service, "'type'=>'note'")
            && str_contains($service, "'type'=>'followup'"),
        'Message and follow-up aggregation avoids consuming result cursors twice' =>
            str_contains($service, '$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);')
            && str_contains($service, "return ['count'=>count(\$rows)")
            && str_contains($service, '$itemsById'),
        'Direct send and direct reward capabilities are explicitly disabled' =>
            str_contains($service, "'send_directly'=>false")
            && str_contains($service, "'issue_reward_directly'=>false"),
        'All five Contact Action Center quick actions are declared server-side' =>
            str_contains($service, "'summarize_activity'")
            && str_contains($service, "'draft_followup'")
            && str_contains($service, "'recommend_reward'")
            && str_contains($service, "'draft_campaign_invite'")
            && str_contains($service, "'create_followup_task'"),
        'API exposes select clear and review-ready contact actions' =>
            str_contains($api, "'select_contact'")
            && str_contains($api, "'clear_contact'")
            && str_contains($api, "'contact_action'")
            && str_contains($api, 'mg_merchant_contact_action_center_prompt'),
        'Contact reads and actions retain campaign and AI permission boundaries' =>
            str_contains($api, "mg_merchant_require_permission('merchant.campaigns.view')")
            && str_contains($api, "'merchant.ai.plan'")
            && str_contains($api, "'merchant.ai.review'"),
        'Exact current-prompt mention replaces persisted selected contact' =>
            str_contains($api, '$hasExplicitMention')
            && str_contains($api, 'unset($input[\'selected_contact_id\']')
            && str_contains($chat, 'An exact @username in the current prompt replaces')
            && str_contains($chat, '$hasExplicitMention = mg_merchant_agent_crm_has_mentions($message)'),
        'Follow-up prompts can use an explicit persisted contact ID without another mention' =>
            str_contains($context, 'mg_merchant_agent_crm_explicit_contact_ids')
            && str_contains($context, "'selected_contact_id'")
            && str_contains($context, "'explicit_contact_count'"),
        'Clearing a thread also clears its selected contact' =>
            str_contains($api, "\$action === 'clear_thread'")
            && str_contains($api, 'mg_merchant_contact_action_center_record_selection($pdo, $actorId, $targetThreadId, null)'),
        'Deleting a thread deletes both contact selection event types' =>
            str_contains($delete, 'merchant.agent_chat.contact_selected')
            && str_contains($delete, 'merchant.agent_chat.contact_cleared')
            && str_contains($delete, 'JSON_VALID(event_context_json)=1'),
        'Claude receives the complete sanitized selected-contact action snapshot' =>
            str_contains($chat, "'selected_contact_action_center'=>mg_merchant_agent_contact_action_prompt_context")
            && str_contains($chat, 'recent_messages')
            && str_contains($chat, 'recent_notes')
            && str_contains($chat, 'followup_tasks'),
        'Prompt sanitizer removes identifiers and action URLs before model use' =>
            str_contains($promptSanitizer, "unset(\$contact['id'])")
            && str_contains($promptSanitizer, "unset(\$item['id'], \$item['action_url'], \$item['thread_url'], \$item['campaign_id'])"),
        'Review cards receive the selected contact reference only after model output' =>
            str_contains($chat, 'mg_merchant_agent_crm_contact_cards($cards')
            && str_contains($chat, "\$payload['crm_contact_id']")
            && str_contains($chat, "\$payload['approval_required'] = true"),
        'Review-mode contact outputs keep existing autonomy and auto-bridge enforcement' =>
            str_contains($api, "mg_agent_autonomy_require_for_merchant(\$pdo, \$actorId, 'review_queue'")
            && str_contains($api, "mg_agent_autonomy_require_for_merchant(\$pdo, \$actorId, 'messages'")
            && str_contains($chat, "if (\$approvalMode === 'review_queue') mg_ai_chat_auto_bridge_cards"),
        'Browser transport injects only the active selected contact into Agent sends' =>
            str_contains($runtime, 'request.selected_contact_id')
            && str_contains($runtime, "request.action === 'send_message'")
            && str_contains($runtime, "url.indexOf('/api/ai/merchant-agent-chat.php')"),
        'CRM result selection activates the Contact Action Center without replacing existing mention insertion' =>
            str_contains($bridge, '[data-agent-crm-select-contact]')
            && str_contains($bridge, 'MicrogifterMerchantContactActionCenter.select')
            && !str_contains($bridge, 'stopPropagation'),
        'Quick actions submit through the existing Merchant Agent chat form' =>
            str_contains($runtime, 'form.requestSubmit()')
            && str_contains($runtime, "approval: 'review_queue'")
            && str_contains($runtime, "scope.value = 'crm'"),
        'Contact Action Center is responsive and preserves a compact collapsed chip' =>
            str_contains($css, '.mg-merchant-contact-center-toggle')
            && str_contains($css, '@media(max-width:820px)')
            && str_contains($css, '@media(max-width:560px)'),
        'New feature layers add no schema mutation authority' =>
            !str_contains($service, 'CREATE TABLE')
            && !str_contains($service, 'ALTER TABLE')
            && !str_contains($api, 'CREATE TABLE')
            && !str_contains($api, 'ALTER TABLE'),
        'Documentation records persistence privacy approval and no-SQL boundaries' =>
            str_contains($docs, 'persistent working context')
            && str_contains($docs, 'email addresses, phone numbers')
            && str_contains($docs, 'Agent Review queue')
            && str_contains($docs, 'No SQL required'),
    ];

    foreach ($checks as $label => $condition) $expect((bool)$condition, $label);
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo '[FAIL] ' . $error->getMessage() . PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Merchant Contact Action Center v1 validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, ' - ' . $failure . PHP_EOL);
    exit(1);
}

echo 'Merchant Contact Action Center v1 contract passed: ' . $passes . " checks.\n";
