<?php
/**
 * TOTP and recovery-code foundation for Microgifter identity hardening.
 * Secrets are encrypted at rest with MG_MFA_ENCRYPTION_KEY.
 */
declare(strict_types=1);

function mg_mfa_schema_ready(): bool
{
    return function_exists('mg_identity_schema_has_table')
        ? mg_identity_schema_has_table('user_mfa_methods') && mg_identity_schema_has_table('user_mfa_recovery_codes')
        : false;
}

function mg_mfa_key(): string
{
    $configured = trim((string) mg_config_value('security', 'mfa_encryption_key', ''));
    if ($configured === '') {
        throw new RuntimeException('MFA encryption key is not configured.');
    }
    $decoded = base64_decode($configured, true);
    $material = is_string($decoded) && strlen($decoded) >= 32 ? $decoded : $configured;
    return hash('sha256', $material, true);
}

function mg_mfa_encrypt(string $plaintext): string
{
    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', mg_mfa_key(), OPENSSL_RAW_DATA, $nonce, $tag);
    if (!is_string($ciphertext) || strlen($tag) !== 16) {
        throw new RuntimeException('Unable to encrypt MFA secret.');
    }
    return base64_encode($nonce . $tag . $ciphertext);
}

function mg_mfa_decrypt(string $encoded): string
{
    $payload = base64_decode($encoded, true);
    if (!is_string($payload) || strlen($payload) < 29) {
        throw new RuntimeException('Invalid MFA secret payload.');
    }
    $nonce = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $ciphertext = substr($payload, 28);
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', mg_mfa_key(), OPENSSL_RAW_DATA, $nonce, $tag);
    if (!is_string($plaintext) || $plaintext === '') {
        throw new RuntimeException('Unable to decrypt MFA secret.');
    }
    return $plaintext;
}

function mg_mfa_base32_encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bits = 0;
    $output = '';
    foreach (unpack('C*', $bytes) ?: [] as $byte) {
        $buffer = ($buffer << 8) | $byte;
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $output .= $alphabet[($buffer >> $bits) & 31];
        }
        $buffer = $bits > 0 ? ($buffer & ((1 << $bits) - 1)) : 0;
    }
    if ($bits > 0) {
        $output .= $alphabet[($buffer << (5 - $bits)) & 31];
    }
    return $output;
}

function mg_mfa_base32_decode(string $value): string
{
    $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
    $value = strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '');
    $buffer = 0;
    $bits = 0;
    $output = '';
    foreach (str_split($value) as $character) {
        if (!isset($alphabet[$character])) continue;
        $buffer = ($buffer << 5) | $alphabet[$character];
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $output .= chr(($buffer >> $bits) & 255);
            $buffer = $bits > 0 ? ($buffer & ((1 << $bits) - 1)) : 0;
        }
    }
    return $output;
}

function mg_mfa_totp_code(string $secret, int $counter): string
{
    $binarySecret = mg_mfa_base32_decode($secret);
    $high = intdiv($counter, 4294967296);
    $low = $counter % 4294967296;
    $message = pack('N2', $high, $low);
    $hash = hash_hmac('sha1', $message, $binarySecret, true);
    $offset = ord($hash[19]) & 15;
    $binary = ((ord($hash[$offset]) & 127) << 24)
        | ((ord($hash[$offset + 1]) & 255) << 16)
        | ((ord($hash[$offset + 2]) & 255) << 8)
        | (ord($hash[$offset + 3]) & 255);
    return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
}

function mg_mfa_verify_totp_secret(string $secret, string $code, ?int $lastCounter = null): ?int
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) return null;
    $counter = intdiv(time(), 30);
    for ($offset = -1; $offset <= 1; $offset++) {
        $candidate = $counter + $offset;
        if ($lastCounter !== null && $candidate <= $lastCounter) continue;
        if (hash_equals(mg_mfa_totp_code($secret, $candidate), $code)) return $candidate;
    }
    return null;
}

function mg_mfa_recovery_hash(string $code): string
{
    $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $code) ?? '');
    return hash_hmac('sha256', $normalized, mg_mfa_key());
}

function mg_mfa_user_enabled(PDO $pdo, int $userId): bool
{
    if (!mg_mfa_schema_ready()) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM user_mfa_methods WHERE user_id=? AND method_type='totp' AND confirmed_at IS NOT NULL AND disabled_at IS NULL LIMIT 1");
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
}

function mg_mfa_status(PDO $pdo, int $userId): array
{
    if (!mg_mfa_schema_ready()) return ['available' => false, 'enabled' => false, 'methods' => []];
    $stmt = $pdo->prepare("SELECT id,method_type,label,confirmed_at,last_used_at,created_at FROM user_mfa_methods WHERE user_id=? AND disabled_at IS NULL ORDER BY id DESC");
    $stmt->execute([$userId]);
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return [
        'available' => true,
        'enabled' => array_reduce($methods, static fn(bool $carry, array $row): bool => $carry || !empty($row['confirmed_at']), false),
        'methods' => $methods,
    ];
}

function mg_mfa_begin_totp(PDO $pdo, int $userId, string $label = 'Authenticator app'): array
{
    if (!mg_mfa_schema_ready()) throw new RuntimeException('MFA database migration is not installed.');
    $secret = mg_mfa_base32_encode(random_bytes(20));
    $encrypted = mg_mfa_encrypt($secret);
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE user_mfa_methods SET disabled_at=NOW(),updated_at=NOW() WHERE user_id=? AND method_type='totp' AND confirmed_at IS NULL AND disabled_at IS NULL")->execute([$userId]);
        $stmt = $pdo->prepare("INSERT INTO user_mfa_methods (user_id,method_type,label,secret_encrypted,created_at,updated_at) VALUES (?,'totp',?,?,NOW(),NOW())");
        $stmt->execute([$userId, mb_substr(trim($label) ?: 'Authenticator app', 0, 120), $encrypted]);
        $methodId = (int) $pdo->lastInsertId();
        if ($owns) $pdo->commit();
        return ['method_id' => $methodId, 'secret' => $secret];
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function mg_mfa_generate_recovery_codes(int $count = 10): array
{
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $raw = strtoupper(bin2hex(random_bytes(6)));
        $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
    }
    return $codes;
}

function mg_mfa_confirm_totp(PDO $pdo, int $userId, int $methodId, string $code): array
{
    if (!mg_mfa_schema_ready()) throw new RuntimeException('MFA database migration is not installed.');
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id,secret_encrypted,last_counter FROM user_mfa_methods WHERE id=? AND user_id=? AND method_type='totp' AND confirmed_at IS NULL AND disabled_at IS NULL LIMIT 1 FOR UPDATE");
        $stmt->execute([$methodId, $userId]);
        $method = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$method) throw new InvalidArgumentException('MFA setup is unavailable or expired.');
        $secret = mg_mfa_decrypt((string) $method['secret_encrypted']);
        $counter = mg_mfa_verify_totp_secret($secret, $code, isset($method['last_counter']) ? (int) $method['last_counter'] : null);
        if ($counter === null) throw new InvalidArgumentException('Enter a valid authenticator code.');
        $pdo->prepare('UPDATE user_mfa_methods SET confirmed_at=NOW(),last_used_at=NOW(),last_counter=?,updated_at=NOW() WHERE id=?')->execute([$counter, $methodId]);
        $pdo->prepare('DELETE FROM user_mfa_recovery_codes WHERE user_id=?')->execute([$userId]);
        $codes = mg_mfa_generate_recovery_codes();
        $insert = $pdo->prepare('INSERT INTO user_mfa_recovery_codes (user_id,method_id,code_hash,created_at) VALUES (?,?,?,NOW())');
        foreach ($codes as $recoveryCode) $insert->execute([$userId, $methodId, mg_mfa_recovery_hash($recoveryCode)]);
        if ($owns) $pdo->commit();
        return $codes;
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function mg_mfa_verify_user(PDO $pdo, int $userId, string $code, bool $consume = true): bool
{
    if (!mg_mfa_schema_ready()) return false;
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id,secret_encrypted,last_counter FROM user_mfa_methods WHERE user_id=? AND method_type='totp' AND confirmed_at IS NOT NULL AND disabled_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $stmt->execute([$userId]);
        $method = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($method) {
            $secret = mg_mfa_decrypt((string) $method['secret_encrypted']);
            $counter = mg_mfa_verify_totp_secret($secret, $code, isset($method['last_counter']) ? (int) $method['last_counter'] : null);
            if ($counter !== null) {
                if ($consume) $pdo->prepare('UPDATE user_mfa_methods SET last_counter=?,last_used_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$counter, (int) $method['id']]);
                if ($owns) $pdo->commit();
                return true;
            }
        }
        $hash = mg_mfa_recovery_hash($code);
        $stmt = $pdo->prepare('SELECT id,method_id FROM user_mfa_recovery_codes WHERE user_id=? AND code_hash=? AND used_at IS NULL LIMIT 1 FOR UPDATE');
        $stmt->execute([$userId, $hash]);
        $recovery = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($recovery) {
            if ($consume) {
                $pdo->prepare('UPDATE user_mfa_recovery_codes SET used_at=NOW() WHERE id=?')->execute([(int) $recovery['id']]);
                $pdo->prepare('UPDATE user_mfa_methods SET last_used_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int) $recovery['method_id']]);
            }
            if ($owns) $pdo->commit();
            return true;
        }
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        return false;
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function mg_mfa_disable_user(PDO $pdo, int $userId): void
{
    if (!mg_mfa_schema_ready()) return;
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE user_mfa_methods SET disabled_at=NOW(),updated_at=NOW() WHERE user_id=? AND disabled_at IS NULL')->execute([$userId]);
        $pdo->prepare('UPDATE user_mfa_recovery_codes SET used_at=COALESCE(used_at,NOW()) WHERE user_id=?')->execute([$userId]);
        if ($owns) $pdo->commit();
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
