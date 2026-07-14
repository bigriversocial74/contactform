<?php
declare(strict_types=1);

function mg_squarespace_sync_contacts(PDO $pdo, int $merchantUserId, bool $reset = false, int $pageSize = 100, int $maxPages = 5): array
{
    if (!mg_integration_schema_ready($pdo)) throw new RuntimeException('Merchant integrations schema is not installed.');
    $auth = mg_squarespace_access_token($pdo, $merchantUserId);
    $connection = $auth['connection'];
    $provider = mg_integration_provider('squarespace');
    if (!$provider instanceof MgSquarespaceProvider) throw new RuntimeException('Squarespace provider is unavailable.');
    $connectionId = (int)$connection['id'];
    $pageSize = max(1, min(250, $pageSize));
    $maxPages = max(1, min(10, $maxPages));

    $stateStmt = $pdo->prepare("SELECT * FROM merchant_integration_sync_state WHERE connection_id=? AND resource_key='contacts' LIMIT 1");
    $stateStmt->execute([$connectionId]);
    $state = $stateStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $cursor = $reset ? null : (trim((string)($state['cursor_value'] ?? '')) ?: null);
    $runPublicId = mg_integration_uuid();
    $pdo->prepare("INSERT INTO merchant_integration_sync_runs (public_id,connection_id,resource_key,direction,trigger_type,status,cursor_value,started_at,created_at,updated_at) VALUES (?,?,'contacts','import','manual','running',?,NOW(),NOW(),NOW())")
        ->execute([$runPublicId, $connectionId, $cursor]);
    $runId = (int)$pdo->lastInsertId();
    $counts = ['processed' => 0, 'created' => 0, 'updated' => 0, 'linked' => 0, 'review' => 0, 'skipped' => 0, 'failed' => 0];
    $hasNext = false;

    try {
        for ($pageNumber = 0; $pageNumber < $maxPages; $pageNumber++) {
            $page = $provider->listContacts($auth['token'], $cursor, $pageSize);
            foreach ((array)($page['contacts'] ?? []) as $raw) {
                if (!is_array($raw)) continue;
                $counts['processed']++;
                try {
                    $result = mg_squarespace_import_contact($pdo, $connection, $raw, 'manual');
                    if (isset($counts[$result])) $counts[$result]++; else $counts['updated']++;
                } catch (Throwable $contactError) {
                    $counts['failed']++;
                    mg_security_log('warning', 'merchant.integration.squarespace_contact_failed', 'Squarespace contact import failed.', ['exception_class' => $contactError::class], $merchantUserId);
                }
            }
            $pagination = is_array($page['pagination'] ?? null) ? $page['pagination'] : [];
            $hasNext = (bool)($pagination['hasNextPage'] ?? false);
            $cursor = trim((string)($pagination['nextPageCursor'] ?? '')) ?: null;
            $pdo->prepare("INSERT INTO merchant_integration_sync_state (connection_id,resource_key,cursor_value,last_attempt_at,metadata_json,created_at,updated_at) VALUES (?,'contacts',?,NOW(),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE cursor_value=VALUES(cursor_value),last_attempt_at=NOW(),metadata_json=VALUES(metadata_json),updated_at=NOW()")
                ->execute([$connectionId, $hasNext ? $cursor : null, json_encode(['has_next_page' => $hasNext, 'addresses_excluded' => true], JSON_UNESCAPED_SLASHES)]);
            if (!$hasNext || $cursor === null) break;
        }
        $status = $counts['failed'] > 0 ? 'partial' : 'completed';
        $pdo->prepare('UPDATE merchant_integration_sync_runs SET status=?,cursor_value=?,processed_count=?,created_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([$status, $hasNext ? $cursor : null, $counts['processed'], $counts['created'], $counts['updated'] + $counts['linked'], $counts['skipped'] + $counts['review'], $counts['failed'], $runId]);
        $pdo->prepare("UPDATE merchant_integration_sync_state SET last_success_at=NOW(),last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE connection_id=? AND resource_key='contacts'")
            ->execute([$connectionId]);
        $pdo->prepare('UPDATE merchant_integration_connections SET last_sync_at=NOW(),last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE id=?')
            ->execute([$connectionId]);
        return ['run_id' => $runPublicId, 'status' => $status, 'counts' => $counts, 'has_more' => $hasNext, 'next_cursor_saved' => $hasNext, 'addresses_imported' => false];
    } catch (Throwable $error) {
        $pdo->prepare("UPDATE merchant_integration_sync_runs SET status='failed',error_code=?,error_message=?,processed_count=?,created_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000), $counts['processed'], $counts['created'], $counts['updated'] + $counts['linked'], $counts['skipped'] + $counts['review'], $counts['failed'], $runId]);
        $pdo->prepare("INSERT INTO merchant_integration_sync_state (connection_id,resource_key,cursor_value,last_attempt_at,last_error_code,last_error_message,created_at,updated_at) VALUES (?,'contacts',?,NOW(),?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_attempt_at=NOW(),last_error_code=VALUES(last_error_code),last_error_message=VALUES(last_error_message),updated_at=NOW()")
            ->execute([$connectionId, $cursor, mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000)]);
        throw $error;
    }
}

function mg_squarespace_contacts_status(PDO $pdo, int $merchantUserId): array
{
    if (!mg_integration_schema_ready($pdo)) return ['connected' => false, 'counts' => []];
    $connection = mg_integration_connection_row($pdo, $merchantUserId, 'squarespace', false);
    if (!$connection) return ['connected' => false, 'counts' => []];
    $connectionId = (int)$connection['id'];
    $stmt = $pdo->prepare("SELECT status,COUNT(*) total FROM merchant_integration_entity_links WHERE connection_id=? AND entity_type='contact' GROUP BY status");
    $stmt->execute([$connectionId]);
    $counts = ['linked' => 0, 'pending_review' => 0, 'conflict' => 0, 'deleted_external' => 0, 'error' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $counts[(string)$row['status']] = (int)$row['total'];
    $runStmt = $pdo->prepare("SELECT public_id,status,processed_count,created_count,updated_count,skipped_count,failed_count,started_at,finished_at FROM merchant_integration_sync_runs WHERE connection_id=? AND resource_key='contacts' ORDER BY id DESC LIMIT 1");
    $runStmt->execute([$connectionId]);
    $lastRun = $runStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $credentialStmt = $pdo->prepare('SELECT webhook_secret_ciphertext,metadata_json FROM merchant_integration_credentials WHERE connection_id=? LIMIT 1');
    $credentialStmt->execute([$connectionId]);
    $credentials = $credentialStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $credentialMetadata = mg_integration_json($credentials['metadata_json'] ?? null);
    return [
        'connected' => (string)$connection['status'] === 'active',
        'connection_status' => (string)$connection['status'],
        'counts' => $counts,
        'total_contacts' => array_sum($counts),
        'last_sync_at' => $connection['last_sync_at'] ?? null,
        'last_run' => $lastRun,
        'webhook' => [
            'configured' => trim((string)($credentials['webhook_secret_ciphertext'] ?? '')) !== '',
            'subscription_id' => $credentialMetadata['squarespace_webhook']['subscription_id'] ?? null,
            'endpoint_url' => $credentialMetadata['squarespace_webhook']['endpoint_url'] ?? null,
            'topics' => $credentialMetadata['squarespace_webhook']['topics'] ?? [],
            'error' => $credentialMetadata['squarespace_webhook']['error'] ?? null,
        ],
        'policy' => ['addresses_imported' => false, 'phone_from_address_imported' => false],
    ];
}
