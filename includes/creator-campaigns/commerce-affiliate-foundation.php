<?php
declare(strict_types=1);

/** Creator Campaign affiliate commerce foundation and exact money helpers. */

function mg_creator_campaign_affiliate_decode_json(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_creator_campaign_affiliate_tables_ready(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) return $cache[$key];

    $tables = [
        'creator_campaigns',
        'creator_campaign_products',
        'creator_campaign_participants',
        'creator_campaign_tracking_sources',
        'creator_campaign_tracking_events',
        'creator_campaign_attributions',
        'creator_campaign_compensation_rules',
        'creator_campaign_compensation_rule_versions',
        'creator_campaign_earning_events',
        'creator_campaign_budgets',
        'creator_campaign_budget_reservations',
        'creator_campaign_budget_events',
        'creator_campaign_payouts',
        'creator_campaign_payout_items',
        'creator_campaign_payout_events',
        'creator_campaign_disputes',
        'creator_campaign_dispute_events',
        'merchant_workspaces',
        'commerce_orders',
    ];
    $placeholders = implode(',', array_fill(0, count($tables), '?'));
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema=DATABASE() AND table_name IN ({$placeholders})"
    );
    $stmt->execute($tables);
    return $cache[$key] = (int) $stmt->fetchColumn() === count($tables);
}

function mg_creator_campaign_affiliate_require_transaction(PDO $pdo): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Creator affiliate commerce hooks require an active transaction.');
    }
}

function mg_creator_campaign_affiliate_mul_div_floor(int $multiplicand, int $multiplier, int $divisor): int
{
    if ($multiplicand < 0 || $multiplier < 0 || $divisor < 1 || $multiplicand >= $divisor) {
        throw new InvalidArgumentException('Exact prorating inputs are invalid.');
    }
    $quotient = 0;
    $remainder = 0;
    foreach (str_split(decbin($multiplier)) as $bit) {
        if ($quotient > intdiv(PHP_INT_MAX, 2)) throw new OverflowException('Exact prorating overflow.');
        $quotient *= 2;
        if ($remainder >= $divisor - $remainder) {
            $quotient++;
            $remainder -= $divisor - $remainder;
        } else {
            $remainder *= 2;
        }
        if ($bit === '1') {
            if ($remainder >= $divisor - $multiplicand) {
                $quotient++;
                $remainder -= $divisor - $multiplicand;
            } else {
                $remainder += $multiplicand;
            }
        }
    }
    return $quotient;
}

function mg_creator_campaign_affiliate_prorated_minor(int $amountMinor, int $partMinor, int $wholeMinor): int
{
    if ($amountMinor < 1 || $partMinor < 1 || $wholeMinor < 1) return 0;
    if ($partMinor >= $wholeMinor) return $amountMinor;
    $wholeUnits = intdiv($amountMinor, $wholeMinor);
    $remainder = $amountMinor % $wholeMinor;
    if ($wholeUnits > 0 && $partMinor > intdiv(PHP_INT_MAX, $wholeUnits)) {
        throw new OverflowException('Exact prorating overflow.');
    }
    return ($wholeUnits * $partMinor)
        + mg_creator_campaign_affiliate_mul_div_floor($remainder, $partMinor, $wholeMinor);
}

function mg_creator_campaign_affiliate_item_totals(array $items): array
{
    $totals = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $productId = (int) ($item['product_id'] ?? 0);
        $versionId = (int) ($item['product_version_id'] ?? 0);
        $lineTotal = (int) ($item['line_total_cents'] ?? $item['line_total_minor'] ?? 0);
        if ($productId < 1 || $lineTotal < 1) continue;
        if (!isset($totals[$productId])) {
            $totals[$productId] = ['amount_minor' => 0, 'version_ids' => []];
        }
        $totals[$productId]['amount_minor'] += $lineTotal;
        if ($versionId > 0) $totals[$productId]['version_ids'][$versionId] = true;
    }
    return $totals;
}
