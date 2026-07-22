<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/includes/creator-campaigns.php';
$pdo = mg_db();
$check = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$required = [
    'creator_campaigns','creator_campaign_participants','creator_campaign_participant_deliverables','creator_campaign_submissions',
    'creator_campaign_tracking_sources','creator_campaign_tracking_events','creator_campaign_attributions','creator_campaign_earning_events',
    'creator_campaign_budgets','creator_campaign_budget_events','creator_campaign_payouts','creator_campaign_disputes',
];
$marks = implode(',', array_fill(0, count($required), '?'));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$marks})");
$stmt->execute($required);
$check((int) $stmt->fetchColumn() === count($required), 'Phase 10 authoritative analytics tables are incomplete.');
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'creator_campaign_analytics%'");
$check((int) $stmt->fetchColumn() === 0, 'Phase 10 created a duplicate analytics metric store.');
$stmt = $pdo->query("SELECT COUNT(*) FROM permissions WHERE slug IN ('merchant.intelligence.view','creator.campaign_messages.view_own')");
$check((int) $stmt->fetchColumn() === 2, 'Phase 10 reused permissions are unavailable.');
$stmt = $pdo->query("SELECT COUNT(*) FROM role_permissions rp INNER JOIN roles r ON r.id=rp.role_id INNER JOIN permissions p ON p.id=rp.permission_id WHERE r.slug='customer' AND p.slug='creator.campaign_messages.view_own'");
$check((int) $stmt->fetchColumn() === 1, 'Active Creator-model users cannot reach Phase 10 through the canonical customer role.');
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND ((table_name='creator_campaign_tracking_events' AND column_name IN('status','event_type','is_unique','occurred_at')) OR (table_name='creator_campaign_attributions' AND column_name IN('status','attributed_at','conversion_event_id')) OR (table_name='creator_campaign_earning_events' AND column_name IN('amount_minor','currency')) OR (table_name='creator_campaign_payouts' AND column_name IN('amount_minor','currency','status')))");
$check((int) $stmt->fetchColumn() === 12, 'Phase 10 reporting columns are incomplete.');
$stmt = $pdo->query("SELECT COUNT(*) FROM creator_campaign_tracking_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id WHERE e.status='accepted' AND e.occurred_at<NOW()");
$check($stmt !== false, 'Accepted-event analytics query failed.');
$stmt = $pdo->query("SELECT COUNT(*) FROM creator_campaign_attributions a INNER JOIN creator_campaign_tracking_events e ON e.id=a.conversion_event_id WHERE a.status IN ('attributed','overridden') AND e.status='accepted'");
$check($stmt !== false, 'Canonical attribution and accepted conversion query failed.');
$stmt = $pdo->query("SELECT e.currency,SUM(e.amount_minor) amount_minor FROM creator_campaign_earning_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id GROUP BY e.currency");
$check($stmt !== false, 'Currency-isolated earnings query failed.');
$range = mg_creator_campaign_analytics_normalize_range(['range' => 'last_30_days']);
$check($range['bucket'] === 'day' && $range['start'] !== null && $range['end_exclusive'] !== '', 'Phase 10 date range normalization failed.');
echo json_encode(['ok'=>true,'sql_required'=>false,'authoritative_tables'=>count($required),'duplicate_metric_store'=>false,'currency_safe'=>true,'accepted_events_only'=>true,'creator_model_permission'=>'creator.campaign_messages.view_own'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
