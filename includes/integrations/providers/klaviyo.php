<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/provider-interface.php';

final class MgKlaviyoProvider implements MgMerchantIntegrationProvider
{
    private const AUTHORIZE_URL = 'https://www.klaviyo.com/oauth/authorize';
    private const TOKEN_URL = 'https://a.klaviyo.com/oauth/token';
    private const API_BASE = 'https://a.klaviyo.com';

    public function key(): string { return 'klaviyo'; }
    public function label(): string { return 'Klaviyo'; }
    public function description(): string { return 'Import Klaviyo profiles and explicit email marketing subscription evidence into the canonical Microgifter CRM.'; }
    public function authType(): string { return 'oauth2'; }

    public function capabilities(): array
    {
        return ['accounts.read', 'profiles.read', 'profiles.email_marketing_status.read'];
    }

    public function scopes(): array
    {
        return ['accounts:read', 'profiles:read'];
    }

    public function clientId(): string { return trim((string)(getenv('MG_KLAVIYO_CLIENT_ID') ?: '')); }
    public function clientSecret(): string { return trim((string)(getenv('MG_KLAVIYO_CLIENT_SECRET') ?: '')); }
    public function redirectUri(): string { return trim((string)(getenv('MG_KLAVIYO_REDIRECT_URI') ?: '')); }

    public function revision(): string
    {
        $revision = trim((string)(getenv('MG_KLAVIYO_API_REVISION') ?: '2026-04-15'));
        return preg_match('/^20\d{2}-\d{2}-\d{2}(?:\.[A-Za-z0-9_-]+)?$/', $revision) ? $revision : '2026-04-15';
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '' && $this->redirectUri() !== '';
    }

    public function configurationStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'client_id' => $this->clientId() !== '',
            'client_secret' => $this->clientSecret() !== '',
            'redirect_uri' => $this->redirectUri() !== '',
            'redirect_uri_value' => $this->redirectUri() !== '' ? $this->redirectUri() : null,
            'revision' => $this->revision(),
            'pkce_required' => true,
            'addresses_imported' => false,
            'phone_numbers_imported' => false,
            'custom_properties_imported' => false,
            'marketing_status_preserved' => true,
        ];
    }

    public function buildAuthorizationUrl(string $state, string $codeChallenge): string
    {
        if (!$this->isConfigured()) throw new RuntimeException('Klaviyo OAuth is not configured.');
        if ($state === '' || $codeChallenge === '') throw new InvalidArgumentException('Klaviyo OAuth state and PKCE challenge are required.');
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
            'code_challenge_method' => 'S256',
            'code_challenge' => $codeChallenge,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code, string $codeVerifier): array
    {
        $code = trim($code);
        $codeVerifier = trim($codeVerifier);
        if ($code === '' || $codeVerifier === '') throw new InvalidArgumentException('Klaviyo authorization code and PKCE verifier are required.');
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $this->redirectUri(),
        ]);
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $refreshToken = trim($refreshToken);
        if ($refreshToken === '') throw new InvalidArgumentException('Klaviyo refresh token is required.');
        return $this->tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    public function fetchAccount(string $accessToken): array
    {
        $query = http_build_query([
            'fields[account]' => implode(',', ['id', 'public_api_key', 'timezone', 'locale', 'test_account']),
        ], '', '&', PHP_QUERY_RFC3986);
        $response = $this->apiRequest('GET', self::API_BASE . '/api/accounts?' . $query, $accessToken);
        $items = is_array($response['data'] ?? null) ? $response['data'] : [];
        $account = is_array($items[0] ?? null) ? $items[0] : [];
        if (trim((string)($account['id'] ?? '')) === '') throw new RuntimeException('Klaviyo did not return an account identifier.');
        return $account;
    }

    public function listProfiles(string $accessToken, ?string $cursor = null, int $pageSize = 100): array
    {
        $fields = [
            'email', 'external_id', 'first_name', 'last_name', 'created', 'updated',
            'subscriptions.email.marketing.can_receive_email_marketing',
            'subscriptions.email.marketing.consent',
            'subscriptions.email.marketing.consent_timestamp',
            'subscriptions.email.marketing.last_updated',
            'subscriptions.email.marketing.method',
            'subscriptions.email.marketing.method_detail',
            'subscriptions.email.marketing.custom_method_detail',
            'subscriptions.email.marketing.double_optin',
            'subscriptions.email.marketing.suppression',
            'subscriptions.email.marketing.list_suppressions',
        ];
        $params = [
            'additional-fields[profile]' => 'subscriptions',
            'fields[profile]' => implode(',', $fields),
            'page[size]' => max(1, min(100, $pageSize)),
        ];
        $cursor = trim((string)$cursor);
        if ($cursor !== '') $params['page[cursor]'] = $cursor;
        $response = $this->apiRequest('GET', self::API_BASE . '/api/profiles?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986), $accessToken);
        $nextUrl = trim((string)($response['links']['next'] ?? ''));
        $nextCursor = null;
        if ($nextUrl !== '') {
            $query = parse_url($nextUrl, PHP_URL_QUERY);
            if (is_string($query)) {
                parse_str($query, $parsed);
                $nextCursor = trim((string)($parsed['page']['cursor'] ?? '')) ?: null;
            }
        }
        return [
            'profiles' => is_array($response['data'] ?? null) ? array_values($response['data']) : [],
            'pagination' => ['has_next_page' => $nextCursor !== null, 'next_cursor' => $nextCursor],
        ];
    }

    private function tokenRequest(array $form): array
    {
        if (!$this->isConfigured()) throw new RuntimeException('Klaviyo OAuth is not configured.');
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for Klaviyo OAuth.');
        $handle = curl_init(self::TOKEN_URL);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->clientId() . ':' . $this->clientSecret()),
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'User-Agent: Microgifter/1.0 AppConnect',
            ],
            CURLOPT_POSTFIELDS => http_build_query($form, '', '&', PHP_QUERY_RFC3986),
        ]);
        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($response)) throw new RuntimeException('Klaviyo token request failed: ' . ($error ?: 'No response.'));
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('Klaviyo returned an invalid token response.');
        if ($status < 200 || $status >= 300) {
            $message = trim((string)($decoded['error_description'] ?? $decoded['error'] ?? 'Klaviyo OAuth request failed.'));
            throw new RuntimeException($message !== '' ? $message : 'Klaviyo OAuth request failed.');
        }
        return $decoded;
    }

    private function apiRequest(string $method, string $url, string $accessToken): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for Klaviyo API requests.');
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . trim($accessToken),
                'Accept: application/vnd.api+json',
                'revision: ' . $this->revision(),
                'User-Agent: Microgifter/1.0 AppConnect',
            ],
        ]);
        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($response)) throw new RuntimeException('Klaviyo API request failed: ' . ($error ?: 'No response.'));
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('Klaviyo returned an invalid API response.');
        if ($status === 401 || $status === 403) throw new RuntimeException('Klaviyo access was rejected or revoked. Reauthorize the connection.');
        if ($status === 429) throw new RuntimeException('Klaviyo rate limited the request. Try the sync again later.');
        if ($status < 200 || $status >= 300) {
            $first = is_array($decoded['errors'][0] ?? null) ? $decoded['errors'][0] : [];
            $message = trim((string)($first['detail'] ?? $first['title'] ?? 'Klaviyo API request failed.'));
            throw new RuntimeException($message !== '' ? $message : 'Klaviyo API request failed.');
        }
        return $decoded;
    }
}
