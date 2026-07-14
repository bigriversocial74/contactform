<?php
declare(strict_types=1);

require_once __DIR__ . '/providers/mailchimp.php';

function mg_mailchimp_provider(): MgMailchimpProvider
{
    static $provider;
    if (!$provider instanceof MgMailchimpProvider) $provider = new MgMailchimpProvider();
    return $provider;
}

function mg_mailchimp_provider_catalog(array $catalog): array
{
    $provider = mg_mailchimp_provider();
    $entry = [
        'key' => $provider->key(), 'label' => $provider->label(), 'description' => $provider->description(),
        'auth_type' => $provider->authType(), 'capabilities' => $provider->capabilities(),
        'available' => true, 'configuration' => $provider->configurationStatus(),
    ];
    $replaced = false;
    foreach ($catalog as $index => $item) {
        if ((string)($item['key'] ?? '') !== 'mailchimp') continue;
        $catalog[$index] = $entry;
        $replaced = true;
        break;
    }
    if (!$replaced) $catalog[] = $entry;
    return array_values($catalog);
}

function mg_mailchimp_datetime(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
}

function mg_mailchimp_status(string $status): array
{
    $status = strtolower(trim($status));
    return match ($status) {
        'subscribed' => ['accepts_marketing' => true, 'status' => 'SUBSCRIBED'],
        'unsubscribed' => ['accepts_marketing' => false, 'status' => 'UNSUBSCRIBED'],
        'cleaned' => ['accepts_marketing' => false, 'status' => 'CLEANED'],
        'pending' => ['accepts_marketing' => false, 'status' => 'PENDING'],
        'transactional' => ['accepts_marketing' => false, 'status' => 'TRANSACTIONAL'],
        'archived' => ['accepts_marketing' => false, 'status' => 'ARCHIVED'],
        default => ['accepts_marketing' => false, 'status' => 'UNKNOWN'],
    } + ['source' => 'mailchimp_member_status', 'inferred' => false, 'raw_status' => $status];
}

function mg_mailchimp_normalize_member(array $member, string $audienceId): array
{
    $email = strtolower(trim((string)($member['email_address'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    $mergeFields = is_array($member['merge_fields'] ?? null) ? $member['merge_fields'] : [];
    $firstName = trim((string)($mergeFields['FNAME'] ?? ''));
    $lastName = trim((string)($mergeFields['LNAME'] ?? ''));
    $name = trim($firstName . ' ' . $lastName);
    $tags = [];
    foreach ((array)($member['tags'] ?? []) as $tag) {
        if (!is_array($tag)) continue;
        $tagName = trim((string)($tag['name'] ?? ''));
        if ($tagName !== '') $tags[] = mb_substr($tagName, 0, 100);
    }
    $tags = array_values(array_unique(array_slice($tags, 0, 100)));
    $normalized = [
        'external_id' => trim((string)($member['id'] ?? '')),
        'audience_id' => trim($audienceId),
        'email' => $email,
        'display_name' => $name !== '' ? mb_substr($name, 0, 180) : ($email !== '' ? mb_substr($email, 0, 180) : 'Mailchimp contact'),
        'first_name' => mb_substr($firstName, 0, 100),
        'last_name' => mb_substr($lastName, 0, 100),
        'unique_email_id' => mb_substr(trim((string)($member['unique_email_id'] ?? '')), 0, 80),
        'web_id' => max(0, (int)($member['web_id'] ?? 0)),
        'email_type' => mb_substr(trim((string)($member['email_type'] ?? '')), 0, 20),
        'vip' => (bool)($member['vip'] ?? false),
        'tags' => $tags,
        'created_on' => mg_mailchimp_datetime($member['timestamp_signup'] ?? null),
        'updated_on' => mg_mailchimp_datetime($member['last_changed'] ?? null),
        'marketing' => mg_mailchimp_status((string)($member['status'] ?? '')),
        'addresses_excluded' => true,
        'phone_numbers_excluded' => true,
        'non_name_merge_fields_excluded' => true,
    ];
    $normalized['sync_hash'] = hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $normalized;
}

function mg_mailchimp_begin_oauth(PDO $pdo, int $merchantUserId): array
{
    if (!mg_integration_schema_ready($pdo)) throw new RuntimeException('Merchant integrations schema is not installed.');
    if (mg_integration_credential_master_key() === null) throw new MgIntegrationCredentialException('Integration credential encryption is not configured.');
    $provider = mg_mailchimp_provider();
    if (!$provider->isConfigured()) throw new RuntimeException('Mailchimp OAuth is not configured.');
    $state = bin2hex(random_bytes(32));
    $stateHash = hash('sha256', $state);

    $pdo->beginTransaction();
    try {
        $connection = mg_integration_connection_row($pdo, $merchantUserId, 'mailchimp', true);
        if (!$connection || (string)($connection['status'] ?? '') === 'disconnected') {
            $pdo->prepare("INSERT INTO merchant_integration_connections (public_id,merchant_user_id,provider_key,auth_type,status,sync_direction,scopes_json,settings_json,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,'oauth2','pending','import_only','[]',?,?,?,NOW(),NOW())")
                ->execute([mg_integration_uuid(), $merchantUserId, 'mailchimp', json_encode(['contact_sync' => 'import_only'], JSON_UNESCAPED_SLASHES), $merchantUserId, $merchantUserId]);
            $connectionId = (int)$pdo->lastInsertId();
        } else {
            $connectionId = (int)$connection['id'];
            $pdo->prepare("UPDATE merchant_integration_connections SET status='pending',last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([$merchantUserId, $connectionId]);
        }
        $pdo->prepare("INSERT INTO merchant_integration_credentials (connection_id,oauth_state_hash,oauth_state_expires_at,metadata_json,created_at,updated_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE oauth_state_hash=VALUES(oauth_state_hash),oauth_state_expires_at=VALUES(oauth_state_expires_at),metadata_json=JSON_MERGE_PATCH(COALESCE(metadata_json,'{}'),VALUES(metadata_json)),updated_at=NOW()")
            ->execute([$connectionId, $stateHash, json_encode(['oauth_started_at' => gmdate('c')], JSON_UNESCAPED_SLASHES)]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return ['provider' => 'mailchimp', 'authorization_url' => $provider->buildAuthorizationUrl($state), 'state_expires_in' => 600];
}

function mg_mailchimp_pending_oauth(PDO $pdo, int $merchantUserId, string $stateHash, bool $forUpdate = false): ?array
{
    $sql = "SELECT c.*,cr.oauth_state_hash,cr.oauth_state_expires_at FROM merchant_integration_connections c INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id WHERE c.merchant_user_id=? AND c.provider_key='mailchimp' AND cr.oauth_state_hash=? AND cr.oauth_state_expires_at>=NOW() ORDER BY c.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId, $stateHash]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mg_mailchimp_complete_oauth(PDO $pdo, int $merchantUserId, string $state, string $code): array
{
    $state = trim($state);
    $code = trim($code);
    if ($state === '' || $code === '') throw new RuntimeException('Mailchimp did not return a valid authorization response.');
    $stateHash = hash('sha256', $state);
    $pending = mg_mailchimp_pending_oauth($pdo, $merchantUserId, $stateHash);
    if (!$pending) throw new RuntimeException('The Mailchimp authorization request expired or does not match this account.');

    $provider = mg_mailchimp_provider();
    $tokens = $provider->exchangeAuthorizationCode($code);
    $accessToken = trim((string)($tokens['access_token'] ?? ''));
    if ($accessToken === '') throw new RuntimeException('Mailchimp did not return an access token.');
    $metadata = $provider->fetchMetadata($accessToken);
    $apiEndpoint = rtrim(trim((string)($metadata['api_endpoint'] ?? '')), '/');
    $dc = trim((string)($metadata['dc'] ?? ''));
    if ($apiEndpoint === '' && $dc !== '') $apiEndpoint = 'https://' . $dc . '.api.mailchimp.com/3.0';
    if ($apiEndpoint === '') throw new RuntimeException('Mailchimp did not return an API endpoint.');
    $accountName = trim((string)($metadata['accountname'] ?? $metadata['login']['login_name'] ?? 'Mailchimp account')) ?: 'Mailchimp account';
    $accountId = trim((string)($metadata['user_id'] ?? $metadata['login']['login_id'] ?? ''));
    if ($accountId === '') $accountId = hash('sha256', strtolower($apiEndpoint . '|' . $accountName));
    $accountUrl = trim((string)($metadata['login_url'] ?? '')) ?: 'https://admin.mailchimp.com/';
    $priorSettings = mg_integration_json($pending['settings_json'] ?? null);
    $sameAccount = trim((string)($pending['external_account_id'] ?? '')) !== ''
        && hash_equals(trim((string)$pending['external_account_id']), $accountId);
    $settings = [
        'account' => ['name' => $accountName, 'dc' => $dc, 'api_endpoint' => $apiEndpoint],
        'contact_sync' => 'import_only',
        'selected_audience_id' => $sameAccount ? ($priorSettings['selected_audience_id'] ?? null) : null,
        'selected_audience_name' => $sameAccount ? ($priorSettings['selected_audience_name'] ?? null) : null,
        'addresses_imported' => false,
        'phone_numbers_imported' => false,
        'marketing_status_mode' => 'preserve_member_status',
    ];

    $pdo->beginTransaction();
    try {
        $locked = mg_mailchimp_pending_oauth($pdo, $merchantUserId, $stateHash, true);
        if (!$locked) throw new RuntimeException('The Mailchimp authorization request was already used or expired.');
        $connectionId = (int)$locked['id'];
        $pdo->prepare("UPDATE merchant_integration_connections SET status='active',external_account_id=?,external_account_name=?,external_account_url=?,scopes_json='[]',settings_json=?,connected_at=COALESCE(connected_at,NOW()),disconnected_at=NULL,last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([$accountId, $accountName, $accountUrl, json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $merchantUserId, $connectionId]);
        $pdo->prepare("UPDATE merchant_integration_credentials SET access_token_ciphertext=?,refresh_token_ciphertext=NULL,token_type='oauth',access_expires_at=NULL,refresh_expires_at=NULL,oauth_state_hash=NULL,oauth_state_expires_at=NULL,metadata_json=?,updated_at=NOW() WHERE connection_id=?")
            ->execute([mg_integration_encrypt_secret($accessToken), json_encode(['dc' => $dc, 'api_endpoint' => $apiEndpoint, 'access_token_non_expiring' => true], JSON_UNESCAPED_SLASHES), $connectionId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $row = mg_integration_connection_row($pdo, $merchantUserId, 'mailchimp', false);
    return $row ? mg_integration_connection_public($row) : [];
}

function mg_mailchimp_credentials(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT c.*,cr.access_token_ciphertext,cr.metadata_json credential_metadata_json FROM merchant_integration_connections c INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id WHERE c.merchant_user_id=? AND c.provider_key='mailchimp' AND c.status IN ('active','error','reauthorization_required') ORDER BY (c.status='active') DESC,c.id DESC LIMIT 1");
    $stmt->execute([$merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('An active Mailchimp connection is required.');
    $token = mg_integration_decrypt_secret($row['access_token_ciphertext'] ?? null);
    if ($token === '') throw new MgIntegrationCredentialException('Stored Mailchimp access credentials are unavailable.');
    $settings = mg_integration_json($row['settings_json'] ?? null);
    $credentialMetadata = mg_integration_json($row['credential_metadata_json'] ?? null);
    $apiEndpoint = trim((string)($settings['account']['api_endpoint'] ?? $credentialMetadata['api_endpoint'] ?? ''));
    if ($apiEndpoint === '') throw new RuntimeException('Mailchimp API endpoint is unavailable. Reauthorize the connection.');
    return ['connection' => $row, 'token' => $token, 'api_endpoint' => $apiEndpoint, 'settings' => $settings];
}

function mg_mailchimp_mark_reauthorization(PDO $pdo, int $connectionId, Throwable $error): void
{
    $message = strtolower($error->getMessage());
    if (!str_contains($message, 'rejected') && !str_contains($message, 'revoked') && !str_contains($message, 'reauthorize')) return;
    $pdo->prepare("UPDATE merchant_integration_connections SET status='reauthorization_required',last_error_at=NOW(),last_error_code=?,last_error_message=?,updated_at=NOW() WHERE id=?")
        ->execute([mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000), $connectionId]);
}

function mg_mailchimp_audiences(PDO $pdo, int $merchantUserId): array
{
    $auth = mg_mailchimp_credentials($pdo, $merchantUserId);
    try {
        $response = mg_mailchimp_provider()->listAudiences($auth['api_endpoint'], $auth['token'], 0, 1000);
    } catch (Throwable $error) {
        mg_mailchimp_mark_reauthorization($pdo, (int)$auth['connection']['id'], $error);
        throw $error;
    }
    $items = [];
    foreach ((array)($response['lists'] ?? []) as $list) {
        if (!is_array($list)) continue;
        $id = trim((string)($list['id'] ?? ''));
        if ($id === '') continue;
        $stats = is_array($list['stats'] ?? null) ? $list['stats'] : [];
        $items[] = [
            'id' => $id, 'name' => trim((string)($list['name'] ?? 'Mailchimp audience')) ?: 'Mailchimp audience',
            'member_count' => max(0, (int)($stats['member_count'] ?? 0)),
            'unsubscribe_count' => max(0, (int)($stats['unsubscribe_count'] ?? 0)),
            'cleaned_count' => max(0, (int)($stats['cleaned_count'] ?? 0)),
            'date_created' => mg_mailchimp_datetime($list['date_created'] ?? null),
        ];
    }
    return ['provider' => 'mailchimp', 'items' => $items, 'total_items' => (int)($response['total_items'] ?? count($items)), 'selected_audience_id' => $auth['settings']['selected_audience_id'] ?? null];
}

function mg_mailchimp_select_audience(PDO $pdo, int $merchantUserId, string $audienceId): array
{
    $audienceId = trim($audienceId);
    if ($audienceId === '') throw new InvalidArgumentException('Mailchimp audience is required.');
    $audiences = mg_mailchimp_audiences($pdo, $merchantUserId);
    $selected = null;
    foreach ($audiences['items'] as $item) {
        if (hash_equals((string)$item['id'], $audienceId)) { $selected = $item; break; }
    }
    if (!$selected) throw new InvalidArgumentException('The selected Mailchimp audience is not available to this connection.');
    $connection = mg_integration_connection_row($pdo, $merchantUserId, 'mailchimp', false);
    if (!$connection) throw new RuntimeException('Mailchimp connection was not found.');
    $settings = mg_integration_json($connection['settings_json'] ?? null);
    $settings['selected_audience_id'] = $selected['id'];
    $settings['selected_audience_name'] = $selected['name'];
    $settings['selected_audience_at'] = gmdate('c');
    $pdo->prepare('UPDATE merchant_integration_connections SET settings_json=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')
        ->execute([json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $merchantUserId, (int)$connection['id']]);
    return ['selected_audience' => $selected, 'connection' => mg_integration_connection_public(mg_integration_connection_row($pdo, $merchantUserId, 'mailchimp', false) ?: $connection)];
}

function mg_mailchimp_selected_audience(array $auth): array
{
    $id = trim((string)($auth['settings']['selected_audience_id'] ?? ''));
    $name = trim((string)($auth['settings']['selected_audience_name'] ?? ''));
    if ($id === '') throw new RuntimeException('Choose a Mailchimp audience before previewing or importing contacts.');
    return ['id' => $id, 'name' => $name !== '' ? $name : 'Mailchimp audience'];
}
