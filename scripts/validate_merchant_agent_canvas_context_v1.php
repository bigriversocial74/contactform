<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'page'=>'merchant-agent-chat.php',
    'view'=>'includes/merchant-agent-chat-view.php',
    'css'=>'assets/css/merchant-agent-personal-canvas-parity-v1.css',
    'sidebar'=>'assets/js/merchant-agent-sidebar-history.js',
    'api'=>'api/ai/merchant-agent-chat.php',
    'context'=>'includes/ai/merchant-agent-crm-contact-context.php',
    'chat'=>'includes/ai/merchant-agent-crm-contact-chat.php',
    'threads'=>'includes/ai/merchant-agent-thread-delete.php',
];
$content = [];
foreach ($paths as $key => $path) {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) throw new RuntimeException('Unable to read ' . $path);
    $content[$key] = $value;
}

$checks = [
    'final canvas asset loads after integrated workspace styles' =>
        str_contains($content['page'], 'merchant-agent-personal-canvas-parity-v1.css?v=1.0.0')
        && strpos($content['page'], 'merchant-agent-personal-canvas-parity-v1.css') > strpos($content['page'], 'merchant-agent-integrated-workspace.css'),
    'current sidebar runtime is cache-busted' => str_contains($content['page'], 'merchant-agent-sidebar-history.js?v=2.1.0'),
    'merchant markup shares personal conversation classes' =>
        str_contains($content['view'], 'mg-personal-agent-main mg-merchant-agent-main')
        && str_contains($content['view'], 'mg-personal-agent-chat-view mg-merchant-agent-chat-view')
        && str_contains($content['view'], 'is-assistant is-intro mg-merchant-agent-intro'),
    'contact-aware examples are visible in the composer help' =>
        str_contains($content['view'], '@username show recent activity')
        && str_contains($content['view'], '@username draft a follow-up')
        && str_contains($content['view'], '@username recommend a reward'),
    'opening and empty states are transparent canvas content' =>
        str_contains($content['css'], '.mg-merchant-agent-intro')
        && str_contains($content['css'], 'background:transparent!important')
        && str_contains($content['css'], '.mg-agent-chat-empty')
        && str_contains($content['css'], 'border-radius:0!important'),
    'sidebar uses one flat personal chat row list' =>
        str_contains($content['sidebar'], 'host.innerHTML = threads.map')
        && str_contains($content['sidebar'], 'mg-personal-chat-row')
        && str_contains($content['sidebar'], 'mg-personal-chat-delete')
        && !str_contains($content['sidebar'], 'mg-personal-chat-group'),
    'sidebar date range grouping is removed' =>
        !str_contains($content['sidebar'], 'groupLabel')
        && !str_contains($content['sidebar'], "return 'Today'")
        && !str_contains($content['sidebar'], "return 'Yesterday'")
        && !str_contains($content['sidebar'], "return 'Previous 7 days'"),
    'sidebar provides a confirmed thread removal action' =>
        str_contains($content['sidebar'], "action: 'delete_thread'")
        && str_contains($content['sidebar'], 'window.confirm')
        && str_contains($content['api'], "'delete_thread'"),
    'thread action is scoped by merchant and thread reference' =>
        str_contains($content['threads'], 'WHERE merchant_user_id=? AND public_id=? LIMIT 1')
        && str_contains($content['threads'], 'thread_public_id'),
    'thread action handles messages snapshots and active replacement' =>
        str_contains($content['threads'], 'merchant_agent_insight_snapshots')
        && str_contains($content['threads'], 'merchant.agent_chat.user')
        && str_contains($content['threads'], 'mg_agent_create_thread'),
    'standalone lookup and contact-aware prompts use separate routes' =>
        str_contains($content['api'], 'mg_merchant_agent_crm_search_is_query')
        && str_contains($content['api'], '$contactAware')
        && str_contains($content['api'], 'mg_merchant_agent_crm_contact_chat_response'),
    'contact-aware route enforces AI and CRM permissions' =>
        str_contains($content['api'], "'merchant.ai.plan'")
        && str_contains($content['api'], "mg_merchant_require_permission('merchant.campaigns.view')"),
    'exact handles support profiles and generated CRM names' =>
        str_contains($content['context'], '^crm-([a-z0-9]{10})$')
        && str_contains($content['context'], "LOWER(REPLACE(public_id,'-',''))")
        && str_contains($content['context'], 'INNER JOIN public_profiles pp')
        && str_contains($content['context'], 'LOWER(pp.slug)=?'),
    'contact data stays scoped to exact handles and workspace owner' =>
        str_contains($content['context'], 'merchant_user_id=?')
        && str_contains($content['context'], 'Only exact CRM contacts explicitly mentioned')
        && str_contains($content['api'], "['_merchant_owner_id']"),
    'context includes activity campaigns purchases and rewards' =>
        str_contains($content['context'], 'merchant_crm_contact_events')
        && str_contains($content['context'], 'merchant_crm_contact_campaigns')
        && str_contains($content['context'], 'total_purchase_cents')
        && str_contains($content['context'], 'total_rewards_redeemed'),
    'agent rules prevent invented contacts and direct actions' =>
        str_contains($content['chat'], 'Never infer or invent a contact')
        && str_contains($content['chat'], 'Never claim a message was sent')
        && str_contains($content['chat'], 'Never issue a reward')
        && str_contains($content['chat'], 'Never execute directly'),
    'unresolved handles receive a deterministic selection response' =>
        str_contains($content['chat'], 'mg_merchant_agent_crm_unresolved_response')
        && str_contains($content['chat'], 'choose a contact from autocomplete'),
    'contact conversations preserve chat history and review cards' =>
        str_contains($content['chat'], 'mg_ai_chat_record_message')
        && str_contains($content['chat'], 'mg_ai_chat_shape_cards')
        && str_contains($content['chat'], 'mg_ai_chat_auto_bridge_cards'),
    'feature uses existing database structures' =>
        !str_contains($content['context'], 'CREATE TABLE')
        && !str_contains($content['threads'], 'ALTER TABLE')
        && !str_contains($content['chat'], 'CREATE TABLE'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}
if ($failed) {
    fwrite(STDERR, "\nMerchant Agent canvas/context validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}
$total = count($checks);
echo "\nMerchant Agent canvas/context contract: {$total}/{$total}.\n";