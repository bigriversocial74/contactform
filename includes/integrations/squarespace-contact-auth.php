<?php
declare(strict_types=1);

/**
 * Squarespace Contacts Import v1.
 * Contact addresses and address-derived phone numbers are intentionally ignored.
 */

function mg_squarespace_datetime(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
}

function mg_squarespace_normalize_contact(array $contact): array
{
    $primaryEmail = is_array($contact['primaryEmail'] ?? null) ? $contact['primaryEmail'] : [];
    $marketing = is_array($primaryEmail['acceptsMarketing'] ?? null) ? $primaryEmail['acceptsMarketing'] : [];
    $externalId = trim((string)($contact['id'] ?? $contact['contactId'] ?? ''));
    $email = strtolower(trim((string)($primaryEmail['email'] ?? $primaryEmail['value'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    $firstName = trim((string)($contact['firstName'] ?? ''));
    $lastName = trim((string)($contact['lastName'] ?? ''));
    $name = trim($firstName . ' ' . $lastName);
    $acceptsMarketing = array_key_exists('acceptsMarketing', $marketing) ? (bool)$marketing['acceptsMarketing'] : false;
    $normalized = [
        'external_id' => $externalId,
        'email' => $email,
        'display_name' => $name !== '' ? mb_substr($name, 0, 180) : ($email !== '' ? mb_substr($email, 0, 180) : 'Squarespace contact'),
        'first_name' => mb_substr($firstName, 0, 100),
        'last_name' => mb_substr($lastName, 0, 100),
        'locale' => mb_substr(trim((string)($contact['locale'] ?? '')), 0, 30),
        'created_on' => mg_squarespace_datetime($contact['createdOn'] ?? $primaryEmail['createdOn'] ?? null),
        'updated_on' => mg_squarespace_datetime($contact['updatedOn'] ?? null),
        'marketing' => [
            'accepts_marketing' => $acceptsMarketing,
            'joined_on' => mg_squarespace_datetime($marketing['joinedOn'] ?? null),
            'left_on' => mg_squarespace_datetime($marketing['leftOn'] ?? null),
            'source' => 'squarespace',
        ],
        'addresses_excluded' => true,
    ];
    $normalized['sync_hash'] = hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $normalized;
}

function mg_squarespace_connection_credentials(PDO $pdo, int $merchantUserId, bool $forUpdate = false): ?array
{
    $sql = "SELECT c.*,cr.access_token_ciphertext,cr.refresh_token_ciphertext,cr.webhook_secret_ciphertext,
                   cr.access_expires_at,cr.refresh_expires_at,cr.refresh_lock_token,cr.refresh_lock_expires_at,
                   cr.metadata_json credential_metadata_json
            FROM merchant_integration_connections c
            INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id
            WHERE c.merchant_user_id=? AND c.provider_key='squarespace'
              AND c.status IN ('active','reauthorization_required','error')
            ORDER BY (c.status='active') DESC,c.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_squarespace_mark_reauthorization(PDO $pdo, int $connectionId, string $message): void
{
    $pdo->prepare("UPDATE merchant_integration_connections SET status='reauthorization_required',last_error_at=NOW(),last_error_code='squarespace_reauthorization_required',last_error_message=?,updated_at=NOW() WHERE id=?")
        ->execute([mb_substr($message, 0, 1000), $connectionId]);
}

function mg_squarespace_access_token(PDO $pdo, int $merchantUserId): array
{
    $row = mg_squarespace_connection_credentials($pdo, $merchantUserId, false);
    if (!$row) throw new RuntimeException('An active Squarespace connection is required.');
    $connectionId = (int)$row['id'];
    $accessToken = mg_integration_decrypt_secret($row['access_token_ciphertext'] ?? null);
    $accessExpires = strtotime((string)($row['access_expires_at'] ?? '')) ?: 0;
    if ($accessToken !== '' && $accessExpires > time() + 90) return ['token' => $accessToken, 'connection' => $row];

    $lockToken = bin2hex(random_bytes(24));
    $refreshToken = '';
    $pdo->beginTransaction();
    try {
        $locked = mg_squarespace_connection_credentials($pdo, $merchantUserId, true);
        if (!$locked) throw new RuntimeException('Squarespace connection credentials were not found.');
        $connectionId = (int)$locked['id'];
        $accessToken = mg_integration_decrypt_secret($locked['access_token_ciphertext'] ?? null);
        $accessExpires = strtotime((string)($locked['access_expires_at'] ?? '')) ?: 0;
        if ($accessToken !== '' && $accessExpires > time() + 90) {
            $pdo->commit();
            return ['token' => $accessToken, 'connection' => $locked];
        }
        $existingLockExpires = strtotime((string)($locked['refresh_lock_expires_at'] ?? '')) ?: 0;
        if (trim((string)($locked['refresh_lock_token'] ?? '')) !== '' && $existingLockExpires > time()) {
            throw new RuntimeException('Squarespace credentials are currently being refreshed. Try again in a moment.');
        }
        $refreshToken = mg_integration_decrypt_secret($locked['refresh_token_ciphertext'] ?? null);
        if ($refreshToken === '') throw new RuntimeException('Squarespace refresh credentials are unavailable. Reauthorize the connection.');
        $pdo->prepare('UPDATE merchant_integration_credentials SET refresh_lock_token=?,refresh_lock_expires_at=DATE_ADD(NOW(),INTERVAL 45 SECOND),updated_at=NOW() WHERE connection_id=?')
            ->execute([$lockToken, $connectionId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    try {
        $provider = mg_integration_provider('squarespace');
        if (!$provider instanceof MgSquarespaceProvider) throw new RuntimeException('Squarespace provider is unavailable.');
        $tokens = $provider->refreshAccessToken($refreshToken);
        $newAccess = trim((string)($tokens['access_token'] ?? $tokens['token'] ?? ''));
        $newRefresh = trim((string)($tokens['refresh_token'] ?? ''));
        if ($newAccess === '' || $newRefresh === '') throw new RuntimeException('Squarespace did not return rotated access and refresh tokens.');
        $accessExpiresAt = mg_integration_datetime_from_epoch($tokens['access_token_expires_at'] ?? null);
        $refreshExpiresAt = mg_integration_datetime_from_epoch($tokens['refresh_token_expires_at'] ?? null);
        $stmt = $pdo->prepare('UPDATE merchant_integration_credentials SET access_token_ciphertext=?,refresh_token_ciphertext=?,access_expires_at=?,refresh_expires_at=?,refresh_lock_token=NULL,refresh_lock_expires_at=NULL,updated_at=NOW() WHERE connection_id=? AND refresh_lock_token=?');
        $stmt->execute([
            mg_integration_encrypt_secret($newAccess),
            mg_integration_encrypt_secret($newRefresh),
            $accessExpiresAt,
            $refreshExpiresAt,
            $connectionId,
            $lockToken,
        ]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Squarespace token refresh lock was lost before credentials could be stored.');
        $pdo->prepare("UPDATE merchant_integration_connections SET status='active',last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE id=?")
            ->execute([$connectionId]);
        $fresh = mg_squarespace_connection_credentials($pdo, $merchantUserId, false) ?: $row;
        return ['token' => $newAccess, 'connection' => $fresh];
    } catch (Throwable $error) {
        $pdo->prepare('UPDATE merchant_integration_credentials SET refresh_lock_token=NULL,refresh_lock_expires_at=NULL,updated_at=NOW() WHERE connection_id=? AND refresh_lock_token=?')
            ->execute([$connectionId, $lockToken]);
        $message = strtolower($error->getMessage());
        $requiresReauthorization = str_contains($message, 'authorization')
            || str_contains($message, 'revoked')
            || str_contains($message, 'refresh credentials')
            || str_contains($message, 'invalid token');
        if ($requiresReauthorization) {
            mg_squarespace_mark_reauthorization($pdo, $connectionId, $error->getMessage());
        } else {
            $pdo->prepare("UPDATE merchant_integration_connections SET last_error_at=NOW(),last_error_code='squarespace_refresh_failed',last_error_message=?,updated_at=NOW() WHERE id=?")
                ->execute([mb_substr($error->getMessage(), 0, 1000), $connectionId]);
        }
        throw $error;
    }
}
