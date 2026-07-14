<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/provider-interface.php';

final class MgWooCommerceProvider implements MgMerchantIntegrationProvider
{
    public function key(): string { return 'woocommerce'; }
    public function label(): string { return 'WooCommerce'; }
    public function description(): string { return 'Import WooCommerce customers into the Microgifter CRM using merchant-generated read-only REST API keys.'; }
    public function authType(): string { return 'api_key'; }

    public function capabilities(): array
    {
        return [
            'contacts.read',
            'customer_order_summary.read',
            'products.read',
            'orders.read',
            'coupons.read',
        ];
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function configurationStatus(): array
    {
        return [
            'configured' => true,
            'merchant_credentials_required' => true,
            'https_required' => true,
            'addresses_imported' => false,
            'marketing_consent_inferred' => false,
            'credential_fields' => [
                ['key' => 'site_url', 'label' => 'Store URL', 'type' => 'url', 'placeholder' => 'https://store.example.com'],
                ['key' => 'consumer_key', 'label' => 'Consumer key', 'type' => 'text', 'placeholder' => 'ck_…'],
                ['key' => 'consumer_secret', 'label' => 'Consumer secret', 'type' => 'password', 'placeholder' => 'cs_…'],
            ],
        ];
    }

    public function normalizeSiteUrl(string $siteUrl): string
    {
        $siteUrl = trim($siteUrl);
        if ($siteUrl === '') throw new InvalidArgumentException('WooCommerce store URL is required.');
        if (!preg_match('~^https://~i', $siteUrl)) throw new InvalidArgumentException('WooCommerce store URL must use HTTPS.');
        $parts = parse_url($siteUrl);
        if (!is_array($parts) || empty($parts['host'])) throw new InvalidArgumentException('WooCommerce store URL is invalid.');
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $path = isset($parts['path']) ? rtrim((string)$parts['path'], '/') : '';
        return 'https://' . strtolower((string)$parts['host']) . $port . $path;
    }

    public function validateCredentials(string $siteUrl, string $consumerKey, string $consumerSecret): array
    {
        $siteUrl = $this->normalizeSiteUrl($siteUrl);
        $consumerKey = trim($consumerKey);
        $consumerSecret = trim($consumerSecret);
        if (!preg_match('/^ck_[A-Za-z0-9]+$/', $consumerKey)) throw new InvalidArgumentException('WooCommerce consumer key is invalid.');
        if (!preg_match('/^cs_[A-Za-z0-9]+$/', $consumerSecret)) throw new InvalidArgumentException('WooCommerce consumer secret is invalid.');
        $this->listCustomers($siteUrl, $consumerKey, $consumerSecret, 1, 1);
        $host = (string)(parse_url($siteUrl, PHP_URL_HOST) ?: 'WooCommerce store');
        return [
            'id' => hash('sha256', $siteUrl),
            'title' => $host,
            'url' => $siteUrl,
            'api_namespace' => 'wc/v3',
        ];
    }

    public function listCustomers(string $siteUrl, string $consumerKey, string $consumerSecret, int $page = 1, int $pageSize = 100): array
    {
        $siteUrl = $this->normalizeSiteUrl($siteUrl);
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $url = $siteUrl . '/wp-json/wc/v3/customers?' . http_build_query([
            'page' => $page,
            'per_page' => $pageSize,
            'orderby' => 'id',
            'order' => 'asc',
            'context' => 'view',
        ], '', '&', PHP_QUERY_RFC3986);
        $response = $this->requestJson($url, $consumerKey, $consumerSecret);
        $customers = is_array($response['data']) ? array_values($response['data']) : [];
        $headers = $response['headers'];
        $totalPages = max(1, (int)($headers['x-wp-totalpages'] ?? 1));
        $total = max(count($customers), (int)($headers['x-wp-total'] ?? count($customers)));
        return [
            'customers' => $customers,
            'pagination' => [
                'page' => $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'has_next_page' => $page < $totalPages,
                'next_page' => $page < $totalPages ? $page + 1 : null,
            ],
        ];
    }

    private function requestJson(string $url, string $consumerKey, string $consumerSecret): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for WooCommerce API requests.');
        $responseHeaders = [];
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $consumerKey . ':' . $consumerSecret,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Microgifter/1.0 AppConnect',
            ],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $position = strpos($line, ':');
                if ($position !== false) {
                    $name = strtolower(trim(substr($line, 0, $position)));
                    $value = trim(substr($line, $position + 1));
                    if ($name !== '') $responseHeaders[$name] = $value;
                }
                return $length;
            },
        ]);
        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($response)) throw new RuntimeException('WooCommerce API request failed: ' . ($error ?: 'No response.'));
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('WooCommerce returned an invalid API response.');
        if ($status === 401 || $status === 403) throw new RuntimeException('WooCommerce API credentials were rejected or do not have customer read access.');
        if ($status === 404) throw new RuntimeException('WooCommerce REST API v3 was not found at this store URL.');
        if ($status === 429) throw new RuntimeException('WooCommerce rate limited the request. Try the sync again later.');
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException((string)($decoded['message'] ?? $decoded['code'] ?? 'WooCommerce API request failed.'));
        }
        return ['data' => $decoded, 'headers' => $responseHeaders];
    }
}
