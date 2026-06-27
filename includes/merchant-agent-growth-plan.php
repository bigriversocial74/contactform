<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-forecast.php';

function mg_agent_growth_goal_config(string $goal): array
{
    $goal = strtolower(trim($goal));
    $map = [
        'claims' => ['key' => 'claims', 'label' => 'Claims growth', 'metric' => 'expected_claims'],
        'revenue' => ['key' => 'revenue', 'label' => 'Revenue growth', 'metric' => 'expected_revenue_cents'],
        'psr' => ['key' => 'psr', 'label' => 'PSR impact', 'metric' => 'expected_psr_cents'],
        'reactivation' => ['key' => 'reactivation', 'label' => 'Customer reactivation', 'metric' => 'required_followups'],
    ];
    return $map[$goal] ?? $map['revenue'];
}

function mg_agent_growth_risk_config(string $risk): array
{
    $risk = strtolower(trim($risk));
    $map = [
        'conservative' => ['key' => 'conservative', 'label' => 'Conservative', 'scenario' => 'conservative', 'claim_lift' => 0.85],
        'balanced' => ['key' => 'balanced', 'label' => 'Balanced', 'scenario' => 'base', 'claim_lift' => 1.00],
        'aggressive' => ['key' => 'aggressive', 'label' => 'Aggressive', 'scenario' => 'aggressive', 'claim_lift' => 1.25],
    ];
    return $map[$risk] ?? $map['balanced'];
}

function mg_agent_growth_effort_config(string $effort): array
{
    $effort = strtolower(trim($effort));
    $map = [
        'low' => ['key' => 'low', 'label' => 'Low', 'actions' => 3, 'message_multiplier' => 1.2, 'followup_multiplier' => 1.0],
        'medium' => ['key' => 'medium', 'label' => 'Medium', 'actions' => 5, 'message_multiplier' => 1.8, 'followup_multiplier' => 1.4],
        'high' => ['key' => 'high', 'label' => 'High', 'actions' => 8, 'message_multiplier' => 2.5, 'followup_multiplier' => 2.0],
    ];
    return $map[$effort] ?? $map['medium'];
}

function mg_agent_growth_input(array $input): array
{
    $goal = mg_agent_growth_goal_config((string)($input['goal'] ?? 'revenue'));
    $risk = mg_agent_growth_risk_config((string)($input['risk'] ?? 'balanced'));
    $effort = mg_agent_growth_effort_config((string)($input['effort'] ?? 'medium'));
    $timeframe = max(30, min(90, (int)($input['timeframe'] ?? 90)));
    if (!in_array($timeframe, [30, 60, 90], true)) $timeframe = $timeframe < 45 ? 30 : ($timeframe < 75 ? 60 : 90);
    return ['goal' => $goal, 'risk' => $risk, 'effort' => $effort, 'timeframe' => $timeframe];
}

function mg_agent_growth_target_summary(array $forecast, array $input): array
{
    $period = $forecast['periods'][(string)$input['timeframe']] ?? $forecast['periods']['90'] ?? [];
    $claims = (int)($period['expected_agent_influenced_claims'] ?? 0);
    $revenue = (int)($period['expected_redemption_value_cents'] ?? 0);
    $psr = (int)($period['psr_impact_estimate_cents'] ?? $revenue);
    $effort = $input['effort'] ?? mg_agent_growth_effort_config('medium');
    return [
        'goal' => $input['goal'],
        'risk' => $input['risk'],
        'effort' => $effort,
        'timeframe_days' => (int)$input['timeframe'],
        'target_claims' => $claims,
        'target_revenue_cents' => $revenue,
        'target_psr_impact_cents' => $psr,
        'required_messages' => max(1, (int)ceil(max(1, $claims) * (float)$effort['message_multiplier'])),
        'required_followups' => max(1, (int)ceil(max(1, $claims) * (float)$effort['followup_multiplier'])),
        'avg_redemption_value_cents' => (int)($period['avg_redemption_value_cents'] ?? ($forecast['summary']['avg_redemption_value_cents'] ?? 2500)),
    ];
}

function mg_agent_growth_score_row(array $row, array $input): int
{
    $goalKey = (string)($input['goal']['key'] ?? 'revenue');
    $claims = (int)($row['projected_claims'] ?? 0);
    $revenue = (int)($row['projected_revenue_cents'] ?? 0);
    $psr = (int)($row['projected_psr_impact_cents'] ?? $revenue);
    if ($goalKey === 'claims') return $claims * 1000 + (int)round($revenue / 100);
    if ($goalKey === 'psr') return $psr;
    if ($goalKey === 'reactivation') return $claims * 700 + (int)round($revenue / 250);
    return $revenue;
}

function mg_agent_growth_pick_rows(array $rows, array $input, int $limit): array
{
    usort($rows, static fn($a, $b) => mg_agent_growth_score_row($b, $input) <=> mg_agent_growth_score_row($a, $input));
    return array_slice($rows, 0, $limit);
}

function mg_agent_growth_action(string $type, string $title, string $body, array $row, array $input, string $actionUrl): array
{
    $claims = max(1, (int)($row['projected_claims'] ?? 1));
    $effort = $input['effort'] ?? mg_agent_growth_effort_config('medium');
    return [
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'label' => (string)($row['label'] ?? $title),
        'expected_claims' => $claims,
        'expected_revenue_cents' => (int)($row['projected_revenue_cents'] ?? 0),
        'expected_psr_impact_cents' => (int)($row['projected_psr_impact_cents'] ?? $row['projected_revenue_cents'] ?? 0),
        'required_messages' => max(1, (int)ceil($claims * (float)$effort['message_multiplier'])),
        'required_followups' => max(1, (int)ceil($claims * (float)$effort['followup_multiplier'])),
        'review_queue_url' => '/merchant-agent-approvals.php',
        'message_outbox_url' => '/merchant-agent-messages.php',
        'followups_url' => '/merchant-followups.php',
        'action_url' => $actionUrl,
    ];
}

function mg_agent_growth_recommended_actions(array $forecast, array $input): array
{
    $effort = $input['effort'] ?? mg_agent_growth_effort_config('medium');
    $limit = (int)$effort['actions'];
    $actions = [];
    foreach (mg_agent_growth_pick_rows($forecast['by_playbook'] ?? [], $input, max(1, (int)ceil($limit / 3))) as $row) {
        $actions[] = mg_agent_growth_action('playbook', 'Repeat high-performing playbook', 'Use this playbook as the next agent-approved campaign action because it has the strongest projected value for the selected goal.', $row, $input, '/merchant-agent-approvals.php');
    }
    foreach (mg_agent_growth_pick_rows($forecast['by_campaign'] ?? [], $input, max(1, (int)ceil($limit / 3))) as $row) {
        $actions[] = mg_agent_growth_action('campaign', 'Repeat or refresh campaign', 'Refresh this campaign with a merchant-reviewed agent action and route the next messages through the controlled outbox.', $row, $input, '/merchant-campaigns.php');
    }
    foreach (mg_agent_growth_pick_rows($forecast['by_customer'] ?? [], $input, max(1, (int)ceil($limit / 3))) as $row) {
        $actions[] = mg_agent_growth_action('customer', 'Follow up with agent-touched customer', 'Create a follow-up or message draft for this customer because the forecast shows near-term reactivation value.', $row, $input, '/merchant-customer.php?tab=timeline');
    }
    if (!$actions) {
        $target = mg_agent_growth_target_summary($forecast, $input);
        $actions[] = [
            'type' => 'starter',
            'title' => 'Start with controlled message drafts',
            'body' => 'No attributed winners exist yet, so begin with a small merchant-reviewed message and follow-up plan to create the first measurable agent signal.',
            'label' => 'Starter plan',
            'expected_claims' => max(1, (int)ceil($target['target_claims'] ?: 1)),
            'expected_revenue_cents' => (int)$target['target_revenue_cents'],
            'expected_psr_impact_cents' => (int)$target['target_psr_impact_cents'],
            'required_messages' => (int)$target['required_messages'],
            'required_followups' => (int)$target['required_followups'],
            'review_queue_url' => '/merchant-agent-approvals.php',
            'message_outbox_url' => '/merchant-agent-messages.php',
            'followups_url' => '/merchant-followups.php',
            'action_url' => '/merchant-agent-messages.php',
        ];
    }
    usort($actions, static fn($a, $b) => ((int)$b['expected_psr_impact_cents']) <=> ((int)$a['expected_psr_impact_cents']));
    return array_slice($actions, 0, $limit);
}

function mg_agent_growth_sections(array $forecast, array $actions): array
{
    return [
        'best_next_playbooks' => array_values(array_filter($actions, static fn($a) => ($a['type'] ?? '') === 'playbook')),
        'best_customers_to_follow_up' => array_values(array_filter($actions, static fn($a) => ($a['type'] ?? '') === 'customer')),
        'campaigns_worth_repeating' => array_values(array_filter($actions, static fn($a) => ($a['type'] ?? '') === 'campaign')),
        'message_opportunities' => array_values(array_map(static fn($a) => $a + ['opportunity' => 'message_draft'], $actions)),
        'followup_opportunities' => array_values(array_map(static fn($a) => $a + ['opportunity' => 'followup_task'], $actions)),
        'claim_revenue_psr_targets' => $forecast['periods'] ?? [],
    ];
}

function mg_agent_growth_plan(PDO $pdo, int $merchantId, array $input = []): array
{
    $planInput = mg_agent_growth_input($input);
    $risk = $planInput['risk'];
    $forecast = mg_agent_forecast($pdo, $merchantId, [
        'scenario' => $risk['scenario'],
        'days' => max(90, (int)$planInput['timeframe']),
        'claim_lift_multiplier' => (float)$risk['claim_lift'],
    ]);
    $target = mg_agent_growth_target_summary($forecast, $planInput);
    $actions = mg_agent_growth_recommended_actions($forecast, $planInput);
    return [
        'summary' => $target,
        'recommended_agent_actions' => $actions,
        'sections' => mg_agent_growth_sections($forecast, $actions),
        'forecast' => $forecast,
        'controls' => $planInput,
        'links' => [
            'forecast' => '/merchant-agent-forecast.php',
            'roi_attribution' => '/merchant-agent-roi.php',
            'outcome_analytics' => '/merchant-agent-analytics.php',
            'review_queue' => '/merchant-agent-approvals.php',
            'message_outbox' => '/merchant-agent-messages.php',
            'followups' => '/merchant-followups.php',
            'campaigns' => '/merchant-campaigns.php',
        ],
    ];
}
