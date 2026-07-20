<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpProductionVpsDeploymentV1ContractTest extends TestCase
{
    private string $root;
    private array $release;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->release = require $this->root . '/config/mcp_production_vps_deployment_release.php';
    }

    public function testReleaseDependsOnProvisionedPhaseOneAndAddsNoSql(): void
    {
        self::assertSame('microgifter_mcp_production_vps_deployment_v1', $this->release['release_key']);
        self::assertSame([], $this->release['required_migrations']);
        self::assertContains('microgifter_mcp_phase1_provisioning_console_v1', $this->release['depends_on']);
        self::assertSame('>=20', $this->release['node_engine']);
    }

    public function testRuntimeProvidesHealthReadinessAndGracefulDrain(): void
    {
        $app = (string)file_get_contents($this->root . '/services/mcp/src/http/app.ts');
        $server = (string)file_get_contents($this->root . '/services/mcp/src/server.ts');

        self::assertStringContainsString('app.get("/health"', $app);
        self::assertStringContainsString('app.get("/ready"', $app);
        self::assertStringContainsString('bridge.resolveConnection', $app);
        self::assertStringContainsString('runtime.draining', $app);
        self::assertStringContainsString('runtime.waitForIdle', $server);
        self::assertStringContainsString('process.once("SIGTERM"', $server);
        self::assertStringContainsString('server.closeAllConnections', $server);
    }

    public function testProductionEnvironmentFailsClosed(): void
    {
        $config = (string)file_get_contents($this->root . '/services/mcp/src/protocolConfig.ts');

        self::assertStringContainsString('runtime.environment === "production"', $config);
        self::assertStringContainsString('Production MCP requires MICROGIFTER_MCP_PUBLIC_BASE_URL.', $config);
        self::assertStringContainsString('Production MCP requires at least one explicit allowed Host value.', $config);
        self::assertStringContainsString('bind outside loopback', $config);
    }

    public function testSystemdDockerAndNginxKeepTheApplicationIsolated(): void
    {
        $systemd = (string)file_get_contents($this->root . '/deploy/vps/systemd/microgifter-mcp.service');
        $compose = (string)file_get_contents($this->root . '/deploy/vps/docker-compose.mcp.yml');
        $nginx = (string)file_get_contents($this->root . '/deploy/vps/nginx/mcp.microgifter.com.conf.template');
        $dockerfile = (string)file_get_contents($this->root . '/services/mcp/Dockerfile');

        self::assertStringContainsString('User=microgifter-mcp', $systemd);
        self::assertStringContainsString('NoNewPrivileges=true', $systemd);
        self::assertStringContainsString('ProtectSystem=strict', $systemd);
        self::assertStringContainsString('127.0.0.1:8787:8787', $compose);
        self::assertStringContainsString('read_only: true', $compose);
        self::assertStringContainsString('server 127.0.0.1:8787', $nginx);
        self::assertStringContainsString('proxy_buffering off', $nginx);
        self::assertStringContainsString('USER node', $dockerfile);
    }

    public function testDeploymentScriptsProtectSecretsAndRollbackFailures(): void
    {
        $installer = (string)file_get_contents($this->root . '/deploy/vps/scripts/install-systemd.sh');
        $activation = (string)file_get_contents($this->root . '/deploy/vps/scripts/activate-systemd.sh');
        $smoke = (string)file_get_contents($this->root . '/services/mcp/scripts/smoke-test.mjs');

        self::assertStringContainsString('chmod 0600 "$ENV_FILE"', $installer);
        self::assertStringContainsString('PREVIOUS_TARGET=', $installer);
        self::assertStringContainsString('previous release restored', $installer);
        self::assertStringContainsString('validateEnvironment.js', $activation);
        self::assertStringContainsString('bearer_token_emitted: false', $smoke);
        self::assertStringNotContainsString('console.log(bearerToken)', $smoke);
    }
}
