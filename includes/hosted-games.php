<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-integrations.php';

final class MgHostedGameException extends RuntimeException {}

function mg_hosted_game_uuid(): string
{
    if (function_exists('mg_public_uuid')) return mg_public_uuid();
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_hosted_game_json_decode(mixed $value, array $default = []): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return $default;
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : $default;
}

function mg_hosted_game_json_encode(mixed $value, int $maxBytes = 1048576): string
{
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > $maxBytes) {
        throw new MgHostedGameException('Hosted game data could not be encoded.');
    }
    return $json;
}

function mg_hosted_game_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function mg_hosted_game_schema_ready(PDO $pdo): bool
{
    foreach ([
        'hosted_games',
        'hosted_game_releases',
        'hosted_game_secrets',
        'hosted_game_database_connections',
        'hosted_game_runs',
        'hosted_game_events',
    ] as $table) {
        if (!mg_hosted_game_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_hosted_game_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    if ($value === '' || strlen($value) > 140 || preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/', $value) !== 1) {
        throw new InvalidArgumentException('Enter a valid game URL slug using letters, numbers, and hyphens.');
    }
    return $value;
}

function mg_hosted_game_base_url(): string
{
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'microgifter.com')) ?: 'microgifter.com';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    return ($https ? 'https://' : 'http://') . $host;
}

function mg_hosted_game_origin(): string
{
    $parts = parse_url(mg_hosted_game_base_url());
    return is_array($parts) && !empty($parts['scheme']) && !empty($parts['host'])
        ? $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . (int)$parts['port'] : '')
        : 'https://microgifter.com';
}

function mg_hosted_game_storage_root(): string
{
    $root = dirname(__DIR__) . '/storage/private/hosted-games';
    if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
        throw new MgHostedGameException('Unable to prepare hosted game storage.');
    }
    $real = realpath($root);
    if ($real === false) throw new MgHostedGameException('Unable to validate hosted game storage.');
    return $real;
}

function mg_hosted_game_release_directory(int $merchantUserId, string $gamePublicId, int $version): string
{
    if ($merchantUserId < 1 || preg_match('/^[a-f0-9-]{36}$/i', $gamePublicId) !== 1 || $version < 1) {
        throw new InvalidArgumentException('Invalid hosted game release path.');
    }
    return mg_hosted_game_storage_root()
        . DIRECTORY_SEPARATOR . $merchantUserId
        . DIRECTORY_SEPARATOR . $gamePublicId
        . DIRECTORY_SEPARATOR . 'releases'
        . DIRECTORY_SEPARATOR . $version;
}

function mg_hosted_game_storage_key(int $merchantUserId, string $gamePublicId, int $version): string
{
    return 'hosted-games/' . $merchantUserId . '/' . $gamePublicId . '/releases/' . $version;
}

function mg_hosted_game_storage_path(string $storageKey): string
{
    $storageKey = str_replace('\\', '/', trim($storageKey));
    if ($storageKey === '' || str_starts_with($storageKey, '/') || str_contains($storageKey, '../') || str_contains($storageKey, "\0")) {
        throw new MgHostedGameException('Invalid hosted game storage key.');
    }
    $candidate = dirname(__DIR__) . '/storage/private/' . $storageKey;
    $real = realpath($candidate);
    $root = mg_hosted_game_storage_root();
    if ($real === false || ($real !== $root && !str_starts_with($real, $root . DIRECTORY_SEPARATOR))) {
        throw new MgHostedGameException('Hosted game release storage is unavailable.');
    }
    return $real;
}

function mg_hosted_game_encrypt_secret(string $plaintext): string
{
    if ($plaintext === '') throw new InvalidArgumentException('Secret value is required.');
    return mg_integration_encrypt_secret($plaintext);
}

function mg_hosted_game_decrypt_secret(?string $ciphertext): string
{
    return mg_integration_decrypt_secret($ciphertext);
}

function mg_hosted_game_encryption_ready(): bool
{
    return mg_integration_credential_master_key() !== null;
}

function mg_hosted_game_for_merchant(PDO $pdo, int $merchantUserId, string $gamePublicId, bool $forUpdate = false): array
{
    $sql = 'SELECT * FROM hosted_games WHERE public_id=? AND merchant_user_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$gamePublicId, $merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgHostedGameException('Hosted game not found.');
    return $row;
}

function mg_hosted_game_by_public_id(PDO $pdo, string $gamePublicId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT * FROM hosted_games WHERE public_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$gamePublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_hosted_game_by_slug(PDO $pdo, string $slug, bool $includeInactive = false): ?array
{
    $where = $includeInactive ? '' : " AND hg.status='active'";
    $stmt = $pdo->prepare(
        "SELECT hg.*,hgr.storage_key,hgr.package_checksum,hgr.manifest_json,hgr.file_count,hgr.extracted_bytes,hgr.version_number,
                mda.status AS app_status,mda.environment AS app_environment,mak.status AS key_status,mak.environment AS key_environment,
                dp.status AS program_status,c.status AS campaign_status,c.title AS campaign_title,
                cpv.title AS reward_title,cpv.unit_value_cents AS reward_value_cents,cpv.currency AS reward_currency
         FROM hosted_games hg
         LEFT JOIN hosted_game_releases hgr ON hgr.public_id=hg.current_release_public_id AND hgr.game_id=hg.id
         LEFT JOIN merchant_developer_apps mda ON mda.id=hg.developer_app_id
         LEFT JOIN merchant_api_keys mak ON mak.id=hg.api_key_id
         LEFT JOIN distribution_programs dp ON dp.id=hg.distribution_program_id
         LEFT JOIN campaigns c ON c.id=hg.campaign_id
         LEFT JOIN catalog_pppm_templates cpt ON cpt.id=hg.pppm_template_id
         LEFT JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id
         WHERE hg.slug=?{$where}
         LIMIT 1"
    );
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_hosted_game_secrets(PDO $pdo, int $gameId): array
{
    $stmt = $pdo->prepare('SELECT * FROM hosted_game_secrets WHERE game_id=? LIMIT 1');
    $stmt->execute([$gameId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'api_credential' => mg_hosted_game_decrypt_secret($row['api_credential_ciphertext'] ?? null),
        'webhook_secret' => mg_hosted_game_decrypt_secret($row['webhook_secret_ciphertext'] ?? null),
        'state_secret' => mg_hosted_game_decrypt_secret($row['state_secret_ciphertext'] ?? null),
    ];
}

function mg_hosted_game_save_secrets(PDO $pdo, int $gameId, array $values): void
{
    $existing = $pdo->prepare('SELECT * FROM hosted_game_secrets WHERE game_id=? LIMIT 1 FOR UPDATE');
    $existing->execute([$gameId]);
    $row = $existing->fetch(PDO::FETCH_ASSOC) ?: [];
    $apiCipher = array_key_exists('api_credential', $values)
        ? mg_hosted_game_encrypt_secret((string)$values['api_credential'])
        : (string)($row['api_credential_ciphertext'] ?? '');
    $webhookCipher = array_key_exists('webhook_secret', $values)
        ? mg_hosted_game_encrypt_secret((string)$values['webhook_secret'])
        : (string)($row['webhook_secret_ciphertext'] ?? '');
    $stateCipher = array_key_exists('state_secret', $values)
        ? mg_hosted_game_encrypt_secret((string)$values['state_secret'])
        : (string)($row['state_secret_ciphertext'] ?? '');
    if ($row) {
        $pdo->prepare('UPDATE hosted_game_secrets SET api_credential_ciphertext=?,webhook_secret_ciphertext=?,state_secret_ciphertext=?,rotated_at=NOW(),updated_at=NOW() WHERE game_id=?')
            ->execute([$apiCipher ?: null, $webhookCipher ?: null, $stateCipher ?: null, $gameId]);
    } else {
        $pdo->prepare('INSERT INTO hosted_game_secrets (game_id,api_credential_ciphertext,webhook_secret_ciphertext,state_secret_ciphertext,encryption_version,rotated_at,created_at,updated_at) VALUES (?,?,?,?,\'secretbox-v1\',NOW(),NOW(),NOW())')
            ->execute([$gameId, $apiCipher ?: null, $webhookCipher ?: null, $stateCipher ?: null]);
    }
}

function mg_hosted_game_credential_material(string $environment = 'live'): array
{
    $environment = $environment === 'test' ? 'test' : 'live';
    $value = 'mg_' . $environment . '_' . bin2hex(random_bytes(24));
    return [
        'value' => $value,
        'prefix' => substr($value, 0, 24),
        'digest' => hash('sha256', $value),
    ];
}

function mg_hosted_game_webhook_material(): array
{
    $secret = bin2hex(random_bytes(32));
    return ['secret' => $secret, 'hint' => substr($secret, 0, 8) . '…' . substr($secret, -6)];
}

function mg_hosted_game_ensure_runtime_integration(PDO $pdo, array $game, int $actorUserId, int $programDbId): array
{
    if (!mg_hosted_game_encryption_ready()) {
        throw new MgHostedGameException('Hosted game credential encryption is not configured.');
    }
    $merchantUserId = (int)$game['merchant_user_id'];
    $gameId = (int)$game['id'];
    $gamePublicId = (string)$game['public_id'];
    $scopes = ['distribution:programs.read','distribution:rewards.issue','distribution:rewards.status','distribution:webhooks.manage'];
    $webhookUrl = mg_hosted_game_base_url() . '/api/hosted-games/webhook.php?game=' . rawurlencode($gamePublicId);
    $origin = mg_hosted_game_origin();
    $app = null;
    if (!empty($game['developer_app_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM merchant_developer_apps WHERE id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([(int)$game['developer_app_id'], $merchantUserId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $newSecrets = [];
    if (!$app) {
        $sourcePublicId = mg_hosted_game_uuid();
        $appPublicId = mg_hosted_game_uuid();
        $providerKey = 'hosted-game-' . substr(str_replace('-', '', $gamePublicId), 0, 20);
        $webhook = mg_hosted_game_webhook_material();
        $metadata = [
            'managed_by' => 'hosted-games-v1',
            'hosted_game_id' => $gamePublicId,
            'webhook_secret_hint' => $webhook['hint'],
            'webhook_secret_rotated_at' => gmdate('c'),
            'webhook_signature_version' => 'v1',
        ];
        $pdo->prepare("INSERT INTO distribution_source_connections (public_id,merchant_user_id,program_id,source_type,provider_key,display_name,status,secret_hash,configuration_json,created_at,updated_at) VALUES (?,?,?,'gaming',?,?,'active',?,?,NOW(),NOW())")
            ->execute([$sourcePublicId,$merchantUserId,$programDbId,$providerKey,(string)$game['name'] . ' hosted game',hash('sha256',$sourcePublicId . '|' . $providerKey),mg_hosted_game_json_encode(['environment'=>'live','hosted_game_id'=>$gamePublicId])]);
        $sourceDbId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO merchant_developer_apps (public_id,merchant_user_id,distribution_source_connection_id,default_program_id,name,environment,status,allowed_origins_json,webhook_url,webhook_secret_hash,scopes_json,metadata_json,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,'live','active',?,?,?,?,?,?,NOW(),NOW())")
            ->execute([$appPublicId,$merchantUserId,$sourceDbId,$programDbId,(string)$game['name'] . ' Hosted Game API',mg_hosted_game_json_encode([$origin]),$webhookUrl,$webhook['secret'],mg_hosted_game_json_encode($scopes),mg_hosted_game_json_encode($metadata),$actorUserId]);
        $appDbId = (int)$pdo->lastInsertId();
        $app = ['id'=>$appDbId,'public_id'=>$appPublicId,'distribution_source_connection_id'=>$sourceDbId,'webhook_secret_hash'=>$webhook['secret']];
        $newSecrets['webhook_secret'] = $webhook['secret'];
    } else {
        $appDbId = (int)$app['id'];
        $webhookSecret = trim((string)($app['webhook_secret_hash'] ?? ''));
        if ($webhookSecret === '') {
            $webhook = mg_hosted_game_webhook_material();
            $webhookSecret = $webhook['secret'];
            $newSecrets['webhook_secret'] = $webhookSecret;
        }
        $metadata = mg_hosted_game_json_decode($app['metadata_json'] ?? null);
        $metadata['managed_by'] = 'hosted-games-v1';
        $metadata['hosted_game_id'] = $gamePublicId;
        $pdo->prepare("UPDATE merchant_developer_apps SET default_program_id=?,name=?,environment='live',status='active',allowed_origins_json=?,webhook_url=?,webhook_secret_hash=?,scopes_json=?,metadata_json=?,updated_at=NOW() WHERE id=?")
            ->execute([$programDbId,(string)$game['name'] . ' Hosted Game API',mg_hosted_game_json_encode([$origin]),$webhookUrl,$webhookSecret,mg_hosted_game_json_encode($scopes),mg_hosted_game_json_encode($metadata),$appDbId]);
        if (!empty($app['distribution_source_connection_id'])) {
            $pdo->prepare("UPDATE distribution_source_connections SET program_id=?,source_type='gaming',display_name=?,status='active',configuration_json=?,updated_at=NOW() WHERE id=? AND merchant_user_id=?")
                ->execute([$programDbId,(string)$game['name'] . ' hosted game',mg_hosted_game_json_encode(['environment'=>'live','hosted_game_id'=>$gamePublicId]),(int)$app['distribution_source_connection_id'],$merchantUserId]);
        }
    }

    $apiKeyRow = null;
    if (!empty($game['api_key_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM merchant_api_keys WHERE id=? AND app_id=? AND merchant_user_id=? AND status='active' LIMIT 1 FOR UPDATE");
        $stmt->execute([(int)$game['api_key_id'],$appDbId,$merchantUserId]);
        $apiKeyRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $stored = mg_hosted_game_secrets($pdo, $gameId);
    $apiCredential = trim((string)$stored['api_credential']);
    if (!$apiKeyRow || $apiCredential === '' || !hash_equals((string)($apiKeyRow['key_hash'] ?? ''), hash('sha256', $apiCredential))) {
        if ($apiKeyRow) {
            $pdo->prepare("UPDATE merchant_api_keys SET status='revoked',revoked_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$apiKeyRow['id']]);
        }
        $material = mg_hosted_game_credential_material('live');
        $credentialPublicId = mg_hosted_game_uuid();
        $pdo->prepare("INSERT INTO merchant_api_keys (public_id,app_id,merchant_user_id,name,environment,key_prefix,key_hash,scopes_json,status,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,'live',?,?,?,'active',?,NOW(),NOW())")
            ->execute([$credentialPublicId,$appDbId,$merchantUserId,(string)$game['name'] . ' Hosted Game Server',$material['prefix'],$material['digest'],mg_hosted_game_json_encode($scopes),$actorUserId]);
        $apiKeyId = (int)$pdo->lastInsertId();
        $apiCredential = $material['value'];
        $newSecrets['api_credential'] = $apiCredential;
    } else {
        $apiKeyId = (int)$apiKeyRow['id'];
    }
    if (trim((string)$stored['state_secret']) === '') {
        $newSecrets['state_secret'] = bin2hex(random_bytes(32));
    }
    if ($newSecrets !== []) mg_hosted_game_save_secrets($pdo, $gameId, $newSecrets);

    $pdo->prepare("UPDATE hosted_games SET developer_app_id=?,api_key_id=?,integration_status='ready',updated_by_user_id=?,updated_at=NOW() WHERE id=?")
        ->execute([$appDbId,$apiKeyId,$actorUserId,$gameId]);

    return ['developer_app_id'=>$appDbId,'api_key_id'=>$apiKeyId,'webhook_url'=>$webhookUrl];
}

function mg_hosted_game_database_row(PDO $pdo, int $gameId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT * FROM hosted_game_database_connections WHERE game_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$gameId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_hosted_game_database_public(?array $row): array
{
    if (!$row) return [
        'configured'=>false,
        'status'=>'pending',
        'host'=>null,
        'port'=>3306,
        'database_name'=>null,
        'username_configured'=>false,
        'password_configured'=>false,
        'last_tested_at'=>null,
        'last_connected_at'=>null,
        'last_error_message'=>null,
    ];
    return [
        'configured'=>true,
        'status'=>(string)$row['status'],
        'host'=>(string)$row['host'],
        'port'=>(int)$row['port'],
        'database_name'=>(string)$row['database_name'],
        'username_configured'=>trim((string)($row['username_ciphertext'] ?? '')) !== '',
        'password_configured'=>trim((string)($row['password_ciphertext'] ?? '')) !== '',
        'last_tested_at'=>$row['last_tested_at'] ?? null,
        'last_connected_at'=>$row['last_connected_at'] ?? null,
        'last_error_message'=>$row['last_error_message'] ?? null,
    ];
}

function mg_hosted_game_database_config(PDO $pdo, int $gameId): array
{
    $row = mg_hosted_game_database_row($pdo, $gameId, false);
    if (!$row || (string)$row['status'] === 'disabled') throw new MgHostedGameException('This game database is not configured.');
    return [
        'row'=>$row,
        'host'=>(string)$row['host'],
        'port'=>(int)$row['port'],
        'database_name'=>(string)$row['database_name'],
        'username'=>mg_hosted_game_decrypt_secret((string)$row['username_ciphertext']),
        'password'=>mg_hosted_game_decrypt_secret((string)$row['password_ciphertext']),
        'charset'=>(string)($row['charset'] ?: 'utf8mb4'),
    ];
}

function mg_hosted_game_database_pdo(PDO $platformPdo, int $gameId): PDO
{
    $config = mg_hosted_game_database_config($platformPdo, $gameId);
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database_name'], $config['charset']);
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    try {
        $platformPdo->prepare('UPDATE hosted_game_database_connections SET last_connected_at=NOW(),last_error_message=NULL,updated_at=NOW() WHERE game_id=?')
            ->execute([$gameId]);
    } catch (Throwable) {
        // Runtime database health updates must not invalidate an otherwise successful connection.
    }
    return $pdo;
}

function mg_hosted_game_database_bootstrap(PDO $gamePdo): void
{
    $gamePdo->exec("CREATE TABLE IF NOT EXISTS microgifter_game_player_state (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        player_key VARCHAR(190) NOT NULL,
        state_key VARCHAR(120) NOT NULL,
        state_json JSON NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_microgifter_game_player_state (player_key,state_key),
        KEY idx_microgifter_game_state_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $gamePdo->exec("CREATE TABLE IF NOT EXISTS microgifter_game_scores (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        public_id CHAR(36) NOT NULL,
        player_key VARCHAR(190) NOT NULL,
        score BIGINT NOT NULL DEFAULT 0,
        metadata_json JSON NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_microgifter_game_scores_public_id (public_id),
        KEY idx_microgifter_game_scores_rank (score,created_at),
        KEY idx_microgifter_game_scores_player (player_key,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function mg_hosted_game_test_database(PDO $platformPdo, int $gameId, bool $bootstrap = true): array
{
    $started = microtime(true);
    $gamePdo = mg_hosted_game_database_pdo($platformPdo, $gameId);
    $value = $gamePdo->query('SELECT 1')->fetchColumn();
    if ((int)$value !== 1) throw new MgHostedGameException('The game database did not return a valid test response.');
    if ($bootstrap) mg_hosted_game_database_bootstrap($gamePdo);
    $version = (string)$gamePdo->query('SELECT VERSION()')->fetchColumn();
    return [
        'connected'=>true,
        'server_version'=>$version,
        'latency_ms'=>(int)round((microtime(true)-$started)*1000),
        'standard_tables_ready'=>$bootstrap,
    ];
}

function mg_hosted_game_readiness(PDO $pdo, array $game): array
{
    $gameId = (int)$game['id'];
    $secrets = ['api_credential'=>'','webhook_secret'=>'','state_secret'=>''];
    try { $secrets = mg_hosted_game_secrets($pdo, $gameId); } catch (Throwable) {}
    $db = mg_hosted_game_database_row($pdo, $gameId, false);
    $releaseReady = trim((string)($game['current_release_public_id'] ?? '')) !== '';
    $integrationReady = (string)($game['integration_status'] ?? '') === 'ready'
        && !empty($game['developer_app_id'])
        && !empty($game['api_key_id'])
        && !empty($game['distribution_program_id'])
        && !empty($game['campaign_id'])
        && !empty($game['pppm_template_id'])
        && trim($secrets['api_credential']) !== ''
        && trim($secrets['state_secret']) !== '';
    $databaseReady = is_array($db) && (string)$db['status'] === 'ready';
    return [
        'schema_ready'=>mg_hosted_game_schema_ready($pdo),
        'release_ready'=>$releaseReady,
        'integration_ready'=>$integrationReady,
        'database_ready'=>$databaseReady,
        'publish_ready'=>$releaseReady && $integrationReady && $databaseReady,
        'api_credential_ready'=>trim($secrets['api_credential']) !== '',
        'state_secret_ready'=>trim($secrets['state_secret']) !== '',
        'webhook_secret_ready'=>trim($secrets['webhook_secret']) !== '',
        'database'=>mg_hosted_game_database_public($db),
    ];
}

function mg_hosted_game_public_record(PDO $pdo, array $row): array
{
    $readiness = mg_hosted_game_readiness($pdo, $row);
    return [
        'id'=>(string)$row['public_id'],
        'name'=>(string)$row['name'],
        'slug'=>(string)$row['slug'],
        'description'=>(string)($row['description'] ?? ''),
        'cover_url'=>(string)($row['cover_url'] ?? ''),
        'status'=>(string)$row['status'],
        'integration_status'=>(string)$row['integration_status'],
        'database_status'=>(string)$row['database_status'],
        'entry_file'=>(string)$row['entry_file'],
        'release_id'=>$row['current_release_public_id'] ?? null,
        'public_url'=>mg_hosted_game_base_url() . '/games/' . rawurlencode((string)$row['slug']) . '/',
        'developer_app_id'=>$row['developer_app_id'] ?? null,
        'distribution_program_id'=>$row['program_public_id'] ?? null,
        'distribution_program_name'=>$row['program_name'] ?? null,
        'campaign_id'=>$row['campaign_public_id'] ?? null,
        'campaign_title'=>$row['campaign_title'] ?? null,
        'reward_template_id'=>$row['pppm_public_id'] ?? null,
        'reward_title'=>$row['reward_title'] ?? null,
        'release_version'=>isset($row['version_number']) ? (int)$row['version_number'] : null,
        'file_count'=>isset($row['file_count']) ? (int)$row['file_count'] : null,
        'extracted_bytes'=>isset($row['extracted_bytes']) ? (int)$row['extracted_bytes'] : null,
        'published_at'=>$row['published_at'] ?? null,
        'created_at'=>$row['created_at'] ?? null,
        'updated_at'=>$row['updated_at'] ?? null,
        'readiness'=>$readiness,
    ];
}

function mg_hosted_game_log_event(PDO $pdo, int $gameId, ?int $runId, ?int $playerUserId, string $eventType, array $event = []): void
{
    try {
        $pdo->prepare('INSERT INTO hosted_game_events (public_id,game_id,run_id,player_user_id,event_type,event_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
            ->execute([mg_hosted_game_uuid(),$gameId,$runId,$playerUserId,substr($eventType,0,100),$event === [] ? null : mg_hosted_game_json_encode($event)]);
    } catch (Throwable) {
        // Game telemetry must not block gameplay.
    }
}

function mg_hosted_game_active_link(PDO $pdo, int $appId, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM developer_app_user_links WHERE app_id=? AND microgifter_user_id=? AND status='active' LIMIT 1");
    $stmt->execute([$appId,$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_hosted_game_connect_player(PDO $pdo, array $game, int $userId): array
{
    $appId = (int)($game['developer_app_id'] ?? 0);
    if ($appId < 1) throw new MgHostedGameException('Game integration is not ready.');
    $existing = mg_hosted_game_active_link($pdo, $appId, $userId);
    if ($existing) return $existing;
    $publicId = mg_hosted_game_uuid();
    $externalUserId = 'hosted-game-user-' . $userId;
    $externalHash = hash('sha256', strtolower($externalUserId));
    $consent = ['source'=>'hosted-game','game_id'=>(string)$game['public_id'],'connected_at'=>gmdate('c')];
    $pdo->prepare("INSERT INTO developer_app_user_links (public_id,app_id,merchant_user_id,microgifter_user_id,external_user_id,external_user_hash,status,consent_json,metadata_json,linked_at,updated_at) VALUES (?,?,?,?,?,?,'active',?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE status='active',consent_json=VALUES(consent_json),metadata_json=VALUES(metadata_json),revoked_at=NULL,linked_at=NOW(),updated_at=NOW()")
        ->execute([$publicId,$appId,(int)$game['merchant_user_id'],$userId,$externalUserId,$externalHash,mg_hosted_game_json_encode($consent),mg_hosted_game_json_encode(['hosted_game_id'=>(string)$game['public_id']])]);
    $linked = mg_hosted_game_active_link($pdo, $appId, $userId);
    if (!$linked) throw new MgHostedGameException('Unable to connect the player account.');
    return $linked;
}

function mg_hosted_game_api_request(array $game, array $secrets, string $method, string $path, ?array $body = null, array $headers = []): array
{
    $apiKey = trim((string)($secrets['api_credential'] ?? ''));
    if ($apiKey === '') throw new MgHostedGameException('Game API credential is unavailable.');
    $url = rtrim(mg_hosted_game_base_url(), '/') . $path;
    $requestHeaders = array_merge([
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
        'User-Agent: Microgifter-Hosted-Game/1.0',
    ], $headers);
    $payload = null;
    if ($body !== null) {
        $payload = mg_hosted_game_json_encode($body);
        $requestHeaders[] = 'Content-Type: application/json';
    }
    if (!function_exists('curl_init')) throw new MgHostedGameException('The server-side cURL extension is required.');
    $ch = curl_init($url);
    if ($ch === false) throw new MgHostedGameException('Unable to initialize the game API request.');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CUSTOMREQUEST=>strtoupper($method),
        CURLOPT_HTTPHEADER=>$requestHeaders,
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_TIMEOUT=>18,
        CURLOPT_FOLLOWLOCATION=>false,
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new MgHostedGameException($error !== '' ? $error : 'Hosted game API request failed.');
    $decoded = json_decode((string)$raw, true);
    $response = is_array($decoded) ? $decoded : ['message'=>(string)$raw];
    $data = is_array($response['data'] ?? null) ? $response['data'] : $response;
    if ($status < 200 || $status >= 300) {
        $message = trim((string)($response['message'] ?? $data['message'] ?? 'Hosted game API request failed.'));
        throw new MgHostedGameException($message !== '' ? $message : 'Hosted game API request failed.');
    }
    return ['status'=>$status,'body'=>$response,'data'=>$data];
}

function mg_hosted_game_run_payload(array $row): array
{
    return [
        'run_id'=>(string)$row['public_id'],
        'status'=>(string)$row['status'],
        'score'=>$row['score'] !== null ? (int)$row['score'] : null,
        'result'=>mg_hosted_game_json_decode($row['result_json'] ?? null),
        'reward_id'=>$row['reward_public_id'] ?? null,
        'api_status'=>$row['api_status'] ?? null,
        'message'=>$row['error_message'] ?? null,
        'started_at'=>$row['started_at'] ?? null,
        'completed_at'=>$row['completed_at'] ?? null,
        'rewarded_at'=>$row['rewarded_at'] ?? null,
        'expires_at'=>$row['expires_at'] ?? null,
        'inbox_url'=>'/inbox.php',
    ];
}
