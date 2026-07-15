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
    $page = $read('agent.php');
    $loader = $read('includes/personal-gifting-agent.php');
    $service = $read('includes/personal-agent/gift-result-cards.php');
    $endpoint = $read('api/user-agent/chat.php');
    $renderer = $read('assets/js/personal-agent-gift-results.js');
    $styles = $read('assets/css/personal-agent-gift-results.css');
    $sendEndpoint = $read('api/account/action-center-send.php');

    $expect(
        str_contains($loader, "require_once __DIR__ . '/personal-agent/gift-result-cards.php';")
        && str_contains($endpoint, 'mg_personal_agent_chat_with_account_gift_response'),
        'Chat endpoint loads the account gift result-card wrapper'
    );
    $expect(
        str_contains($service, "return 'inbox';")
        && str_contains($service, "return 'sent';")
        && str_contains($service, "return 'claimed';"),
        'Account gift questions classify Inbox, Sent, and Claimed folders'
    );
    $expect(
        str_contains($service, 'mg_action_center_items($pdo, $userId, $folder, $limit)')
        && str_contains($service, 'mg_ac_wallet_items($pdo, $userId'),
        'Gift results reuse owner-scoped Action Center and wallet sources'
    );
    $expect(
        str_contains($service, "'type' => 'account_gift'")
        && str_contains($service, "'action_item_id' => \$actionItemId")
        && str_contains($service, "'can_send' => \$canSend")
        && str_contains($service, "'send_label' => 'Send'"),
        'Server emits complete account gift cards with send eligibility'
    );
    $expect(
        str_contains($service, "if (\$folder !== 'inbox') return false;")
        && str_contains($service, 'These results are view-only because only currently owned Inbox gifts can be transferred.'),
        'Only Inbox gifts are presented as transferable'
    );
    foreach (['claim_code', 'qr_code_token', 'phone_ciphertext', 'payment_method_id', 'private merchant notes'] as $blocked) {
        $expect(!str_contains(mb_strtolower($service), $blocked), 'Gift result payload excludes sensitive marker: ' . $blocked);
    }
    $expect(
        str_contains($service, 'UPDATE user_agent_messages SET body=?,cards_json=? WHERE owner_user_id=?')
        && str_contains($service, "role='assistant'"),
        'Account gift cards and grounded copy persist owner-scoped with chat history'
    );
    $expect(
        str_contains($renderer, "text(card.type).toLowerCase() === 'account_gift'")
        && str_contains($renderer, 'mg-agent-gift-media')
        && str_contains($renderer, 'mg-agent-gift-meta')
        && str_contains($renderer, 'mg-agent-gift-actions'),
        'Client renders complete image, detail, metadata, and action cards'
    );
    $expect(
        str_contains($renderer, 'data-agent-gift-send')
        && str_contains($renderer, '/api/account/action-center.php?folder=inbox&limit=100')
        && str_contains($renderer, '/api/account/action-center-send.php')
        && str_contains($renderer, 'Yes, Send Gift'),
        'Send buttons revalidate ownership and use the canonical confirmed transfer endpoint'
    );
    $expect(
        str_contains($renderer, '/api/public/discover.php?q=')
        && str_contains($renderer, 'data-selected-recipient')
        && str_contains($renderer, 'recipient_user_id')
        && str_contains($renderer, 'idempotency_key'),
        'Send modal requires an explicit recipient selection and idempotent confirmation'
    );
    $expect(
        str_contains($sendEndpoint, "if ((string)\$instance['folder'] !== 'inbox')")
        && str_contains($sendEndpoint, 'mg_pppm_transfer_owner_canonical')
        && str_contains($sendEndpoint, 'mg_create_notification'),
        'Canonical send endpoint enforces Inbox ownership, PPPM transfer, and recipient notification'
    );
    $expect(
        str_contains($styles, 'grid-template-columns:repeat(4,minmax(0,1fr))')
        && substr_count($styles, 'grid-template-columns:repeat(2,minmax(0,1fr))') >= 2
        && str_contains($styles, '.mg-agent-gift-card')
        && str_contains($styles, '@media(max-width:520px)'),
        'Gift cards use four desktop columns and two mobile columns'
    );
    $expect(
        str_contains($page, '/assets/css/gift-action-center-modals.css?v=1.0.0')
        && str_contains($page, '/assets/css/personal-agent-gift-results.css?v=1.0.0')
        && str_contains($page, '/assets/js/personal-agent-gift-results.js?v=1.0.0'),
        'Agent page loads account gift cards and the canonical send modal presentation'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Personal Agent account gift-card validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "Personal Agent account gift-card validation passed: {$passes} checks.\n";
