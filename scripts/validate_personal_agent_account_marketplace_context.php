<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) throw new RuntimeException('Missing required file: ' . $path);
    $content = file_get_contents($full);
    if (!is_string($content)) throw new RuntimeException('Unable to read required file: ' . $path);
    return $content;
};

$expect = static function (bool $condition, string $label) use (&$failures, &$passes): void {
    if ($condition) {
        $passes++;
        echo "PASS: {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
};

try {
    $loader = $read('includes/personal-gifting-agent.php');
    $knowledge = $read('includes/personal-agent/knowledge.php');
    $endpoint = $read('api/user-agent/chat.php');
    $dashboard = $read('includes/personal-agent/workspace-dashboard.php');
    $canvas = $read('assets/js/personal-agent-chat-canvas.js');
    $agentPage = $read('agent.php');

    $expect(
        str_contains($loader, "require_once __DIR__ . '/personal-agent/knowledge.php';"),
        'Personal agent loads the permission-safe knowledge layer'
    );
    $expect(
        str_contains($endpoint, 'mg_personal_agent_chat_v2(')
        && !str_contains($endpoint, 'mg_personal_agent_chat(mg_db()'),
        'Chat endpoint uses the expanded permission-safe runtime'
    );
    $expect(
        str_contains($knowledge, 'function mg_personal_agent_contact_knowledge')
        && str_contains($knowledge, 'function mg_personal_agent_list_knowledge')
        && str_contains($knowledge, 'function mg_personal_agent_commerce_knowledge'),
        'Agent receives owner-scoped contacts, lists, and commerce knowledge'
    );
    $expect(
        str_contains($knowledge, 'WHERE c.owner_user_id=?')
        && str_contains($knowledge, 'WHERE o.buyer_user_id=?')
        && str_contains($knowledge, "mg_account_items($pdo, $userId")
        && str_contains($knowledge, "mg_account_gifts($pdo, $userId")
        && str_contains($knowledge, "mg_account_claims($pdo, $userId"),
        'Account knowledge is constrained to the signed-in user'
    );
    $expect(
        str_contains($knowledge, 'mg_profile_discovery_search($pdo, $filters, $userId)')
        && str_contains($knowledge, 'mg_product_discovery_search($pdo'),
        'Marketplace knowledge reuses public discovery permission filters'
    );
    $expect(
        str_contains($knowledge, "'merchant_secrets' => false")
        && str_contains($knowledge, 'merchant-permission-only')
        && str_contains($knowledge, 'Claim status may be summarized'),
        'Merchant-only secret boundary is explicit in runtime and prompt'
    );
    foreach (['code_last4', 'phone_ciphertext', 'phone_hash', 'address_line_1', 'address_line_2', 'source_reference', 'payment_method_id'] as $blockedMarker) {
        $expect(
            !str_contains($knowledge, $blockedMarker),
            'Knowledge projection excludes sensitive field: ' . $blockedMarker
        );
    }
    $expect(
        str_contains($knowledge, "'restrictions' =>")
        && str_contains($knowledge, "'preferred_merchants' =>")
        && str_contains($knowledge, "'preferred_categories' =>")
        && str_contains($knowledge, "'budget_min' =>")
        && str_contains($knowledge, "'budget_max' =>"),
        'Permission-safe contact attributes are available to the agent'
    );
    $expect(
        !str_contains($dashboard, 'Start a new conversation')
        && !str_contains($dashboard, 'data-personal-agent-new-thread'),
        'New conversation line is removed from the agent dashboard'
    );
    $expect(
        !str_contains($canvas, 'data-personal-agent-new-thread')
        && !str_contains($canvas, 'New personal gifting conversation started.'),
        'Obsolete new-thread canvas behavior is removed'
    );
    $expect(
        str_contains($dashboard, 'Ask about your contacts, lists, purchases, gifts')
        && str_contains($dashboard, 'Explore marketplace'),
        'Agent intro communicates account and marketplace knowledge'
    );
    $expect(
        str_contains($agentPage, '/assets/js/personal-agent-chat-canvas.js?v=1.2.0'),
        'Agent page cache-busts the revised chat canvas'
    );
    $expect(
        str_contains($knowledge, 'mg_personal_agent_store_message')
        && str_contains($knowledge, "'assistant_message' => $assistant")
        && str_contains($knowledge, 'mg_personal_agent_fallback_v2'),
        'Every successful chat request stores and returns an assistant response'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Personal agent account/marketplace validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "Personal agent account/marketplace validation passed: {$passes} checks.\n";
