<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'search_helper' => $root . '/includes/merchant-crm-search.php',
    'search_api' => $root . '/api/merchant/crm-search.php',
    'agent_helper' => $root . '/includes/ai/merchant-agent-crm-search.php',
    'agent_api' => $root . '/api/ai/merchant-agent-chat.php',
    'page' => $root . '/merchant-agent-chat.php',
    'mention_js' => $root . '/assets/js/merchant-agent-crm-mention-search.js',
    'crm_search_js' => $root . '/assets/js/merchant-crm-directory.js',
    'css' => $root . '/assets/css/merchant-agent-crm-mention-search.css',
];
$content = [];
foreach ($paths as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
    $content[$key] = (string)file_get_contents($path);
}

$crmRoutePosition = strpos($content['agent_api'], "if (\$action === 'crm_search')");
$aiLimitPosition = strpos($content['agent_api'], 'mg_agent_admin_limit_enforce_default');
$pageMentionPosition = strpos($content['page'], '/assets/js/merchant-agent-crm-mention-search.js?v=1.0.0');
$pageChatPosition = strpos($content['page'], '/assets/js/merchant-agent-chat.js?v=2.3.0');

$checks = [
    'search uses the canonical merchant CRM contact store' =>
        str_contains($content['search_helper'], 'FROM merchant_crm_contacts mc')
        && str_contains($content['search_helper'], 'mc.merchant_user_id=?')
        && str_contains($content['search_helper'], 'merged_into_contact_id IS NULL'),
    'real profile slugs become CRM mention usernames with a stable fallback' =>
        str_contains($content['search_helper'], 'pp.slug profile_slug')
        && str_contains($content['search_helper'], "return 'crm-' . substr(\$publicId, 0, 10)")
        && str_contains($content['search_helper'], "'mention'=>'@' . \$handle"),
    'search covers names usernames email phone stage status campaign source and public id' =>
        str_contains($content['search_helper'], "COALESCE(mc.display_name,'')")
        && str_contains($content['search_helper'], "COALESCE(mc.primary_email,'')")
        && str_contains($content['search_helper'], "COALESCE(mc.primary_phone,'')")
        && str_contains($content['search_helper'], "COALESCE(mc.lifecycle_stage,'')")
        && str_contains($content['search_helper'], "COALESCE(mc.crm_status,'')")
        && str_contains($content['search_helper'], "COALESCE(mc.last_campaign_type,'')")
        && str_contains($content['search_helper'], "COALESCE(mc.last_source_type,'')")
        && str_contains($content['search_helper'], "COALESCE(pp.slug,'')"),
    'autocomplete endpoint separates acting user from owning merchant workspace' =>
        str_contains($content['search_api'], "mg_merchant_require_permission('merchant.ai.review')")
        && str_contains($content['search_api'], "mg_merchant_require_permission('merchant.campaigns.view')")
        && str_contains($content['search_api'], "\$workspace['merchant_user_id']")
        && str_contains($content['search_api'], "'user:' . \$actorId"),
    'search auditing stores lengths and counts without recording raw search text' =>
        str_contains($content['search_api'], "mg_audit('merchant.agent_crm_search.read', 'merchant_crm'")
        && str_contains($content['search_api'], "'query_length'=>mb_strlen(\$query)")
        && !str_contains($content['search_api'], "'query'=>\$query"),
    'standalone at-mentions route to deterministic CRM search before any AI request' =>
        str_contains($content['agent_helper'], "preg_match('/^@[a-z0-9][a-z0-9._-]{0,119}$/i'")
        && str_contains($content['agent_api'], "\$action = 'crm_search'")
        && $crmRoutePosition !== false
        && $aiLimitPosition !== false
        && $crmRoutePosition < $aiLimitPosition
        && !str_contains($content['agent_helper'], 'mg_anthropic_'),
    'CRM lookup persists both sides of the chat thread for later rehydration' =>
        substr_count($content['agent_helper'], 'mg_ai_chat_record_message') >= 2
        && str_contains($content['agent_helper'], "'output_type'=>'crm_results'")
        && str_contains($content['agent_helper'], "'thread_public_id'=>\$threadId")
        && str_contains($content['mention_js'], 'hydrateHistory')
        && str_contains($content['mention_js'], 'new MutationObserver'),
    'autocomplete is debounced accessible and inserts the complete CRM mention' =>
        str_contains($content['mention_js'], "setAttribute('role', 'listbox')")
        && str_contains($content['mention_js'], 'window.setTimeout')
        && str_contains($content['mention_js'], "'/api/merchant/crm-search.php?q='")
        && str_contains($content['mention_js'], 'replaceMention(contact)')
        && str_contains($content['mention_js'], 'aria-activedescendant'),
    'pure at-mention submission owns the form before the standard Agent handler' =>
        str_contains($content['mention_js'], "form.addEventListener('submit'")
        && str_contains($content['mention_js'], 'event.stopImmediatePropagation()')
        && str_contains($content['mention_js'], "action: 'crm_search'")
        && $pageMentionPosition !== false
        && $pageChatPosition !== false
        && $pageMentionPosition < $pageChatPosition,
    'all matching results are loaded through bounded server pagination' =>
        str_contains($content['search_helper'], 'min(100, $limit)')
        && str_contains($content['search_helper'], "'has_more'=>")
        && str_contains($content['search_helper'], "'next_offset'=>")
        && str_contains($content['mention_js'], 'while (result.has_more && safety < 100)')
        && str_contains($content['mention_js'], "'&limit=100&offset='")
        && str_contains($content['mention_js'], 'Showing all '),
    'chat results render compact CRM rows with requested contact actions' =>
        str_contains($content['mention_js'], '<table class="mg-agent-crm-table">')
        && str_contains($content['mention_js'], '>Select</button>')
        && str_contains($content['mention_js'], '>Profile</a>')
        && str_contains($content['mention_js'], '>Timeline</a>')
        && str_contains($content['mention_js'], '>Message</a>')
        && str_contains($content['mention_js'], '>Reward</a>'),
    'result rows expose CRM state source engagement and recent activity' =>
        str_contains($content['mention_js'], 'contact.stage')
        && str_contains($content['mention_js'], 'contact.status')
        && str_contains($content['mention_js'], 'contact.campaign_title')
        && str_contains($content['mention_js'], 'contact.score_label')
        && str_contains($content['mention_js'], 'contact.last_activity_at')
        && str_contains($content['mention_js'], 'contact.next_best_action'),
    'Agent results deep-link into the current Merchant CRM search' =>
        str_contains($content['mention_js'], '/merchant-crm.php?search=')
        && str_contains($content['crm_search_js'], "params.get('search')")
        && str_contains($content['crm_search_js'], "replace(/^@+/, '')"),
    'responsive styles cover autocomplete desktop table and mobile result cards' =>
        str_contains($content['css'], '.mg-agent-crm-mentions')
        && str_contains($content['css'], '.mg-agent-crm-table')
        && str_contains($content['css'], '@media(max-width:820px)')
        && str_contains($content['css'], '@media(max-width:520px)'),
    'feature loads as cache-busted scoped assets without a schema migration' =>
        str_contains($content['page'], '/assets/css/merchant-agent-crm-mention-search.css?v=1.0.0')
        && str_contains($content['page'], '/assets/js/merchant-agent-crm-mention-search.js?v=1.0.0')
        && !str_contains($content['search_helper'], 'CREATE TABLE')
        && !str_contains($content['agent_helper'], 'ALTER TABLE'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}
if ($failed !== []) {
    fwrite(STDERR, "\nMerchant Agent CRM mention search validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}
$total = count($checks);
echo "\nMerchant Agent CRM mention search contract: {$total}/{$total}.\n";
