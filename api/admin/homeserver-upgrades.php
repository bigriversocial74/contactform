<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-upgrades.php';

function mg_admin_homeserver_upgrade_release(PDO $pdo, string $publicId): array
{
    $bundle = mg_homeserver_upgrade_release_bundle($pdo, $publicId);
    if (!$bundle) mg_fail('HomeServer release not found.', 404);
    return $bundle;
}

function mg_admin_homeserver_upgrade_control(PDO $pdo, string $publicId): array
{
    $stmt = $pdo->prepare(
        'SELECT c.*,r.public_id AS release_public_id,r.version,r.release_channel,r.platform,r.architecture,
                r.status AS release_status,r.is_latest,r.published_at,r.checksum_sha256,r.byte_size,
                r.minimum_supported_version,r.release_notes
         FROM homeserver_release_controls_v2 c
         INNER JOIN homeserver_releases r ON r.id=c.release_id
         WHERE c.public_id=? LIMIT 1'
    );
    $stmt->execute([strtolower(trim($publicId))]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('HomeServer release control not found.', 404);
    return $row;
}

function mg_admin_homeserver_upgrade_payload(PDO $pdo): array
{
    $schemaReady = mg_homeserver_upgrade_schema_ready($pdo);
    $payload = [
        'schema_ready' => $schemaReady,
        'release_schema_ready' => mg_homeserver_release_schema_ready($pdo),
        'release_key_configured' => mg_homeserver_upgrade_key_configured(),
        'release_key_id' => (string)(getenv('MG_HOMESERVER_RELEASE_KEY_ID') ?: MG_HOMESERVER_UPGRADE_DEFAULT_KEY_ID),
        'manifest_url' => mg_homeserver_upgrade_public_base_url() . '/api/homeserver/update-manifest-stable.php',
        'controls' => [],
        'recent_events' => [],
        'receipt_stats' => [
            'received' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'rolled_back' => 0,
        ],
    ];
    if (!$schemaReady || !mg_homeserver_release_schema_ready($pdo)) return $payload;

    $rows = $pdo->query(
        'SELECT r.*,c.id AS control_id,c.public_id AS control_public_id,c.update_class,c.control_state,
                c.rollout_percentage,c.manifest_schema_version,c.manifest_key_id,c.manifest_signature,
                c.manifest_payload_sha256,c.authenticode_thumbprint,c.rollback_release_id,
                c.revocation_reason,c.activated_at,c.paused_at,c.revoked_at
         FROM homeserver_releases r
         LEFT JOIN homeserver_release_controls_v2 c ON c.release_id=r.id
         ORDER BY r.is_latest DESC,r.published_at DESC,r.created_at DESC,r.id DESC
         LIMIT 100'
    )->fetchAll(PDO::FETCH_ASSOC);
    $payload['controls'] = array_map(static function (array $row): array {
        $release = mg_homeserver_release_row_payload($row);
        $release['upgrade'] = mg_homeserver_upgrade_control_payload($row);
        return $release;
    }, $rows ?: []);

    $events = $pdo->query(
        'SELECT e.public_id,e.event_type,e.previous_state,e.new_state,e.metadata_json,e.created_at,
                r.public_id AS release_public_id,r.version,u.email AS actor_email
         FROM homeserver_release_control_events_v2 e
         INNER JOIN homeserver_releases r ON r.id=e.release_id
         LEFT JOIN users u ON u.id=e.actor_user_id
         ORDER BY e.created_at DESC,e.id DESC LIMIT 50'
    )->fetchAll(PDO::FETCH_ASSOC);
    $payload['recent_events'] = array_map(static function (array $row): array {
        $metadata = json_decode((string)$row['metadata_json'], true);
        return [
            'event_id' => (string)$row['public_id'],
            'release_id' => (string)$row['release_public_id'],
            'version' => (string)$row['version'],
            'event_type' => (string)$row['event_type'],
            'previous_state' => $row['previous_state'],
            'new_state' => $row['new_state'],
            'metadata' => is_array($metadata) ? $metadata : [],
            'actor_email' => $row['actor_email'],
            'created_at' => $row['created_at'],
        ];
    }, $events ?: []);

    try {
        $stats = $pdo->query(
            "SELECT COUNT(*) AS received,
                    SUM(CASE WHEN disposition IN ('succeeded','success','installed') THEN 1 ELSE 0 END) AS succeeded,
                    SUM(CASE WHEN disposition IN ('failed','error') THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN disposition='rolled_back' THEN 1 ELSE 0 END) AS rolled_back
             FROM homeserver_update_receipts_v1"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (array_keys($payload['receipt_stats']) as $key) {
            $payload['receipt_stats'][$key] = (int)($stats[$key] ?? 0);
        }
    } catch (Throwable) {
        // The pairing/update contract migration may be deployed separately.
    }

    return $payload;
}

$user = mg_require_permission('admin.settings.manage');
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') mg_ok(mg_admin_homeserver_upgrade_payload($pdo));
if ($method !== 'POST') mg_fail('Method not allowed.', 405);

$input = $_POST ?: mg_input();
mg_require_csrf_for_write($input);
if (function_exists('mg_rate_limit')) {
    mg_rate_limit('admin.homeserver_upgrades', 'user:' . (int)$user['id'], 80, 3600);
}
if (!mg_homeserver_release_schema_ready($pdo) || !mg_homeserver_upgrade_schema_ready($pdo)) {
    mg_fail('Run the HomeServer release and upgrade-control migrations first.', 409);
}

$action = strtolower(trim((string)($input['action'] ?? '')));

if ($action === 'configure') {
    if (!mg_homeserver_upgrade_key_configured()) {
        mg_fail('Configure MG_HOMESERVER_RELEASE_PUBLIC_KEY_BASE64 before publishing signed updates.', 409);
    }
    $release = mg_admin_homeserver_upgrade_release($pdo, (string)($input['release_id'] ?? ''));
    if ((string)$release['status'] === 'retired') mg_fail('A retired release cannot be configured.', 409);

    $updateClass = mg_homeserver_upgrade_normalize_class((string)($input['update_class'] ?? 'feature'));
    $rollout = mg_homeserver_upgrade_rollout_percentage($input['rollout_percentage'] ?? 100);
    $keyId = trim((string)($input['manifest_key_id'] ?? MG_HOMESERVER_UPGRADE_DEFAULT_KEY_ID));
    $expectedKeyId = trim((string)(getenv('MG_HOMESERVER_RELEASE_KEY_ID') ?: MG_HOMESERVER_UPGRADE_DEFAULT_KEY_ID));
    if ($keyId !== $expectedKeyId) mg_fail('The manifest key ID does not match the configured release key.', 422);
    $signature = trim((string)($input['manifest_signature'] ?? ''));
    $thumbprint = strtoupper(preg_replace('/\s+/', '', trim((string)($input['authenticode_thumbprint'] ?? ''))) ?? '');
    if (preg_match('/^(?:[A-F0-9]{40}|[A-F0-9]{64})$/', $thumbprint) !== 1) {
        mg_fail('Enter the exact Authenticode certificate thumbprint.', 422);
    }
    $rollbackReleaseId = null;
    $rollbackPublicId = trim((string)($input['rollback_release_id'] ?? ''));
    if ($rollbackPublicId !== '') {
        $rollback = mg_admin_homeserver_upgrade_release($pdo, $rollbackPublicId);
        if ((int)$rollback['id'] === (int)$release['id']) mg_fail('A release cannot roll back to itself.', 422);
        $rollbackReleaseId = (int)$rollback['id'];
    }
    $activate = filter_var($input['activate'] ?? false, FILTER_VALIDATE_BOOL);

    $candidateControl = array_merge($release, [
        'update_class' => $updateClass,
        'control_state' => $activate ? 'active' : 'draft',
        'rollout_percentage' => $rollout,
        'manifest_schema_version' => MG_HOMESERVER_UPGRADE_MANIFEST_SCHEMA_VERSION,
        'manifest_key_id' => $keyId,
        'manifest_signature' => $signature,
        'authenticode_thumbprint' => $thumbprint,
    ]);
    if (!mg_homeserver_upgrade_verify_signature($release, $candidateControl, $signature)) {
        mg_fail('The Ed25519 manifest signature does not verify against the configured release public key.', 422);
    }
    $payloadHash = mg_homeserver_upgrade_payload_sha256($release, $candidateControl);

    $existing = mg_homeserver_upgrade_release_control($pdo, (int)$release['id']);
    $previousState = $existing['control_state'] ?? null;
    $controlPublicId = $existing['public_id'] ?? mg_homeserver_release_uuid();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO homeserver_release_controls_v2
             (public_id,release_id,update_class,control_state,rollout_percentage,manifest_schema_version,
              manifest_key_id,manifest_signature,manifest_payload_sha256,authenticode_thumbprint,
              rollback_release_id,activated_at,created_by_user_id,updated_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,IF(?='active',UTC_TIMESTAMP(),NULL),?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE update_class=VALUES(update_class),control_state=VALUES(control_state),
              rollout_percentage=VALUES(rollout_percentage),manifest_schema_version=VALUES(manifest_schema_version),
              manifest_key_id=VALUES(manifest_key_id),manifest_signature=VALUES(manifest_signature),
              manifest_payload_sha256=VALUES(manifest_payload_sha256),authenticode_thumbprint=VALUES(authenticode_thumbprint),
              rollback_release_id=VALUES(rollback_release_id),activated_at=IF(VALUES(control_state)='active',COALESCE(activated_at,UTC_TIMESTAMP()),activated_at),
              paused_at=NULL,revoked_at=NULL,revocation_reason=NULL,updated_by_user_id=VALUES(updated_by_user_id),updated_at=UTC_TIMESTAMP()"
        );
        $state = $activate ? 'active' : 'draft';
        $stmt->execute([
            $controlPublicId,
            (int)$release['id'],
            $updateClass,
            $state,
            $rollout,
            MG_HOMESERVER_UPGRADE_MANIFEST_SCHEMA_VERSION,
            $keyId,
            $signature,
            $payloadHash,
            $thumbprint,
            $rollbackReleaseId,
            $state,
            (int)$user['id'],
            (int)$user['id'],
        ]);
        if ($activate) {
            $pdo->prepare("UPDATE homeserver_releases SET status='published',published_at=COALESCE(published_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=?")
                ->execute([(int)$release['id']]);
        }
        $control = mg_homeserver_upgrade_release_control($pdo, (int)$release['id']);
        mg_homeserver_upgrade_record_event(
            $pdo,
            $control,
            $activate ? 'activated' : 'configured',
            $previousState,
            $state,
            ['rollout_percentage' => $rollout, 'update_class' => $updateClass, 'payload_sha256' => $payloadHash],
            (int)$user['id']
        );
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail_unexpected($error, 'homeserver.upgrade_configure_failed', 'The HomeServer upgrade control could not be saved.', 500, [
            'release_id' => (string)$release['public_id'],
        ], (int)$user['id']);
    }
    mg_audit('homeserver.upgrade_configured', 'homeserver_release', [
        'release_id' => (string)$release['public_id'],
        'version' => (string)$release['version'],
        'state' => $activate ? 'active' : 'draft',
        'rollout_percentage' => $rollout,
        'update_class' => $updateClass,
        'manifest_payload_sha256' => $payloadHash,
    ], (int)$user['id']);
    mg_ok(mg_admin_homeserver_upgrade_payload($pdo), $activate ? 'Signed HomeServer update activated.' : 'Signed HomeServer update configured.');
}

if (in_array($action, ['pause','resume','set_rollout','revoke'], true)) {
    $control = mg_admin_homeserver_upgrade_control($pdo, (string)($input['control_id'] ?? ''));
    $previousState = (string)$control['control_state'];
    $newState = $previousState;
    $eventType = 'rollout_changed';
    $metadata = [];

    if ($action === 'pause') {
        if ($previousState !== 'active') mg_fail('Only an active update can be paused.', 409);
        $newState = 'paused';
        $eventType = 'paused';
    } elseif ($action === 'resume') {
        if ($previousState !== 'paused') mg_fail('Only a paused update can be resumed.', 409);
        $newState = 'active';
        $eventType = 'resumed';
    } elseif ($action === 'set_rollout') {
        if (!in_array($previousState, ['active','paused'], true)) mg_fail('Configure the signed update before changing rollout.', 409);
        $metadata['rollout_percentage'] = mg_homeserver_upgrade_rollout_percentage($input['rollout_percentage'] ?? 100);
    } else {
        $reason = trim((string)($input['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > MG_HOMESERVER_UPGRADE_MAX_REASON_LENGTH) {
            mg_fail('Enter a bounded revocation reason.', 422);
        }
        $newState = 'revoked';
        $eventType = 'revoked';
        $metadata['reason'] = $reason;
    }

    $pdo->beginTransaction();
    try {
        if ($action === 'set_rollout') {
            $pdo->prepare('UPDATE homeserver_release_controls_v2 SET rollout_percentage=?,updated_by_user_id=?,updated_at=UTC_TIMESTAMP() WHERE id=?')
                ->execute([(int)$metadata['rollout_percentage'], (int)$user['id'], (int)$control['id']]);
        } elseif ($action === 'revoke') {
            $pdo->prepare("UPDATE homeserver_release_controls_v2 SET control_state='revoked',revocation_reason=?,revoked_at=UTC_TIMESTAMP(),updated_by_user_id=?,updated_at=UTC_TIMESTAMP() WHERE id=?")
                ->execute([(string)$metadata['reason'], (int)$user['id'], (int)$control['id']]);
            $pdo->prepare("UPDATE homeserver_releases SET status='retired',is_latest=0,updated_at=UTC_TIMESTAMP() WHERE id=?")
                ->execute([(int)$control['release_id']]);
        } else {
            $timestampColumn = $action === 'pause' ? 'paused_at=UTC_TIMESTAMP()' : 'paused_at=NULL';
            $pdo->prepare("UPDATE homeserver_release_controls_v2 SET control_state=?,{$timestampColumn},updated_by_user_id=?,updated_at=UTC_TIMESTAMP() WHERE id=?")
                ->execute([$newState, (int)$user['id'], (int)$control['id']]);
        }
        $updated = mg_homeserver_upgrade_release_control($pdo, (int)$control['release_id']);
        mg_homeserver_upgrade_record_event($pdo, $updated, $eventType, $previousState, $newState, $metadata, (int)$user['id']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail_unexpected($error, 'homeserver.upgrade_control_failed', 'The HomeServer upgrade control could not be changed.', 500, [
            'control_id' => (string)$control['public_id'],
            'action' => $action,
        ], (int)$user['id']);
    }
    mg_audit('homeserver.upgrade_' . $action, 'homeserver_release_control', [
        'control_id' => (string)$control['public_id'],
        'release_id' => (string)$control['release_public_id'],
        'version' => (string)$control['version'],
        'previous_state' => $previousState,
        'new_state' => $newState,
        'metadata' => $metadata,
    ], (int)$user['id']);
    mg_ok(mg_admin_homeserver_upgrade_payload($pdo), 'HomeServer upgrade control updated.');
}

if ($action === 'activate_rollback') {
    $control = mg_admin_homeserver_upgrade_control($pdo, (string)($input['control_id'] ?? ''));
    $rollbackId = (int)($control['rollback_release_id'] ?? 0);
    if ($rollbackId < 1) mg_fail('No rollback release is configured.', 409);
    $targetStmt = $pdo->prepare(
        'SELECT r.*,c.id AS control_id,c.public_id AS control_public_id,c.update_class,c.control_state,
                c.rollout_percentage,c.manifest_schema_version,c.manifest_key_id,c.manifest_signature,
                c.manifest_payload_sha256,c.authenticode_thumbprint,c.rollback_release_id,
                c.revocation_reason,c.activated_at,c.paused_at,c.revoked_at
         FROM homeserver_releases r
         INNER JOIN homeserver_release_controls_v2 c ON c.release_id=r.id
         WHERE r.id=? LIMIT 1'
    );
    $targetStmt->execute([$rollbackId]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
    if (!$target || (string)$target['control_state'] !== 'active') mg_fail('The rollback target is not an active signed release.', 409);
    try {
        mg_homeserver_upgrade_manifest($target, $target);
    } catch (Throwable $error) {
        mg_fail('The rollback target manifest is no longer valid: ' . $error->getMessage(), 409);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE homeserver_releases SET is_latest=0,updated_at=UTC_TIMESTAMP() WHERE release_channel='stable' AND platform='windows' AND architecture='x64'")
            ->execute();
        $pdo->prepare("UPDATE homeserver_releases SET status='published',is_latest=1,published_at=COALESCE(published_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=?")
            ->execute([$rollbackId]);
        $pdo->prepare("UPDATE homeserver_release_controls_v2 SET control_state='paused',paused_at=UTC_TIMESTAMP(),updated_by_user_id=?,updated_at=UTC_TIMESTAMP() WHERE id=?")
            ->execute([(int)$user['id'], (int)$control['id']]);
        $updated = mg_homeserver_upgrade_release_control($pdo, (int)$control['release_id']);
        mg_homeserver_upgrade_record_event($pdo, $updated, 'rollback_activated', (string)$control['control_state'], 'paused', [
            'rollback_release_id' => (string)$target['public_id'],
            'rollback_version' => (string)$target['version'],
        ], (int)$user['id']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail_unexpected($error, 'homeserver.upgrade_rollback_failed', 'The HomeServer rollback release could not be activated.', 500, [
            'control_id' => (string)$control['public_id'],
        ], (int)$user['id']);
    }
    mg_audit('homeserver.upgrade_rollback_activated', 'homeserver_release_control', [
        'control_id' => (string)$control['public_id'],
        'release_id' => (string)$control['release_public_id'],
        'rollback_release_id' => (string)$target['public_id'],
        'rollback_version' => (string)$target['version'],
    ], (int)$user['id']);
    mg_ok(mg_admin_homeserver_upgrade_payload($pdo), 'Rollback release activated.');
}

mg_fail('Unsupported HomeServer upgrade action.', 422);
