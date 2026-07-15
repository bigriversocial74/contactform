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
    $service = $read('includes/personal-agent/marketplace-result-cards.php');
    $endpoint = $read('api/user-agent/chat.php');
    $renderer = $read('assets/js/personal-agent-marketplace-cards.js');
    $styles = $read('assets/css/personal-agent-marketplace-cards.css');
    $schema = $read('database/20260714_personal_gifting_agent_phase2.sql');

    $expect(
        str_contains($loader, "require_once __DIR__ . '/personal-agent/marketplace-result-cards.php';")
        && str_contains($endpoint, 'mg_personal_agent_chat_with_marketplace_cards'),
        'Chat endpoint loads the marketplace result-card wrapper'
    );
    $expect(
        str_contains($service, "return 'merchant';")
        && str_contains($service, "return 'product';")
        && str_contains($service, "return 'campaign';"),
        'Marketplace questions are classified as merchant, product, or campaign requests'
    );
    $expect(
        str_contains($service, 'mg_profile_discovery_search($pdo, $input, $userId)')
        && str_contains($service, 'mg_product_discovery_search($pdo, $input, $userId)'),
        'Merchant and product cards reuse public discovery permission filters'
    );
    $expect(
        str_contains($service, "c.status='active'")
        && str_contains($service, 'c.agent_discoverable=1')
        && str_contains($service, "pp.visibility IN ('public','unlisted')")
        && str_contains($service, 'NOT EXISTS(SELECT 1 FROM social_blocks'),
        'Campaign cards are active, agent-discoverable, public, and block-aware'
    );
    $expect(
        str_contains($service, 'mg_campaign_type_get($type)')
        && str_contains($service, "empty(\$definition['public_enabled'])")
        && str_contains($service, "!empty(\$definition['internal_only'])")
        && str_contains($service, "'?campaign=' . rawurlencode(\$reference)"),
        'Campaign links use the public campaign-type registry and exclude internal campaigns'
    );
    $expect(
        str_contains($service, "'type'=>'marketplace_merchant'")
        && str_contains($service, "'type'=>'marketplace_product'")
        && str_contains($service, "'type'=>'marketplace_campaign'"),
        'Server emits distinct merchant, product, and campaign card payloads'
    );
    $expect(
        str_contains($service, "'url_label'=>'View merchant'")
        && str_contains($service, "'url_label'=>'View product'")
        && str_contains($service, "'url_label'=>'View campaign'")
        && substr_count($service, "'secondary_label'=>'View merchant'") >= 2,
        'Cards link to merchant, product, and campaign pages'
    );
    $expect(
        str_contains($service, "UPDATE user_agent_messages SET cards_json=? WHERE owner_user_id=? AND public_id=? AND role='assistant'")
        && str_contains($schema, 'cards_json JSON NULL'),
        'Marketplace cards persist with the owner-scoped assistant message'
    );
    foreach (['claim code','qr_code_token','phone_ciphertext','payment_method_id','private merchant notes'] as $blocked) {
        $expect(!str_contains(mb_strtolower($service), $blocked), 'Marketplace card payload excludes sensitive marker: ' . $blocked);
    }
    $expect(
        str_contains($renderer, "['marketplace_merchant', 'marketplace_product', 'marketplace_campaign']")
        && str_contains($renderer, 'new URL(String(value || \'\'), window.location.origin)')
        && str_contains($renderer, 'url.origin !== window.location.origin'),
        'Client renderer recognizes card types and restricts navigation to same-origin links'
    );
    $expect(
        str_contains($renderer, 'mg-agent-marketplace-media')
        && str_contains($renderer, 'mg-agent-marketplace-meta')
        && str_contains($renderer, 'mg-agent-marketplace-actions')
        && str_contains($renderer, 'View details'),
        'Client renders imagery, details, metadata, and linked actions'
    );
    $expect(
        str_contains($styles, 'grid-template-columns:repeat(3,minmax(0,1fr))')
        && str_contains($styles, '.mg-agent-marketplace-card')
        && str_contains($styles, '.mg-agent-marketplace-action.is-primary')
        && str_contains($styles, '@media(max-width:700px)'),
        'Marketplace cards use a responsive card grid'
    );
    $expect(
        str_contains($page, '/assets/css/personal-agent-marketplace-cards.css?v=1.0.0')
        && str_contains($page, '/assets/js/personal-agent-marketplace-cards.js?v=1.0.0'),
        'Agent page loads versioned marketplace card assets'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Personal Agent marketplace result-card validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "Personal Agent marketplace result-card validation passed: {$passes} checks.\n";
