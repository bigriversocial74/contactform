<?php
declare(strict_types=1);

function mg_demand_snapshot_window(DateTimeImmutable $asOf,int $horizonDays): array
{
    $utc=new DateTimeZone('UTC');
    $start=$asOf->setTimezone($utc)->setTime(0,0,0);
    $days=max(1,min($horizonDays,365));
    return [$start,$start->modify('+'.$days.' day'),$days];
}

function mg_demand_window_predicate(string $alias=''): string
{
    $prefix=$alias!==''?$alias.'.':'';
    return $prefix."status IN ('outstanding','redeemed')"
        .' AND '.$prefix.'expected_from<?'
        .' AND (('.$prefix.'expected_to IS NULL AND '.$prefix.'expected_from>=?)'
        .' OR ('.$prefix.'expected_to IS NOT NULL AND '.$prefix.'expected_to>?))';
}

function mg_demand_window_overlaps(DateTimeImmutable $signalFrom,?DateTimeImmutable $signalTo,DateTimeImmutable $windowFrom,DateTimeImmutable $windowTo): bool
{
    $utc=new DateTimeZone('UTC');
    $signalFrom=$signalFrom->setTimezone($utc);
    $signalTo=$signalTo?->setTimezone($utc);
    $windowFrom=$windowFrom->setTimezone($utc);
    $windowTo=$windowTo->setTimezone($utc);
    if($signalFrom >= $windowTo)return false;
    if($signalTo===null)return $signalFrom >= $windowFrom;
    return $signalTo > $windowFrom;
}
