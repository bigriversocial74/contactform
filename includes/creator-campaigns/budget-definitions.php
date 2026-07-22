<?php
declare(strict_types=1);

function mg_creator_campaign_budget_required_tables(): array
{
    return ['creator_campaign_budgets','creator_campaign_budget_reservations','creator_campaign_budget_events'];
}

function mg_creator_campaign_budget_installed(PDO $pdo): bool
{
    $tables=mg_creator_campaign_budget_required_tables();$p=implode(',',array_fill(0,count($tables),'?'));
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$p})");$stmt->execute($tables);
    return (int)$stmt->fetchColumn()===count($tables);
}

function mg_creator_campaign_budget_currency(mixed $value): string
{
    return mg_creator_campaign_compensation_currency($value);
}

function mg_creator_campaign_budget_minor(mixed $value,string $field): int
{
    $amount=mg_creator_campaign_compensation_minor($value,$field,false);
    return (int)$amount;
}
