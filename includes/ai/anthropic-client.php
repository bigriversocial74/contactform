<?php
declare(strict_types=1);

/**
 * Minimal first-party Anthropic Messages API client for Microgifter.
 *
 * This client only returns model text and the raw response. It does not execute
 * any business action. Merchant automation must continue through the Stage 16
 * approval and adapter boundary.
 */

function mg_anthropic_api_key(): string
{
    $key = trim((string) getenv('MG_ANTHROPIC_API_KEY'));
    if ($key === '') {
        throw new RuntimeException('Anthropic API key is not configured.');
    }
    return $key;
}

function mg_anthropic_timeout_seconds(): int
{
    $value = (int) (getenv('MG_ANTHROPIC_TIMEOUT_SECONDS') ?: 45);
    return max(10, min(120, $value));
}

function mg_anthropic_messages(array $payload): array
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode Anthropic request.');
    }

    $curl = curl_init('https://api.anthropic.com/v1/messages');
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize Anthropic request.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => mg_anthropic_timeout_seconds(),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . mg_anthropic_api_key(),
            'anthropic-version: 2023-06-01',
        ],
    ]);

    $raw = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if (!is_string($raw) || $raw === '') {
        throw new RuntimeException($curlError !== '' ? ('Anthropic request failed: ' . $curlError) : 'Anthropic request returned an empty response.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Anthropic response was not valid JSON.');
    }

    if ($status < 200 || $status >= 300) {
        $message = (string) ($decoded['error']['message'] ?? ('Anthropic request failed with HTTP ' . $status . '.'));
        throw new RuntimeException($message);
    }

    return $decoded;
}

function mg_anthropic_text_from_response(array $response): string
{
    $text = '';
    foreach (($response['content'] ?? []) as $block) {
        if (is_array($block) && ($block['type'] ?? '') === 'text') {
            $text .= (string) ($block['text'] ?? '');
        }
    }
    return trim($text);
}

function mg_anthropic_extract_json_object(string $text): array
{
    $trimmed = trim($text);
    if ($trimmed === '') {
        throw new RuntimeException('Claude returned an empty plan.');
    }

    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $trimmed, $match) === 1) {
        $decoded = json_decode(trim($match[1]), true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $start = strpos($trimmed, '{');
    $end = strrpos($trimmed, '}');
    if ($start === false || $end === false || $end <= $start) {
        throw new RuntimeException('Claude response did not contain a JSON object.');
    }

    $candidate = substr($trimmed, $start, $end - $start + 1);
    $decoded = json_decode($candidate, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Claude plan JSON could not be parsed.');
    }

    return $decoded;
}
