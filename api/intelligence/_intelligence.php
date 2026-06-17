<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/distribution/_distribution.php';

function mg_intelligence_uuid(): string
{
    return mg_distribution_uuid();
}

function mg_intelligence_date(string $value, string $fallback): string
{
    $value = trim($value) ?: $fallback;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || strtotime($value) === false) {
        mg_fail('Invalid intelligence date.', 422);
    }
    return $value;
}

function mg_intelligence_json(mixed $value, int $max = 262144): ?string
{
    if ($value === null || $value === '' || $value === []) {
        return null;
    }
    if (!is_array($value)) {
        mg_fail('Expected an object.', 422);
    }
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > $max) {
        mg_fail('Intelligence payload is too large.', 422);
    }
    return $json;
}

function mg_intelligence_safe_div(float $numerator, float $denominator): float
{
    return $denominator > 0 ? $numerator / $denominator : 0.0;
}

function mg_intelligence_clamp(float $value, float $min = 0, float $max = 100): float
{
    return max($min, min($max, $value));
}

function mg_intelligence_growth(float $current, float $previous): ?float
{
    if ($previous == 0.0) {
        return $current == 0.0 ? 0.0 : null;
    }
    return ($current - $previous) / abs($previous);
}

function mg_intelligence_forecast_series(array $history, int $horizon, string $type, array $parameters = []): array
{
    $values = array_values(array_map(static fn($value): float => (float) $value, $history));
    if (!$values) {
        return array_fill(0, $horizon, 0.0);
    }

    if ($type === 'seasonal_naive') {
        $season = max(1, (int) ($parameters['season_days'] ?? 7));
        $result = [];
        for ($index = 0; $index < $horizon; $index++) {
            $sourceIndex = max(0, count($values) - $season + ($index % $season));
            $result[] = $values[$sourceIndex] ?? end($values);
        }
        return $result;
    }

    if ($type === 'exponential_smoothing') {
        $alpha = max(0.01, min(0.99, (float) ($parameters['alpha'] ?? 0.35)));
        $level = $values[0];
        foreach ($values as $value) {
            $level = $alpha * $value + (1 - $alpha) * $level;
        }
        return array_fill(0, $horizon, $level);
    }

    if ($type === 'moving_average') {
        $window = max(1, min(count($values), (int) ($parameters['window_days'] ?? 28)));
        $average = array_sum(array_slice($values, -$window)) / $window;
        return array_fill(0, $horizon, $average);
    }

    throw new InvalidArgumentException('Unsupported forecast model type.');
}

function mg_intelligence_interval(array $history, float $prediction, float $multiplier = 1.96): array
{
    $values = array_values(array_map(static fn($value): float => (float) $value, $history));
    if (count($values) < 2) {
        return [max(0, $prediction), max(0, $prediction)];
    }

    $mean = array_sum($values) / count($values);
    $variance = 0.0;
    foreach ($values as $value) {
        $variance += ($value - $mean) ** 2;
    }
    $standardDeviation = sqrt($variance / max(1, count($values) - 1));

    return [
        max(0, $prediction - $multiplier * $standardDeviation),
        max(0, $prediction + $multiplier * $standardDeviation),
    ];
}
