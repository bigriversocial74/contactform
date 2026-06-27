<?php
declare(strict_types=1);

function mg_claim_voucher_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function mg_claim_voucher_base64url_decode(string $value): string
{
    $value = trim($value);
    $padding = str_repeat('=', (4 - strlen($value) % 4) % 4);
    $decoded = base64_decode(strtr($value, '-_', '+/') . $padding, true);
    if (!is_string($decoded)) {
        throw new RuntimeException('Invalid claim voucher token.');
    }
    return $decoded;
}

function mg_claim_voucher_secret(): string
{
    if (function_exists('mg_claim_code_pepper')) {
        return mg_claim_code_pepper();
    }
    $config = require dirname(__DIR__) . '/config.php';
    $secret = trim((string)($config['security']['claim_code_pepper'] ?? ''));
    if ($secret !== '') {
        return $secret;
    }
    throw new RuntimeException('Claim voucher security is not configured.');
}

function mg_claim_voucher_sign(string $payload): string
{
    return hash_hmac('sha256', $payload, mg_claim_voucher_secret());
}

function mg_claim_voucher_issue_token(string $voucherId, int $userId, int $ttlSeconds = 900): string
{
    $voucherId = trim($voucherId);
    if ($voucherId === '' || $userId < 1) {
        throw new InvalidArgumentException('Invalid voucher token request.');
    }
    $now = time();
    $payload = [
        'v' => 1,
        'typ' => 'microgifter_claim_voucher',
        'id' => $voucherId,
        'uid' => $userId,
        'iat' => $now,
        'exp' => $now + max(60, min($ttlSeconds, 3600)),
        'nonce' => bin2hex(random_bytes(12)),
    ];
    $encoded = mg_claim_voucher_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    return $encoded . '.' . mg_claim_voucher_sign($encoded);
}

function mg_claim_voucher_decode_token(string $token): array
{
    $token = trim($token);
    if ($token === '' || strlen($token) > 1000 || !str_contains($token, '.')) {
        throw new RuntimeException('Invalid claim voucher token.');
    }
    [$encoded, $signature] = explode('.', $token, 2);
    if (!hash_equals(mg_claim_voucher_sign($encoded), $signature)) {
        throw new RuntimeException('Invalid claim voucher signature.');
    }
    $payload = json_decode(mg_claim_voucher_base64url_decode($encoded), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || ($payload['typ'] ?? '') !== 'microgifter_claim_voucher') {
        throw new RuntimeException('Invalid claim voucher payload.');
    }
    if ((int)($payload['exp'] ?? 0) < time()) {
        throw new RuntimeException('Claim voucher token has expired.');
    }
    $id = trim((string)($payload['id'] ?? ''));
    if ($id === '') {
        throw new RuntimeException('Claim voucher token is missing its voucher ID.');
    }
    return $payload;
}
