<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpExternalAgentAuthorizationPhase2aV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testReleaseManifestDeclaresOAuthBoundaryAndMigration(): void
    {
        $release = require $this->root . '/config/mcp_external_agent_authorization_phase2a_release.php';
        self::assertSame('microgifter_mcp_external_agent_authorization_phase2a_v1', $release['release_key']);
        self::assertContains('20260720_mcp_external_agent_authorization_phase2a_v1', $release['required_migrations']);
        self::assertSame('https://mcp.microgifter.com/mcp', $release['resource_uri']);
        self::assertContains('pkce_s256', $release['capabilities']);
        self::assertContains('refresh_reuse_family_revocation', $release['capabilities']);
        self::assertContains('raw_tokens_never_persisted', $release['security']);
        self::assertContains('read_only_tools_only', $release['boundaries']);
    }

    public function testCanonicalMigrationOrderIncludesPhase2AAfterFoundation(): void
    {
        $manifest = require $this->root . '/config/migrations.php';
        $foundation = array_search('20260720_microgifter_mcp_automation_foundation_v1.sql', $manifest['ordered_files'], true);
        $phase2a = array_search('20260720_mcp_external_agent_authorization_phase2a_v1.sql', $manifest['ordered_files'], true);
        self::assertIsInt($foundation);
        self::assertIsInt($phase2a);
        self::assertSame($foundation + 1, $phase2a);
    }

    public function testOAuthCredentialsAreHashOnlyAtRest(): void
    {
        $sql = file_get_contents($this->root . '/database/20260720_mcp_external_agent_authorization_phase2a_v1.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('code_hash CHAR(64)', $sql);
        self::assertStringContainsString('token_hash CHAR(64)', $sql);
        self::assertStringNotContainsString('access_token VARCHAR', $sql);
        self::assertStringNotContainsString('refresh_token VARCHAR', $sql);
        self::assertStringNotContainsString('client_secret', $sql);
    }

    public function testDiscoveryAndDynamicResolutionAreWired(): void
    {
        $app = file_get_contents($this->root . '/services/mcp/src/http/app.ts');
        $server = file_get_contents($this->root . '/services/mcp/src/server.ts');
        $bridge = file_get_contents($this->root . '/api/internal/_mcp_oauth_bridge.php');
        self::assertStringContainsString('/.well-known/oauth-protected-resource', (string)$app);
        self::assertStringContainsString('HttpOAuthTokenResolver', (string)$server);
        self::assertStringContainsString('operation: "oauth.token.resolve"', (string)file_get_contents($this->root . '/services/mcp/src/bridge/oauthBridge.ts'));
        self::assertStringContainsString('array_intersect', (string)$bridge);
        self::assertTrue(
            str_contains((string)$bridge, 'mg_mcp_bridge_connection')
            || str_contains((string)$bridge, 'mg_mcp_draft_bridge_connection'),
            'OAuth access tokens must resolve through a canonical live connection authority.'
        );
    }

    public function testUserConsentAndRevocationStayProtected(): void
    {
        $authorize = file_get_contents($this->root . '/oauth/authorize.php');
        $account = file_get_contents($this->root . '/account-ai-connections.php');
        self::assertStringContainsString('mg_require_auth', (string)$authorize);
        self::assertStringContainsString('mg_verify_csrf', (string)$authorize);
        self::assertStringContainsString('mg_verify_csrf', (string)$account);
        self::assertStringContainsString('mg_mcp_oauth_revoke_user_connection', (string)$account);
    }
}
