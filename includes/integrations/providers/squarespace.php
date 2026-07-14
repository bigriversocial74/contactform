<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/provider-interface.php';

final class MgSquarespaceProvider implements MgMerchantIntegrationOAuthProvider
{
    private const AUTHORIZE_URL = 'https://login.squarespace.com/api/1/login/oauth/provider/authorize';
    private const TOKEN_URL = 'https://login.squarespace.com/api/1/login/oauth/provider/tokens';
    private const WEBSITE_URL = 'https://api.squarespace.com/1.0/authorization/website';
    private const CONTACTS_URL = 'https://api.squarespace.com/v1/contacts';
    private const WEBHOOKS_URL = 'https://api.squarespace.com/1.0/webhook_subscriptions';

    public function key(): string { return 'squarespace'; }
    public function label(): string { return 'Squarespace'; }
    public function description(): string { return 'Import contacts and marketing-consent history from Squarespace stores. Contact addresses are excluded.'; }
    public function authType(): string { return 'oauth2'; }

    public function capabilities(): array
    {
        return [
            'contacts.read',
            'marketing_consent.read',
            'orders.read',
            'products.read',
            'inventory.read',
            'webhooks.contacts',
        ];
    }

    public function scopes(): array
    {
        return [
            // Squarespace requires website.contacts, rather than website.contacts.read,
            // to create contact webhook subscriptions. Microgifter still performs read-only contact operations.
            'website.contacts',
            'website.orders.read',
            'website.products.read',
            'website.inventory.read',
        ];
    }

    public function contactWebhookTopics(): array
    {
        return ['contact.create', 'contact.update', 'contact.delete'];
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

    public function contactWebhookUrl(): string
    {
        $configured = trim((string)(getenv('MG_SQUARESPACE_CONTACT_WEBHOOK_URL') ?: ''));
        if ($configured !== '') return $configured;
        $redirect = $this->redirectUri();
        if ($redirect === '') return '';
        $parts = parse_url($redirect);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return '';
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        return $parts['scheme'] . '://' . $parts['host'] . $port . '/webhooks/squarespace-contacts.php';
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
            'contact_webhook_url' => $this->contactWebhookUrl(),
            'contact_webhook_ready' => str_starts_with($this->contactWebhookUrl(), 'https://'),
            'addresses_imported' => false,
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
            'code' => $code,
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

    public function listContacts(string $accessToken, ?string $cursor = null, int $pageSize = 100): array
    {
        $pageSize = max(1, min(1000, $pageSize));
        $query = ['pageSize' => $pageSize];
        $cursor = trim((string)$cursor);
        if ($cursor !== '') $query['cursor'] = $cursor;
        return $this->requestJson('GET', self::CONTACTS_URL . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), $accessToken);
    }

    public function getContact(string $accessToken, string $contactId): array
    {
        $contactId = trim($contactId);
        if ($contactId === '') throw new InvalidArgumentException('Squarespace contact ID is required.');
        return $this->requestJson('GET', self::CONTACTS_URL . '/' . rawurlencode($contactId), $accessToken);
    }

    public function ensureContactWebhook(string $accessToken): array
    {
        $endpoint = $this->contactWebhookUrl();
        if (!str_starts_with($endpoint, 'https://')) {
            throw new RuntimeException('MG_SQUARESPACE_CONTACT_WEBHOOK_URL must be an HTTPS URL before contact webhooks can be enabled.');
        }
        $topics = $this->contactWebhookTopics();
        $subscriptions = $this->requestJson('GET', self::WEBHOOKS_URL, $accessToken);
        foreach ((array)($subscriptions['webhookSubscriptions'] ?? []) as $subscription) {
            if (!is_array($subscription)) continue;
            $existingEndpoint = trim((string)($subscription['endpointUrl'] ?? ''));
            $existingTopics = array_values(array_map('strval', (array)($subscription['topics'] ?? [])));
            sort($existingTopics);
            $expectedTopics = $topics;
            sort($expectedTopics);
            if ($existingEndpoint !== $endpoint || $existingTopics !== $expectedTopics) continue;
            $subscriptionId = trim((string)($subscription['id'] ?? ''));
            if ($subscriptionId === '') continue;
            $rotation = $this->requestJson('POST', self::WEBHOOKS_URL . '/' . rawurlencode($subscriptionId) . '/actions/rotateSecret', $accessToken);
            $secret = trim((string)($rotation['secret'] ?? ''));
            if ($secret === '') throw new RuntimeException('Squarespace rotated the webhook secret but did not return it.');
            return $subscription + ['secret' => $secret, 'rotated' => true];
        }
        return $this->requestJson('POST', self::WEBHOOKS_URL, $accessToken, [
            'endpointUrl' => $endpoint,
            'topics' => $topics,
        ]);
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

    private function requestJson(string $method, string $url, string $accessToken, ?array $body = null): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for Squarespace API requests.');
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
            'User-Agent: Microgifter/1.0 AppConnect',
        ];
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        $handle = curl_init($url);
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($response)) throw new RuntimeException('Squarespace API request failed: ' . ($error ?: 'No response.'));
        if ($response === '' && $status >= 200 && $status < 300) return [];
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('Squarespace returned an invalid API response.');
        if ($status === 401) throw new RuntimeException('Squarespace authorization has expired or was revoked.');
        if ($status === 429) throw new RuntimeException('Squarespace rate limited the request. Try the sync again later.');
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException((string)($decoded['message'] ?? $decoded['error'] ?? 'Squarespace API request failed.'));
        }
        return $decoded;
    }
}
