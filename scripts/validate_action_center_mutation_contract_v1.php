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
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if ($condition) $passes++;
    else $failures[] = $label;
};

try {
    $helper = $read('api/account/_action_center_mutation_contract.php');
    $sync = $read('api/account/action-center-mutation-state.php');
    $actions = $read('assets/js/gift-action-center-actions.js');
    $runtime = $read('assets/js/gift-action-center-runtime-v4.js');
    $include = $read('includes/gift-action-center.php');
    $regiftUi = $read('assets/js/gift-action-center-regift-submit.js');
    $claimUi = $read('assets/js/gift-action-center-claim-modal.js');
    $docs = $read('docs/action-center-mutation-contract-v1.md');

    $stateEndpoints = [];
    foreach (['read','unread','archive','restore'] as $name) {
        $stateEndpoints[$name] = $read('api/account/action-center-' . $name . '.php');
    }

    $expect(
        str_contains($helper, 'MG_ACTION_CENTER_MUTATION_CONTRACT_VERSION = 1')
        && str_contains($helper, 'MG_ACTION_CENTER_CONTRACT_VERSION')
        && str_contains($helper, "'remove_action_item_ids'")
        && str_contains($helper, "'synchronized_at'"),
        'Mutation helper publishes a versioned Contract v2 reconciliation envelope'
    );

    foreach (['send','claim','follow-up','message','tip','read','unread','archive','restore','voucher-token','voucher-redeem','merchant-redeem'] as $action) {
        $expect(str_contains($helper, "'{$action}'"), 'Mutation helper registers ' . $action);
    }

    $expect(
        str_contains($helper, 'mg_action_center_detail(')
        && str_contains($helper, 'mg_ac_wallet_load_for_user(')
        && str_contains($helper, 'mg_ac_wallet_public_item(')
        && str_contains($helper, 'mg_action_center_contract_items('),
        'Reconciliation reloads canonical and wallet rows through Contract v2'
    );

    $expect(
        str_contains($helper, 'mg_action_center_counts(')
        && str_contains($helper, 'mg_ac_wallet_counts(')
        && str_contains($helper, 'mg_ac_wallet_counts_merge('),
        'Reconciliation returns merged canonical and wallet folder counts'
    );

    $expect(
        str_contains($sync, "mg_require_method('POST')")
        && str_contains($sync, 'mg_require_api_user()')
        && str_contains($sync, 'mg_require_csrf_for_write($input)')
        && str_contains($sync, 'mg_action_center_mutation_ok('),
        'Mutation reconciliation endpoint is authenticated, CSRF protected, and server projected'
    );

    foreach ($stateEndpoints as $name => $source) {
        $expect(
            str_contains($source, "'_action_center_mutation_contract.php'")
            && str_contains($source, 'mg_require_csrf_for_write($input)')
            && str_contains($source, "mg_action_center_mutation_ok(\$pdo, \$user, '{$name}'"),
            ucfirst($name) . ' returns the shared mutation envelope'
        );
    }

    $expect(
        str_contains($actions, 'MicrogifterActionCenterRuntime')
        && str_contains($actions, 'selectedContract()')
        && str_contains($actions, 'capabilities(item)')
        && str_contains($actions, 'assertCapability(type, item)'),
        'Mutation client uses Runtime v4 Contract v2 selection and capability gates'
    );

    $expect(
        !str_contains($actions, 'function actionItemFromRow')
        && !str_contains($actions, "currency: 'USD'")
        && !str_contains($actions, 'refresh.click()'),
        'Mutation client no longer reconstructs legacy items or simulates Refresh clicks'
    );

    $expect(
        str_contains($actions, '/api/account/action-center-mutation-state.php')
        && str_contains($actions, 'mutation_contract_version')
        && str_contains($actions, 'setCounts(envelope.counts)')
        && str_contains($actions, "api.refresh === 'function'"),
        'Mutation client reconciles authoritative state and counts through Runtime v4'
    );

    $expect(
        str_contains($actions, "var ACTIVE = ['send','follow-up','claim','message','tip']")
        && str_contains($actions, "var STATE = ['read','unread','archive','restore']")
        && str_contains($actions, 'var inFlight = new Map()')
        && str_contains($actions, 'window.MicrogifterActionCenterMutations'),
        'One mutation client owns transactional and state action dispatch'
    );

    $expect(
        str_contains($actions, "mg:action-center:voucher-claimed")
        && str_contains($actions, "mg:action-center:regift-sent")
        && str_contains($actions, '{ refresh: false }')
        && !str_contains($actions, "event.stopImmediatePropagation();\n    finalizeExternal"),
        'Specialized voucher and regift confirmation events remain compatible'
    );

    $expect(
        str_contains($include, 'gift-action-center-actions.js?v=2.0.0')
        && str_contains($include, 'action-center-contract-v2.js?v=2.0.0')
        && str_contains($include, 'gift-action-center-runtime-v4.js?v=4.0.0'),
        'Action Center loads Contract v2, Runtime v4, and the cache-bumped mutation client in order'
    );

    $expect(
        str_contains($regiftUi, "mg:action-center:regift-sent")
        && !str_contains($regiftUi, "querySelector('[data-gift-refresh]')")
        && !str_contains($regiftUi, 'refresh.click()'),
        'Exact-recipient regift flow delegates list reconciliation to the mutation contract'
    );

    $expect(
        str_contains($claimUi, '/api/account/action-center-voucher-token.php')
        && str_contains($claimUi, '/api/account/action-center-voucher-claim.php')
        && str_contains($claimUi, "mg:action-center:voucher-claimed"),
        'Voucher preparation and manual redemption retain their specialized confirmation flow'
    );

    $authorities = [
        'regift' => $read('api/account/action-center-send.php'),
        'claim' => $read('api/account/action-center-claim.php'),
        'follow_up' => $read('api/account/action-center-follow-up.php'),
        'message' => $read('api/account/action-center-message.php'),
        'tip' => $read('api/account/action-center-tip.php'),
        'voucher_token' => $read('api/account/action-center-voucher-token.php'),
        'voucher_claim' => $read('api/account/action-center-voucher-claim.php'),
        'scanner' => $read('api/merchant/scanner-claim.php'),
    ];

    $expect(
        str_contains($authorities['regift'], 'FOR UPDATE')
        && str_contains($authorities['regift'], 'mg_pppm_transfer_owner_canonical(')
        && str_contains($authorities['regift'], 'idempotency_key'),
        'Regift keeps row locking, PPPM ownership authority, and idempotency'
    );
    $expect(
        str_contains($authorities['claim'], 'mg_microgift_claim_canonical(')
        && str_contains($authorities['claim'], 'FOR UPDATE')
        && str_contains($authorities['claim'], 'idempotency_key'),
        'Claim keeps canonical claim, row locking, and replay authority'
    );
    $expect(
        str_contains($authorities['follow_up'], 'Only the most recent sender can follow up.')
        && str_contains($authorities['follow_up'], 'mg_message_send_microgift(')
        && !str_contains($authorities['follow_up'], 'mg_pppm_transfer_owner_canonical('),
        'Follow Up retains latest-sender messaging without ownership mutation'
    );
    $expect(
        str_contains($authorities['message'], 'Message recipient does not match this transfer.')
        && str_contains($authorities['message'], 'mg_message_send_microgift(')
        && str_contains($authorities['message'], 'idempotencyKey'),
        'Message retains participant binding and durable idempotent messaging'
    );
    $expect(
        str_contains($authorities['tip'], "mg_require_permission('tips.create')")
        && str_contains($authorities['tip'], 'mg_tip_create(')
        && str_contains($authorities['tip'], "folder']!=='claimed'"),
        'Tip retains permission, eligibility, and ledger service authority'
    );
    $expect(
        str_contains($authorities['voucher_token'], 'mg_claim_voucher_issue_token(')
        && str_contains($authorities['voucher_token'], 'mg_wallet_claim_voucher_issue_token(')
        && str_contains($authorities['voucher_token'], '900'),
        'Voucher preparation retains signed short-lived microgift and wallet tokens'
    );
    $expect(
        str_contains($authorities['voucher_claim'], 'mg_ac_voucher_assert_not_locked(')
        && str_contains($authorities['voucher_claim'], 'merchant_claim_codes')
        && str_contains($authorities['voucher_claim'], 'action_center_voucher_claim_attempts'),
        'Manual voucher redemption retains lockout, merchant code, and attempt authority'
    );
    $expect(
        str_contains($authorities['scanner'], "mg_require_permission('merchant.gifts.redeem')")
        && str_contains($authorities['scanner'], "['verify','redeem']")
        && str_contains($authorities['scanner'], 'mg_claim_voucher_require_active(')
        && str_contains($authorities['scanner'], 'mg_wallet_claim_voucher_require_active('),
        'Merchant scanner retains permission, verify/redeem, and signed-token authority'
    );

    $expect(
        str_contains($docs, 'separate')
        && str_contains($docs, 'Mutation Contract v1')
        && str_contains($docs, 'No SQL required.'),
        'Architecture and SQL boundary are documented'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo '[FAIL] ' . $error->getMessage() . PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Action Center Mutation Contract v1 failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, ' - ' . $failure . PHP_EOL);
    exit(1);
}

echo sprintf("Action Center Mutation Contract v1 passed: %d checks.\n", $passes);
