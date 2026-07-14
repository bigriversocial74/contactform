<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/provider-interface.php';

final class MgSquareProvider implements MgMerchantIntegrationProvider
{
    public function key(): string { return 'square'; }
    public function label(): string { return 'Square'; }
    public function description(): string { return 'Import Square customer profiles into the canonical Microgifter CRM with encrypted OAuth access and durable cursors.'; }
    public function authType(): string { return 'oauth2'; }

    public function capabilities(): array
    {
        return [
            'contacts.read',
            'contacts.email_unsubscribe_state.read',
            'merchant_profile.read',
        ];
    }

    public function scopes(): array
    {
        return ['CUSTOMERS_READ', 'MERCHANT_PROFILE_READ'];
    }

    public function isConfigured(): bool
    {
        return $this->applicationId() !== '' && $this->applicationSecret() !== '' && $this->redirectUri() !== '';
    }

    public function configurationStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'application_id' => $this->applicationId() !== '',
            'application_secret' => $this->applicationSecret() !== '',
            'redirect_uri' => $this->redirectUri() !== '',
            'redirect_uri_value' => $this->redirectUri() !== '' ? $this->redirectUri() : null,
            'environment' => $this->environment(),
            'addresses_imported' => false,
            'phone_numbers_imported' => false,
            'birthdays_imported' => false,
        ];
    }

    public function applicationId(): string { return trim((string)(getenv('MG_SQUARE_APPLICATION_ID') ?: '')); }
    public function applicationSecret(): string { return trim((string)(getenv('MG_SQUARE_APPLICATION_SECRET') ?: '')); }
    public function redirectUri(): string { return trim((string)(getenv('MG_SQUARE_REDIRECT_URI') ?: '')); }
    public function apiVersion(): string { return trim((string)(getenv('MG_SQUARE_API_VERSION') ?: '')); }

    public function environment(): string
    {
        return strtolower(trim((string)(getenv('MG_SQUARE_ENVIRONMENT') ?: 'production'))) === 'sandbox' ? 'sandbox' : 'production';
    }

    public function oauthBaseUrl(): string
    {
        return $this->environment() === 'sandbox'
            ? 'https://connect.squareupsandbox.com/oauth2'
            : 'https://connect.squareup.com/oauth2';
    }

    public function apiBaseUrl(): string
    {
        return $this->environment() === 'sandbox'
            ? 'https://connect.squareupsandbox.com/v2'
            : 'https://connect.squareup.com/v2';
    }

    public function buildAuthorizationUrl(string $state): string
    {
        if (!$this->isConfigured()) throw new RuntimeException('Square OAuth is not configured.');
        $params = [
            'client_id' => $this->applicationId(),
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
        ];
        if ($this->environment() === 'production') $params['session'] = 'false';
        return $this->oauthBaseUrl() . '/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        return $this->oauthRequest([
            'client_id' => $this->applicationId(),
            'client_secret' => $this->applicationSecret(),
            'code' => trim($code),
            'grant_type' => 'authorization_code',
            'use_jwt' => true,
        ]);
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->oauthRequest([
            'client_id' => $this->applicationId(),
            'client_secret' => $this->applicationSecret(),
            'refresh_token' => trim($refreshToken),
            'grant_type' => 'refresh_token',
            'use_jwt' => true,
        ]);
    }

    public function fetchMerchant(string $merchantId, string $accessToken): array
    {
        $merchantId = trim($merchantId);
        if ($merchantId === '') throw new InvalidArgumentException('Square merchant ID is required.');
        $data = $this->apiRequest('/merchants/' . rawurlencode($merchantId), $accessToken);
        $merchant = is_array($data['merchant'] ?? null) ? $data['merchant'] : [];
        if (trim((string)($merchant['id'] ?? '')) === '') throw new RuntimeException('Square did not return the connected merchant profile.');
        return $merchant;
    }

    public function listCustomers(string $accessToken, ?string $cursor = null, int $pageSize = 100): array
    {
        $params = ['limit' => max(1, min(100, $pageSize))];
        if ($cursor !== null && trim($cursor) !== '') $params['cursor'] = trim($cursor);
        $data = $this->apiRequest('/customers?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986), $accessToken);
        return [
            'customers' => is_array($data['customers'] ?? null) ? array_values($data['customers']) : [],
            'pagination' => [
                'has_next_page' => trim((string)($data['cursor'] ?? '')) !== '',
                'next_cursor' => trim((string)($data['cursor'] ?? '')) ?: null,
            ],
        ];
    }

    private function oauthRequest(array $payload): array
    {
        return $this->requestJson($this->oauthBaseUrl() . '/token', 'POST', $payload, null);
    }

    private function apiRequest(string $path, string $accessToken): array
    {
        return $this->requestJson($this->apiBaseUrl() . $path, 'GET', null, trim($accessToken));
    }

    private function requestJson(string $url, string $method, ?array $payload, ?string $accessToken): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for Square API requests.');
        $headers = ['Accept: application/json', 'Content-Type: application/json', 'User-Agent: Microgifter/1.0 AppConnect'];
        if ($accessToken !== null && $accessToken !== '') $headers[] = 'Authorization: Bearer ' . $accessToken;
        if ($this->apiVersion() !== '') $headers[] = 'Square-Version: ' . $this->apiVersion();
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($payload !== null) $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $handle = curl_init($url);
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($response)) throw new RuntimeException('Square API request failed: ' . ($error ?: 'No response.'));
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('Square returned an invalid API response.');
        if ($status === 401 || $status === 403) throw new RuntimeException('Square access was rejected. Reauthorize the connection or verify the granted permissions.');
        if ($status === 429) throw new RuntimeException('Square rate limited the request. Try the sync again later.');
        if ($status < 200 || $status >= 300) {
            $errors = is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [];
            $detail = trim((string)($errors[0]['detail'] ?? $errors[0]['code'] ?? $decoded['message'] ?? 'Square API request failed.'));
            throw new RuntimeException($detail !== '' ? $detail : 'Square API request failed.');
        }
        return $decoded;
    }
}
