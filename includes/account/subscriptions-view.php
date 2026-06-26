<?php
require_once dirname(__DIR__) . '/pricing-packages.php';

if (!function_exists('mg_subscription_view_db')) {
    function mg_subscription_view_db(): ?PDO
    {
        if (!function_exists('mg_db')) {
            $dbPath = dirname(__DIR__, 2) . '/api/db.php';
            if (is_file($dbPath)) {
                require_once $dbPath;
            }
        }

        if (!function_exists('mg_db')) {
            return null;
        }

        try {
            return mg_db();
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('mg_subscription_view_table_exists')) {
    function mg_subscription_view_table_exists(PDO $pdo, string $table): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }

        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('mg_subscription_view_columns')) {
    function mg_subscription_view_columns(PDO $pdo, string $table): array
    {
        static $cache = [];

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return [];
        }

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            $cache[$table] = array_fill_keys(array_map('strval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME')), true);
        } catch (Throwable $e) {
            $cache[$table] = [];
        }

        return $cache[$table];
    }
}

if (!function_exists('mg_subscription_view_first_column')) {
    function mg_subscription_view_first_column(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (!empty($columns[$candidate])) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('mg_subscription_view_scalar')) {
    function mg_subscription_view_scalar(PDO $pdo, string $sql, array $params = [], int|float $fallback = 0): int|float
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $value = $stmt->fetchColumn();
            return is_numeric($value) ? $value + 0 : $fallback;
        } catch (Throwable $e) {
            return $fallback;
        }
    }
}

if (!function_exists('mg_subscription_view_slug')) {
    function mg_subscription_view_slug(mixed $value): string
    {
        $value = strtolower(trim((string)$value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
        return trim($value, '-');
    }
}

if (!function_exists('mg_subscription_view_money')) {
    function mg_subscription_view_money(int|float $cents): string
    {
        return '$' . number_format(((float)$cents) / 100, 0);
    }
}

if (!function_exists('mg_subscription_view_short_number')) {
    function mg_subscription_view_short_number(int|float $value): string
    {
        $value = (float)$value;
        if ($value >= 1000000) {
            return rtrim(rtrim(number_format($value / 1000000, 1), '0'), '.') . 'M';
        }
        if ($value >= 1000) {
            return rtrim(rtrim(number_format($value / 1000, 1), '0'), '.') . 'K';
        }
        return number_format($value);
    }
}

if (!function_exists('mg_subscription_view_decode_json')) {
    function mg_subscription_view_decode_json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('mg_subscription_view_nested_value')) {
    function mg_subscription_view_nested_value(array $source, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== '' && $source[$key] !== null) {
                return $source[$key];
            }
        }

        foreach (['subscription', 'billing', 'package', 'plan', 'metadata'] as $group) {
            if (!empty($source[$group]) && is_array($source[$group])) {
                foreach ($keys as $key) {
                    if (array_key_exists($key, $source[$group]) && $source[$group][$key] !== '' && $source[$group][$key] !== null) {
                        return $source[$group][$key];
                    }
                }
            }
        }

        return null;
    }
}

if (!function_exists('mg_subscription_view_plan_by_id')) {
    function mg_subscription_view_plan_by_id(array $plans, string $planId, ?array $fallback = null): array
    {
        $planId = mg_subscription_view_slug($planId);
        foreach ($plans as $plan) {
            if (mg_subscription_view_slug((string)($plan['id'] ?? $plan['name'] ?? '')) === $planId) {
                return $plan;
            }
        }
        return $fallback ?? ($plans[0] ?? []);
    }
}

if (!function_exists('mg_subscription_view_known_plan_id')) {
    function mg_subscription_view_known_plan_id(mixed $value, array $plans, string $fallback = 'starter'): string
    {
        $value = mg_subscription_view_slug($value);
        if ($value === '') {
            return $fallback;
        }

        foreach ($plans as $plan) {
            $id = mg_subscription_view_slug((string)($plan['id'] ?? ''));
            $name = mg_subscription_view_slug((string)($plan['name'] ?? ''));
            if ($value === $id || $value === $name) {
                return $id !== '' ? $id : $name;
            }
        }

        return $fallback;
    }
}

if (!function_exists('mg_subscription_view_load_platform_subscription')) {
    function mg_subscription_view_load_platform_subscription(?PDO $pdo, int $userId, array $plans): array
    {
        $result = [
            'package_id' => 'starter',
            'status' => 'Active',
            'billing_cycle' => 'Monthly',
            'renews_on' => date('M j, Y', strtotime('+30 days')),
            'next_charge_label' => null,
            'source' => 'pricing_fallback',
        ];

        if (!empty($GLOBALS['user']) && is_array($GLOBALS['user'])) {
            $sessionValue = mg_subscription_view_nested_value($GLOBALS['user'], [
                'package_id', 'package_slug', 'pricing_package_id', 'pricing_plan_id', 'plan_id', 'plan_slug', 'current_plan', 'current_package', 'subscription_plan',
            ]);
            if ($sessionValue !== null) {
                $result['package_id'] = mg_subscription_view_known_plan_id($sessionValue, $plans, $result['package_id']);
                $result['source'] = 'session';
            }
        }

        if (!$pdo || $userId < 1 || !mg_subscription_view_table_exists($pdo, 'subscriptions') || !mg_subscription_view_table_exists($pdo, 'subscription_plans')) {
            return $result;
        }

        try {
            $stmt = $pdo->prepare("SELECT s.status,s.amount_cents,s.currency,s.current_period_end,s.next_billing_at,s.cancel_at_period_end,s.recovery_status,s.metadata_json,p.name plan_name,p.interval_unit,p.interval_count,p.metadata_json plan_metadata
              FROM subscriptions s
              LEFT JOIN subscription_plans p ON p.id = s.plan_id
              WHERE s.subscriber_user_id = ?
              ORDER BY FIELD(s.status,'active','trialing','cancel_pending','past_due','pending_payment','paused','canceled','expired'), s.updated_at DESC, s.id DESC
              LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $row = null;
        }

        if (!$row) {
            return $result;
        }

        $subscriptionMeta = mg_subscription_view_decode_json($row['metadata_json'] ?? null);
        $planMeta = mg_subscription_view_decode_json($row['plan_metadata'] ?? null);
        $packageValue = mg_subscription_view_nested_value($subscriptionMeta, ['package_id', 'package_slug', 'pricing_package_id', 'pricing_plan_id', 'platform_package_id', 'plan_slug']);
        if ($packageValue === null) {
            $packageValue = mg_subscription_view_nested_value($planMeta, ['package_id', 'package_slug', 'pricing_package_id', 'pricing_plan_id', 'platform_package_id', 'plan_slug']);
        }
        if ($packageValue !== null) {
            $result['package_id'] = mg_subscription_view_known_plan_id($packageValue, $plans, $result['package_id']);
        }

        $status = ucwords(str_replace('_', ' ', (string)($row['status'] ?? 'active')));
        if ((int)($row['cancel_at_period_end'] ?? 0) === 1) {
            $status = 'Cancel Pending';
        }

        $periodEnd = (string)($row['current_period_end'] ?? '');
        $nextBillingAt = (string)($row['next_billing_at'] ?? '');
        $renewalDate = $nextBillingAt !== '' ? $nextBillingAt : $periodEnd;
        $intervalUnit = (string)($row['interval_unit'] ?? 'month');
        $intervalCount = max(1, (int)($row['interval_count'] ?? 1));
        $billingCycle = match ($intervalUnit) {
            'year' => $intervalCount === 1 ? 'Yearly' : $intervalCount . '-Year',
            'week' => $intervalCount === 1 ? 'Weekly' : $intervalCount . '-Week',
            default => $intervalCount === 1 ? 'Monthly' : $intervalCount . '-Month',
        };

        $result['status'] = $status;
        $result['billing_cycle'] = $billingCycle;
        $result['renews_on'] = $renewalDate !== '' ? date('M j, Y', strtotime($renewalDate)) : $result['renews_on'];
        $result['next_charge_label'] = is_numeric($row['amount_cents'] ?? null) ? mg_subscription_view_money((int)$row['amount_cents']) : null;
        $result['source'] = 'subscriptions_table';

        return $result;
    }
}

if (!function_exists('mg_subscription_view_count_owned')) {
    function mg_subscription_view_count_owned(PDO $pdo, string $table, int $userId, array $ownerColumns, array $statusFilters = []): int
    {
        if ($userId < 1 || !mg_subscription_view_table_exists($pdo, $table)) {
            return 0;
        }

        $columns = mg_subscription_view_columns($pdo, $table);
        $ownerColumn = mg_subscription_view_first_column($columns, $ownerColumns);
        if (!$ownerColumn) {
            return 0;
        }

        $where = ["`$ownerColumn` = ?"];
        $params = [$userId];

        foreach ($statusFilters as $column => $values) {
            if (!empty($columns[$column]) && is_array($values) && $values) {
                $where[] = "`$column` IN (" . implode(',', array_fill(0, count($values), '?')) . ')';
                foreach ($values as $value) {
                    $params[] = $value;
                }
            }
        }

        return (int)mg_subscription_view_scalar($pdo, 'SELECT COUNT(*) FROM `' . $table . '` WHERE ' . implode(' AND ', $where), $params, 0);
    }
}

if (!function_exists('mg_subscription_view_sum_owned')) {
    function mg_subscription_view_sum_owned(PDO $pdo, string $table, string $sumColumn, int $userId, array $ownerColumns, array $statusFilters = []): int
    {
        if ($userId < 1 || !mg_subscription_view_table_exists($pdo, $table)) {
            return 0;
        }

        $columns = mg_subscription_view_columns($pdo, $table);
        if (empty($columns[$sumColumn])) {
            return 0;
        }

        $ownerColumn = mg_subscription_view_first_column($columns, $ownerColumns);
        if (!$ownerColumn) {
            return 0;
        }

        $where = ["`$ownerColumn` = ?"];
        $params = [$userId];

        foreach ($statusFilters as $column => $values) {
            if (!empty($columns[$column]) && is_array($values) && $values) {
                $where[] = "`$column` IN (" . implode(',', array_fill(0, count($values), '?')) . ')';
                foreach ($values as $value) {
                    $params[] = $value;
                }
            }
        }

        return (int)mg_subscription_view_scalar($pdo, 'SELECT COALESCE(SUM(`' . $sumColumn . '`),0) FROM `' . $table . '` WHERE ' . implode(' AND ', $where), $params, 0);
    }
}

if (!function_exists('mg_subscription_view_usage')) {
    function mg_subscription_view_usage(?PDO $pdo, int $userId, array $currentPlan): array
    {
        $limits = is_array($currentPlan['limits'] ?? null) ? $currentPlan['limits'] : [];
        $fallbackPromotionsLimit = max(50, (int)($limits['max_active_campaigns'] ?? 50));
        $fallbackRewards = max(245, max(1, (int)($limits['max_rewards'] ?? 5)) * 49);
        $fallbackStamps = is_numeric($limits['monthly_stamps_included'] ?? null) ? (int)$limits['monthly_stamps_included'] : 0;

        $usage = [
            'promotions_used' => 12,
            'promotions_limit' => $fallbackPromotionsLimit,
            'rewards_distributed' => $fallbackRewards,
            'customer_engagements' => max(1200, $fallbackStamps + 200),
            'revenue_cents' => 634000,
            'stamps_available' => null,
            'stamps_used' => null,
            'data_source' => 'fallback',
        ];

        if (!$pdo || $userId < 1) {
            return $usage;
        }

        $hasRealData = false;

        if (mg_subscription_view_table_exists($pdo, 'account_stamp_balances')) {
            $columns = mg_subscription_view_columns($pdo, 'account_stamp_balances');
            if (!empty($columns['account_user_id'])) {
                try {
                    $stmt = $pdo->prepare('SELECT balance,included_monthly_stamps,used_stamps FROM account_stamp_balances WHERE account_user_id = ? ORDER BY current_period_key DESC, updated_at DESC LIMIT 1');
                    $stmt->execute([$userId]);
                    $stampRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    if ($stampRow) {
                        $usage['stamps_available'] = (int)($stampRow['balance'] ?? 0);
                        $usage['stamps_used'] = (int)($stampRow['used_stamps'] ?? 0);
                        $usage['customer_engagements'] = max((int)$usage['customer_engagements'], (int)($stampRow['used_stamps'] ?? 0) + (int)($stampRow['balance'] ?? 0));
                        $hasRealData = true;
                    }
                } catch (Throwable $e) {}
            }
        }

        $promotions = 0;
        $promotions += mg_subscription_view_count_owned($pdo, 'feed_posts', $userId, ['user_id', 'owner_user_id', 'author_user_id', 'merchant_user_id'], ['status' => ['published', 'promoted', 'active']]);
        $promotions += mg_subscription_view_count_owned($pdo, 'catalog_products', $userId, ['owner_user_id', 'merchant_user_id', 'created_by_user_id', 'user_id'], ['status' => ['published', 'active']]);
        if ($promotions > 0) {
            $usage['promotions_used'] = $promotions;
            $hasRealData = true;
        }

        $rewards = 0;
        $rewards += mg_subscription_view_count_owned($pdo, 'microgift_inbox_items', $userId, ['sender_user_id', 'merchant_user_id', 'user_id'], ['folder' => ['sent', 'claimed', 'inbox']]);
        $rewards += mg_subscription_view_count_owned($pdo, 'microgift_instances', $userId, ['sender_user_id', 'merchant_user_id', 'purchaser_user_id', 'owner_user_id', 'created_by_user_id'], ['status' => ['issued', 'delivered', 'claim_pending', 'claimed', 'redeemable', 'redeemed']]);
        if ($rewards > 0) {
            $usage['rewards_distributed'] = $rewards;
            $hasRealData = true;
        }

        $engagements = 0;
        $engagements += mg_subscription_view_count_owned($pdo, 'microgift_claims', $userId, ['claimant_user_id', 'previous_owner_user_id'], ['status' => ['verified', 'completed']]);
        $engagements += mg_subscription_view_count_owned($pdo, 'microgift_redemptions', $userId, ['merchant_user_id', 'claimant_user_id'], ['status' => ['completed']]);
        $engagements += mg_subscription_view_count_owned($pdo, 'website_analytics_events', $userId, ['user_id', 'actor_user_id', 'owner_user_id'], []);
        if ($engagements > 0) {
            $usage['customer_engagements'] = max((int)$usage['customer_engagements'], $engagements);
            $hasRealData = true;
        }

        $revenueCents = 0;
        $revenueCents += mg_subscription_view_sum_owned($pdo, 'commerce_orders', 'total_cents', $userId, ['merchant_user_id', 'seller_user_id', 'owner_user_id', 'user_id'], ['payment_status' => ['paid', 'partially_refunded']]);
        $revenueCents += mg_subscription_view_sum_owned($pdo, 'microgift_redemptions', 'amount_cents', $userId, ['merchant_user_id'], ['status' => ['completed']]);
        $revenueCents += mg_subscription_view_sum_owned($pdo, 'tips', 'amount_cents', $userId, ['recipient_user_id', 'owner_user_id', 'user_id'], ['status' => ['posted']]);
        if ($revenueCents > 0) {
            $usage['revenue_cents'] = $revenueCents;
            $hasRealData = true;
        }

        $usage['data_source'] = $hasRealData ? 'database' : 'fallback';
        return $usage;
    }
}

$plans = mg_public_pricing_packages();
$packageSummary = mg_pricing_package_summary();
$accountUserId = (int)($user['id'] ?? 0);
$pdo = mg_subscription_view_db();
$subscriptionSnapshot = mg_subscription_view_load_platform_subscription($pdo, $accountUserId, $plans);
$currentPackageId = mg_subscription_view_known_plan_id($subscriptionSnapshot['package_id'] ?? 'starter', $plans, 'starter');
$currentPlan = mg_subscription_view_plan_by_id($plans, $currentPackageId);
$currentLimits = is_array($currentPlan['limits'] ?? null) ? $currentPlan['limits'] : [];
$currentPromotionsLimit = max(50, (int)($currentLimits['max_active_campaigns'] ?? 50));
$currentPlanPrice = (string)($currentPlan['price_label'] ?? '$0');
$currentBillingLabel = (string)($currentPlan['billing_label'] ?? '/mo');
$subscriptionUsage = mg_subscription_view_usage($pdo, $accountUserId, $currentPlan);
$nextChargeLabel = $subscriptionSnapshot['next_charge_label'] ?: $currentPlanPrice . ($currentBillingLabel === '/mo' ? '.00' : '');
?>
<style>
  .mg-subscription-redesign,
  .mg-subscription-redesign *{box-sizing:border-box}
  .mg-subscription-redesign{overflow:hidden;background:#fff;border-color:#dbe7f8;box-shadow:0 22px 55px rgba(13,38,76,.08)}
  .mg-subscription-redesign .mg-app-panel-head{align-items:flex-start;padding:24px 26px 20px;background:linear-gradient(180deg,#fff,#fbfdff);border-bottom:1px solid #dbe7f8}
  .mg-subscription-redesign .mg-app-panel-head h2{margin:0;color:#03132e;font-size:24px;line-height:1.1;font-weight:950;letter-spacing:-.05em}
  .mg-subscription-redesign .mg-app-panel-head p{margin-top:8px;color:#536789;font-size:14px;font-weight:550}
  .mg-subscription-redesign .mg-app-panel-body{padding:24px 26px 28px;background:radial-gradient(circle at 88% 0,rgba(47,93,245,.08),transparent 30%),linear-gradient(180deg,#fff 0%,#fbfdff 100%)}
  .mg-sub-hero{display:grid;grid-template-columns:minmax(300px,.9fr) minmax(460px,1.28fr);gap:22px;align-items:stretch;margin-bottom:26px;padding:24px;border-radius:20px;background:radial-gradient(circle at 34% 12%,rgba(51,91,255,.4),transparent 20%),radial-gradient(circle at 88% 86%,rgba(136,63,255,.38),transparent 26%),linear-gradient(135deg,#09194a 0%,#111266 52%,#12072c 100%);box-shadow:0 22px 48px rgba(10,20,82,.28);position:relative;overflow:hidden}
  .mg-sub-hero:before{content:"";position:absolute;inset:-80px -120px auto auto;width:420px;height:280px;border-radius:999px;border:2px solid rgba(78,109,255,.4);transform:rotate(28deg);opacity:.65}
  .mg-sub-hero:after{content:"";position:absolute;inset:auto auto -90px 19%;width:280px;height:240px;border-radius:999px;background:radial-gradient(circle,rgba(58,83,255,.2),transparent 68%);opacity:.8}
  .mg-sub-current,.mg-sub-metrics{position:relative;z-index:2}.mg-sub-current{color:#fff;padding:4px 0}.mg-sub-kicker{display:inline-flex;align-items:center;gap:8px;margin-bottom:9px;color:rgba(255,255,255,.76);font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.mg-sub-kicker:before{content:"";width:7px;height:7px;border-radius:999px;background:#30d49b;box-shadow:0 0 0 6px rgba(48,212,155,.15)}
  .mg-sub-plan-title{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.mg-sub-plan-title h3{margin:0;color:#fff;font-size:30px;line-height:1.05;font-weight:950;letter-spacing:-.05em}.mg-sub-status{display:inline-flex;align-items:center;min-height:26px;padding:0 10px;border-radius:999px;background:#19a873;color:#fff;font-size:12px;font-weight:950}.mg-sub-status.is-warning{background:#f59e0b}.mg-sub-current-copy{max-width:560px;margin:15px 0 18px;color:rgba(255,255,255,.82);font-size:14px;line-height:1.55;font-weight:520}.mg-sub-current-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:0;margin-top:20px;padding-top:18px;border-top:1px solid rgba(255,255,255,.16)}.mg-sub-current-meta div{padding-right:18px}.mg-sub-current-meta div+div{padding-left:18px;border-left:1px solid rgba(255,255,255,.13)}.mg-sub-current-meta span{display:block;margin-bottom:4px;color:rgba(255,255,255,.64);font-size:12px;font-weight:750}.mg-sub-current-meta strong{display:block;color:#fff;font-size:15px;line-height:1.3;font-weight:950}
  .mg-sub-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:0;align-self:center;min-height:150px;border-radius:14px;background:rgba(255,255,255,.96);box-shadow:0 18px 45px rgba(0,0,0,.14);overflow:hidden}.mg-sub-metric{display:flex;flex-direction:column;justify-content:center;min-height:150px;padding:18px;text-align:center;border-right:1px solid #e2e9f5}.mg-sub-metric:last-child{border-right:0}.mg-sub-metric-icon{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;margin:0 auto 14px;border-radius:14px;background:#eef3ff;color:#3159ff;font-size:18px;font-weight:950}.mg-sub-metric:nth-child(2) .mg-sub-metric-icon{background:#f6eaff;color:#a53dff}.mg-sub-metric:nth-child(3) .mg-sub-metric-icon{background:#fff0e8;color:#ff7628}.mg-sub-metric:nth-child(4) .mg-sub-metric-icon{background:#fff7de;color:#d69a00}.mg-sub-metric span{margin-bottom:8px;color:#536789;font-size:12px;line-height:1.35;font-weight:750}.mg-sub-metric strong{color:#071a44;font-size:clamp(19px,1.5vw,25px);line-height:1.1;font-weight:950;letter-spacing:-.045em}.mg-sub-metric small{margin-top:8px;color:#7788a3;font-size:12px;font-weight:750}
  .mg-sub-section-top{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin:0 0 20px}.mg-sub-section-title h3{margin:0;color:#061735;font-size:22px;line-height:1.15;font-weight:950;letter-spacing:-.04em}.mg-sub-section-title p{max-width:920px;margin:9px 0 0;color:#536789;font-size:15px;line-height:1.55;font-weight:520}.mg-sub-toggle{display:inline-grid;grid-template-columns:1fr 1fr;min-width:310px;padding:5px;gap:4px;border-radius:16px;border:1px solid #e0e8f5;background:#f1f5fb;box-shadow:inset 0 0 0 1px rgba(255,255,255,.7)}.mg-sub-toggle a{display:flex;align-items:center;justify-content:center;min-height:36px;padding:0 16px;border-radius:12px;color:#435b80!important;font-size:13px;font-weight:950;white-space:nowrap;text-decoration:none}.mg-sub-toggle a:first-child{background:#fff;color:#3159ff!important;box-shadow:0 8px 20px rgba(13,38,76,.1)}
  .mg-sub-plans{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px}.mg-sub-plan-card{position:relative;display:flex;flex-direction:column;min-height:440px;border:1px solid #dbe7f8;border-radius:17px;background:radial-gradient(circle at 100% 0,rgba(47,93,245,.06),transparent 28%),#fff;box-shadow:0 16px 38px rgba(13,38,76,.06);overflow:hidden}.mg-sub-plan-card.is-featured{border-color:#3b5bff;box-shadow:0 20px 48px rgba(47,93,245,.16)}.mg-sub-ribbon{height:34px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#3159ff 0%,#5d6cff 100%);color:#fff;font-size:12px;font-weight:950}.mg-sub-plan-inner{display:flex;flex-direction:column;height:100%;padding:24px 22px 22px}.mg-sub-plan-card.is-featured .mg-sub-plan-inner{padding-top:22px}.mg-sub-plan-card h4{margin:0;color:#071a44;font-size:20px;line-height:1.1;font-weight:950;letter-spacing:-.035em}.mg-sub-plan-desc{min-height:72px;margin:10px 0 18px;color:#536789;font-size:13px;line-height:1.5;font-weight:520}.mg-sub-price{display:flex;align-items:flex-end;gap:6px;margin:0 0 4px;color:#061735}.mg-sub-price strong{font-size:34px;line-height:.95;font-weight:950;letter-spacing:-.065em}.mg-sub-price span{padding-bottom:2px;color:#52688b;font-size:13px;font-weight:850}.mg-sub-billed{min-height:20px;margin:4px 0 16px;color:#7788a3;font-size:12px;font-weight:750}.mg-sub-action{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:42px;margin:0 0 20px;border-radius:9px;border:1px solid #355cff;background:#fff;color:#3159ff!important;font-size:14px;font-weight:950;line-height:1;text-decoration:none;transition:.18s ease}.mg-sub-action.is-primary{background:linear-gradient(135deg,#3159ff 0%,#465dff 100%);color:#fff!important}.mg-sub-action.is-current{border-color:#d9e3f4;background:#f3f6fb;color:#a9b6cc!important;pointer-events:none}.mg-sub-features{display:grid;gap:11px;margin:0;padding:0;list-style:none}.mg-sub-features li{display:grid;grid-template-columns:18px 1fr;gap:9px;align-items:start;color:#334a6f;font-size:13px;line-height:1.45;font-weight:650}.mg-sub-features li:before{content:"✓";display:grid;place-items:center;width:16px;height:16px;margin-top:1px;border-radius:50%;background:#071a44;color:#fff;font-size:10px;font-weight:950}.mg-sub-features li.is-muted{color:#9aa8bf}.mg-sub-features li.is-muted:before{content:"–";background:#edf2fa;color:#8b9ab3}.mg-sub-fit{margin:auto 0 0;padding-top:18px;color:#71829f;font-size:12px;line-height:1.45;font-weight:750}
  .mg-sub-bottom{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(300px,.65fr);gap:28px;margin-top:28px}.mg-sub-why{padding:22px;border:1px solid #dbe7f8;border-radius:16px;background:#fff;box-shadow:0 16px 38px rgba(13,38,76,.05)}.mg-sub-why h3{margin:0 0 16px;color:#061735;font-size:18px;line-height:1.2;font-weight:950;letter-spacing:-.03em}.mg-sub-reasons{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.mg-sub-reason{display:grid;grid-template-columns:38px 1fr;gap:12px;align-items:start}.mg-sub-reason-icon{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:13px;background:#eef3ff;color:#3159ff;font-size:18px;font-weight:950}.mg-sub-reason:nth-child(2) .mg-sub-reason-icon{background:#f6eaff;color:#a53dff}.mg-sub-reason:nth-child(3) .mg-sub-reason-icon{background:#e9fff1;color:#17a562}.mg-sub-reason:nth-child(4) .mg-sub-reason-icon{background:#fff0f5;color:#f2457b}.mg-sub-reason strong{display:block;margin-bottom:4px;color:#071a44;font-size:14px;line-height:1.25;font-weight:950}.mg-sub-reason span{display:block;color:#536789;font-size:13px;line-height:1.4;font-weight:520}.mg-sub-custom{display:flex;align-items:center;gap:18px;min-height:100%;padding:24px;border:1px solid #e7e8ff;border-radius:16px;background:radial-gradient(circle at 0 0,rgba(167,104,255,.18),transparent 38%),linear-gradient(135deg,#fbfaff 0%,#f0f4ff 100%);box-shadow:0 16px 38px rgba(13,38,76,.05)}.mg-sub-custom-icon{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:16px;background:#fff;color:#9747ff;font-size:25px;font-weight:950;box-shadow:0 10px 22px rgba(91,53,181,.12)}.mg-sub-custom h3{margin:0 0 6px;color:#071a44;font-size:18px;line-height:1.2;font-weight:950;letter-spacing:-.03em}.mg-sub-custom p{margin:0 0 14px;color:#536789;font-size:14px;line-height:1.45;font-weight:520}.mg-sub-custom a{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 20px;border-radius:9px;border:1px solid #cbd8ff;background:#fff;color:#3159ff!important;font-size:13px;font-weight:950;text-decoration:none}
  @media(max-width:1280px){.mg-sub-hero{grid-template-columns:1fr}.mg-sub-plans{grid-template-columns:repeat(2,minmax(0,1fr))}.mg-sub-bottom{grid-template-columns:1fr}.mg-sub-metrics{min-height:auto}.mg-sub-metric{min-height:132px}}
  @media(max-width:920px){.mg-subscription-redesign .mg-app-panel-body{padding:20px}.mg-sub-current-meta{grid-template-columns:1fr;gap:12px}.mg-sub-current-meta div,.mg-sub-current-meta div+div{padding:0;border-left:0}.mg-sub-metrics{grid-template-columns:repeat(2,1fr)}.mg-sub-metric:nth-child(2){border-right:0}.mg-sub-metric:nth-child(1),.mg-sub-metric:nth-child(2){border-bottom:1px solid #e2e9f5}.mg-sub-section-top{align-items:stretch;flex-direction:column}.mg-sub-toggle{width:100%;min-width:0}.mg-sub-reasons{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media(max-width:640px){.mg-sub-hero{padding:18px}.mg-sub-plans{grid-template-columns:1fr}.mg-sub-metrics{grid-template-columns:1fr}.mg-sub-metric{border-right:0;border-bottom:1px solid #e2e9f5}.mg-sub-metric:last-child{border-bottom:0}.mg-sub-reasons{grid-template-columns:1fr}.mg-sub-custom{align-items:flex-start;flex-direction:column}}
</style>
<section class="mg-app-panel mg-account-pane mg-subscription-redesign is-active" data-account-pane="subscriptions">
  <div class="mg-app-panel-head">
    <div>
      <h2>My Subscription</h2>
      <p>The Rewards Layer for Local Commerce.</p>
    </div>
  </div>
  <div class="mg-app-panel-body">
    <section class="mg-sub-hero" aria-label="Current subscription">
      <div class="mg-sub-current">
        <div class="mg-sub-kicker">Current Plan</div>
        <div class="mg-sub-plan-title"><h3><?= mg_e((string)($currentPlan['name'] ?? 'Starter')) ?></h3><span class="mg-sub-status<?= in_array(strtolower((string)$subscriptionSnapshot['status']), ['past due', 'paused', 'cancel pending'], true) ? ' is-warning' : '' ?>"><?= mg_e((string)$subscriptionSnapshot['status']) ?></span></div>
        <p class="mg-sub-current-copy">Your platform access is active. Manage Promotional CRM, Rewards Layer, pre-sale commerce, customer engagement, and tracked revenue from one workspace.</p>
        <div class="mg-sub-current-meta">
          <div><span>Renews on</span><strong><?= mg_e((string)$subscriptionSnapshot['renews_on']) ?></strong></div>
          <div><span>Billing</span><strong><?= mg_e((string)$subscriptionSnapshot['billing_cycle']) ?></strong></div>
          <div><span>Next charge</span><strong><?= mg_e($nextChargeLabel) ?></strong></div>
        </div>
      </div>
      <div class="mg-sub-metrics" aria-label="Subscription usage">
        <div class="mg-sub-metric"><div class="mg-sub-metric-icon">◎</div><span>Total Promotions</span><strong><?= mg_e(number_format((int)$subscriptionUsage['promotions_used'])) ?> / <?= mg_e(number_format($currentPromotionsLimit)) ?></strong><small>This billing cycle</small></div>
        <div class="mg-sub-metric"><div class="mg-sub-metric-icon">♡</div><span>Total Rewards Distributed</span><strong><?= mg_e(number_format((int)$subscriptionUsage['rewards_distributed'])) ?></strong><small>All time</small></div>
        <div class="mg-sub-metric"><div class="mg-sub-metric-icon">♙</div><span>Customer Engagements</span><strong><?= mg_e(mg_subscription_view_short_number((int)$subscriptionUsage['customer_engagements'])) ?></strong><small><?= $subscriptionUsage['stamps_used'] !== null ? 'Includes Stamps usage' : 'All time' ?></small></div>
        <div class="mg-sub-metric"><div class="mg-sub-metric-icon">↗</div><span>Revenue Tracked</span><strong><?= mg_e(mg_subscription_view_money((int)$subscriptionUsage['revenue_cents'])) ?></strong><small><?= $subscriptionUsage['data_source'] === 'database' ? 'Live database' : 'Starter sample' ?></small></div>
      </div>
    </section>

    <section aria-label="Plans and pricing">
      <div class="mg-sub-section-top">
        <div class="mg-sub-section-title">
          <h3>Plans &amp; Pricing</h3>
          <p>Compare plans for Promotional CRM, direct feed distribution, engagement campaigns, landing pages, pre-sale commerce, multi-location management, design studio, and automated commerce solutions.</p>
        </div>
        <div class="mg-sub-toggle" aria-label="Billing cycle"><a href="/pricing.php?billing=monthly">Monthly</a><a href="/pricing.php?billing=yearly">Yearly (Save 20%)</a></div>
      </div>

      <div class="mg-sub-plans">
        <?php foreach ($plans as $plan): ?>
          <?php
            $planId = mg_subscription_view_slug((string)($plan['id'] ?? ''));
            $isCurrent = $planId === $currentPackageId;
            $isFeatured = !empty($plan['featured']);
            $isEnterprise = $planId === 'enterprise';
            $actionLabel = $isCurrent ? 'Current Plan' : ($isEnterprise ? 'Contact Sales' : 'Upgrade Plan');
            $actionHref = $isEnterprise ? '/learn-more.php?plan=enterprise' : '/pricing.php?plan=' . rawurlencode($planId) . '&source=account_subscription';
          ?>
          <article class="mg-sub-plan-card<?= $isFeatured ? ' is-featured' : '' ?>" data-package-id="<?= mg_e($planId) ?>">
            <?php if ($isFeatured): ?><div class="mg-sub-ribbon">Most Popular</div><?php endif; ?>
            <div class="mg-sub-plan-inner">
              <h4><?= mg_e((string)($plan['name'] ?? 'Plan')) ?></h4>
              <p class="mg-sub-plan-desc"><?= mg_e((string)($plan['description'] ?? '')) ?></p>
              <div class="mg-sub-price"><strong><?= mg_e((string)($plan['price_label'] ?? '$0')) ?></strong><span><?= mg_e((string)($plan['billing_label'] ?? '/mo')) ?></span></div>
              <div class="mg-sub-billed">Monthly billing</div>
              <a class="mg-sub-action<?= $isCurrent ? ' is-current' : ($isFeatured ? ' is-primary' : '') ?>" href="<?= mg_e($isCurrent ? '#' : $actionHref) ?>"><?= mg_e($actionLabel) ?></a>
              <ul class="mg-sub-features">
                <?php foreach (($plan['included_features'] ?? []) as $feature): ?><li><?= mg_e((string)$feature) ?></li><?php endforeach; ?>
                <?php foreach (($plan['excluded_features'] ?? []) as $feature): ?><li class="is-muted"><?= mg_e((string)$feature) ?></li><?php endforeach; ?>
              </ul>
              <?php if (!empty($plan['fit'])): ?><p class="mg-sub-fit"><?= mg_e((string)$plan['fit']) ?></p><?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="mg-sub-bottom" aria-label="Upgrade benefits">
      <div class="mg-sub-why">
        <h3>Why upgrade?</h3>
        <div class="mg-sub-reasons">
          <div class="mg-sub-reason"><div class="mg-sub-reason-icon">▣</div><div><strong>Increase Revenue</strong><span>Turn promotions into measurable tracked revenue.</span></div></div>
          <div class="mg-sub-reason"><div class="mg-sub-reason-icon">♙</div><div><strong>Engage Customers</strong><span>Drive loyalty and repeat business.</span></div></div>
          <div class="mg-sub-reason"><div class="mg-sub-reason-icon">⌘</div><div><strong>Scale Operations</strong><span>Manage multiple locations and campaigns.</span></div></div>
          <div class="mg-sub-reason"><div class="mg-sub-reason-icon">◴</div><div><strong>Save Time</strong><span>Automate tasks and streamline workflows.</span></div></div>
        </div>
      </div>
      <aside class="mg-sub-custom">
        <div class="mg-sub-custom-icon">✦</div>
        <div><h3>Need a custom solution?</h3><p>Let’s build a plan that fits your business perfectly.</p><a href="/learn-more.php">Book a demo</a></div>
      </aside>
    </section>
  </div>
</section>
