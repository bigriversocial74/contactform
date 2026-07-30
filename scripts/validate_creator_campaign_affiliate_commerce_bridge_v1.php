<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

function cca_bridge_source(string $root, string $path): string
{
    $source = file_get_contents($root . '/' . $path);
    if (!is_string($source)) throw new RuntimeException('Unable to read ' . $path);
    return $source;
}

function cca_bridge_check(array &$checks, string $name, bool $ok, int $points): void
{
    $checks[] = ['name' => $name, 'ok' => $ok, 'points' => $points];
}

require_once $root . '/includes/creator-campaigns/commerce-affiliate-foundation.php';

$bridge = implode("\n", [
    cca_bridge_source($root, 'includes/creator-campaigns/commerce-affiliate-foundation.php'),
    cca_bridge_source($root, 'includes/creator-campaigns/commerce-affiliate-checkout.php'),
    cca_bridge_source($root, 'includes/creator-campaigns/commerce-affiliate-earning.php'),
    cca_bridge_source($root, 'includes/creator-campaigns/commerce-affiliate-payment.php'),
]);
$refund = cca_bridge_source($root, 'includes/creator-campaigns/commerce-affiliate-refund.php')
    . "\n" . cca_bridge_source($root, 'includes/creator-campaigns/commerce-affiliate-refund-reconciliation.php');
$bootstrap = cca_bridge_source($root, 'includes/creator-campaigns/bootstrap.php');
$checkout = cca_bridge_source($root, 'api/commerce/_checkout.php');
$capture = cca_bridge_source($root, 'api/payments/_capture.php');
$paymentRefund = cca_bridge_source($root, 'api/payments/_refund.php');

$checks = [];
cca_bridge_check($checks, 'bridge loaded by canonical Creator Campaign bootstrap',
    str_contains($bootstrap, "require_once __DIR__.'/commerce-affiliate-foundation.php'")
    && str_contains($bootstrap, "require_once __DIR__.'/commerce-affiliate-checkout.php'")
    && str_contains($bootstrap, "require_once __DIR__.'/commerce-affiliate-earning.php'")
    && str_contains($bootstrap, "require_once __DIR__.'/commerce-affiliate-payment.php'")
    && str_contains($bootstrap, "require_once __DIR__.'/commerce-affiliate-refund.php'")
    && str_contains($bootstrap, "require_once __DIR__.'/commerce-affiliate-refund-reconciliation.php'"), 5);
cca_bridge_check($checks, 'checkout captures Creator context without blocking checkout',
    str_contains($checkout, 'mg_creator_campaign_affiliate_checkout_context')
    && str_contains($checkout, "'creator_affiliate'")
    && str_contains($checkout, 'catch(Throwable $affiliateError)'), 7);
cca_bridge_check($checks, 'checkout persists privacy-safe context only',
    str_contains($bridge, "'session_hash' => \$sessionHash")
    && !str_contains($checkout, "'session_key'")
    && !str_contains($checkout, 'mg_cc_session'), 7);
cca_bridge_check($checks, 'product eligibility honors selected versions and exclusions',
    str_contains($bridge, "relationship_type")
    && str_contains($bridge, "['primary','featured','commissionable']")
    && str_contains($bridge, "if (\$type === 'excluded')")
    && str_contains($bridge, 'selected_product_version_id'), 6);
cca_bridge_check($checks, 'canonical attribution model remains authoritative',
    str_contains($bridge, "'attribution_model'")
    && str_contains($bridge, 'mg_creator_campaign_attribution_decide')
    && str_contains($bridge, "source_id,participant_id,creator_user_id,event_type")
    && str_contains($bridge, "NULL,NULL,NULL,\\'purchase\\'"), 7);
cca_bridge_check($checks, 'paid order automatically enters affiliate lifecycle',
    str_contains($capture, 'mg_creator_campaign_affiliate_record_paid_order')
    && str_contains($capture, "'after_creator_affiliate'")
    && str_contains($capture, "'creator_affiliate'=>\$creatorAffiliate"), 7);
cca_bridge_check($checks, 'purchase event and earning are replay-safe',
    str_contains($bridge, "'purchase.order.'")
    && str_contains($bridge, "'affiliate:purchase:'")
    && str_contains($bridge, 'WHERE campaign_id=? AND idempotency_hash=? LIMIT 1 FOR UPDATE'), 7);
cca_bridge_check($checks, 'automatic commission uses canonical rule and agreement services',
    str_contains($bridge, 'mg_creator_campaign_compensation_source')
    && str_contains($bridge, 'mg_creator_campaign_compensation_active_rule')
    && str_contains($bridge, 'mg_creator_campaign_compensation_calculate')
    && str_contains($bridge, 'agreement_version_id'), 7);
cca_bridge_check($checks, 'budget reservation is automatic but payment remains fail-open',
    str_contains($bridge, 'mg_creator_campaign_affiliate_reserve_earning')
    && str_contains($bridge, 'SAVEPOINT creator_affiliate_reservation')
    && str_contains($bridge, "'status' => 'unreserved'")
    && str_contains($bridge, 'creator_affiliate_budget_attention'), 7);
cca_bridge_check($checks, 'refund path automatically reconciles Creator commission',
    str_contains($paymentRefund, 'mg_creator_campaign_affiliate_record_refund')
    && str_contains($paymentRefund, 'o.metadata_json')
    && str_contains($paymentRefund, "'after_creator_affiliate_refund'")
    && str_contains($refund, "'affiliate:refund:'"), 7);
cca_bridge_check($checks, 'refunds proportionally adjust earnings and budget obligations',
    str_contains($refund, 'mg_creator_campaign_affiliate_prorated_minor')
    && str_contains($refund, "event_type='adjustment'")
    && str_contains($refund, "'affiliate:refund-budget:'")
    && str_contains($refund, "status='released'"), 7);
cca_bridge_check($checks, 'unsafe payout states open disputes and pre-processing payouts cancel',
    str_contains($refund, "['processing','paid','reversed']")
    && str_contains($refund, 'mg_creator_campaign_affiliate_open_refund_dispute')
    && str_contains($refund, "['draft','approved']")
    && str_contains($refund, 'mg_creator_campaign_payout_append_event'), 7);
cca_bridge_check($checks, 'payment and refund hooks require outer transaction and use savepoints',
    str_contains($bridge, 'mg_creator_campaign_affiliate_require_transaction')
    && str_contains($bridge, 'SAVEPOINT creator_affiliate_paid_order')
    && str_contains($refund, 'SAVEPOINT creator_affiliate_refund'), 7);
cca_bridge_check($checks, 'affiliate payout remains provider-neutral and approval-controlled',
    !str_contains($bridge . $refund, '/v1/transfers')
    && !str_contains(strtolower($bridge . $refund), 'stripe secret')
    && str_contains($refund, 'creator_campaign_disputes'), 5);
cca_bridge_check($checks, 'exact overflow-safe refund proration',
    mg_creator_campaign_affiliate_prorated_minor(9999, 1, 3) === 3333
    && mg_creator_campaign_affiliate_prorated_minor(PHP_INT_MAX, PHP_INT_MAX - 1, PHP_INT_MAX) === PHP_INT_MAX - 1, 7);

$score = array_sum(array_map(static fn(array $check): int => $check['ok'] ? $check['points'] : 0, $checks));
$maximum = array_sum(array_column($checks, 'points'));
$failed = array_values(array_filter($checks, static fn(array $check): bool => !$check['ok']));

$result = [
    'ok' => $failed === [] && $score === $maximum,
    'score' => $score . '/' . $maximum,
    'rating' => number_format(($score / max(1, $maximum)) * 10, 1) . '/10',
    'checks' => $checks,
    'failed' => $failed,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
