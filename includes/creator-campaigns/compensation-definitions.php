<?php
declare(strict_types=1);

function mg_creator_campaign_compensation_required_tables(): array
{
    return [
        'creator_campaign_compensation_rules',
        'creator_campaign_compensation_rule_versions',
        'creator_campaign_earning_events',
    ];
}

function mg_creator_campaign_compensation_installed(PDO $pdo): bool
{
    $tables=mg_creator_campaign_compensation_required_tables();
    $p=implode(',',array_fill(0,count($tables),'?'));
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$p})");
    $stmt->execute($tables);
    return (int)$stmt->fetchColumn()===count($tables);
}

function mg_creator_campaign_compensation_types(): array
{
    return ['fixed_deliverable','percent_conversion','flat_conversion','milestone','manual_only'];
}

function mg_creator_campaign_compensation_triggers(): array
{
    return ['deliverable_verified','purchase_attributed','claim_attributed','redemption_attributed','milestone_approved','manual'];
}

function mg_creator_campaign_compensation_currency(mixed $value): string
{
    $currency=strtoupper(trim((string)$value));
    if(!preg_match('/^[A-Z]{3}$/',$currency)) throw new InvalidArgumentException('currency must be a three-letter code.');
    return $currency;
}

function mg_creator_campaign_compensation_minor(mixed $value,string $field,bool $nullable=false): ?int
{
    if(($value===null||$value==='')&&$nullable) return null;
    if(filter_var($value,FILTER_VALIDATE_INT)===false) throw new InvalidArgumentException("{$field} must be an integer minor-unit amount.");
    $amount=(int)$value;
    if($amount<0) throw new InvalidArgumentException("{$field} cannot be negative.");
    return $amount;
}

function mg_creator_campaign_compensation_rule_snapshot(array $input): array
{
    return [
        'compensation_type'=>(string)$input['compensation_type'],
        'trigger_type'=>(string)$input['trigger_type'],
        'currency'=>(string)$input['currency'],
        'flat_amount_minor'=>$input['flat_amount_minor'],
        'rate_bps'=>$input['rate_bps'],
        'minimum_source_amount_minor'=>$input['minimum_source_amount_minor'],
        'maximum_earning_minor'=>$input['maximum_earning_minor'],
        'terms_text'=>(string)$input['terms_text'],
    ];
}
