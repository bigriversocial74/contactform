<?php
declare(strict_types=1);

function mg_lqv_safe_proof_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '') return null;
    if (strlen($url) > 700 || filter_var($url, FILTER_VALIDATE_URL) === false) mg_fail('Invalid proof URL.', 422);
    $parts = parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $local = in_array($host, ['localhost','127.0.0.1','::1'], true);
    if (!is_array($parts) || $host === '' || ($scheme !== 'https' && !($local && $scheme === 'http'))) {
        mg_fail('Proof links must use HTTPS.', 422);
    }
    return $url;
}

function mg_lqv_base64url_decode(string $value): string|false
{
    if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) return false;
    $padding = (4 - strlen($value) % 4) % 4;
    return base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
}

function mg_lqv_signed_qr(array $campaign, string $code): array
{
    $parts = explode('.', $code);
    if (count($parts) !== 3) mg_fail('The signed quest QR code is invalid.', 422);
    [$payloadEncoded, $nonce, $signature] = $parts;
    if (strlen($nonce) < 16 || strlen($nonce) > 190 || preg_match('/^[A-Za-z0-9_-]+$/', $nonce) !== 1 || preg_match('/^[a-f0-9]{64}$/i', $signature) !== 1) {
        mg_fail('The signed quest QR code is invalid.', 422);
    }
    $secret = (string)($campaign['qr_code_token'] ?? '');
    if ($secret === '') mg_fail('Signed quest verification is not configured.', 409);
    $expected = hash_hmac('sha256', $payloadEncoded . '.' . $nonce, $secret);
    if (!hash_equals($expected, strtolower($signature))) mg_fail('The signed quest QR code is invalid.', 422);
    $decoded = mg_lqv_base64url_decode($payloadEncoded);
    $payload = is_string($decoded) ? json_decode($decoded, true) : null;
    if (!is_array($payload)) mg_fail('The signed quest QR payload is invalid.', 422);
    $campaignRef = strtolower(trim((string)($payload['campaign'] ?? $payload['campaign_id'] ?? '')));
    $validRefs = [strtolower((string)$campaign['public_id']), strtolower((string)($campaign['public_slug'] ?? ''))];
    if ($campaignRef === '' || !in_array($campaignRef, $validRefs, true)) mg_fail('This signed QR code belongs to another Loyalty Quest.', 422);
    $expires = filter_var($payload['exp'] ?? null, FILTER_VALIDATE_INT);
    if ($expires === false || $expires < time()) mg_fail('This signed quest QR code has expired.', 409);
    if ($expires > time() + 2678400) mg_fail('The signed quest QR expiration is invalid.', 422);
    $issued = filter_var($payload['iat'] ?? null, FILTER_VALIDATE_INT);
    if ($issued !== false && $issued > time() + 300) mg_fail('The signed quest QR issue time is invalid.', 422);
    return ['payload'=>$payload,'nonce_hash'=>hash('sha256', $nonce),'code_hash'=>hash('sha256', $code)];
}

function mg_lqv_reference_unique(PDO $pdo, array $campaign, string $referenceId): void
{
    if ($referenceId === '') return;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loyalty_quest_evidence WHERE campaign_id=? AND merchant_user_id=? AND reference_id=? AND status<>'rejected'");
    $stmt->execute([(int)$campaign['id'], (int)$campaign['merchant_user_id'], $referenceId]);
    if ((int)$stmt->fetchColumn() > 0) mg_fail('This purchase or completion reference has already been submitted.', 409);
}

function mg_lqv_use_signed_code(PDO $pdo, array $campaign, array $user, array $signed): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO loyalty_quest_code_uses (campaign_id,participant_user_id,code_hash,nonce_hash,used_at) VALUES (?,?,?,?,NOW())');
        $stmt->execute([(int)$campaign['id'], (int)$user['id'], (string)$signed['code_hash'], (string)$signed['nonce_hash']]);
    } catch (PDOException) {
        mg_fail('This signed quest QR code has already been used.', 409);
    }
}
