<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/provider-interface.php';

final class MgShopifyProvider implements MgMerchantIntegrationProvider
{
    public function key(): string { return 'shopify'; }
    public function label(): string { return 'Shopify'; }
    public function description(): string { return 'Import Shopify customers and verified email marketing state into the canonical Microgifter CRM.'; }
    public function authType(): string { return 'oauth2'; }

    public function capabilities(): array
    {
        return [
            'contacts.read',
            'contacts.email_marketing_state.read',
            'customer_order_summary.read',
        ];
    }

    public function scopes(): array
    {
        return ['read_customers'];
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
            'protected_customer_data_required' => true,
            'addresses_imported' => false,
            'phone_numbers_imported' => false,
        ];
    }

    public function clientId(): string { return trim((string)(getenv('MG_SHOPIFY_CLIENT_ID') ?: '')); }
    public function clientSecret(): string { return trim((string)(getenv('MG_SHOPIFY_CLIENT_SECRET') ?: '')); }
    public function redirectUri(): string { return trim((string)(getenv('MG_SHOPIFY_REDIRECT_URI') ?: '')); }

    public function apiVersion(): string
    {
        $version = trim((string)(getenv('MG_SHOPIFY_API_VERSION') ?: '2026-07'));
        return preg_match('/^20\d{2}-(01|04|07|10)$/', $version) ? $version : '2026-07';
    }

    public function normalizeShopDomain(string $shop): string
    {
        $shop = strtolower(trim($shop));
        $shop = preg_replace('~^https?://~', '', $shop) ?? $shop;
        $shop = trim(explode('/', $shop, 2)[0]);
        if (!str_ends_with($shop, '.myshopify.com')) $shop .= '.myshopify.com';
        if (!preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', $shop)) {
            throw new InvalidArgumentException('Enter a valid Shopify store domain such as store-name.myshopify.com.');
        }
        return $shop;
    }

    public function buildAuthorizationUrl(string $shop, string $state): string
    {
        if (!$this->isConfigured()) throw new RuntimeException('Shopify OAuth is not configured.');
        $shop = $this->normalizeShopDomain($shop);
        return 'https://' . $shop . '/admin/oauth/authorize?' . http_build_query([
            'client_id' => $this->clientId(),
            'scope' => implode(',', $this->scopes()),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function verifyCallbackHmac(array $query): bool
    {
        $provided = strtolower(trim((string)($query['hmac'] ?? '')));
        if ($provided === '' || !preg_match('/^[a-f0-9]{64}$/', $provided)) return false;
        unset($query['hmac'], $query['signature']);
        ksort($query, SORT_STRING);
        $pairs = [];
        foreach ($query as $key => $value) {
            if (is_array($value)) continue;
            $pairs[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
        }
        $message = implode('&', $pairs);
        $expected = hash_hmac('sha256', $message, $this->clientSecret());
        return hash_equals($expected, $provided);
    }

    public function exchangeAuthorizationCode(string $shop, string $code): array
    {
        $shop = $this->normalizeShopDomain($shop);
        return $this->requestJson(
            'https://' . $shop . '/admin/oauth/access_token',
            'POST',
            [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'code' => trim($code),
            ],
            []
        );
    }

    public function fetchShop(string $shop, string $accessToken): array
    {
        $data = $this->graphql($shop, $accessToken, <<<'GQL'
query MicrogifterShopIdentity {
  shop {
    id
    name
    myshopifyDomain
    primaryDomain { url }
  }
}
GQL);
        $record = is_array($data['data']['shop'] ?? null) ? $data['data']['shop'] : [];
        if (trim((string)($record['id'] ?? '')) === '') throw new RuntimeException('Shopify did not return the connected shop identity.');
        return $record;
    }

    public function listCustomers(string $shop, string $accessToken, ?string $cursor = null, int $pageSize = 100): array
    {
        $pageSize = max(1, min(250, $pageSize));
        $query = <<<'GQL'
query MicrogifterCustomerList($first: Int!, $after: String) {
  customers(first: $first, after: $after, sortKey: ID) {
    edges {
      cursor
      node {
        id
        firstName
        lastName
        defaultEmailAddress {
          emailAddress
          marketingState
        }
        createdAt
        updatedAt
        numberOfOrders
        state
        amountSpent {
          amount
          currencyCode
        }
        verifiedEmail
        tags
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
GQL;
        $data = $this->graphql($shop, $accessToken, $query, ['first' => $pageSize, 'after' => $cursor]);
        $connection = is_array($data['data']['customers'] ?? null) ? $data['data']['customers'] : [];
        $customers = [];
        foreach ((array)($connection['edges'] ?? []) as $edge) {
            if (is_array($edge['node'] ?? null)) $customers[] = $edge['node'];
        }
        $pageInfo = is_array($connection['pageInfo'] ?? null) ? $connection['pageInfo'] : [];
        return [
            'customers' => $customers,
            'pagination' => [
                'has_next_page' => (bool)($pageInfo['hasNextPage'] ?? false),
                'next_cursor' => trim((string)($pageInfo['endCursor'] ?? '')) ?: null,
            ],
        ];
    }

    public function graphql(string $shop, string $accessToken, string $query, array $variables = []): array
    {
        $shop = $this->normalizeShopDomain($shop);
        $response = $this->requestJson(
            'https://' . $shop . '/admin/api/' . $this->apiVersion() . '/graphql.json',
            'POST',
            ['query' => $query, 'variables' => $variables],
            ['X-Shopify-Access-Token: ' . trim($accessToken)]
        );
        if (!empty($response['errors'])) {
            $message = is_array($response['errors']) ? json_encode($response['errors'], JSON_UNESCAPED_SLASHES) : (string)$response['errors'];
            throw new RuntimeException('Shopify GraphQL request failed: ' . mb_substr((string)$message, 0, 700));
        }
        return $response;
    }

    private function requestJson(string $url, string $method, array $payload, array $headers): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for Shopify API requests.');
        $isTokenExchange = str_ends_with($url, '/admin/oauth/access_token');
        $body = $isTokenExchange
            ? http_build_query($payload, '', '&', PHP_QUERY_RFC3986)
            : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $requestHeaders = array_merge([
            'Accept: application/json',
            'User-Agent: Microgifter/1.0 AppConnect',
            $isTokenExchange ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json',
        ], $headers);
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $requestHeaders,
        ]);
        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($response)) throw new RuntimeException('Shopify API request failed: ' . ($error ?: 'No response.'));
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('Shopify returned an invalid API response.');
        if ($status === 401 || $status === 403) throw new RuntimeException('Shopify access was rejected. Reauthorize the connection and confirm protected customer data access.');
        if ($status === 429) throw new RuntimeException('Shopify rate limited the request. Try the sync again later.');
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException((string)($decoded['error_description'] ?? $decoded['error'] ?? 'Shopify API request failed.'));
        }
        return $decoded;
    }
}
