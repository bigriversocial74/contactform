<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpPhase1ProvisioningConsoleV1ContractTest extends TestCase
{
    private string $root;
    private array $release;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->release = require $this->root . '/config/mcp_phase1_provisioning_console_release.php';
    }

    public function testReleaseDependsOnCompletedPhaseOneLayersAndAddsNoSql(): void
    {
        self::assertSame('microgifter_mcp_phase1_provisioning_console_v1', $this->release['release_key']);
        self::assertSame([], $this->release['required_migrations']);
        self::assertSame('20260720_microgifter_mcp_automation_foundation_v1.sql', $this->release['foundation_migration']);
        self::assertContains('microgifter_mcp_phase1_canonical_bridge_v1', $this->release['depends_on']);
    }

    public function testAdminSurfaceAndApisArePermissionProtected(): void
    {
        $page = (string)file_get_contents($this->root . '/admin/mcp-connections.php');
        $helper = (string)file_get_contents($this->root . '/api/admin/_mcp_connections.php');
        $permissions = (string)file_get_contents($this->root . '/includes/admin-permission-matrix.php');
        $sidebar = (string)file_get_contents($this->root . '/includes/admin-sidebar.php');

        self::assertStringContainsString("mg_require_admin_page_key('admin.mcp_connections')", $page);
        self::assertStringContainsString("mg_require_permission('admin.settings.manage')", $helper);
        self::assertStringContainsString("'admin.mcp_connections'", $permissions);
        self::assertStringContainsString("'mcp-connections'", $sidebar);
    }

    public function testEveryMutationRequiresCsrfAndRateLimiting(): void
    {
        foreach ([
            'api/admin/mcp-connection-create.php',
            'api/admin/mcp-connection-action.php',
            'api/admin/mcp-runtime-credentials.php',
        ] as $path) {
            $source = (string)file_get_contents($this->root . '/' . $path);
            self::assertStringContainsString('mg_require_csrf_for_write($input)', $source, $path);
            self::assertStringContainsString('mg_rate_limit(', $source, $path);
        }
    }

    public function testProvisioningEnforcesUserWorkspaceScopeAndReadOnlyBoundaries(): void
    {
        $helper = (string)file_get_contents($this->root . '/api/admin/_mcp_connections.php');

        self::assertStringContainsString("(string)$user['status'] !== 'active'", $helper);
        self::assertStringContainsString('merchant_team_members', $helper);
        self::assertStringContainsString("active=1 AND grantable=1 AND operation_class='read'", $helper);
        self::assertStringContainsString("'active','read',1,NOW()", $helper);
        self::assertStringContainsString("maximum_operation_class,metadata_json", $helper);
    }

    public function testRuntimeSecretsAreOneTimeAndNeverPersisted(): void
    {
        $helper = (string)file_get_contents($this->root . '/api/admin/_mcp_connections.php');
        $endpoint = (string)file_get_contents($this->root . '/api/admin/mcp-runtime-credentials.php');
        $javascript = (string)file_get_contents($this->root . '/assets/js/admin-mcp-connections.js');

        self::assertStringContainsString('random_bytes(32)', $helper);
        self::assertStringContainsString("hash('sha256', $bearerToken)", $helper);
        self::assertStringContainsString('random_bytes(48)', $helper);
        self::assertStringContainsString("'secrets_persisted' => false", $helper);
        self::assertDoesNotMatchRegularExpression('/(?:INSERT|UPDATE)[^;]{0,400}(?:bearer_token|bridge_secret)/is', $helper);
        self::assertStringContainsString("header('Cache-Control: private, no-store, max-age=0')", $endpoint);
        self::assertStringNotContainsString('localStorage', $javascript);
        self::assertStringNotContainsString('sessionStorage', $javascript);
    }

    public function testConnectionLifecycleActionsRemainExplicitAndAudited(): void
    {
        $helper = (string)file_get_contents($this->root . '/api/admin/_mcp_connections.php');

        foreach (['pause', 'resume', 'revoke', 'rotate_token', 'grant_scope', 'revoke_scope'] as $action) {
            self::assertStringContainsString("'{$action}'", $helper);
        }
        self::assertStringContainsString("mg_audit('admin_mcp_connection_action'", $helper);
        self::assertStringContainsString("mg_event('admin.mcp.connection.action'", $helper);
        self::assertStringContainsString("mg_security_log('info', 'admin.mcp_connection.action'", $helper);
    }
}
