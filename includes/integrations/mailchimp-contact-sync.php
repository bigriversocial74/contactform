<?php
declare(strict_types=1);

function mg_mailchimp_sync_contacts(PDO $pdo, int $merchantUserId, bool $reset = false, int $pageSize = 100, int $maxPages = 5): array
{
    $auth = mg_mailchimp_credentials($pdo, $merchantUserId);
    $audience = mg_mailchimp_selected_audience($auth);
    $connection = $auth['connection']; $connectionId = (int)$connection['id'];
    $pageSize = max(1, min(1000, $pageSize)); $maxPages = max(1, min(10, $maxPages));
    $resourceKey = 'contacts:' . $audience['id'];
    $stateStmt = $pdo->prepare('SELECT * FROM merchant_integration_sync_state WHERE connection_id=? AND resource_key=? LIMIT 1');
    $stateStmt->execute([$connectionId, $resourceKey]);
    $state = $stateStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $offset = $reset ? 0 : max(0, (int)($state['cursor_value'] ?? 0));
    $runPublicId = mg_integration_uuid();
    $pdo->prepare("INSERT INTO merchant_integration_sync_runs (public_id,connection_id,resource_key,direction,trigger_type,status,cursor_value,started_at,created_at,updated_at) VALUES (?,?,?,'import','manual','running',?,NOW(),NOW(),NOW())")
        ->execute([$runPublicId, $connectionId, $resourceKey, (string)$offset]);
    $runId = (int)$pdo->lastInsertId();
    $counts = ['processed' => 0, 'created' => 0, 'updated' => 0, 'linked' => 0, 'review' => 0, 'skipped' => 0, 'failed' => 0];
    $hasNext = false; $nextOffset = $offset;
    try {
        for ($pageNumber = 0; $pageNumber < $maxPages; $pageNumber++) {
            $page = mg_mailchimp_provider()->listMembers($auth['api_endpoint'], $auth['token'], $audience['id'], $nextOffset, $pageSize);
            $members = is_array($page['members'] ?? null) ? $page['members'] : [];
            foreach ($members as $raw) {
                if (!is_array($raw)) continue; $counts['processed']++;
                try { $result = mg_mailchimp_import_contact($pdo, $connection, $raw, $audience['id'], $audience['name'], 'manual'); if (isset($counts[$result])) $counts[$result]++; else $counts['updated']++; }
                catch (Throwable $contactError) { $counts['failed']++; mg_security_log('warning', 'merchant.integration.mailchimp_contact_failed', 'Mailchimp member import failed.', ['exception_class' => $contactError::class], $merchantUserId); }
            }
            $nextOffset += count($members); $total = max(0, (int)($page['total_items'] ?? $nextOffset)); $hasNext = $nextOffset < $total;
            $pdo->prepare("INSERT INTO merchant_integration_sync_state (connection_id,resource_key,cursor_value,last_attempt_at,metadata_json,created_at,updated_at) VALUES (?,?,?,NOW(),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE cursor_value=VALUES(cursor_value),last_attempt_at=NOW(),metadata_json=VALUES(metadata_json),updated_at=NOW()")
                ->execute([$connectionId, $resourceKey, $hasNext ? (string)$nextOffset : null, json_encode(['has_next_page' => $hasNext, 'audience_id' => $audience['id'], 'audience_name' => $audience['name'], 'marketing_status_preserved' => true], JSON_UNESCAPED_SLASHES)]);
            if (!$hasNext || $members === []) break;
        }
        $status = $counts['failed'] > 0 ? 'partial' : 'completed';
        $pdo->prepare('UPDATE merchant_integration_sync_runs SET status=?,cursor_value=?,processed_count=?,created_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([$status, $hasNext ? (string)$nextOffset : null, $counts['processed'], $counts['created'], $counts['updated'] + $counts['linked'], $counts['skipped'] + $counts['review'], $counts['failed'], $runId]);
        $pdo->prepare('UPDATE merchant_integration_sync_state SET last_success_at=NOW(),last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE connection_id=? AND resource_key=?')->execute([$connectionId, $resourceKey]);
        $pdo->prepare('UPDATE merchant_integration_connections SET last_sync_at=NOW(),last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE id=?')->execute([$connectionId]);
        return ['run_id' => $runPublicId, 'status' => $status, 'counts' => $counts, 'audience' => $audience, 'has_more' => $hasNext, 'next_cursor_saved' => $hasNext, 'marketing_status_preserved' => true];
    } catch (Throwable $error) {
        mg_mailchimp_mark_reauthorization($pdo, $connectionId, $error);
        $pdo->prepare("UPDATE merchant_integration_sync_runs SET status='failed',error_code=?,error_message=?,processed_count=?,created_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000), $counts['processed'], $counts['created'], $counts['updated'] + $counts['linked'], $counts['skipped'] + $counts['review'], $counts['failed'], $runId]);
        throw $error;
    }
}

function mg_mailchimp_contacts_status(PDO $pdo, int $merchantUserId): array
{
    if (!mg_integration_schema_ready($pdo)) return ['connected' => false, 'counts' => []];
    $connection = mg_integration_connection_row($pdo, $merchantUserId, 'mailchimp', false);
    if (!$connection) return ['connected' => false, 'counts' => []];
    $settings = mg_integration_json($connection['settings_json'] ?? null);
    $connectionId = (int)$connection['id'];
    $stmt = $pdo->prepare("SELECT status,COUNT(*) total FROM merchant_integration_entity_links WHERE connection_id=? AND entity_type='contact' GROUP BY status");
    $stmt->execute([$connectionId]);
    $counts = ['linked' => 0, 'pending_review' => 0, 'conflict' => 0, 'deleted_external' => 0, 'error' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $counts[(string)$row['status']] = (int)$row['total'];
    $selectedAudienceId = trim((string)($settings['selected_audience_id'] ?? ''));
    $resourceKey = $selectedAudienceId !== '' ? 'contacts:' . $selectedAudienceId : 'contacts';
    $runStmt = $pdo->prepare('SELECT public_id,status,processed_count,created_count,updated_count,skipped_count,failed_count,started_at,finished_at FROM merchant_integration_sync_runs WHERE connection_id=? AND resource_key=? ORDER BY id DESC LIMIT 1');
    $runStmt->execute([$connectionId, $resourceKey]);
    return [
        'connected' => (string)$connection['status'] === 'active', 'connection_status' => (string)$connection['status'],
        'counts' => $counts, 'total_contacts' => array_sum($counts), 'last_sync_at' => $connection['last_sync_at'] ?? null,
        'last_run' => $runStmt->fetch(PDO::FETCH_ASSOC) ?: null,
        'selected_audience' => [
            'id' => trim((string)($settings['selected_audience_id'] ?? '')) ?: null,
            'name' => trim((string)($settings['selected_audience_name'] ?? '')) ?: null,
        ],
        'policy' => ['addresses_imported' => false, 'phone_numbers_imported' => false, 'marketing_status_preserved' => true, 'marketing_consent_inferred' => false],
    ];
}
