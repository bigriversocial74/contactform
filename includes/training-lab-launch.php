<?php
/**
 * Stage 887 — signed Microgifter to Training Lab launch support.
 *
 * This service creates a short-lived HMAC-SHA256 identity assertion from the
 * authenticated server-side Microgifter user. It never accepts identity, role,
 * merchant context, passwords, or password hashes from browser input.
 */
declare(strict_types=1);

if (!function_exists('mg_env')) {
    require_once __DIR__ . '/app-core.php';
}

if (!function_exists('mg_training_lab_bool')) {
    function mg_training_lab_bool(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $filtered === null ? $default : $filtered;
    }
}

if (!function_exists('mg_training_lab_launch_config')) {
    function mg_training_lab_launch_config(): array
    {
        $section = is_array(mg_app_config()['training_lab'] ?? null)
            ? mg_app_config()['training_lab']
            : [];
        $ttl = (int) mg_env('TL_IDENTITY_LAUNCH_TTL', $section['launch_ttl_seconds'] ?? 120);
        $ttl = max(60, min(180, $ttl));

        return [
            'enabled' => mg_training_lab_bool(
                mg_env('TL_IDENTITY_LAUNCH_ENABLED', $section['launch_enabled'] ?? false),
                false
            ),
            'issuer' => trim((string) mg_env('TL_IDENTITY_ISSUER', $section['identity_issuer'] ?? 'microgifter.com')),
            'audience' => trim((string) mg_env('TL_IDENTITY_AUDIENCE', $section['identity_audience'] ?? 'training-lab')),
            'target_url' => trim((string) mg_env(
                'TL_IDENTITY_TARGET_URL',
                $section['target_url'] ?? 'https://labs.microgifter.com/account-link.php'
            )),
            'secret' => (string) mg_env('TL_IDENTITY_SHARED_SECRET', $section['identity_shared_secret'] ?? ''),
            'ttl' => $ttl,
        ];
    }
}

if (!function_exists('mg_training_lab_target_is_safe')) {
    function mg_training_lab_target_is_safe(string $target): bool
    {
        $parts = parse_url(trim($target));
        if (!is_array($parts)) {
            return false;
        }
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }
        if (trim((string) ($parts['host'] ?? '')) === '') {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }
        $path = (string) ($parts['path'] ?? '');
        return $path !== '' && str_ends_with($path, '/account-link.php');
    }
}

if (!function_exists('mg_training_lab_launch_ready')) {
    function mg_training_lab_launch_ready(): bool
    {
        $config = mg_training_lab_launch_config();
        return $config['enabled']
            && $config['issuer'] !== ''
            && $config['audience'] !== ''
            && strlen((string) $config['secret']) >= 32
            && mg_training_lab_target_is_safe((string) $config['target_url']);
    }
}

if (!function_exists('mg_training_lab_b64url_encode')) {
    function mg_training_lab_b64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('mg_training_lab_normalize_role')) {
    function mg_training_lab_normalize_role(array $user): string
    {
        $roles = array_values(array_filter(array_map(
            static fn(mixed $role): string => strtolower(trim((string) $role)),
            is_array($user['roles'] ?? null) ? $user['roles'] : []
        )));
        $permissions = array_values(array_filter(array_map(
            static fn(mixed $permission): string => strtolower(trim((string) $permission)),
            is_array($user['permissions'] ?? null) ? $user['permissions'] : []
        )));

        if (array_intersect($roles, ['super_admin', 'admin', 'platform_admin'])) {
            return 'admin';
        }
        if (array_intersect($roles, ['reviewer', 'proof_reviewer'])) {
            return 'reviewer';
        }
        if (array_intersect($roles, ['coach', 'trainer', 'mentor'])) {
            return 'coach';
        }
        if (
            array_intersect($roles, ['merchant', 'merchant_admin', 'merchant_owner', 'owner', 'operator'])
            || array_intersect($permissions, ['merchant.manage', 'agent.manage', 'admin.merchants.view'])
        ) {
            return 'manager';
        }
        return 'participant';
    }
}

if (!function_exists('mg_training_lab_server_context')) {
    function mg_training_lab_server_context(array $user): array
    {
        $context = [
            'merchant' => '',
            'organization' => '',
        ];
        $userId = (int) ($user['id'] ?? 0);
        if ($userId < 1 || !function_exists('mg_db')) {
            return $context;
        }

        try {
            $pdo = mg_db();
            $stmt = $pdo->prepare(
                'SELECT public_id, display_name FROM merchant_workspaces WHERE merchant_user_id = ? LIMIT 1'
            );
            $stmt->execute([$userId]);
            $workspace = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($workspace)) {
                $context['merchant'] = mb_substr(trim((string) ($workspace['public_id'] ?? '')), 0, 191);
                $context['organization'] = mb_substr(trim((string) ($workspace['display_name'] ?? '')), 0, 191);
            }
        } catch (Throwable) {
            // Merchant context is optional and must never block a valid account launch.
        }

        return $context;
    }
}

if (!function_exists('mg_training_lab_assertion_payload')) {
    function mg_training_lab_assertion_payload(
        array $user,
        ?int $now = null,
        ?string $nonce = null,
        ?string $tokenId = null,
        ?array $context = null
    ): array {
        $config = mg_training_lab_launch_config();
        $userId = (int) ($user['id'] ?? 0);
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($userId < 1) {
            throw new RuntimeException('Authenticated Microgifter user ID is required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Authenticated Microgifter email is invalid.');
        }
        if (!mg_training_lab_launch_ready()) {
            throw new RuntimeException('Training Lab signed launch is not configured.');
        }

        $now = $now ?? time();
        $nonce = trim((string) ($nonce ?? mg_training_lab_b64url_encode(random_bytes(24))));
        $tokenId = trim((string) ($tokenId ?? (function_exists('mg_public_uuid')
            ? mg_public_uuid()
            : mg_training_lab_b64url_encode(random_bytes(18)))));
        if (strlen($nonce) < 16 || strlen($nonce) > 191) {
            throw new RuntimeException('Training Lab launch nonce is invalid.');
        }
        if ($tokenId === '' || strlen($tokenId) > 191) {
            throw new RuntimeException('Training Lab launch token ID is invalid.');
        }

        $context = is_array($context) ? $context : mg_training_lab_server_context($user);
        $name = trim((string) ($user['display_name'] ?? $user['full_name'] ?? $email));

        return [
            'iss' => (string) $config['issuer'],
            'aud' => (string) $config['audience'],
            'sub' => (string) $userId,
            'email' => $email,
            'name' => mb_substr($name !== '' ? $name : $email, 0, 191),
            'role' => mg_training_lab_normalize_role($user),
            'merchant' => mb_substr(trim((string) ($context['merchant'] ?? '')), 0, 191),
            'organization' => mb_substr(trim((string) ($context['organization'] ?? '')), 0, 191),
            'iat' => $now,
            'exp' => $now + (int) $config['ttl'],
            'nonce' => $nonce,
            'jti' => $tokenId,
        ];
    }
}

if (!function_exists('mg_training_lab_build_assertion')) {
    function mg_training_lab_build_assertion(
        array $user,
        ?int $now = null,
        ?string $nonce = null,
        ?string $tokenId = null,
        ?array $context = null
    ): array {
        $config = mg_training_lab_launch_config();
        $header = ['alg' => 'HS256', 'typ' => 'TL-ID'];
        $payload = mg_training_lab_assertion_payload($user, $now, $nonce, $tokenId, $context);
        $encodedHeader = mg_training_lab_b64url_encode(json_encode($header, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $encodedPayload = mg_training_lab_b64url_encode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac(
            'sha256',
            $encodedHeader . '.' . $encodedPayload,
            (string) $config['secret'],
            true
        );

        return [
            'assertion' => $encodedHeader . '.' . $encodedPayload . '.' . mg_training_lab_b64url_encode($signature),
            'target_url' => (string) $config['target_url'],
            'payload' => $payload,
        ];
    }
}
