<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/provider-interface.php';

final class MgHubSpotProvider implements MgMerchantIntegrationProvider
{
    private const AUTHORIZE_URL = 'https://app.hubspot.com/oauth/authorize';
    private const TOKEN_URL = 'https://api.hubapi.com/oauth/v1/token';
    private const API_BASE = 'https://api.hubapi.com';

    public function key(): string { return 'hubspot'; }
    public function label(): string { return 'HubSpot'; }
    public function description(): string { return 'Import HubSpot contacts and lifecycle metadata into the canonical Microgifter CRM.'; }
    public function authType(): string { return 'oauth2'; }

    public function capabilities(): array
    {
        return ['contacts.read', 'contacts.lifecycle_stage.read'];
    }

    public function scopes(): array
    {
        return ['oauth', 'crm.objects.contacts.read'];
    }

    public function clientId(): string { return trim((string)(getenv('MG_HUBSPOT_CLIENT_ID') ?: '')); }
    public function clientSecret(): string { return trim((string)(getenv('MG_HUBSPOT_CLIENT_SECRET') ?: '')); }
    public function redirectUri(): string { return trim((string)(getenv('MG_HUBSPOT_REDIRECT_URI') ?: '')); }

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
            'addresses_imported' => false,
            'phone_numbers_imported' => false,
            'marketing_consent_imported' => false,
        ];
    }

    public function buildAuthorizationUrl(string $state): string
    {
        if (!$this->isConfigured()) throw new RuntimeException('HubSpot OAuth is not configured.');
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->clientId(),
            'scope' => implode(' ', $this->scopes()),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'code' => trim($code),
        ]);
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'refresh_token' => trim($refreshToken),
        ]);
    }

    public function fetchAccessTokenInfo(string $accessToken): array
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') throw new InvalidArgumentException('HubSpot access token is required.');
        return $this->requestJson('GET', self::API_BASE . '/oauth/v1/access-tokens/' . rawurlencode($accessToken), null, null);
    }

    public function listContacts(string $accessToken, ?string $after = null, int $pageSize = 100): array
    {
        $params = [
            'limit' => max(1, min(100, $pageSize)),
            'archived' => 'false',
            'properties' => implode(',', ['email', 'firstname', 'lastname', 'lifecyclestage', 'createdate', 'lastmodifieddate']),
        ];
        if ($after !== null && trim($after) !== '') $params['after'] = trim($after);
        $data = $this->requestJson('GET', self::API_BASE . '/crm/v3/objects/contacts?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986), $accessToken, null);
        $next = is_array($data['paging']['next'] ?? null) ? $data['paging']['next'] : [];
        return [
            'contacts' => is_array($data['results'] ?? null) ? array_values($data['results']) : [],
            'pagination' => [
                'has_next_page' => trim((string)($next['after'] ?? '')) !== '',
                'next_cursor' => trim((string)($next['after'] ?? '')) ?: null,
            ],
        ];
    }

    private function tokenRequest(array $payload): array
    {
        if (!$this->isConfigured()) throw new RuntimeException('HubSpot OAuth is not configured.');
        return $this->requestJson('POST', self::TOKEN_URL, null, $payload);
    }

    private function requestJson(string $method, string $url, ?string $accessToken, ?array $form): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for HubSpot API requests.');
        $headers = ['Accept: application/json', 'User-Agent: Microgifter/1.0 AppConnect'];
        if ($accessToken !== null && trim($accessToken) !== '') $headers[] = 'Authorization: Bearer ' . trim($accessToken);
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($form !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        }
        $handle = curl_init($url);
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($response)) throw new RuntimeException('HubSpot API request failed: ' . ($error ?: 'No response.'));
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('HubSpot returned an invalid API response.');
        if ($status === 401 || $status === 403) throw new RuntimeException('HubSpot access was rejected. Reauthorize the connection or verify the app scopes.');
        if ($status === 429) throw new RuntimeException('HubSpot rate limited the request. Try the sync again later.');
        if ($status < 200 || $status >= 300) {
            $message = trim((string)($decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? 'HubSpot API request failed.'));
            throw new RuntimeException($message !== '' ? $message : 'HubSpot API request failed.');
        }
        return $decoded;
    }
}
