<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-roi.php';

function mg_agent_forecast_scenario_config(string $scenario): array
{
    $scenario = strtolower(trim($scenario));
    $map = [
        'conservative' => ['key' => 'conservative', 'label' => 'Conservative', 'multiplier' => 0.75],
        'base' => ['key' => 'base', 'label' => 'Base', 'multiplier' => 1.00],
        'aggressive' => ['key' => 'aggressive', 'label' => 'Aggressive', 'multiplier' => 1.35],
    ];
    return $map[$scenario] ?? $map['base'];
}

function mg_agent_forecast_input(array $input): array
{
    $scenario = mg_agent_forecast_scenario_config((string)($input['scenario'] ?? 'base'));
    $days = max(30, min(365, (int)($input['days'] ?? 90)));
    $claimLift = max(0.1, min(5.0, (float)($input['claim_lift_multiplier'] ?? 1.0)));
    $avgOverride = (float)($input['avg_redemption_value'] ?? 0);
    return [
        'scenario' => $scenario,
        'days' => $days,
        'claim_lift_multiplier' => $claimLift,
        'avg_redemption_value_cents' => $avgOverride > 0 ? (int)round($avgOverride * 100) : 0,
    ];
}

function mg_agent_forecast_daily_rate(int $value, int $days): float
{
    return $days > 0 ? $value / $days : 0.0;
}

function mg_agent_forecast_money_rate(int $valueCents, int $days): float
{
    return $days > 0 ? $valueCents / $days : 0.0;
}

function mg_agent_forecast_average_redemption(array $summary, array $input): int
{
    if ((int)($input['avg_redemption_value_cents'] ?? 0) > 0) return (int)$input['avg_redemption_value_cents'];
    $claims = max(1, (int)($summary['agent_influenced_claims'] ?? 0));
    $revenue = (int)($summary['estimated_revenue_influenced_cents'] ?? 0);
    if ($revenue > 0) return max(1, (int)round($revenue / $claims));
    $totalClaims = max(1, (int)($summary['total_claims'] ?? 0));
    $totalRevenue = (int)($summary['total_redemption_value_cents'] ?? 0);
    if ($totalRevenue > 0) return max(1, (int)round($totalRevenue / $totalClaims));
    return 2500;
}

function mg_agent_forecast_project_period(array $summary, array $input, int $periodDays): array
{
    $windowDays = max(1, (int)($input['days'] ?? 90));
    $scenario = $input['scenario'] ?? mg_agent_forecast_scenario_config('base');
    $scenarioMultiplier = (float)($scenario['multiplier'] ?? 1.0);
    $claimLift = (float)($input['claim_lift_multiplier'] ?? 1.0);
    $baseClaims = (int)($summary['agent_influenced_claims'] ?? 0);
    $avgRedemption = mg_agent_forecast_average_redemption($summary, $input);
    $dailyClaims = mg_agent_forecast_daily_rate($baseClaims, $windowDays);
    $projectedClaims = (int)round($dailyClaims * $periodDays * $scenarioMultiplier * $claimLift);
    if ($projectedClaims === 0 && ((int)($summary['agent_touched_customers'] ?? 0) > 0 || (int)($summary['events_total'] ?? 0) > 0)) {
        $projectedClaims = (int)max(1, round(($periodDays / 30) * $scenarioMultiplier * min(1.0, $claimLift)));
    }
    $projectedRevenue = $projectedClaims * $avgRedemption;
    return [
        'days' => $periodDays,
        'expected_agent_influenced_claims' => $projectedClaims,
        'expected_redemption_value_cents' => $projectedRevenue,
        'message_to_claim_projection' => (int)($summary['message_to_claim_rate'] ?? 0),
        'followup_to_claim_projection' => (int)($summary['followup_to_claim_rate'] ?? 0),
        'psr_impact_estimate_cents' => $projectedRevenue,
        'avg_redemption_value_cents' => $avgRedemption,
    ];
}

function mg_agent_forecast_rows(array $items, array $input, int $periodDays, string $type): array
{
    $scenario = $input['scenario'] ?? mg_agent_forecast_scenario_config('base');
    $scenarioMultiplier = (float)($scenario['multiplier'] ?? 1.0);
    $claimLift = (float)($input['claim_lift_multiplier'] ?? 1.0);
    $windowDays = max(1, (int)($input['days'] ?? 90));
    $rows = [];
    foreach ($items as $item) {
        $claims = (int)($item['claims'] ?? 0);
        $revenue = (int)($item['revenue_cents'] ?? 0);
        $avg = $claims > 0 ? max(1, (int)round($revenue / $claims)) : (int)($input['avg_redemption_value_cents'] ?: 2500);
        $projectedClaims = (int)round(($claims / $windowDays) * $periodDays * $scenarioMultiplier * $claimLift);
        $projectedRevenue = $projectedClaims * $avg;
        $rows[] = [
            'id' => (string)($item['id'] ?? ''),
            'label' => (string)($item['label'] ?? 'Unknown ' . $type),
            'type' => $type,
            'historical_claims' => $claims,
            'historical_revenue_cents' => $revenue,
            'projected_claims' => $projectedClaims,
            'projected_revenue_cents' => $projectedRevenue,
            'projected_psr_impact_cents' => $projectedRevenue,
        ];
    }
    usort($rows, static fn($a, $b) => ((int)$b['projected_revenue_cents']) <=> ((int)$a['projected_revenue_cents']));
    return array_slice($rows, 0, 20);
}

function mg_agent_forecast_from_roi(array $roi, array $input): array
{
    $summary = $roi['summary'] ?? [];
    $periods = [
        '30' => mg_agent_forecast_project_period($summary, $input, 30),
        '60' => mg_agent_forecast_project_period($summary, $input, 60),
        '90' => mg_agent_forecast_project_period($summary, $input, 90),
    ];
    $basePeriod = $periods['90'];
    return [
        'summary' => [
            'scenario' => $input['scenario'],
            'source_window_days' => (int)$input['days'],
            'claim_lift_multiplier' => (float)$input['claim_lift_multiplier'],
            'avg_redemption_value_cents' => mg_agent_forecast_average_redemption($summary, $input),
            'expected_agent_influenced_claims_90d' => (int)$basePeriod['expected_agent_influenced_claims'],
            'expected_redemption_value_90d_cents' => (int)$basePeriod['expected_redemption_value_cents'],
            'message_to_claim_projection' => (int)$basePeriod['message_to_claim_projection'],
            'followup_to_claim_projection' => (int)$basePeriod['followup_to_claim_projection'],
            'psr_impact_estimate_90d_cents' => (int)$basePeriod['psr_impact_estimate_cents'],
            'historical_agent_influenced_claims' => (int)($summary['agent_influenced_claims'] ?? 0),
            'historical_revenue_influenced_cents' => (int)($summary['estimated_revenue_influenced_cents'] ?? 0),
        ],
        'periods' => $periods,
        'by_playbook' => mg_agent_forecast_rows($roi['by_playbook'] ?? [], $input, 90, 'playbook'),
        'by_campaign' => mg_agent_forecast_rows($roi['by_campaign'] ?? [], $input, 90, 'campaign'),
        'by_customer' => mg_agent_forecast_rows($roi['by_customer'] ?? [], $input, 90, 'customer'),
        'historical_daily' => $roi['daily'] ?? [],
        'data_sources' => $roi['data_sources'] ?? [],
        'links' => [
            'roi_attribution' => '/merchant-agent-roi.php',
            'outcome_analytics' => '/merchant-agent-analytics.php',
            'customer_timeline' => '/merchant-customer.php?tab=timeline',
            'message_outbox' => '/merchant-agent-messages.php',
            'claims' => '/merchant-claims.php',
        ],
    ];
}

function mg_agent_forecast(PDO $pdo, int $merchantId, array $input = []): array
{
    $forecastInput = mg_agent_forecast_input($input);
    $roi = mg_agent_roi($pdo, $merchantId, ['days' => $forecastInput['days']]);
    return mg_agent_forecast_from_roi($roi, $forecastInput);
}
