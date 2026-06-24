<?php
declare(strict_types=1);

require_once __DIR__ . '/_stamps.php';

function mg_stamp_plan_allowance(string $planId): int
{
    $planId = strtolower(trim($planId));
    foreach (mg_pricing_packages() as $package) {
        if (($package['id'] ?? '') === $planId) {
            $value = $package['limits']['monthly_stamps_included'] ?? 0;
            return is_numeric($value) ? max(0, (int)$value) : 0;
        }
    }
    return 0;
}

function mg_stamp_active_package_assignments(PDO $pdo, int $limit = 500): array
{
    $limit = max(1, min(5000, $limit));
    try {
        $stmt = $pdo->query('SELECT account_user_id,package_id,status,started_at,renews_at FROM account_package_assignments WHERE status = \'active\' ORDER BY id LIMIT ' . $limit);
        return array_values(array_filter(array_map(static function(array $row): array {
            $planId = strtolower((string)$row['package_id']);
            $allowance = mg_stamp_plan_allowance($planId);
            return [
                'account_user_id' => (int)$row['account_user_id'],
                'plan_id' => $planId,
                'monthly_stamps_included' => $allowance,
                'status' => (string)$row['status'],
                'started_at' => $row['started_at'] ?? null,
                'renews_at' => $row['renews_at'] ?? null,
            ];
        }, $stmt->fetchAll()), static fn(array $row): bool => (int)$row['monthly_stamps_included'] > 0));
    } catch (Throwable $error) {
        mg_security_log('warning', 'stamps.package_assignments_unavailable', 'Stamp package assignments unavailable.', ['exception_class' => $error::class]);
        return [];
    }
}

function mg_stamp_monthly_renewal_preview(PDO $pdo, string $period = '', int $limit = 500): array
{
    $period = trim($period) !== '' ? trim($period) : mg_stamp_period_key();
    $assignments = mg_stamp_active_package_assignments($pdo, $limit);
    $rows = [];
    foreach ($assignments as $assignment) {
        $accountUserId = (int)$assignment['account_user_id'];
        $planId = (string)$assignment['plan_id'];
        $idempotencyKey = 'stamp:monthly:' . $period . ':' . $accountUserId . ':' . $planId;
        $existing = mg_stamp_existing_entry($pdo, $accountUserId, $idempotencyKey);
        $rows[] = $assignment + [
            'period' => $period,
            'idempotency_key' => $idempotencyKey,
            'already_credited' => (bool)$existing,
            'ledger_entry_id' => $existing['public_id'] ?? null,
        ];
    }
    return [
        'period' => $period,
        'count' => count($rows),
        'pending_count' => count(array_filter($rows, static fn(array $row): bool => empty($row['already_credited']))),
        'assignments' => $rows,
    ];
}

function mg_stamp_run_monthly_renewals(PDO $pdo, ?int $actorUserId, string $period = '', int $limit = 500, bool $dryRun = false): array
{
    $period = trim($period) !== '' ? trim($period) : mg_stamp_period_key();
    $preview = mg_stamp_monthly_renewal_preview($pdo, $period, $limit);
    if ($dryRun) {
        return $preview + ['dry_run' => true, 'credited_count' => 0, 'skipped_count' => $preview['count'] - $preview['pending_count']];
    }
    $credited = [];
    $skipped = [];
    foreach ($preview['assignments'] as $assignment) {
        if (!empty($assignment['already_credited'])) {
            $skipped[] = $assignment;
            continue;
        }
        $result = mg_stamp_credit($pdo, (int)$assignment['account_user_id'], $actorUserId, (int)$assignment['monthly_stamps_included'], (string)$assignment['idempotency_key'], [
            'actor_type' => $actorUserId ? 'admin' : 'system',
            'source_type' => 'monthly_package_allowance',
            'source_id' => $period,
            'reference' => (string)$assignment['plan_id'],
            'reason_code' => 'monthly_allowance_renewal',
            'metadata' => ['plan_id'=>(string)$assignment['plan_id'], 'period'=>$period, 'renewal'=>'automatic'],
        ]);
        $credited[] = $assignment + ['ledger_entry_id' => $result['entry']['entry_id'] ?? null, 'balance_after' => $result['entry']['balance_after'] ?? null];
    }
    return [
        'period' => $period,
        'dry_run' => false,
        'count' => $preview['count'],
        'credited_count' => count($credited),
        'skipped_count' => count($skipped),
        'credited' => $credited,
        'skipped' => $skipped,
    ];
}
