<?php
declare(strict_types=1);

/**
 * Creator Campaign Phase 10 reporting definitions.
 *
 * Analytics remains read-only. All metrics are derived from authoritative
 * Phase 3-9 records and are never persisted as parallel counters.
 */
function mg_creator_campaign_analytics_range_keys(): array
{
    return [
        'last_7_days' => 7,
        'last_30_days' => 30,
        'last_90_days' => 90,
        'last_365_days' => 365,
        'all_time' => null,
        'custom' => null,
    ];
}

function mg_creator_campaign_analytics_report_types(): array
{
    return ['campaigns', 'creators', 'channels', 'timeseries', 'deliverables'];
}

function mg_creator_campaign_analytics_normalize_range(array $filters): array
{
    $timezone = new DateTimeZone('UTC');
    $today = new DateTimeImmutable('today', $timezone);
    $rangeKey = strtolower(trim((string) ($filters['range'] ?? 'last_30_days')));
    $ranges = mg_creator_campaign_analytics_range_keys();
    if (!array_key_exists($rangeKey, $ranges)) {
        throw new InvalidArgumentException('Analytics date range is invalid.');
    }

    if ($rangeKey === 'custom') {
        $fromRaw = trim((string) ($filters['from'] ?? ''));
        $toRaw = trim((string) ($filters['to'] ?? ''));
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $fromRaw, $timezone);
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', $toRaw, $timezone);
        if (!$from || $from->format('Y-m-d') !== $fromRaw || !$to || $to->format('Y-m-d') !== $toRaw) {
            throw new InvalidArgumentException('Custom analytics dates must use YYYY-MM-DD.');
        }
        if ($from > $to) {
            throw new InvalidArgumentException('Analytics start date cannot be after the end date.');
        }
        if ($to > $today) {
            $to = $today;
        }
        $days = (int) $from->diff($to)->days + 1;
        if ($days > 731) {
            throw new InvalidArgumentException('Custom analytics ranges cannot exceed 731 days.');
        }
        $start = $from;
        $endExclusive = $to->modify('+1 day');
    } elseif ($rangeKey === 'all_time') {
        $start = null;
        $endExclusive = $today->modify('+1 day');
        $days = null;
    } else {
        $days = (int) $ranges[$rangeKey];
        $start = $today->modify('-' . ($days - 1) . ' days');
        $endExclusive = $today->modify('+1 day');
    }

    $bucket = $days === null || $days > 366 ? 'month' : ($days > 90 ? 'week' : 'day');

    return [
        'key' => $rangeKey,
        'start' => $start?->format('Y-m-d 00:00:00'),
        'end_exclusive' => $endExclusive->format('Y-m-d 00:00:00'),
        'from' => $start?->format('Y-m-d'),
        'to' => $endExclusive->modify('-1 day')->format('Y-m-d'),
        'days' => $days,
        'bucket' => $bucket,
    ];
}

function mg_creator_campaign_analytics_bucket_expression(string $column, string $bucket): string
{
    return match ($bucket) {
        'month' => "DATE_FORMAT({$column},'%Y-%m-01')",
        'week' => "DATE_FORMAT(DATE_SUB(DATE({$column}),INTERVAL WEEKDAY({$column}) DAY),'%Y-%m-%d')",
        default => "DATE_FORMAT({$column},'%Y-%m-%d')",
    };
}

function mg_creator_campaign_analytics_date_condition(string $column, array $range, array &$params): string
{
    $parts = [];
    if (!empty($range['start'])) {
        $parts[] = $column . '>=?';
        $params[] = (string) $range['start'];
    }
    $parts[] = $column . '<?';
    $params[] = (string) $range['end_exclusive'];
    return implode(' AND ', $parts);
}

function mg_creator_campaign_analytics_conversion_rate_bps(int $conversions, int $uniqueClicks): int
{
    if ($conversions <= 0 || $uniqueClicks <= 0) {
        return 0;
    }
    return (int) min(1000000, round(($conversions / $uniqueClicks) * 10000));
}

function mg_creator_campaign_analytics_money_map_add(array &$target, string $currency, int $amount): void
{
    $currency = strtoupper(trim($currency));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $currency = 'USD';
    }
    $target[$currency] = (int) ($target[$currency] ?? 0) + $amount;
}
