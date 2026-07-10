<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

function mg_wc_sync_read(string $root, string $path): string
{
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) throw new RuntimeException("Missing required file: {$path}");
    $content = file_get_contents($full);
    if (!is_string($content)) throw new RuntimeException("Unable to read required file: {$path}");
    return $content;
}

function mg_wc_sync_expect(bool $condition, string $label, array &$failures, int &$passes): void
{
    if ($condition) {
        $passes++;
        echo "PASS: {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
}

try {
    $page = mg_wc_sync_read($root, 'world-canvas.php');
    $loader = mg_wc_sync_read($root, 'assets/js/world-canvas-square-map.js');
    $controller = mg_wc_sync_read($root, 'assets/js/world-canvas-target-drops.js');
    $dashboard = mg_wc_sync_read($root, 'assets/js/world-canvas-merchant-settings.js');
    $campaigns = mg_wc_sync_read($root, 'api/world-canvas/_target_drop_campaigns.php');
    $drops = mg_wc_sync_read($root, 'api/world-canvas/_target_drops.php');
    $endpoint = mg_wc_sync_read($root, 'api/world-canvas/target-drops.php');
    $anchorApi = mg_wc_sync_read($root, 'api/store/avatar-anchor.php');
    $anchorUi = mg_wc_sync_read($root, 'assets/js/avatar-anchor-consent.js');
    $exitApi = mg_wc_sync_read($root, 'api/store/exit.php');

    mg_wc_sync_expect(
        !str_contains($page, 'world-canvas-reward-drops.css')
        && !str_contains($page, 'world-canvas-reward-drops.js'),
        'World Canvas no longer loads the legacy conversation reward-drop creator',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        !str_contains($loader, 'world-canvas-target-reward-cleanup.js')
        && str_contains($loader, 'world-canvas-target-drops.js'),
        'Only the primary Campaign Drop Zone controller is loaded',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        str_contains($controller, 'data-campaign-select')
        && str_contains($controller, 'data-target-campaign-summary')
        && str_contains($controller, 'reward_template_title')
        && str_contains($controller, 'savePending'),
        'Campaign selector, derived reward display, and duplicate-save guard share one controller',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        str_contains($campaigns, "c.merchant_user_id=?")
        && str_contains($campaigns, "rt.merchant_user_id=c.merchant_user_id")
        && str_contains($campaigns, "c.status='active'")
        && str_contains($campaigns, "rt.status='active'"),
        'Campaign options enforce merchant ownership and active campaign/reward status',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        str_contains($campaigns, 'mg_campaign_type_registry')
        && str_contains($campaigns, 'available_quantity')
        && str_contains($campaigns, "throw new RuntimeException('Select an active merchant-owned campaign with an active available reward.')"),
        'Campaign eligibility enforces public type, dates, and available reward inventory',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        str_contains($campaigns, "\$input['campaign_id'] = \$campaign['campaign_id']")
        && str_contains($campaigns, "\$input['reward_template_public_id'] = \$campaign['reward_template_id']")
        && str_contains($campaigns, "\$input['payload_type'] = \$campaign['payload_type']"),
        'Server derives campaign ID, reward template, and payload type from the canonical campaign',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        str_contains($drops, "'campaign_id' =>")
        && str_contains($drops, 'SET merchant_location_id=?,campaign_id=?,campaign_public_id=?')
        && str_contains($drops, '$campaignPublicId === \'\' ? null'),
        'Campaign database ID and public ID persist and hydrate together',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        str_contains($drops, 'Attach an active campaign with an active available reward before publishing')
        && str_contains($drops, 'Set an active merchant location before publishing'),
        'Publishing is blocked without canonical campaign/reward and merchant location authority',
        $failures,
        $passes
    );

    $targetScope = $campaigns . "\n" . $drops . "\n" . $endpoint . "\n" . $controller;
    $forbiddenWrites = [
        'INSERT INTO campaigns',
        'INSERT INTO reward_templates',
        'INSERT INTO wallet_items',
        'UPDATE wallet_items',
        'INSERT INTO inbox',
        'INSERT INTO messages',
        'INSERT INTO microgifts',
        'mg_store_send_reward',
        'mg_store_manual_ops_send_reward',
    ];
    $hasForbiddenWrite = false;
    foreach ($forbiddenWrites as $needle) {
        if (stripos($targetScope, $needle) !== false) {
            $hasForbiddenWrite = true;
            break;
        }
    }
    mg_wc_sync_expect(
        !$hasForbiddenWrite,
        'Campaign Drop Zone configuration cannot create campaigns/rewards or write Wallet, Inbox, PPPM, messages, or gifts',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        str_contains($endpoint, 'mg_require_api_user')
        && str_contains($endpoint, 'mg_require_csrf_for_write')
        && str_contains($endpoint, "mg_require_permission('merchant.locations.manage')")
        && str_contains($endpoint, 'mg_rate_limit'),
        'Campaign Drop Zone endpoint retains authentication, merchant access, CSRF, and rate limits',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        str_contains($anchorApi, 'mg_world_location_main_merchant')
        && str_contains($anchorApi, 'merchant_location_opt_in')
        && !str_contains($anchorApi, "\$input['avatar_latitude']")
        && !str_contains($anchorApi, "\$input['avatar_longitude']"),
        'Store Canvas avatar anchoring resolves merchant coordinates server-side',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        str_contains($anchorUi, "apiPost('/api/store/avatar-anchor.php', { consent: 'yes' })")
        && !str_contains($anchorUi, 'navigator.geolocation')
        && str_contains($anchorUi, 'merchant’s saved latitude and longitude'),
        'Entry prompt is consent-based and does not submit browser coordinates',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        str_contains($exitApi, "if (\$source !== 'merchant_location_opt_in') return null;")
        && str_contains($exitApi, 'mg_world_location_save_user')
        && str_contains($exitApi, "'store_session'")
        && str_contains($exitApi, "'world_transition' => \$worldTransition"),
        'Consented Store Canvas exit places the avatar into World Canvas at the merchant anchor',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        !str_contains($dashboard, "['rewards','Rewards']")
        && !str_contains($dashboard, 'mg-world-merchant-zone-layer')
        && !str_contains($dashboard, 'Mystery Box Reward')
        && str_contains($dashboard, 'Merchant Location Anchors'),
        'Placeholder reward types and merchant-zone circles are removed from the World Dashboard',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        str_contains($controller, 'launchAnimation')
        && str_contains($controller, 'mg-world-drop-trail')
        && str_contains($controller, 'mg-world-drop-ripple')
        && str_contains($controller, 'data-target-radius-handle'),
        'Drop Zone movement, trail, ripple, and radius animations remain available',
        $failures,
        $passes
    );

    mg_wc_sync_expect(
        !str_contains($targetScope, 'getBoundingClientRect().contains')
        && !str_contains($targetScope, 'browser_overlap_authority')
        && !str_contains($targetScope, 'visual_overlap'),
        'Browser avatar overlap remains visual-only and is not execution authority',
        $failures,
        $passes
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("World Canvas Campaign Drop Zone sync validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "World Canvas Campaign Drop Zone sync validation passed: {$passes} checks.\n";
