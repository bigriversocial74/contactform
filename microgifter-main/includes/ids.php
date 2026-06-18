<?php
/**
 * Public ID helpers.
 *
 * Stage 1 uses 26-character Crockford-base32 IDs. The first 10 characters are
 * time-derived and the remaining characters are random. This gives us URL-safe,
 * mostly time-sortable IDs without exposing sequential database IDs.
 */
declare(strict_types=1);

function mg_public_id(?int $timestampMs = null): string
{
    $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $timestampMs = $timestampMs ?? (int) floor(microtime(true) * 1000);

    $time = '';
    for ($i = 0; $i < 10; $i++) {
        $time = $alphabet[$timestampMs % 32] . $time;
        $timestampMs = intdiv($timestampMs, 32);
    }

    $random = '';
    $bytes = random_bytes(16);
    for ($i = 0; $i < 16; $i++) {
        $random .= $alphabet[ord($bytes[$i]) & 31];
    }

    return $time . $random;
}

function mg_request_id(): string
{
    if (function_exists('mg_security_request_id')) {
        return mg_security_request_id();
    }
    return mg_public_id();
}
