<?php
declare(strict_types=1);

if (!function_exists('mg_test_base_url')) {
    function mg_test_base_url(): string
    {
        return rtrim((string) (getenv('MG_TEST_BASE_URL') ?: 'http://localhost:8000'), '/');
    }
}

if (!function_exists('mg_test_skip_authenticated')) {
    function mg_test_skip_authenticated(): bool
    {
        return (string) getenv('MG_TEST_SKIP_AUTHENTICATED') === '1';
    }
}

if (!function_exists('mg_test_request')) {
    function mg_test_request(string $method, string $path, ?array $payload = null, ?string $cookieFile = null, array $headers = []): array
    {
        $ch = curl_init(mg_test_base_url() . $path);
        $requestHeaders = array_merge(['Accept: application/json'], $headers);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 20,
        ]);

        if ($cookieFile) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        }

        if ($payload !== null) {
            $requestHeaders[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $body = substr((string) $raw, $headerSize);
        $decoded = json_decode($body, true);
        return [$status, is_array($decoded) ? $decoded : ['raw' => $body]];
    }
}

if (!function_exists('mg_test_csrf')) {
    function mg_test_csrf(string $pagePath, string $cookieFile): string
    {
        $ch = curl_init(mg_test_base_url() . $pagePath);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_TIMEOUT => 20,
        ]);
        $html = (string) curl_exec($ch);
        curl_close($ch);

        if (preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }
        if (preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }
        throw new RuntimeException('Unable to locate CSRF token on ' . $pagePath);
    }
}

if (!function_exists('mg_test_register_user')) {
    function mg_test_register_user(string $cookieFile): array
    {
        $email = 'stage1+' . bin2hex(random_bytes(6)) . '@example.test';
        $password = 'TestPass!' . random_int(100000, 999999);
        $csrf = mg_test_csrf('/signup.php', $cookieFile);

        [$status, $body] = mg_test_request('POST', '/api/auth/register.php', [
            'full_name' => 'Stage One Test User',
            'email' => $email,
            'password' => $password,
            'csrf_token' => $csrf,
        ], $cookieFile, ['X-CSRF-Token: ' . $csrf]);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Unable to register test user. Status ' . $status . ': ' . json_encode($body));
        }

        return ['email' => $email, 'password' => $password, 'body' => $body];
    }
}
