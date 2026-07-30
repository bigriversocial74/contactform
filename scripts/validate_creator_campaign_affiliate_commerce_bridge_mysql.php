<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/includes/creator-campaigns.php';
$pdo = mg_db();
$required = [
    'creator_campaign_tracking_sources','creator_campaign_tracking_events','creator_campaign_attributions',
    'creator_campaign_compensation_rules','creator_campaign_compensation_rule_versions','creator_campaign_earning_events',
    'creator_campaign_budgets','creator_campaign_budget_reservations','creator_campaign_budget_events',
    'creator_campaign_payouts','creator_campaign_payout_items','creator_campaign_payout_events',
    'creator_campaign_disputes','creator_campaign_dispute_events','commerce_orders',
];
$placeholders = implode(',', array_fill(0, count($required), '?'));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$placeholders})");
$stmt->execute($required);
if ((int)$stmt->fetchColumn() !== count($required)) throw new RuntimeException('Creator affiliate commerce tables are incomplete.');
if (!mg_creator_campaign_affiliate_tables_ready($pdo)) throw new RuntimeException('Creator affiliate commerce bridge did not recognize the installed schema.');
foreach ([
    ['creator_campaign_tracking_events','uq_cc_tracking_event_key'],
    ['creator_campaign_earning_events','uq_cc_earning_idempotency'],
    ['creator_campaign_budget_reservations','uq_cc_budget_reservation_earning'],
    ['creator_campaign_payout_items','uq_cc_payout_item_active_reservation'],
    ['creator_campaign_disputes','uq_cc_dispute_active_source'],
] as [$table,$index]) {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=? AND non_unique=0');
    $stmt->execute([$table,$index]);
    if ((int)$stmt->fetchColumn() < 1) throw new RuntimeException("Missing unique contract {$table}.{$index}");
}
if (mg_creator_campaign_affiliate_prorated_minor(9999,1,3) !== 3333) throw new RuntimeException('Exact refund proration failed.');
echo json_encode(['ok'=>true,'tables'=>count($required),'unique_contracts'=>5,'exact_proration'=>true], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR) . PHP_EOL;
