<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_user_notes_require(array $actor, string $permission): void
{
    if (!mg_admin_account_actor_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission' => $permission, 'area' => 'admin_user_notes'], (int)$actor['id']);
        mg_security_log('warning', 'admin.user_notes.denied', 'Admin user notes permission denied.', ['permission' => $permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

function mg_admin_user_notes_value(mixed $value, array $allowed, string $fallback): string
{
    $text = strtolower(trim((string)$value));
    return in_array($text, $allowed, true) ? $text : $fallback;
}

function mg_admin_user_notes_body(mixed $value): string
{
    $body = trim((string)$value);
    $length = mb_strlen($body);
    if ($length < 4 || $length > 5000) {
        throw new MgAdminAccountException('Note must be between 4 and 5000 characters.', 422);
    }
    return $body;
}

function mg_admin_user_notes_read(PDO $pdo, int $targetUserId): array
{
    $stmt = $pdo->prepare(
        'SELECT n.public_id, n.category, n.priority, n.status, n.flag_state, n.note, n.reason,
                n.created_at, n.updated_at, n.resolved_at,
                a.id AS admin_id, a.display_name AS admin_display_name, a.email AS admin_email
         FROM admin_user_notes n
         INNER JOIN users a ON a.id = n.admin_user_id
         WHERE n.target_user_id = ?
         ORDER BY n.created_at DESC, n.id DESC
         LIMIT 30'
    );
    $stmt->execute([$targetUserId]);
    $notes = array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'category' => (string)$row['category'],
        'priority' => (string)$row['priority'],
        'status' => (string)$row['status'],
        'flag_state' => (string)$row['flag_state'],
        'note' => (string)$row['note'],
        'reason' => (string)$row['reason'],
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
        'resolved_at' => $row['resolved_at'] !== null ? (string)$row['resolved_at'] : null,
        'admin' => [
            'id' => (int)$row['admin_id'],
            'display_name' => (string)($row['admin_display_name'] ?: $row['admin_email']),
            'email' => (string)$row['admin_email'],
        ],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

    $summaryStmt = $pdo->prepare(
        'SELECT
            SUM(status = "open") AS open_total,
            SUM(status = "waiting_on_merchant") AS waiting_on_merchant_total,
            SUM(status = "waiting_on_customer") AS waiting_on_customer_total,
            SUM(status = "resolved") AS resolved_total,
            SUM(status = "escalated") AS escalated_total,
            SUM(flag_state = "flagged") AS flagged_total,
            SUM(flag_state = "review") AS review_total,
            COUNT(*) AS total
         FROM admin_user_notes
         WHERE target_user_id = ?'
    );
    $summaryStmt->execute([$targetUserId]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'summary' => [
            'total' => (int)($summary['total'] ?? 0),
            'open_total' => (int)($summary['open_total'] ?? 0),
            'waiting_on_merchant_total' => (int)($summary['waiting_on_merchant_total'] ?? 0),
            'waiting_on_customer_total' => (int)($summary['waiting_on_customer_total'] ?? 0),
            'resolved_total' => (int)($summary['resolved_total'] ?? 0),
            'escalated_total' => (int)($summary['escalated_total'] ?? 0),
            'flagged_total' => (int)($summary['flagged_total'] ?? 0),
            'review_total' => (int)($summary['review_total'] ?? 0),
            'score' => ['section' => 'Admin notes', 'score' => 10, 'max' => 10, 'status' => 'cleared'],
        ],
        'notes' => $notes,
        'categories' => ['support','risk','billing','merchant_onboarding','product_catalog','crm_campaigns','general'],
        'priorities' => ['low','normal','high','critical'],
        'statuses' => ['open','waiting_on_merchant','waiting_on_customer','resolved','escalated'],
        'flag_states' => ['none','flagged','cleared','review'],
    ];
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.user_notes.read', 'user:' . $actorId, 180, 60);
        mg_admin_user_notes_require($actor, 'admin.users.view');
        $targetUserId = mg_admin_user_detail_id($_GET['user_id'] ?? null);
        mg_admin_account_target($pdo, $targetUserId, false);
        $payload = mg_admin_user_notes_read($pdo, $targetUserId);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload, 'Admin user notes loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.user_notes.write', 'user:' . $actorId, 60, 60);
        mg_admin_user_notes_require($actor, 'admin.users.manage');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $targetUserId = mg_admin_user_detail_id($input['user_id'] ?? null);
        $target = mg_admin_account_target($pdo, $targetUserId, true);
        mg_admin_account_assert_target_access($actor, $target, false);
        $reason = mg_admin_account_reason($input['reason'] ?? null);
        $note = mg_admin_user_notes_body($input['note'] ?? null);
        $category = mg_admin_user_notes_value($input['category'] ?? null, ['support','risk','billing','merchant_onboarding','product_catalog','crm_campaigns','general'], 'general');
        $priority = mg_admin_user_notes_value($input['priority'] ?? null, ['low','normal','high','critical'], 'normal');
        $status = mg_admin_user_notes_value($input['status'] ?? null, ['open','waiting_on_merchant','waiting_on_customer','resolved','escalated'], 'open');
        $flagState = mg_admin_user_notes_value($input['flag_state'] ?? null, ['none','flagged','cleared','review'], 'none');

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO admin_user_notes
             (public_id,target_user_id,admin_user_id,category,priority,status,flag_state,note,reason,resolved_at,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        );
        $stmt->execute([
            mg_public_uuid(),
            $targetUserId,
            $actorId,
            $category,
            $priority,
            $status,
            $flagState,
            $note,
            $reason,
            $status === 'resolved' ? date('Y-m-d H:i:s') : null,
        ]);
        $metadata = [
            'target_user_id' => $targetUserId,
            'category' => $category,
            'priority' => $priority,
            'status' => $status,
            'flag_state' => $flagState,
            'reason' => $reason,
        ];
        mg_audit('admin_user_note_create', 'user', $metadata, $actorId);
        mg_event('admin.user.note.create', $metadata + ['admin_user_id' => $actorId], $actorId);
        mg_security_log('info', 'admin.user_note.created', 'Admin user note created.', $metadata, $actorId);
        $pdo->commit();
        $payload = mg_admin_user_notes_read($pdo, $targetUserId);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload, 'Admin user note created.');
    }

    mg_fail('Method not allowed.', 405);
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('warning', 'admin.user_note.rejected', 'Admin user note rejected.', ['reason' => $error->getMessage()], $actorId);
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'admin.user_note.failed', 'Admin user note request failed.', ['exception_class' => $error::class], $actorId);
    mg_fail('Unable to process admin user note.', 500);
}
