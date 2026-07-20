<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpPhase1ProtocolV1ContractTest extends TestCase
{
    private string $root;
    private array $release;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->release = require $this->root . '/config/mcp_phase1_protocol_release.php';
    }

    public function testProtocolUsesStableStatelessStreamableHttp(): void
    {
        self::assertSame('2025-11-25', $this->release['protocol']['revision']);
        self::assertSame('streamable_http', $this->release['protocol']['transport']);
        self::assertSame('stateless', $this->release['protocol']['mode']);
        self::assertSame('/mcp', $this->release['protocol']['endpoint']);
        self::assertSame('1.29.0', $this->release['protocol']['sdk_version']);
    }

    public function testProtocolSectionRequiresNoSqlAndRemainsInternalOnly(): void
    {
        self::assertSame([], $this->release['required_migrations']);
        self::assertFalse($this->release['runtime']['enabled_by_default']);
        self::assertFalse($this->release['runtime']['internal_http_enabled_by_default']);
        self::assertFalse($this->release['runtime']['external_http_enabled']);
        self::assertFalse($this->release['runtime']['oauth_enabled']);
        self::assertContains('internal_development_only', $this->release['boundaries']);
        self::assertContains('no_public_dns_or_external_oauth', $this->release['boundaries']);
    }

    public function testInternalAuthenticationIsHashOnlyAndReadOnly(): void
    {
        $auth = $this->release['internal_authentication'];
        self::assertSame('bearer', $auth['scheme']);
        self::assertSame('sha256_hash_only', $auth['stored_form']);
        self::assertTrue($auth['constant_time_comparison']);
        self::assertTrue($auth['explicit_scopes_required']);
        self::assertSame('read', $auth['maximum_operation_class']);
    }

    public function testInitialToolsAreOrderedAndCatalogToolsRemainBridgeDisabled(): void
    {
        self::assertSame([
            'microgifter.account.get_connection_context',
            'microgifter.catalog.search',
            'microgifter.catalog.get_item',
        ], array_keys($this->release['initial_tools']));
        self::assertSame('internal_active', $this->release['initial_tools']['microgifter.account.get_connection_context']['status']);
        self::assertSame('listed_bridge_disabled', $this->release['initial_tools']['microgifter.catalog.search']['status']);
        self::assertSame('listed_bridge_disabled', $this->release['initial_tools']['microgifter.catalog.get_item']['status']);
    }

    public function testSourceUsesOfficialTransportAndContainsNoDatabaseAccess(): void
    {
        $http = (string)file_get_contents($this->root . '/services/mcp/src/http/app.ts');
        $registry = (string)file_get_contents($this->root . '/services/mcp/src/tools/registry.ts');
        $auth = (string)file_get_contents($this->root . '/services/mcp/src/auth/internalToken.ts');

        self::assertStringContainsString('StreamableHTTPServerTransport', $http);
        self::assertStringContainsString('sessionIdGenerator: undefined', $http);
        self::assertStringContainsString('timingSafeEqual', $auth);
        self::assertDoesNotMatchRegularExpression('/(?:mysql|PDO|SELECT\s+|INSERT\s+|UPDATE\s+|DELETE\s+FROM)/i', $registry);
    }
}
