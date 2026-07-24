<?php
declare(strict_types=1);

require_once __DIR__ . '/public-donations-governance.php';

/**
 * Serialize merchant-wide allocation or recall admission while the canonical
 * lifecycle transaction runs. MySQL named locks are connection-scoped and are
 * always released by the endpoint in a finally block.
 */
function mg_public_donations_governance_lock_name(int $merchantId, string $kind): string
{
    $kind = strtolower(trim($kind));
    if (!in_array($kind, ['allocation', 'recall'], true)) {
        mg_public_donations_governance_fail('Invalid Public Donations operation lock.', 500);
    }
    return 'mg:public-donations:' . $merchantId . ':' . $kind;
}

function mg_public_donations_governance_acquire_operation_lock(PDO $pdo, int $merchantId, string $kind): string
{
    $name = mg_public_donations_governance_lock_name($merchantId, $kind);
    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 8)');
    $stmt->execute([$name]);
    if ((int)$stmt->fetchColumn() !== 1) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'public_donations.operation_lock_busy', 'A concurrent Public Donations operation is already running.', [
                'merchant_user_id' => $merchantId,
                'operation_kind' => $kind,
            ]);
        }
        mg_public_donations_governance_fail('Another Public Donations operation is already running. Try again shortly.', 409);
    }
    return $name;
}

function mg_public_donations_governance_release_operation_lock(PDO $pdo, ?string $name): void
{
    if ($name === null || $name === '') {
        return;
    }
    try {
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$name]);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('error', 'public_donations.operation_lock_release_failed', 'Unable to release a Public Donations operation lock.', [
                'exception_class' => $error::class,
            ]);
        }
    }
}

/**
 * Check the hourly budget while the named operation lock remains held. The
 * short transaction locks the relevant operation range; the caller then runs
 * the canonical lifecycle transaction before releasing the named lock.
 */
function mg_public_donations_governance_admit_operation(
    PDO $pdo,
    int $merchantId,
    string $kind,
    int $requestedQuantity
): string {
    $lockName = mg_public_donations_governance_acquire_operation_lock($pdo, $merchantId, $kind);
    try {
        $pdo->beginTransaction();
        mg_public_donations_governance_assert_hourly_budget($pdo, $merchantId, $kind, $requestedQuantity);
        $pdo->commit();
        return $lockName;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        mg_public_donations_governance_release_operation_lock($pdo, $lockName);
        throw $error;
    }
}
