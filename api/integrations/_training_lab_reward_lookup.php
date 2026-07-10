<?php
declare(strict_types=1);

/**
 * Stage 894 — signed, read-only Training Lab reward lookup service.
 *
 * The service verifies a short-lived HMAC request, reserves the request nonce in
 * the existing idempotency table, binds every lookup to the supplied Microgifter
 * user, and reads only canonical Microgift/PPPM records.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/idempotency.php';

if (!class_exists('MgTrainingLabRewardLookupException')) {
    final class MgTrainingLabRewardLookupException extends RuntimeException
    {
        private int $httpStatus;
        private string $errorCode;

        public function __construct(string $message, int $httpStatus = 400, string $errorCode = 'invalid_request')
        {
            parent::__construct($message);
            $this->httpStatus = max(400, min(599, $httpStatus));
            $this->errorCode = preg_replace('/[^a-z0-9_\-]/i', '', $errorCode) ?: 'invalid_request';
        }

        public function httpStatus(): int { return $this->httpStatus; }
        public function errorCode(): string { return $this->errorCode; }
    }
}

if (!function_exists('mg_training_lab_reward_lookup_bool')) {
    function mg_training_lab_reward_lookup_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null || $value === '') return $default;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('mg_training_lab_reward_lookup_config')) {
    function mg_training_lab_reward_lookup_config(): array
    {
        $enabled = getenv('MG_TRAINING_LAB_REWARD_LOOKUP_ENABLED');
        $secret = trim((string)(getenv('MG_TRAINING_LAB_REWARD_LOOKUP_SECRET') ?: ''));
        $skew = getenv('MG_TRAINING_LAB_REWARD_LOOKUP_MAX_SKEW_SECONDS');
        $maxBytes = getenv('MG_TRAINING_LAB_REWARD_LOOKUP_MAX_BODY_BYTES');
        $nonceTtl = getenv('MG_TRAINING_LAB_REWARD_LOOKUP_NONCE_TTL_SECONDS');

        return [
            'enabled' => mg_training_lab_reward_lookup_bool($enabled !== false ? $enabled : false, false),
            'secret' => $secret,
            'secret_present' => strlen($secret) >= 32,
            'max_skew_seconds' => max(30, min(900, (int)($skew !== false && $skew !== '' ? $skew : 300))),
            'max_body_bytes' => max(1024, min(262144, (int)($maxBytes !== false && $maxBytes !== '' ? $maxBytes : 65536))),
            'nonce_ttl_seconds' => max(300, min(3600, (int)($nonceTtl !== false && $nonceTtl !== '' ? $nonceTtl : 900))),
        ];
    }
}

if (!function_exists('mg_training_lab_reward_lookup_header')) {
    function mg_training_lab_reward_lookup_header(string $serverKey): string
    {
        return trim((string)($_SERVER[$serverKey] ?? ''));
    }
}

if (!function_exists('mg_training_lab_reward_lookup_canonical')) {
    function mg_training_lab_reward_lookup_canonical(string $timestamp, string $nonce, string $rawBody): string
    {
        return "training-lab-reward-lookup-v1\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $rawBody);
    }
}

if (!function_exists('mg_training_lab_reward_lookup_signature')) {
    function mg_training_lab_reward_lookup_signature(string $secret, string $timestamp, string $nonce, string $rawBody): string
    {
        return hash_hmac('sha256', mg_training_lab_reward_lookup_canonical($timestamp, $nonce, $rawBody), $secret);
    }
}

if (!function_exists('mg_training_lab_reward_lookup_clean_reference')) {
    function mg_training_lab_reward_lookup_clean_reference($value, int $max = 190): string
    {
        $value = trim((string)$value);
        if ($value === '') return '';
        if (strlen($value) > $max) {
            throw new MgTrainingLabRewardLookupException('A lookup reference is too long.', 422, 'reference_too_long');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new MgTrainingLabRewardLookupException('A lookup reference contains invalid characters.', 422, 'reference_invalid');
        }
        return $value;
    }
}

if (!function_exists('mg_training_lab_reward_lookup_validate_payload')) {
    function mg_training_lab_reward_lookup_validate_payload(array $payload): array
    {
        if ((string)($payload['contract'] ?? '') !== 'training_lab_reward_reconciliation_v1') {
            throw new MgTrainingLabRewardLookupException('Unsupported lookup contract.', 422, 'contract_invalid');
        }
        if ((string)($payload['source'] ?? '') !== 'training_lab') {
            throw new MgTrainingLabRewardLookupException('Unsupported lookup source.', 422, 'source_invalid');
        }
        if (($payload['read_only'] ?? null) !== true) {
            throw new MgTrainingLabRewardLookupException('The lookup request must declare read_only=true.', 422, 'read_only_required');
        }

        $microgifterUserId = trim((string)($payload['microgifter_user_id'] ?? ''));
        if ($microgifterUserId === '' || !ctype_digit($microgifterUserId) || (int)$microgifterUserId < 1) {
            throw new MgTrainingLabRewardLookupException('A valid Microgifter user ID is required.', 422, 'microgifter_user_required');
        }

        $idempotencyKey = mg_training_lab_reward_lookup_clean_reference($payload['idempotency_key'] ?? '', 190);
        $externalReference = mg_training_lab_reward_lookup_clean_reference($payload['external_reference'] ?? '', 190);
        if ($idempotencyKey === '' && $externalReference === '') {
            throw new MgTrainingLabRewardLookupException('An idempotency key or external reference is required.', 422, 'lookup_reference_required');
        }

        return [
            'contract' => 'training_lab_reward_reconciliation_v1',
            'source' => 'training_lab',
            'read_only' => true,
            'microgifter_user_id' => (int)$microgifterUserId,
            'idempotency_key' => $idempotencyKey,
            'external_reference' => $externalReference,
            'training_handoff_id' => max(0, (int)($payload['training_handoff_id'] ?? 0)),
            'training_handoff_public_id' => mg_training_lab_reward_lookup_clean_reference($payload['training_handoff_public_id'] ?? '', 190),
            'training_reward_event_id' => max(0, (int)($payload['training_reward_event_id'] ?? 0)),
            'training_reward_public_id' => mg_training_lab_reward_lookup_clean_reference($payload['training_reward_public_id'] ?? '', 190),
            'training_user_id' => max(0, (int)($payload['training_user_id'] ?? 0)),
        ];
    }
}

if (!function_exists('mg_training_lab_reward_lookup_reserve_nonce')) {
    function mg_training_lab_reward_lookup_reserve_nonce(string $nonce, string $requestHash, int $ttlSeconds): string
    {
        $nonceKey = 'tl-reward-lookup:' . hash('sha256', $nonce);
        try {
            $reservation = mg_idempotency_reserve($nonceKey, 'tl_reward_lookup', 0, $requestHash, $ttlSeconds);
        } catch (Throwable $e) {
            throw new MgTrainingLabRewardLookupException('Replay protection is unavailable.', 503, 'replay_protection_unavailable');
        }
        if ((string)($reservation['status'] ?? '') !== 'reserved') {
            throw new MgTrainingLabRewardLookupException('This signed request nonce has already been used.', 409, 'request_replayed');
        }
        return $nonceKey;
    }
}

if (!function_exists('mg_training_lab_reward_lookup_authenticate')) {
    function mg_training_lab_reward_lookup_authenticate(string $rawBody): array
    {
        $config = mg_training_lab_reward_lookup_config();
        if (empty($config['enabled'])) {
            throw new MgTrainingLabRewardLookupException('Training Lab reward lookup is disabled.', 503, 'lookup_disabled');
        }
        if (empty($config['secret_present'])) {
            throw new MgTrainingLabRewardLookupException('Training Lab reward lookup is not configured.', 503, 'lookup_secret_missing');
        }
        if ($rawBody === '' || strlen($rawBody) > (int)$config['max_body_bytes']) {
            throw new MgTrainingLabRewardLookupException('The request body is missing or too large.', 413, 'request_body_invalid');
        }

        $timestamp = mg_training_lab_reward_lookup_header('HTTP_X_MICROGIFTER_TRAINING_LAB_TIMESTAMP');
        $nonce = mg_training_lab_reward_lookup_header('HTTP_X_MICROGIFTER_TRAINING_LAB_NONCE');
        $signature = strtolower(mg_training_lab_reward_lookup_header('HTTP_X_MICROGIFTER_TRAINING_LAB_SIGNATURE'));

        if ($timestamp === '' || !ctype_digit($timestamp)) {
            throw new MgTrainingLabRewardLookupException('The signed request timestamp is invalid.', 401, 'timestamp_invalid');
        }
        if (abs(time() - (int)$timestamp) > (int)$config['max_skew_seconds']) {
            throw new MgTrainingLabRewardLookupException('The signed request timestamp is outside the allowed window.', 401, 'timestamp_expired');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $nonce)) {
            throw new MgTrainingLabRewardLookupException('The signed request nonce is invalid.', 401, 'nonce_invalid');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $signature)) {
            throw new MgTrainingLabRewardLookupException('The signed request signature is invalid.', 401, 'signature_invalid');
        }

        $expected = mg_training_lab_reward_lookup_signature((string)$config['secret'], $timestamp, $nonce, $rawBody);
        if (!hash_equals($expected, $signature)) {
            throw new MgTrainingLabRewardLookupException('The signed request signature is invalid.', 401, 'signature_invalid');
        }

        try {
            $decoded = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new MgTrainingLabRewardLookupException('The request JSON is invalid.', 422, 'json_invalid');
        }
        if (!is_array($decoded)) {
            throw new MgTrainingLabRewardLookupException('The request JSON must be an object.', 422, 'json_shape_invalid');
        }
        $payload = mg_training_lab_reward_lookup_validate_payload($decoded);
        $requestHash = hash('sha256', mg_training_lab_reward_lookup_canonical($timestamp, $nonce, $rawBody));
        $nonceKey = mg_training_lab_reward_lookup_reserve_nonce($nonce, $requestHash, (int)$config['nonce_ttl_seconds']);

        return [
            'payload' => $payload,
            'nonce_key' => $nonceKey,
            'nonce_hash' => substr(hash('sha256', $nonce), 0, 16),
            'request_hash' => $requestHash,
            'timestamp' => (int)$timestamp,
        ];
    }
}

if (!function_exists('mg_training_lab_reward_lookup_instance_by_idempotency')) {
    function mg_training_lab_reward_lookup_instance_by_idempotency(PDO $pdo, int $userId, string $idempotencyKey): ?array
    {
        if ($idempotencyKey === '') return null;
        $stmt = $pdo->prepare(
            "SELECT i.id,i.public_id,i.status,i.idempotency_key,i.owner_user_id,i.recipient_user_id,
                    i.legacy_gift_id,i.pppm_item_id,i.issued_at,i.delivered_at,i.claimed_at,
                    i.redeemed_at,i.expires_at,i.cancelled_at,i.revoked_at,i.updated_at,
                    p.public_id AS pppm_item_public_id
             FROM microgift_instances i
             LEFT JOIN pppm_items p ON p.id=i.pppm_item_id
             WHERE i.idempotency_key=? AND (i.owner_user_id=? OR i.recipient_user_id=?)
             LIMIT 1"
        );
        $stmt->execute([$idempotencyKey, $userId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('mg_training_lab_reward_lookup_instance_by_external')) {
    function mg_training_lab_reward_lookup_instance_by_external(PDO $pdo, int $userId, string $externalReference): ?array
    {
        if ($externalReference === '') return null;
        $legacyGiftId = ctype_digit($externalReference) ? (int)$externalReference : 0;
        $stmt = $pdo->prepare(
            "SELECT i.id,i.public_id,i.status,i.idempotency_key,i.owner_user_id,i.recipient_user_id,
                    i.legacy_gift_id,i.pppm_item_id,i.issued_at,i.delivered_at,i.claimed_at,
                    i.redeemed_at,i.expires_at,i.cancelled_at,i.revoked_at,i.updated_at,
                    p.public_id AS pppm_item_public_id
             FROM microgift_instances i
             LEFT JOIN pppm_items p ON p.id=i.pppm_item_id
             WHERE (i.public_id=? OR (? > 0 AND i.legacy_gift_id=?))
               AND (i.owner_user_id=? OR i.recipient_user_id=?)
             LIMIT 1"
        );
        $stmt->execute([$externalReference, $legacyGiftId, $legacyGiftId, $userId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('mg_training_lab_reward_lookup_result')) {
    function mg_training_lab_reward_lookup_result(PDO $pdo, array $payload): array
    {
        $userId = (int)$payload['microgifter_user_id'];
        $byKey = mg_training_lab_reward_lookup_instance_by_idempotency($pdo, $userId, (string)$payload['idempotency_key']);
        $byExternal = mg_training_lab_reward_lookup_instance_by_external($pdo, $userId, (string)$payload['external_reference']);

        if ($byKey && $byExternal && (int)$byKey['id'] !== (int)$byExternal['id']) {
            throw new MgTrainingLabRewardLookupException('The lookup references resolve to different Microgift instances.', 409, 'lookup_reference_conflict');
        }
        $row = $byKey ?: $byExternal;
        if (!$row) {
            return [
                'found' => false,
                'status' => 'not_found',
                'delivery_status' => 'not_found',
                'read_only' => true,
                'message' => 'No canonical Microgift instance matched the signed lookup request.',
            ];
        }

        return [
            'found' => true,
            // Existence of a canonical instance proves issuance occurred, even if
            // the current lifecycle is expired, cancelled, revoked, or replaced.
            'status' => 'delivered',
            'delivery_status' => 'delivered',
            'lifecycle_status' => (string)$row['status'],
            'external_reference' => (string)$row['public_id'],
            'microgift_instance_id' => (string)$row['public_id'],
            'gift_id' => $row['legacy_gift_id'] !== null ? (int)$row['legacy_gift_id'] : null,
            'pppm_item_id' => $row['pppm_item_public_id'] !== null ? (string)$row['pppm_item_public_id'] : null,
            'issued_at' => $row['issued_at'] ?? null,
            'delivered_at' => $row['delivered_at'] ?? null,
            'claimed_at' => $row['claimed_at'] ?? null,
            'redeemed_at' => $row['redeemed_at'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
            'cancelled_at' => $row['cancelled_at'] ?? null,
            'revoked_at' => $row['revoked_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'read_only' => true,
        ];
    }
}

if (!function_exists('mg_training_lab_reward_lookup_complete')) {
    function mg_training_lab_reward_lookup_complete(array $authentication, array $result): void
    {
        try {
            mg_idempotency_complete(
                (string)$authentication['nonce_key'],
                'tl_reward_lookup',
                0,
                200,
                ['ok'=>true,'data'=>$result],
                'microgift_instance',
                (string)($result['microgift_instance_id'] ?? '')
            );
        } catch (Throwable $e) {
            // The signed lookup result remains read-only and valid. Audit the
            // completion issue without exposing implementation details.
            mg_security_log('error', 'training_lab.lookup_nonce_completion_failed', 'Training Lab lookup nonce completion failed.', []);
        }
    }
}

if (!function_exists('mg_training_lab_reward_lookup_audit')) {
    function mg_training_lab_reward_lookup_audit(array $authentication, array $result): void
    {
        $payload = (array)$authentication['payload'];
        mg_audit('training_lab.reward_lookup', 'microgift_instance', [
            'contract'=>'training_lab_reward_reconciliation_v1',
            'found'=>!empty($result['found']),
            'delivery_status'=>(string)($result['delivery_status'] ?? 'unknown'),
            'lifecycle_status'=>(string)($result['lifecycle_status'] ?? ''),
            'nonce_hash'=>(string)$authentication['nonce_hash'],
            'idempotency_key_hash'=>(string)$payload['idempotency_key'] !== '' ? substr(hash('sha256', (string)$payload['idempotency_key']), 0, 16) : null,
            'external_reference_hash'=>(string)$payload['external_reference'] !== '' ? substr(hash('sha256', (string)$payload['external_reference']), 0, 16) : null,
            'training_handoff_public_id'=>(string)$payload['training_handoff_public_id'],
            'read_only'=>true,
        ], (int)$payload['microgifter_user_id']);
    }
}
