<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/intelligence/_intelligence.php';
$pdo = mg_db();
$rules = $pdo->query("SELECT * FROM demand_alert_rules WHERE status = 'active'")->fetchAll();
$triggered = 0;

foreach ($rules as $rule) {
    $days = max(1, (int) $rule['lookback_days']);
    $currentFrom = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $currentTo = date('Y-m-d');
    $previousFrom = date('Y-m-d', strtotime($currentFrom . ' -' . $days . ' days'));
    $previousTo = date('Y-m-d', strtotime($currentFrom . ' -1 day'));

    $queryMetric = static function (PDO $pdo, int $merchantId, string $metric, string $from, string $to): float {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(items_issued),0) items_issued,COALESCE(SUM(issued_value_cents),0) issued_value_cents,COALESCE(SUM(impressions),0) impressions,COALESCE(SUM(opens),0) opens,COALESCE(SUM(claims),0) claims,COALESCE(SUM(redemptions),0) redemptions FROM demand_fact_daily WHERE merchant_user_id=? AND metric_date BETWEEN ? AND ?');
        $stmt->execute([$merchantId, $from, $to]);
        $row = $stmt->fetch() ?: [];
        return match ($metric) {
            'items_issued' => (float) ($row['items_issued'] ?? 0),
            'issued_value_cents' => (float) ($row['issued_value_cents'] ?? 0),
            'claims' => (float) ($row['claims'] ?? 0),
            'redemptions' => (float) ($row['redemptions'] ?? 0),
            'open_rate' => mg_intelligence_safe_div((float) ($row['opens'] ?? 0), (float) ($row['impressions'] ?? 0)),
            'claim_rate' => mg_intelligence_safe_div((float) ($row['claims'] ?? 0), (float) ($row['items_issued'] ?? 0)),
            'redemption_rate' => mg_intelligence_safe_div((float) ($row['redemptions'] ?? 0), (float) ($row['claims'] ?? 0)),
            default => 0.0,
        };
    };

    $current = $queryMetric($pdo, (int) $rule['merchant_user_id'], (string) $rule['metric_key'], $currentFrom, $currentTo);
    $baseline = $queryMetric($pdo, (int) $rule['merchant_user_id'], (string) $rule['metric_key'], $previousFrom, $previousTo);
    $change = mg_intelligence_growth($current, $baseline);
    $threshold = (float) $rule['threshold_value'];
    $matches = match ((string) $rule['comparison']) {
        'gt' => $current > $threshold,
        'gte' => $current >= $threshold,
        'lt' => $current < $threshold,
        'lte' => $current <= $threshold,
        'change_gt' => $change !== null && $change > $threshold,
        'change_lt' => $change !== null && $change < $threshold,
        default => false,
    };

    if (!$matches) {
        $pdo->prepare("UPDATE demand_alert_events SET status='resolved',resolved_at=NOW() WHERE rule_id=? AND status IN ('open','acknowledged')")
            ->execute([(int) $rule['id']]);
        continue;
    }

    $existing = $pdo->prepare("SELECT id FROM demand_alert_events WHERE rule_id=? AND status IN ('open','acknowledged') LIMIT 1");
    $existing->execute([(int) $rule['id']]);
    if ($existing->fetchColumn()) continue;

    $pdo->prepare('INSERT INTO demand_alert_events (public_id,rule_id,merchant_user_id,observed_value,baseline_value,status,context_json,triggered_at) VALUES (?,?,?,?,?,\'open\',?,NOW())')
        ->execute([
            mg_intelligence_uuid(),
            (int) $rule['id'],
            (int) $rule['merchant_user_id'],
            $current,
            $baseline,
            json_encode(['change' => $change, 'current_window' => [$currentFrom, $currentTo], 'baseline_window' => [$previousFrom, $previousTo]], JSON_UNESCAPED_SLASHES),
        ]);
    $triggered++;
}

echo "Evaluated " . count($rules) . " alert rules; triggered {$triggered}.\n";
