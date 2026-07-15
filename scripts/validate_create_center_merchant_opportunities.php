<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;
$read = static function (string $path) use ($root): string {
    $file = $root . '/' . ltrim($path, '/');
    if (!is_file($file)) throw new RuntimeException('Missing required file: ' . $path);
    $content = file_get_contents($file);
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
    $extension = $read('includes/header-components/create-list-extension.php');
    $viewportCss = $read('assets/css/create-center-viewport-fix.css');
    $viewportJs = $read('assets/js/create-center-viewport-fix.js');
    $loader = $read('includes/personal-gifting-agent.php');
    $endpoint = $read('api/user-agent/chat.php');
    $opportunities = $read('includes/personal-agent/merchant-opportunities.php');
    $offers = $read('assets/js/stage12-agent-offers.js');

    $expect(
        str_contains($extension, '/assets/css/create-center-viewport-fix.css?v=1.0.0')
        && str_contains($extension, '/assets/js/create-center-viewport-fix.js?v=1.0.0'),
        'Authenticated Create Center loads the viewport repair assets'
    );
    $expect(
        str_contains($viewportJs, 'document.body.appendChild(modal)')
        && str_contains($viewportJs, 'data-create-center-viewport-root'),
        'Create Center is portaled to the body viewport root'
    );
    $expect(
        str_contains($viewportCss, 'width:min(1480px,calc(100vw - 32px))!important')
        && str_contains($viewportCss, 'padding-left:24px!important')
        && str_contains($viewportCss, 'padding-right:24px!important')
        && str_contains($viewportCss, 'width:100vw!important'),
        'Create Center uses balanced desktop padding and full mobile viewport width'
    );
    $expect(
        str_contains($loader, "require_once __DIR__ . '/personal-agent/merchant-opportunities.php';")
        && str_contains($endpoint, 'mg_personal_agent_chat_with_merchant_opportunities'),
        'Personal Agent routes through the merchant opportunity layer'
    );
    $expect(
        str_contains($opportunities, "mg_personal_agent_account_gift_items(\$pdo, \$userId, 'inbox', 24)")
        && str_contains($opportunities, 'merchant_user_id')
        && str_contains($opportunities, 'sender_id'),
        'Opportunity sourcing is limited to merchants represented in the current Inbox'
    );
    $expect(
        str_contains($opportunities, 'mg_personal_agent_merchant_campaign_opportunities')
        && str_contains($opportunities, 'mg_personal_agent_merchant_reward_opportunities')
        && str_contains($opportunities, 'mg_personal_agent_merchant_product_opportunities'),
        'Recommendations cover campaigns, rewards, products, experiences, and entertainment-capable inventory'
    );
    $expect(
        str_contains($opportunities, 'NOT EXISTS(SELECT 1 FROM campaign_contacts')
        && str_contains($opportunities, "wi.status<>'cancelled'")
        && str_contains($opportunities, 'reward_template_id=rt.id'),
        'Joined campaigns and rewards already in the wallet are excluded'
    );
    $expect(
        str_contains($opportunities, 'personal entertainment or experiences')
        && str_contains($opportunities, 'Personal or gift')
        && str_contains($opportunities, 'local deals'),
        'Recommendation copy supports personal entertainment, savings, and gifting goals'
    );
    $expect(
        str_contains($opportunities, 'MG_PERSONAL_AGENT_MERCHANT_OPPORTUNITY_PROMPT')
        && str_contains($opportunities, 'mg_personal_agent_merchant_opportunity_affirmative')
        && str_contains($opportunities, 'mg_personal_agent_previous_opportunity_prompt'),
        'Inbox results ask a follow-up and a contextual yes resolves merchant opportunities'
    );
    $expect(
        str_contains($opportunities, 'UPDATE user_agent_messages SET body=?,cards_json=? WHERE owner_user_id=?')
        && str_contains($opportunities, "role='assistant'"),
        'Opportunity results persist only to the owner-scoped assistant message'
    );
    foreach (['claim_code', 'qr_code_token', 'payment_method_id', 'phone_ciphertext', 'private merchant notes'] as $blocked) {
        $expect(!str_contains(mb_strtolower($opportunities), $blocked), 'Opportunity payload excludes sensitive marker: ' . $blocked);
    }
    $expect(
        str_contains($offers, "initialParams.get('offer')")
        && str_contains($offers, 'loadDetail(initialOffer)'),
        'Reward cards can deep-link to the exact public offer detail'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Create Center and merchant opportunity validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "Create Center and merchant opportunity validation passed: {$passes} checks.\n";
