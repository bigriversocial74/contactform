<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function source(string $root, string $path, array &$failures): string
{
    $value = @file_get_contents($root . '/' . $path);
    if (!is_string($value)) {
        $failures[] = $path . ' could not be read';
        return '';
    }
    return $value;
}

function mustContain(string $path, string $source, string $needle, array &$failures): void
{
    if (!str_contains($source, $needle)) {
        $failures[] = $path . ' missing required marker: ' . $needle;
    }
}

function mustNotContain(string $path, string $source, string $needle, array &$failures): void
{
    if (str_contains($source, $needle)) {
        $failures[] = $path . ' still contains blocked runtime: ' . $needle;
    }
}

$page = source($root, 'merchant-canvas.php', $failures);
mustContain('merchant-canvas.php', $page, '$hasMerchantAccess = mg_user_has_merchant_access', $failures);
mustContain('merchant-canvas.php', $page, 'Production containment active', $failures);
mustContain('merchant-canvas.php', $page, '/assets/js/merchant-canvas.js', $failures);
mustContain('merchant-canvas.php', $page, '/assets/css/merchant-canvas-containment.css', $failures);
foreach ([
    '/assets/js/merchant-canvas-rewards.js',
    '/assets/js/merchant-canvas-motion.js',
    '/assets/js/merchant-canvas-automation-rules.js',
    '/assets/js/merchant-canvas-merchant-settings.js',
    '/assets/js/merchant-canvas-customer-tabs.js',
    '/assets/js/merchant-canvas-intelligence.js',
    '/assets/js/merchant-canvas-store-health.js',
    '/assets/js/store-health-completion-events.js',
] as $blockedScript) {
    mustNotContain('merchant-canvas.php', $page, $blockedScript, $failures);
}

$footer = source($root, 'includes/footer.php', $failures);
mustContain('includes/footer.php', $footer, '/assets/js/merchant-canvas-containment.js', $failures);
mustNotContain('includes/footer.php', $footer, '/assets/js/merchant-canvas-behavior-post.js', $failures);
mustNotContain('includes/footer.php', $footer, '/assets/js/merchant-canvas-trigger-manager.js', $failures);

$containment = source($root, 'assets/js/merchant-canvas-containment.js', $failures);
foreach ([
    '/api/merchant-canvas/auto-chat.php',
    '/api/merchant-canvas/campaign-trigger.php',
    '/api/merchant-canvas/campaign-trigger-automation.php',
    'merchant_canvas_automatic_actions_disabled',
    'reward_template_id',
    'data-canvas-add-trigger',
] as $marker) {
    mustContain('assets/js/merchant-canvas-containment.js', $containment, $marker, $failures);
}

foreach ([
    'api/merchant-canvas/auto-chat.php',
    'api/merchant-canvas/campaign-trigger.php',
    'api/merchant-canvas/campaign-trigger-automation.php',
] as $endpoint) {
    $endpointSource = source($root, $endpoint, $failures);
    mustContain($endpoint, $endpointSource, 'merchant_canvas_automatic_actions_disabled', $failures);
    mustContain($endpoint, $endpointSource, 'mg_fail(', $failures);
    mustNotContain($endpoint, $endpointSource, 'mg_store_send_direct_message_via_messaging', $failures);
    mustNotContain($endpoint, $endpointSource, 'mg_store_reward_issue', $failures);
}

$sendReward = source($root, 'api/merchant-canvas/send-reward.php', $failures);
mustContain('api/merchant-canvas/send-reward.php', $sendReward, 'attached_reward_template_required', $failures);
mustContain('api/merchant-canvas/send-reward.php', $sendReward, 'INNER JOIN reward_templates', $failures);
mustContain('api/merchant-canvas/send-reward.php', $sendReward, '$templateId = $attachedTemplateId;', $failures);

if ($failures !== []) {
    fwrite(STDERR, "Merchant Canvas Production Containment v1 validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Merchant Canvas Production Containment v1 validation passed.\n";
