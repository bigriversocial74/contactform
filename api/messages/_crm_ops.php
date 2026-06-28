<?php
declare(strict_types=1);

function mg_message_crm_ops_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS message_thread_crm_state (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      public_id CHAR(36) NOT NULL,
      thread_id BIGINT UNSIGNED NOT NULL,
      status ENUM('open','resolved') NOT NULL DEFAULT 'open',
      label VARCHAR(80) NULL,
      assigned_user_id BIGINT UNSIGNED NULL,
      resolved_by_user_id BIGINT UNSIGNED NULL,
      resolved_at DATETIME NULL,
      created_by_user_id BIGINT UNSIGNED NULL,
      updated_by_user_id BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_message_thread_crm_state_public_id (public_id),
      UNIQUE KEY uq_message_thread_crm_state_thread (thread_id),
      KEY idx_message_thread_crm_state_status (status,label,updated_at),
      KEY idx_message_thread_crm_state_assigned (assigned_user_id,status,updated_at),
      CONSTRAINT fk_message_thread_crm_state_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
      CONSTRAINT fk_message_thread_crm_state_assigned FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
      CONSTRAINT fk_message_thread_crm_state_resolved_by FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
      CONSTRAINT fk_message_thread_crm_state_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
      CONSTRAINT fk_message_thread_crm_state_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS message_thread_notes (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      public_id CHAR(36) NOT NULL,
      thread_id BIGINT UNSIGNED NOT NULL,
      user_id BIGINT UNSIGNED NOT NULL,
      note_body TEXT NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_message_thread_notes_public_id (public_id),
      UNIQUE KEY uq_message_thread_notes_thread_user (thread_id,user_id),
      KEY idx_message_thread_notes_thread (thread_id,updated_at),
      CONSTRAINT fk_message_thread_notes_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
      CONSTRAINT fk_message_thread_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS message_thread_drafts (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      public_id CHAR(36) NOT NULL,
      thread_id BIGINT UNSIGNED NOT NULL,
      user_id BIGINT UNSIGNED NOT NULL,
      body TEXT NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_message_thread_drafts_public_id (public_id),
      UNIQUE KEY uq_message_thread_drafts_thread_user (thread_id,user_id),
      KEY idx_message_thread_drafts_user (user_id,updated_at),
      CONSTRAINT fk_message_thread_drafts_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
      CONSTRAINT fk_message_thread_drafts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $done = true;
}

function mg_message_crm_ops_thread(PDO $pdo, string $publicId, int $userId): array
{
    if ($publicId === '' || strlen($publicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/i', $publicId)) {
        mg_fail('Invalid thread identifier.', 422);
    }

    $stmt = $pdo->prepare(
        'SELECT mt.id,mt.public_id,mt.subject,mt.conversation_key
         FROM message_threads mt
         INNER JOIN message_thread_participants mtp ON mtp.thread_id=mt.id
         WHERE mt.public_id=? AND mtp.user_id=?
         LIMIT 1'
    );
    $stmt->execute([strtolower($publicId), $userId]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$thread) mg_fail('Thread not found.', 404);
    return $thread;
}

function mg_message_crm_ops_clean_label(mixed $value): ?string
{
    $label = trim((string)$value);
    if ($label === '') return null;
    $label = mb_substr($label, 0, 80);
    if (!preg_match('/^[\pL\pN][\pL\pN .:_-]{0,79}$/u', $label)) {
        throw new InvalidArgumentException('Invalid thread label.');
    }
    return $label;
}

function mg_message_crm_ops_clean_body(mixed $value, int $limit, string $name): string
{
    $body = trim((string)$value);
    if (mb_strlen($body) > $limit) {
        throw new InvalidArgumentException($name . ' is too long.');
    }
    return $body;
}

function mg_message_crm_ops_get(PDO $pdo, int $threadId, int $userId): array
{
    mg_message_crm_ops_ensure_schema($pdo);

    $stateStmt = $pdo->prepare(
        "SELECT s.status,s.label,s.assigned_user_id,s.resolved_at,
                COALESCE(NULLIF(au.display_name,''),NULLIF(au.full_name,''),au.email) assigned_user_name
         FROM message_thread_crm_state s
         LEFT JOIN users au ON au.id=s.assigned_user_id
         WHERE s.thread_id=? LIMIT 1"
    );
    $stateStmt->execute([$threadId]);
    $state = $stateStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $noteStmt = $pdo->prepare('SELECT note_body,updated_at FROM message_thread_notes WHERE thread_id=? AND user_id=? LIMIT 1');
    $noteStmt->execute([$threadId, $userId]);
    $note = $noteStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $draftStmt = $pdo->prepare('SELECT body,updated_at FROM message_thread_drafts WHERE thread_id=? AND user_id=? LIMIT 1');
    $draftStmt->execute([$threadId, $userId]);
    $draft = $draftStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'status' => (string)($state['status'] ?? 'open'),
        'label' => $state['label'] !== null && isset($state['label']) ? (string)$state['label'] : 'Needs follow-up',
        'assigned_user_id' => isset($state['assigned_user_id']) ? (int)$state['assigned_user_id'] : null,
        'assigned_user_name' => $state['assigned_user_name'] !== null && isset($state['assigned_user_name']) ? (string)$state['assigned_user_name'] : 'Unassigned',
        'resolved_at' => $state['resolved_at'] ?? null,
        'note' => (string)($note['note_body'] ?? ''),
        'note_updated_at' => $note['updated_at'] ?? null,
        'draft' => (string)($draft['body'] ?? ''),
        'draft_updated_at' => $draft['updated_at'] ?? null,
    ];
}

function mg_message_crm_ops_save_draft(PDO $pdo, int $threadId, int $userId, string $body): void
{
    mg_message_crm_ops_ensure_schema($pdo);
    if ($body === '') {
        $pdo->prepare('DELETE FROM message_thread_drafts WHERE thread_id=? AND user_id=?')->execute([$threadId, $userId]);
        return;
    }
    $stmt = $pdo->prepare(
        "INSERT INTO message_thread_drafts (public_id,thread_id,user_id,body,created_at,updated_at)
         VALUES (?,?,?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE body=VALUES(body),updated_at=NOW()"
    );
    $stmt->execute([mg_public_uuid(), $threadId, $userId, $body]);
}

function mg_message_crm_ops_save_note(PDO $pdo, int $threadId, int $userId, string $body): void
{
    mg_message_crm_ops_ensure_schema($pdo);
    if ($body === '') {
        $pdo->prepare('DELETE FROM message_thread_notes WHERE thread_id=? AND user_id=?')->execute([$threadId, $userId]);
        return;
    }
    $stmt = $pdo->prepare(
        "INSERT INTO message_thread_notes (public_id,thread_id,user_id,note_body,created_at,updated_at)
         VALUES (?,?,?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE note_body=VALUES(note_body),updated_at=NOW()"
    );
    $stmt->execute([mg_public_uuid(), $threadId, $userId, $body]);
}

function mg_message_crm_ops_update_state(PDO $pdo, int $threadId, int $userId, array $input): void
{
    mg_message_crm_ops_ensure_schema($pdo);
    $current = mg_message_crm_ops_get($pdo, $threadId, $userId);
    $status = isset($input['status']) ? strtolower(trim((string)$input['status'])) : (string)$current['status'];
    if (!in_array($status, ['open','resolved'], true)) {
        throw new InvalidArgumentException('Invalid thread status.');
    }
    $label = array_key_exists('label', $input) ? mg_message_crm_ops_clean_label($input['label']) : (($current['label'] ?? '') !== 'Needs follow-up' ? (string)$current['label'] : null);
    $assignedUserId = array_key_exists('assigned_user_id', $input)
        ? ((int)$input['assigned_user_id'] > 0 ? (int)$input['assigned_user_id'] : null)
        : ($current['assigned_user_id'] ?? null);
    if (($input['assign_to_self'] ?? false) === true || (string)($input['assign_to_self'] ?? '') === '1') {
        $assignedUserId = $userId;
    }
    $resolvedBy = $status === 'resolved' ? $userId : null;
    $resolvedAtSql = $status === 'resolved' ? 'NOW()' : 'NULL';

    $stmt = $pdo->prepare(
        "INSERT INTO message_thread_crm_state
         (public_id,thread_id,status,label,assigned_user_id,resolved_by_user_id,resolved_at,created_by_user_id,updated_by_user_id,created_at,updated_at)
         VALUES (?,?,?,?,?,?,$resolvedAtSql,?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE
           status=VALUES(status),label=VALUES(label),assigned_user_id=VALUES(assigned_user_id),
           resolved_by_user_id=VALUES(resolved_by_user_id),resolved_at=$resolvedAtSql,
           updated_by_user_id=VALUES(updated_by_user_id),updated_at=NOW()"
    );
    $stmt->execute([mg_public_uuid(), $threadId, $status, $label, $assignedUserId, $resolvedBy, $userId, $userId]);
}
?>