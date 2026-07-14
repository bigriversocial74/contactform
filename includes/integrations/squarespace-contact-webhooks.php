<?php
declare(strict_types=1);

function mg_squarespace_webhook_connection(PDO $pdo, string $websiteId, string $subscriptionId): ?array
{
    $stmt = $pdo->prepare("SELECT c.*,cr.webhook_secret_ciphertext,cr.metadata_json credential_metadata_json
                           FROM merchant_integration_connections c
                           INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id
                           WHERE c.provider_key='squarespace' AND c.external_account_id=? AND c.status IN ('active','reauthorization_required')
                           ORDER BY (c.status='active') DESC,c.id DESC LIMIT 1");
    $stmt->execute([$websiteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $metadata = mg_integration_json($row['credential_metadata_json'] ?? null);
    $storedSubscription = trim((string)($metadata['squarespace_webhook']['subscription_id'] ?? ''));
    return $storedSubscription !== '' && hash_equals($storedSubscription, $subscriptionId) ? $row : null;
}

function mg_squarespace_receive_webhook(PDO $pdo, string $rawBody, string $signature): array
{
    if ($rawBody === '' || strlen($rawBody) > 1048576) throw new InvalidArgumentException('Invalid webhook payload size.');
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) throw new InvalidArgumentException('Webhook payload must be valid JSON.');
    $eventId = trim((string)($payload['id'] ?? ''));
    $websiteId = trim((string)($payload['websiteId'] ?? ''));
    $subscriptionId = trim((string)($payload['subscriptionId'] ?? ''));
    $topic = trim((string)($payload['topic'] ?? ''));
    if ($eventId === '' || $websiteId === '' || $subscriptionId === '' || !in_array($topic, ['contact.create', 'contact.update', 'contact.delete'], true)) {
        throw new InvalidArgumentException('Webhook identifiers or topic are invalid.');
    }
    $connection = mg_squarespace_webhook_connection($pdo, $websiteId, $subscriptionId);
    if (!$connection) throw new RuntimeException('Squarespace webhook subscription is not recognized.');
    $secretHex = mg_integration_decrypt_secret($connection['webhook_secret_ciphertext'] ?? null);
    $secret = ctype_xdigit($secretHex) && strlen($secretHex) % 2 === 0 ? hex2bin($secretHex) : false;
    if (!is_string($secret) || $secret === '') throw new RuntimeException('Squarespace webhook secret is unavailable.');
    $expected = hash_hmac('sha256', $rawBody, $secret);
    $signature = strtolower(trim($signature));
    if ($signature === '' || !hash_equals($expected, $signature)) throw new RuntimeException('Squarespace webhook signature verification failed.');

    $dedupe = hash('sha256', 'squarespace|' . $eventId . '|' . $subscriptionId);
    $payloadHash = hash('sha256', $rawBody);
    try {
        $pdo->prepare("INSERT INTO merchant_integration_webhook_events (public_id,connection_id,provider_key,external_event_id,dedupe_key,topic,status,signature_verified,payload_sha256,payload_json,attempt_count,received_at,created_at,updated_at) VALUES (?,?,'squarespace',?,?,?,'processing',1,?,?,1,NOW(),NOW(),NOW())")
            ->execute([mg_integration_uuid(), (int)$connection['id'], $eventId, $dedupe, $topic, $payloadHash, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        $eventDbId = (int)$pdo->lastInsertId();
    } catch (PDOException $error) {
        if ((string)$error->getCode() === '23000') return ['status' => 'duplicate', 'event_id' => $eventId];
        throw $error;
    }

    try {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        if ($topic === 'contact.delete') {
            $externalId = trim((string)($data['contactId'] ?? ''));
            if ($externalId !== '') {
                $link = mg_squarespace_contact_link($pdo, (int)$connection['id'], $externalId, false);
                if ($link) {
                    $metadata = mg_integration_json($link['metadata_json'] ?? null);
                    $metadata['deleted_on'] = mg_squarespace_datetime($data['deletedOn'] ?? null);
                    $metadata['deletion_policy'] = 'preserve_microgifter_contact';
                    $pdo->prepare("UPDATE merchant_integration_entity_links SET status='deleted_external',metadata_json=?,updated_at=NOW() WHERE id=?")
                        ->execute([json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int)$link['id']]);
                }
            }
        } else {
            $contact = is_array($data['contact'] ?? null) ? $data['contact'] : [];
            if (!isset($contact['createdOn']) && isset($data['createdOn'])) $contact['createdOn'] = $data['createdOn'];
            if (!isset($contact['updatedOn']) && isset($data['updatedOn'])) $contact['updatedOn'] = $data['updatedOn'];
            mg_squarespace_import_contact($pdo, $connection, $contact, 'webhook');
        }
        $pdo->prepare("UPDATE merchant_integration_webhook_events SET status='processed',processed_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([$eventDbId]);
        return ['status' => 'processed', 'event_id' => $eventId, 'topic' => $topic];
    } catch (Throwable $error) {
        $pdo->prepare("UPDATE merchant_integration_webhook_events SET status='failed',last_error_at=NOW(),last_error_message=?,updated_at=NOW() WHERE id=?")
            ->execute([mb_substr($error->getMessage(), 0, 1000), $eventDbId]);
        throw $error;
    }
}

function mg_squarespace_setup_contact_webhook(PDO $pdo, int $merchantUserId): array
{
    $auth = mg_squarespace_access_token($pdo, $merchantUserId);
    $connection = $auth['connection'];
    $provider = mg_integration_provider('squarespace');
    if (!$provider instanceof MgSquarespaceProvider) throw new RuntimeException('Squarespace provider is unavailable.');
    $connectionId = (int)$connection['id'];
    $metadataStmt = $pdo->prepare('SELECT metadata_json FROM merchant_integration_credentials WHERE connection_id=? LIMIT 1');
    $metadataStmt->execute([$connectionId]);
    $metadata = mg_integration_json($metadataStmt->fetchColumn() ?: null);
    try {
        $subscription = $provider->ensureContactWebhook($auth['token']);
        $secret = trim((string)($subscription['secret'] ?? ''));
        $subscriptionId = trim((string)($subscription['id'] ?? ''));
        if ($secret === '' || $subscriptionId === '') throw new RuntimeException('Squarespace webhook subscription credentials were incomplete.');
        $metadata['squarespace_webhook'] = [
            'subscription_id' => $subscriptionId,
            'endpoint_url' => trim((string)($subscription['endpointUrl'] ?? $provider->contactWebhookUrl())),
            'topics' => array_values(array_map('strval', (array)($subscription['topics'] ?? $provider->contactWebhookTopics()))),
            'website_id' => trim((string)($subscription['websiteId'] ?? $connection['external_account_id'] ?? '')),
            'configured_at' => gmdate('Y-m-d H:i:s'),
            'secret_rotated' => (bool)($subscription['rotated'] ?? false),
            'addresses_subscribed' => false,
        ];
        $pdo->prepare('UPDATE merchant_integration_credentials SET webhook_secret_ciphertext=?,metadata_json=?,updated_at=NOW() WHERE connection_id=?')
            ->execute([
                mg_integration_encrypt_secret($secret),
                json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $connectionId,
            ]);
        return ['configured' => true, 'subscription_id' => $subscriptionId, 'topics' => $metadata['squarespace_webhook']['topics'], 'addresses_subscribed' => false];
    } catch (Throwable $error) {
        $metadata['squarespace_webhook'] = [
            'configured_at' => gmdate('Y-m-d H:i:s'),
            'endpoint_url' => $provider->contactWebhookUrl(),
            'topics' => $provider->contactWebhookTopics(),
            'error' => mb_substr($error->getMessage(), 0, 1000),
            'addresses_subscribed' => false,
        ];
        $pdo->prepare('UPDATE merchant_integration_credentials SET metadata_json=?,updated_at=NOW() WHERE connection_id=?')
            ->execute([json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $connectionId]);
        return ['configured' => false, 'error' => $error->getMessage(), 'addresses_subscribed' => false];
    }
}
