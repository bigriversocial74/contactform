<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/bootstrap.php';

final class MgMcpOAuthException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $oauthError = 'invalid_request',
        private readonly int $httpStatus = 400
    ) {
        parent::__construct($message);
    }

    public function oauthError(): string
    {
        return $this->oauthError;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

function mg_mcp_oauth_env_enabled(string $key, bool $default = false): bool
{
    $value = getenv($key);
    if ($value === false || trim((string)$value) === '') {
        return $default;
    }
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function mg_mcp_oauth_enabled(): bool
{
    return mg_mcp_oauth_env_enabled('MG_MCP_OAUTH_ENABLED', false);
}

function mg_mcp_oauth_issuer(): string
{
    return rtrim(trim((string)(getenv('MG_MCP_OAUTH_ISSUER') ?: 'https://microgifter.com')), '/');
}

function mg_mcp_oauth_resource_uri(): string
{
    return rtrim(trim((string)(getenv('MG_MCP_OAUTH_RESOURCE_URI') ?: 'https://mcp.microgifter.com/mcp')), '/');
}

function mg_mcp_oauth_access_ttl(): int
{
    return max(300, min(3600, (int)(getenv('MG_MCP_OAUTH_ACCESS_TTL_SECONDS') ?: 3600)));
}

function mg_mcp_oauth_refresh_ttl(): int
{
    return max(86400, min(7776000, (int)(getenv('MG_MCP_OAUTH_REFRESH_TTL_SECONDS') ?: 2592000)));
}

function mg_mcp_oauth_require_enabled(): void
{
    if (!mg_mcp_oauth_enabled()) {
        throw new MgMcpOAuthException('External MCP authorization is not enabled.', 'temporarily_unavailable', 503);
    }
}

function mg_mcp_oauth_base64url(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function mg_mcp_oauth_random_token(string $prefix, int $bytes): string
{
    return $prefix . mg_mcp_oauth_base64url(random_bytes($bytes));
}

function mg_mcp_oauth_hash_token(string $token): string
{
    return hash('sha256', $token);
}

function mg_mcp_oauth_json_decode(mixed $value, array $fallback = []): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || trim($value) === '') {
        return $fallback;
    }
    try {
        $decoded = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return $fallback;
    }
    return is_array($decoded) ? $decoded : $fallback;
}

function mg_mcp_oauth_text(mixed $value, int $min, int $max, string $label, bool $required = true): string
{
    $text = trim((string)$value);
    $length = mb_strlen($text);
    if (($required && $length < $min) || $length > $max) {
        throw new MgMcpOAuthException(
            $label . ' must be between ' . $min . ' and ' . $max . ' characters.',
            'invalid_request',
            422
        );
    }
    return $text;
}

function mg_mcp_oauth_https_url(string $value, string $label, bool $allowPath = true): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) > 1000 || preg_match('/[\x00-\x20\x7f]/', $value) === 1) {
        throw new MgMcpOAuthException('Invalid ' . $label . '.', 'invalid_request', 422);
    }
    $parts = parse_url($value);
    if (!is_array($parts)
        || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host'])
        || isset($parts['user'], $parts['pass'], $parts['fragment'])) {
        throw new MgMcpOAuthException($label . ' must be an HTTPS URL without embedded credentials or a fragment.', 'invalid_request', 422);
    }
    if (!$allowPath && !in_array((string)($parts['path'] ?? ''), ['', '/'], true)) {
        throw new MgMcpOAuthException($label . ' must be an HTTPS origin.', 'invalid_request', 422);
    }
    return $value;
}

function mg_mcp_oauth_redirect_uri(string $value): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) > 1000 || preg_match('/[\x00-\x20\x7f]/', $value) === 1) {
        throw new MgMcpOAuthException('Invalid redirect URI.', 'invalid_redirect_uri', 422);
    }
    $parts = parse_url($value);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])
        || isset($parts['user'], $parts['pass'], $parts['fragment'])) {
        throw new MgMcpOAuthException('Invalid redirect URI.', 'invalid_redirect_uri', 422);
    }
    $scheme = strtolower((string)$parts['scheme']);
    $host = strtolower(trim((string)$parts['host'], '[]'));
    $loopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    if ($scheme !== 'https' && !($scheme === 'http' && $loopback)) {
        throw new MgMcpOAuthException('Redirect URIs must use HTTPS, except for localhost loopback clients.', 'invalid_redirect_uri', 422);
    }
    return $value;
}

function mg_mcp_oauth_redirect_uris(mixed $value): array
{
    $items = is_array($value) ? $value : [];
    $uris = [];
    foreach ($items as $item) {
        $uri = mg_mcp_oauth_redirect_uri((string)$item);
        $uris[$uri] = true;
    }
    $uris = array_keys($uris);
    if ($uris === [] || count($uris) > 20) {
        throw new MgMcpOAuthException('Provide between one and twenty exact redirect URIs.', 'invalid_client_metadata', 422);
    }
    sort($uris);
    return $uris;
}

function mg_mcp_oauth_scope_keys(PDO $pdo, mixed $value, bool $requireProfile = true): array
{
    if (is_string($value)) {
        $requested = preg_split('/\s+/', trim($value)) ?: [];
    } elseif (is_array($value)) {
        $requested = $value;
    } else {
        $requested = [];
    }
    $requested = array_values(array_unique(array_filter(array_map(
        static fn(mixed $scope): string => strtolower(trim((string)$scope)),
        $requested
    ))));
    if ($requested === []) {
        $requested = ['profile:read', 'catalog:read'];
    }
    if (count($requested) > 20) {
        throw new MgMcpOAuthException('Too many scopes were requested.', 'invalid_scope', 422);
    }
    if ($requireProfile && !in_array('profile:read', $requested, true)) {
        throw new MgMcpOAuthException('The profile:read scope is required.', 'invalid_scope', 422);
    }
    foreach ($requested as $scope) {
        if (preg_match('/^[a-z][a-z0-9._-]{0,79}:[a-z][a-z0-9._-]{0,79}$/', $scope) !== 1) {
            throw new MgMcpOAuthException('Invalid OAuth scope.', 'invalid_scope', 422);
        }
    }
    $placeholders = implode(',', array_fill(0, count($requested), '?'));
    $stmt = $pdo->prepare(
        "SELECT scope_key FROM mcp_scope_catalog
         WHERE scope_key IN ($placeholders) AND active=1 AND grantable=1 AND operation_class='read'"
    );
    $stmt->execute($requested);
    $allowed = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    sort($allowed);
    $expected = $requested;
    sort($expected);
    if ($allowed !== $expected) {
        throw new MgMcpOAuthException('One or more requested scopes are unavailable.', 'invalid_scope', 422);
    }
    return $allowed;
}

function mg_mcp_oauth_scopes_supported(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT scope_key FROM mcp_scope_catalog
         WHERE active=1 AND grantable=1 AND operation_class='read'
         ORDER BY scope_key"
    );
    return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
}
