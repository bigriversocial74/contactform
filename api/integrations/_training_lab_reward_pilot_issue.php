<?php
declare(strict_types=1);

/**
 * Stage 896 — signed, pilot-only Training Lab reward issuance service.
 *
 * This service resolves the issuer from the signed merchant workspace context,
 * validates a published merchant-owned Microgift template, calls the canonical
 * idempotent Microgift engine once, and projects the result into Action Center.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/idempotency.php';
require_once dirname(__DIR__) . '/microgifts/_engine.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';

if (!class_exists('MgTrainingLabPilotIssueException')) {
    final class MgTrainingLabPilotIssueException extends RuntimeException
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

if (!function_exists('mg_training_lab_pilot_issue_bool')) {
    function mg_training_lab_pilot_issue_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null || $value === '') return $default;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('mg_training_lab_pilot_issue_config')) {
    function mg_training_lab_pilot_issue_config(): array
    {
        $enabled = getenv('MG_TRAINING_LAB_PILOT_ISSUE_ENABLED');
        $secret = trim((string)(getenv('MG_TRAINING_LAB_PILOT_ISSUE_SECRET') ?: ''));
        $skew = getenv('MG_TRAINING_LAB_PILOT_ISSUE_MAX_SKEW_SECONDS');
        $nonceTtl = getenv('MG_TRAINING_LAB_PILOT_ISSUE_NONCE_TTL_SECONDS');
        $maxBody = getenv('MG_TRAINING_LAB_PILOT_ISSUE_MAX_BODY_BYTES');
        $maxValue = getenv('MG_TRAINING_LAB_PILOT_ISSUE_MAX_VALUE_CENTS');
        return [
            'enabled'=>mg_training_lab_pilot_issue_bool($enabled !== false ? $enabled : false, false),
            'secret'=>$secret,
            'secret_present'=>strlen($secret) >= 32,
            'max_skew_seconds'=>max(30, min(900, (int)($skew !== false && $skew !== '' ? $skew : 300))),
            'nonce_ttl_seconds'=>max(300, min(3600, (int)($nonceTtl !== false && $nonceTtl !== '' ? $nonceTtl : 900))),
            'max_body_bytes'=>max(1024, min(262144, (int)($maxBody !== false && $maxBody !== '' ? $maxBody : 65536))),
            'max_value_cents'=>max(0, min(100000, (int)($maxValue !== false && $maxValue !== '' ? $maxValue : 2500))),
            'currency'=>'USD',
        ];
    }
}

if (!function_exists('mg_training_lab_pilot_issue_header')) {
    function mg_training_lab_pilot_issue_header(string $key): string
    {
        return trim((string)($_SERVER[$key] ?? ''));
    }
}

if (!function_exists('mg_training_lab_pilot_issue_canonical')) {
    function mg_training_lab_pilot_issue_canonical(string $timestamp, string $nonce, string $rawBody): string
    {
        return "training-lab-reward-issue-v1\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $rawBody);
    }
}

if (!function_exists('mg_training_lab_pilot_issue_signature')) {
    function mg_training_lab_pilot_issue_signature(string $secret, string $timestamp, string $nonce, string $rawBody): string
    {
        return hash_hmac('sha256', mg_training_lab_pilot_issue_canonical($timestamp, $nonce, $rawBody), $secret);
    }
}

if (!function_exists('mg_training_lab_pilot_issue_clean')) {
    function mg_training_lab_pilot_issue_clean($value, int $max = 190, bool $required = false, string $label = 'Value'): string
    {
        $value = trim((string)$value);
        if ($required && $value === '') throw new MgTrainingLabPilotIssueException($label . ' is required.', 422, 'required_value_missing');
        if (strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new MgTrainingLabPilotIssueException($label . ' is invalid.', 422, 'value_invalid');
        }
        return $value;
    }
}

if (!function_exists('mg_training_lab_pilot_issue_validate_payload')) {
    function mg_training_lab_pilot_issue_validate_payload(array $payload): array
    {
        $config = mg_training_lab_pilot_issue_config();
        if ((string)($payload['contract'] ?? '') !== 'training_lab_reward_issue_pilot_v1') {
            throw new MgTrainingLabPilotIssueException('Unsupported issue contract.', 422, 'contract_invalid');
        }
        if ((string)($payload['source'] ?? '') !== 'training_lab' || ($payload['pilot_only'] ?? null) !== true || ($payload['readback_required'] ?? null) !== true) {
            throw new MgTrainingLabPilotIssueException('The request must be a pilot-only Training Lab issue with mandatory read-back.', 422, 'pilot_contract_invalid');
        }
        $recipient = trim((string)($payload['microgifter_user_id'] ?? ''));
        if ($recipient === '' || !ctype_digit($recipient) || (int)$recipient < 1) {
            throw new MgTrainingLabPilotIssueException('A valid Microgifter recipient is required.', 422, 'recipient_invalid');
        }
        $value = (int)($payload['value_cents'] ?? -1);
        $currency = strtoupper(trim((string)($payload['currency'] ?? '')));
        if ($value < 0 || $value > (int)$config['max_value_cents']) {
            throw new MgTrainingLabPilotIssueException('The pilot reward exceeds the configured value ceiling.', 422, 'pilot_value_invalid');
        }
        if ($currency !== (string)$config['currency']) {
            throw new MgTrainingLabPilotIssueException('The pilot reward currency is not supported.', 422, 'pilot_currency_invalid');
        }
        $templateRef = mg_training_lab_pilot_issue_clean($payload['linked_microgift_template_id'] ?? '', 190, true, 'Published Microgift template reference');
        return [
            'contract'=>'training_lab_reward_issue_pilot_v1',
            'source'=>'training_lab',
            'pilot_only'=>true,
            'readback_required'=>true,
            'pilot_id'=>mg_training_lab_pilot_issue_clean($payload['pilot_id'] ?? '', 190, true, 'Pilot ID'),
            'idempotency_key'=>mg_training_lab_pilot_issue_clean($payload['idempotency_key'] ?? '', 190, true, 'Idempotency key'),
            'training_handoff_public_id'=>mg_training_lab_pilot_issue_clean($payload['training_handoff_public_id'] ?? '', 190, true, 'Training handoff reference'),
            'training_reward_public_id'=>mg_training_lab_pilot_issue_clean($payload['training_reward_public_id'] ?? '', 190, true, 'Training reward reference'),
            'microgifter_user_id'=>(int)$recipient,
            'merchant_context'=>mg_training_lab_pilot_issue_clean($payload['merchant_context'] ?? '', 190, true, 'Merchant workspace context'),
            'linked_microgift_template_id'=>$templateRef,
            'linked_catalog_product_id'=>mg_training_lab_pilot_issue_clean($payload['linked_catalog_product_id'] ?? '', 190),
            'reward_label'=>mg_training_lab_pilot_issue_clean($payload['reward_label'] ?? 'Training Reward', 190),
            'value_cents'=>$value,
            'currency'=>$currency,
        ];
    }
}

if (!function_exists('mg_training_lab_pilot_issue_reserve_nonce')) {
    function mg_training_lab_pilot_issue_reserve_nonce(string $nonce, string $requestHash, int $ttlSeconds): void
    {
        $key = 'tl-pilot-issue:' . hash('sha256', $nonce);
        try {
            $reservation = mg_idempotency_reserve($key, 'tl_pilot_issue_nonce', 0, $requestHash, $ttlSeconds);
        } catch (Throwable $e) {
            throw new MgTrainingLabPilotIssueException('Replay protection is unavailable.', 503, 'replay_protection_unavailable');
        }
        if ((string)($reservation['status'] ?? '') !== 'reserved') {
            throw new MgTrainingLabPilotIssueException('This signed issue request nonce has already been used.', 409, 'request_replayed');
        }
    }
}

if (!function_exists('mg_training_lab_pilot_issue_authenticate')) {
    function mg_training_lab_pilot_issue_authenticate(string $rawBody): array
    {
        $config = mg_training_lab_pilot_issue_config();
        if (empty($config['enabled'])) throw new MgTrainingLabPilotIssueException('Training Lab pilot issuing is disabled.', 503, 'pilot_issue_disabled');
        if (empty($config['secret_present'])) throw new MgTrainingLabPilotIssueException('Training Lab pilot issuing is not configured.', 503, 'pilot_issue_secret_missing');
        if ($rawBody === '' || strlen($rawBody) > (int)$config['max_body_bytes']) {
            throw new MgTrainingLabPilotIssueException('The request body is missing or too large.', 413, 'request_body_invalid');
        }
        $timestamp = mg_training_lab_pilot_issue_header('HTTP_X_MICROGIFTER_TRAINING_LAB_ISSUE_TIMESTAMP');
        $nonce = mg_training_lab_pilot_issue_header('HTTP_X_MICROGIFTER_TRAINING_LAB_ISSUE_NONCE');
        $signature = strtolower(mg_training_lab_pilot_issue_header('HTTP_X_MICROGIFTER_TRAINING_LAB_ISSUE_SIGNATURE'));
        if ($timestamp === '' || !ctype_digit($timestamp)) throw new MgTrainingLabPilotIssueException('The signed request timestamp is invalid.', 401, 'timestamp_invalid');
        if (abs(time() - (int)$timestamp) > (int)$config['max_skew_seconds']) throw new MgTrainingLabPilotIssueException('The signed request timestamp is outside the allowed window.', 401, 'timestamp_expired');
        if (!preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $nonce)) throw new MgTrainingLabPilotIssueException('The signed request nonce is invalid.', 401, 'nonce_invalid');
        if (!preg_match('/^[a-f0-9]{64}$/', $signature)) throw new MgTrainingLabPilotIssueException('The signed request signature is invalid.', 401, 'signature_invalid');
        $expected = mg_training_lab_pilot_issue_signature((string)$config['secret'], $timestamp, $nonce, $rawBody);
        if (!hash_equals($expected, $signature)) throw new MgTrainingLabPilotIssueException('The signed request signature is invalid.', 401, 'signature_invalid');
        try {
            $decoded = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new MgTrainingLabPilotIssueException('The request JSON is invalid.', 422, 'json_invalid');
        }
        if (!is_array($decoded)) throw new MgTrainingLabPilotIssueException('The request JSON must be an object.', 422, 'json_shape_invalid');
        $payload = mg_training_lab_pilot_issue_validate_payload($decoded);
        $requestHash = hash('sha256', mg_training_lab_pilot_issue_canonical($timestamp, $nonce, $rawBody));
        mg_training_lab_pilot_issue_reserve_nonce($nonce, $requestHash, (int)$config['nonce_ttl_seconds']);
        return [
            'payload'=>$payload,
            'nonce_hash'=>substr(hash('sha256', $nonce), 0, 16),
            'request_hash'=>$requestHash,
            'timestamp'=>(int)$timestamp,
        ];
    }
}

if (!function_exists('mg_training_lab_pilot_issue_resolve_merchant')) {
    function mg_training_lab_pilot_issue_resolve_merchant(PDO $pdo, string $workspacePublicId): array
    {
        $stmt = $pdo->prepare('SELECT mw.id,mw.public_id,mw.merchant_user_id FROM merchant_workspaces mw INNER JOIN users u ON u.id=mw.merchant_user_id WHERE mw.public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$workspacePublicId]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$workspace || (int)$workspace['merchant_user_id'] < 1) {
            throw new MgTrainingLabPilotIssueException('The signed merchant workspace context is not active.', 403, 'merchant_context_invalid');
        }
        return $workspace;
    }
}

if (!function_exists('mg_training_lab_pilot_issue_require_recipient')) {
    function mg_training_lab_pilot_issue_require_recipient(PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$userId]);
        if (!(int)$stmt->fetchColumn()) throw new MgTrainingLabPilotIssueException('The Microgifter recipient does not exist.', 422, 'recipient_not_found');
    }
}

if (!function_exists('mg_training_lab_pilot_issue_resolve_template')) {
    function mg_training_lab_pilot_issue_resolve_template(PDO $pdo, int $merchantUserId, string $templateReference): array
    {
        $numeric = ctype_digit($templateReference) ? (int)$templateReference : 0;
        $stmt = $pdo->prepare(
            "SELECT v.*,t.public_id AS template_public_id,t.owner_user_id,t.active_version_id
             FROM microgift_template_versions v
             INNER JOIN microgift_templates t ON t.id=v.template_id
             WHERE t.owner_user_id=? AND t.status='active' AND v.status='published'
               AND (v.public_id=? OR t.public_id=? OR (? > 0 AND v.id=?) OR (? > 0 AND t.id=? AND t.active_version_id=v.id))
             ORDER BY (t.active_version_id=v.id) DESC,v.version_number DESC
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$merchantUserId,$templateReference,$templateReference,$numeric,$numeric,$numeric,$numeric]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$version) throw new MgTrainingLabPilotIssueException('A published merchant-owned Microgift template version was not found.', 422, 'template_not_found');
        return $version;
    }
}

if (!function_exists('mg_training_lab_pilot_issue_execute')) {
    function mg_training_lab_pilot_issue_execute(array $payload): array
    {
        $pdo = mg_db();
        $pdo->beginTransaction();
        try {
            $workspace = mg_training_lab_pilot_issue_resolve_merchant($pdo, (string)$payload['merchant_context']);
            $merchantUserId = (int)$workspace['merchant_user_id'];
            $recipientUserId = (int)$payload['microgifter_user_id'];
            mg_training_lab_pilot_issue_require_recipient($pdo, $recipientUserId);
            $version = mg_training_lab_pilot_issue_resolve_template($pdo, $merchantUserId, (string)$payload['linked_microgift_template_id']);
            if ((int)($version['face_value_cents'] ?? -1) !== (int)$payload['value_cents']) {
                throw new MgTrainingLabPilotIssueException('The pilot value does not match the published Microgift template.', 409, 'template_value_mismatch');
            }
            if (strtoupper((string)($version['currency'] ?? '')) !== (string)$payload['currency']) {
                throw new MgTrainingLabPilotIssueException('The pilot currency does not match the published Microgift template.', 409, 'template_currency_mismatch');
            }
            $issue = mg_microgift_issue($pdo, $merchantUserId, [
                'template_version_id'=>(string)$version['public_id'],
                'source_type'=>'merchant',
                'source_reference'=>(string)$payload['training_handoff_public_id'],
                'idempotency_key'=>(string)$payload['idempotency_key'],
                'recipient_user_id'=>$recipientUserId,
                'recipient_reference'=>(string)$payload['training_reward_public_id'],
                'metadata'=>[
                    'source'=>'training_lab',
                    'pilot_only'=>true,
                    'pilot_id'=>(string)$payload['pilot_id'],
                    'training_handoff_public_id'=>(string)$payload['training_handoff_public_id'],
                    'training_reward_public_id'=>(string)$payload['training_reward_public_id'],
                    'merchant_workspace_public_id'=>(string)$workspace['public_id'],
                    'readback_required'=>true,
                ],
            ]);
            $instanceStmt = $pdo->prepare('SELECT * FROM microgift_instances WHERE public_id=? LIMIT 1 FOR UPDATE');
            $instanceStmt->execute([(string)$issue['instance_id']]);
            $instance = $instanceStmt->fetch(PDO::FETCH_ASSOC);
            if (!$instance) throw new RuntimeException('The issued Microgift instance could not be reloaded.');
            if ((int)($instance['recipient_user_id'] ?? 0) !== $recipientUserId) {
                throw new MgTrainingLabPilotIssueException('The idempotent Microgift instance is bound to a different recipient.', 409, 'recipient_idempotency_conflict');
            }
            if (!hash_equals((string)$instance['idempotency_key'], (string)$payload['idempotency_key'])) {
                throw new MgTrainingLabPilotIssueException('The issued Microgift idempotency binding is invalid.', 409, 'idempotency_binding_invalid');
            }
            $projection = mg_action_center_sent($pdo, (int)$instance['id'], $merchantUserId, $recipientUserId, [
                'merchant_user_id'=>$merchantUserId,
                'occurred_at'=>(string)($instance['issued_at'] ?? date('Y-m-d H:i:s')),
            ]);
            mg_microgift_event($pdo, 'microgift.training_lab_pilot_issued', (int)$instance['id'], (int)$instance['template_id'], $merchantUserId, 'training_lab', (string)$payload['training_handoff_public_id'], [
                'pilot_id'=>(string)$payload['pilot_id'],
                'training_reward_public_id'=>(string)$payload['training_reward_public_id'],
                'recipient_user_id'=>$recipientUserId,
                'duplicate'=>!empty($issue['duplicate']),
                'readback_required'=>true,
            ]);
            $pdo->commit();
            return [
                'issued'=>true,
                'delivery_status'=>'delivered',
                'status'=>(string)($instance['status'] ?? 'issued'),
                'microgift_instance_id'=>(string)$instance['public_id'],
                'external_reference'=>(string)$instance['public_id'],
                'duplicate'=>!empty($issue['duplicate']),
                'recipient_action_center_item_id'=>(string)($projection['recipient_inbox_item_id'] ?? ''),
                'sent_action_center_item_id'=>(string)($projection['sent_item_id'] ?? ''),
                'readback_required'=>true,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('mg_training_lab_pilot_issue_audit')) {
    function mg_training_lab_pilot_issue_audit(array $authentication, array $result): void
    {
        if (!function_exists('mg_security_log')) return;
        $payload = (array)($authentication['payload'] ?? []);
        mg_security_log('info', 'training_lab.pilot_reward_issued', 'A signed Training Lab pilot reward was processed.', [
            'pilot_id_fingerprint'=>substr(hash('sha256', (string)($payload['pilot_id'] ?? '')), 0, 16),
            'handoff_reference_fingerprint'=>substr(hash('sha256', (string)($payload['training_handoff_public_id'] ?? '')), 0, 16),
            'reward_reference_fingerprint'=>substr(hash('sha256', (string)($payload['training_reward_public_id'] ?? '')), 0, 16),
            'recipient_fingerprint'=>substr(hash('sha256', (string)($payload['microgifter_user_id'] ?? '')), 0, 16),
            'external_reference_fingerprint'=>substr(hash('sha256', (string)($result['external_reference'] ?? '')), 0, 16),
            'duplicate'=>!empty($result['duplicate']),
            'raw_identity_secret_signature_nonce_and_payload_excluded'=>true,
        ]);
    }
}
