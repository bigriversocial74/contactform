<?php
declare(strict_types=1);

interface MgMerchantIntegrationProvider
{
    public function key(): string;
    public function label(): string;
    public function description(): string;
    public function authType(): string;
    public function capabilities(): array;
    public function isConfigured(): bool;
    public function configurationStatus(): array;
}

interface MgMerchantIntegrationOAuthProvider extends MgMerchantIntegrationProvider
{
    public function scopes(): array;
    public function redirectUri(): string;
    public function buildAuthorizationUrl(string $state, ?string $externalAccountHint = null): string;
    public function exchangeAuthorizationCode(string $code): array;
    public function refreshAccessToken(string $refreshToken): array;
    public function fetchExternalAccount(string $accessToken): array;
}
