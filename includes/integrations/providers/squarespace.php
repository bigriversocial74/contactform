<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/provider-interface.php';

final class MgSquarespaceProvider implements MgMerchantIntegrationOAuthProvider
{
    private const AUTHORIZE_URL = 'https://login.squarespace.com/api/1/login/oauth/provider/authorize';
    private const TOKEN_URL = 'https://login.squarespace.com/api/1/login/oauth/provider/tokens';
    private const WEBSITE_URL = 'https://api.squarespace.com/1.0/authorization/website';

    public function key(): string { return 'squarespace'; }
    public function label(): string { return 'Squarespace'; }
    public function description(): string { return 'Import contacts and prepare commerce synchronization for Squarespace stores.'; }
    public function authType(): string { return 'oauth2'; }

    public function capabilities(): array
    {
        return [
            'contacts.read',
            'addresses.read',
            'marketing_consent.read',
            'orders.read',
            'products.read',
            'inventory.read',
            'webhooks.contacts',
            'webhooks.addresses',
        ];
    }

    public function scopes(): array
    {
        return [
            'website.contacts.read',
            'website.orders.read',
            'website.products.read',
            'website.inventory.read',
        ];
    }

    public function clientId(): string
    {
        return trim((string)(getenv('MG_SQUARESPACE_CLIENT_ID') ?: ''));
    }

    private function clientSecret(): string
    {
        return trim((string)(getenv('MG_SQUARESPACE_CLIENT_SECRET') ?: ''));
    }

    public function redirectUri(): string
    {
        return trim((string)(getenv('MG_SQUARESPACE_REDIRECT_URI') ?: ''));
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '' && $this->redirectUri() !== '';
    }

    public function configurationStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'client_id_configured' => $this->clientId() !== '',
            'client_secret_configured' => $this->clientSecret() !== '',
            'redirect_uri_configured' => $this->redirectUri() !== '',
            'redirect_uri' => $this->redirectUri(),
        ];
    }

    public function buildAuthorizationUrl(string $state, ?string $externalAccountHint = null): string
    {
        if (!$this->isConfigured()) throw new RuntimeException('Squarespace OAuth is not configured.');
        $params = [
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(',', $this->scopes()),
            'state' => $state,
            'access_type' => 'offline',
        ];
        $externalAccountHint = trim((string)$externalAccountHint);
        if ($externalAccountHint !== '') $params['website_id'] = $externalAccountHint;
        return self::AUTHORIZE_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        $code = trim($code);
        if ($code === '') throw new InvalidArgumentException('Squarespace authorization code is required.');
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => rawurldecode($code),
            'redirect_uri' => $this->redirectUri(),
        ]);
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $refreshToken = trim($refreshToken);
        if ($refreshToken === '') throw new InvalidArgumentException('Squarespace refresh token is required.');
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function fetchExternalAccount(string $accessToken): array
    {
        return $this->requestJson('GET', self::WEBSITE_URL, $accessToken);
    }

    private function tokenRequest(array $body): array
    {
        if (!$this->isConfigured()) throw new RuntimeException('Squarespace OAuth is not configured.');
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for Squarespace OAuth.');
        $handle = curl_init(self::TOKEN_URL);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->clientId() . ':' . $this->clientSecret()),
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'User-Agent: Microgifter/1.0 AppConnect',
            ],
            CURLOPT_POSTFIELDS => http_build_query($body, '', '&', PHP_QUERY_RFC3986),
        ]);
        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($response)) throw new RuntimeException('Squarespace token request failed: ' . ($error ?: 'No response.'));
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('Squarespace returned an invalid token response.');
        if ($status < 200 || $status >= 300) {
            $message = (string)($decoded['error_description'] ?? $decoded['message'] ?? $decoded['error'] ?? 'Squarespace OAuth request failed.');
            throw new RuntimeException($message);
        }
        return $decoded;
    }

    private function requestJson(string $method, string $url, string $accessToken): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for Squarespace API requests.');
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: Microgifter/1.0 AppConnect',
            ],
        ]);
        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($response)) throw new RuntimeException('Squarespace API request failed: ' . ($error ?: 'No response.'));
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('Squarespace returned an invalid API response.');
        if ($status === 401) throw new RuntimeException('Squarespace authorization has expired or was revoked.');
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException((string)($decoded['message'] ?? $decoded['error'] ?? 'Squarespace API request failed.'));
        }
        return $decoded;
    }
}
