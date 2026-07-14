<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/provider-interface.php';

final class MgMailchimpProvider implements MgMerchantIntegrationProvider
{
    private const AUTHORIZE_URL = 'https://login.mailchimp.com/oauth2/authorize';
    private const TOKEN_URL = 'https://login.mailchimp.com/oauth2/token';
    private const METADATA_URL = 'https://login.mailchimp.com/oauth2/metadata';

    public function key(): string { return 'mailchimp'; }
    public function label(): string { return 'Mailchimp'; }
    public function description(): string { return 'Import audience members and explicit Mailchimp subscription status into the canonical Microgifter CRM.'; }
    public function authType(): string { return 'oauth2'; }

    public function capabilities(): array
    {
        return ['audiences.read', 'contacts.read', 'contacts.marketing_status.read'];
    }

    public function clientId(): string { return trim((string)(getenv('MG_MAILCHIMP_CLIENT_ID') ?: '')); }
    public function clientSecret(): string { return trim((string)(getenv('MG_MAILCHIMP_CLIENT_SECRET') ?: '')); }
    public function redirectUri(): string { return trim((string)(getenv('MG_MAILCHIMP_REDIRECT_URI') ?: '')); }

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
            'marketing_status_preserved' => true,
        ];
    }

    public function buildAuthorizationUrl(string $state): string
    {
        if (!$this->isConfigured()) throw new RuntimeException('Mailchimp OAuth is not configured.');
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        if (!$this->isConfigured()) throw new RuntimeException('Mailchimp OAuth is not configured.');
        return $this->requestJson('POST', self::TOKEN_URL, null, [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'code' => trim($code),
        ]);
    }

    public function fetchMetadata(string $accessToken): array
    {
        return $this->requestJson('GET', self::METADATA_URL, $accessToken, null);
    }

    public function listAudiences(string $apiEndpoint, string $accessToken, int $offset = 0, int $count = 100): array
    {
        $url = $this->apiUrl($apiEndpoint, '/lists?' . http_build_query([
            'offset' => max(0, $offset),
            'count' => max(1, min(1000, $count)),
            'fields' => 'lists.id,lists.name,lists.stats.member_count,lists.stats.unsubscribe_count,lists.stats.cleaned_count,lists.date_created,total_items',
        ], '', '&', PHP_QUERY_RFC3986));
        return $this->requestJson('GET', $url, $accessToken, null);
    }

    public function listMembers(string $apiEndpoint, string $accessToken, string $audienceId, int $offset = 0, int $count = 100): array
    {
        $audienceId = trim($audienceId);
        if ($audienceId === '') throw new InvalidArgumentException('Mailchimp audience ID is required.');
        $fields = implode(',', [
            'members.id', 'members.email_address', 'members.unique_email_id', 'members.web_id',
            'members.email_type', 'members.status', 'members.merge_fields.FNAME', 'members.merge_fields.LNAME', 'members.vip',
            'members.timestamp_signup', 'members.last_changed', 'members.tags', 'total_items',
        ]);
        $url = $this->apiUrl($apiEndpoint, '/lists/' . rawurlencode($audienceId) . '/members?' . http_build_query([
            'offset' => max(0, $offset),
            'count' => max(1, min(1000, $count)),
            'fields' => $fields,
        ], '', '&', PHP_QUERY_RFC3986));
        return $this->requestJson('GET', $url, $accessToken, null);
    }

    private function apiUrl(string $apiEndpoint, string $path): string
    {
        $apiEndpoint = rtrim(trim($apiEndpoint), '/');
        if (!preg_match('#^https://[a-z0-9.-]+\.api\.mailchimp\.com(?:/3\.0)?$#i', $apiEndpoint)) {
            throw new RuntimeException('Stored Mailchimp API endpoint is invalid. Reauthorize the connection.');
        }
        if (!str_ends_with($apiEndpoint, '/3.0')) $apiEndpoint .= '/3.0';
        return $apiEndpoint . $path;
    }

    private function requestJson(string $method, string $url, ?string $accessToken, ?array $form): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for Mailchimp API requests.');
        $headers = ['Accept: application/json', 'User-Agent: Microgifter/1.0 AppConnect'];
        if ($accessToken !== null && trim($accessToken) !== '') $headers[] = 'Authorization: OAuth ' . trim($accessToken);
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
        if (!is_string($response)) throw new RuntimeException('Mailchimp API request failed: ' . ($error ?: 'No response.'));
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('Mailchimp returned an invalid API response.');
        if ($status === 401 || $status === 403) throw new RuntimeException('Mailchimp access was rejected or revoked. Reauthorize the connection.');
        if ($status === 429) throw new RuntimeException('Mailchimp rate limited the request. Try the sync again later.');
        if ($status < 200 || $status >= 300) {
            $message = trim((string)($decoded['detail'] ?? $decoded['title'] ?? $decoded['error_description'] ?? $decoded['error'] ?? 'Mailchimp API request failed.'));
            throw new RuntimeException($message !== '' ? $message : 'Mailchimp API request failed.');
        }
        return $decoded;
    }
}
