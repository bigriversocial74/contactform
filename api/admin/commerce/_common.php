<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

const MG_ADMIN_COMMERCE_DEFAULT_LIMIT = 25;
const MG_ADMIN_COMMERCE_MAX_LIMIT = 50;
const MG_ADMIN_COMMERCE_MAX_PAGE = 1000;
const MG_ADMIN_COMMERCE_SUBJECT_TYPES = ['order','refund','dispute','subscription','tip','microgift'];

final class MgAdminCommerceException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

function mg_admin_commerce_require_user(string $permission = 'admin.commerce.view'): array
{
    $user = mg_require_api_user();
    $allowed = mg_api_user_has_permission($user, $permission);
    if (!$allowed && $permission === 'admin.commerce.view') {
        foreach (['merchant.payments.view','subscriptions.admin','microgift.operations.view','tips.reverse'] as $legacyPermission) {
            if (mg_api_user_has_permission($user, $legacyPermission)) {
                $allowed = true;
                break;
            }
        }
    }
    if (!$allowed) {
        mg_security_log('warning', 'admin.commerce.denied', 'Admin commerce access denied.', [
            'permission' => $permission,
        ], (int)$user['id']);
        mg_fail('Permission denied.', 403);
    }
    return $user;
}

function mg_admin_commerce_has(array $user, string $permission): bool
{
    return mg_api_user_has_permission($user, $permission);
}

function mg_admin_commerce_text(mixed $value, int $maxLength, bool $required = false): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    if (($required && $text === '') || mb_strlen($text) > $maxLength) {
        throw new MgAdminCommerceException('Invalid commerce operations input.', 422);
    }
    return $text;
}

function mg_admin_commerce_reason(mixed $value): string
{
    $reason = mg_admin_commerce_text($value, 500, true);
    if (mb_strlen($reason) < 8) {
        throw new MgAdminCommerceException('Provide an action reason of at least 8 characters.', 422);
    }
    return $reason;
}

function mg_admin_commerce_public_reference(mixed $value): string
{
    $reference = trim((string)$value);
    if ($reference === '' || strlen($reference) > 190 || preg_match('/^[A-Za-z0-9._:-]+$/', $reference) !== 1) {
        throw new MgAdminCommerceException('Invalid commerce subject reference.', 422);
    }
    return $reference;
}

function mg_admin_commerce_subject_type(mixed $value): string
{
    $type = strtolower(trim((string)$value));
    if (!in_array($type, MG_ADMIN_COMMERCE_SUBJECT_TYPES, true)) {
        throw new MgAdminCommerceException('Invalid commerce subject type.', 422);
    }
    return $type;
}

function mg_admin_commerce_case_id(mixed $value): int
{
    $raw = trim((string)$value);
    if ($raw === '' || preg_match('/^[1-9][0-9]{0,19}$/', $raw) !== 1) {
        throw new MgAdminCommerceException('Invalid commerce case identifier.', 422);
    }
    $id = filter_var($raw, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new MgAdminCommerceException('Invalid commerce case identifier.', 422);
    }
    return (int)$id;
}

function mg_admin_commerce_limit(mixed $value): int
{
    $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => MG_ADMIN_COMMERCE_DEFAULT_LIMIT]]);
    return max(1, min((int)$limit, MG_ADMIN_COMMERCE_MAX_LIMIT));
}

function mg_admin_commerce_page(mixed $value): int
{
    $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
    return max(1, min((int)$page, MG_ADMIN_COMMERCE_MAX_PAGE));
}

function mg_admin_commerce_date(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new MgAdminCommerceException('Invalid commerce date filter.', 422);
    }
    return $value;
}

function mg_admin_commerce_user_id(mixed $value): ?int
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    if (preg_match('/^[1-9][0-9]{0,19}$/', $raw) !== 1) {
        throw new MgAdminCommerceException('Invalid user filter.', 422);
    }
    $id = filter_var($raw, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new MgAdminCommerceException('Invalid user filter.', 422);
    }
    return (int)$id;
}

function mg_admin_commerce_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_admin_commerce_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_admin_commerce_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function mg_admin_commerce_user_summary(array $row, string $prefix): ?array
{
    $id = (int)($row[$prefix . '_user_id'] ?? 0);
    if ($id < 1) return null;
    return [
        'id' => $id,
        'display_name' => (string)($row[$prefix . '_display_name'] ?? ''),
        'email' => (string)($row[$prefix . '_email'] ?? ''),
    ];
}

function mg_admin_commerce_case_summary(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'public_id' => (string)$row['public_id'],
        'subject_type' => (string)$row['subject_type'],
        'subject_reference' => (string)$row['subject_reference'],
        'status' => (string)$row['status'],
        'priority' => (string)$row['priority'],
        'summary' => (string)$row['summary'],
        'latest_note' => $row['latest_note'] !== null ? (string)$row['latest_note'] : null,
        'opened_by_user_id' => (int)$row['opened_by_user_id'],
        'assigned_user_id' => $row['assigned_user_id'] !== null ? (int)$row['assigned_user_id'] : null,
        'resolved_by_user_id' => $row['resolved_by_user_id'] !== null ? (int)$row['resolved_by_user_id'] : null,
        'resolution_code' => $row['resolution_code'] !== null ? (string)$row['resolution_code'] : null,
        'opened_at' => (string)$row['opened_at'],
        'resolved_at' => $row['resolved_at'] !== null ? (string)$row['resolved_at'] : null,
        'updated_at' => (string)$row['updated_at'],
    ];
}

function mg_admin_commerce_subject_exists(PDO $pdo, string $type, string $reference, bool $lock = false): bool
{
    $map = [
        'order' => ['commerce_orders', 'public_id'],
        'refund' => ['payment_refunds', 'public_id'],
        'dispute' => ['payment_disputes', 'public_id'],
        'subscription' => ['subscriptions', 'public_id'],
        'tip' => ['tips', 'public_id'],
        'microgift' => ['microgift_instances', 'public_id'],
    ];
    [$table, $column] = $map[$type];
    $sql = "SELECT id FROM {$table} WHERE {$column}=? LIMIT 1" . ($lock ? ' FOR UPDATE' : '');
    return (bool)mg_admin_commerce_scalar($pdo, $sql, [$reference]);
}

function mg_admin_commerce_cases(PDO $pdo, string $type, string $reference): array
{
    $rows = mg_admin_commerce_all($pdo,
        'SELECT * FROM commerce_operation_cases WHERE subject_type=? AND subject_reference=? ORDER BY updated_at DESC,id DESC LIMIT 25',
        [$type, $reference]
    );
    return array_map('mg_admin_commerce_case_summary', $rows);
}

function mg_admin_commerce_case_events(PDO $pdo, int $caseId): array
{
    $rows = mg_admin_commerce_all($pdo,
        'SELECT e.*,COALESCE(u.display_name,u.full_name,u.email) actor_name
         FROM commerce_operation_case_events e
         INNER JOIN users u ON u.id=e.actor_user_id
         WHERE e.case_id=? ORDER BY e.created_at DESC,e.id DESC LIMIT 100',
        [$caseId]
    );
    return array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'public_id' => (string)$row['public_id'],
        'action_type' => (string)$row['action_type'],
        'from_status' => $row['from_status'] !== null ? (string)$row['from_status'] : null,
        'to_status' => $row['to_status'] !== null ? (string)$row['to_status'] : null,
        'actor_user_id' => (int)$row['actor_user_id'],
        'actor_name' => (string)$row['actor_name'],
        'note' => $row['note'] !== null ? (string)$row['note'] : null,
        'created_at' => (string)$row['created_at'],
    ], $rows);
}

function mg_admin_commerce_timeline_sort(array &$timeline): void
{
    usort($timeline, static function(array $a, array $b): int {
        $time = strcmp((string)($b['occurred_at'] ?? ''), (string)($a['occurred_at'] ?? ''));
        return $time !== 0 ? $time : strcmp((string)($b['event_type'] ?? ''), (string)($a['event_type'] ?? ''));
    });
}
