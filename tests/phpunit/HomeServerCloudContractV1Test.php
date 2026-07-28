<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HomeServerCloudContractV1Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function contractSource(): string
    {
        $source = '';
        foreach (['_contract-foundation.php','_contract-entitlement.php','_contract-auth.php','_contract-pairing.php','_contract-runtime.php','_contract-replacement.php'] as $file) {
            $content = file_get_contents($this->root . '/api/homeserver/v1/' . $file);
            self::assertIsString($content);
            $source .= $content;
        }
        return $source;
    }

    public function testProviderContractFilesExist(): void
    {
        foreach ([
            '/database/20260728_homeserver_pairing_entitlement_update_contract_v1.sql',
            '/includes/homeserver-device-identity.php',
            '/api/homeserver/v1/.htaccess',
            '/api/homeserver/v1/_contract.php',
            '/api/homeserver/v1/_contract-core.php',
            '/api/homeserver/v1/_contract-foundation.php',
            '/api/homeserver/v1/_contract-entitlement.php',
            '/api/homeserver/v1/_contract-auth.php',
            '/api/homeserver/v1/_contract-pairing.php',
            '/api/homeserver/v1/_contract-runtime.php',
            '/api/homeserver/v1/_contract-replacement.php',
            '/api/homeserver/v1/pairing-exchange.php',
            '/api/homeserver/v1/entitlement-refresh.php',
            '/api/homeserver/v1/device-heartbeat.php',
            '/api/homeserver/v1/credential-rotate.php',
            '/api/homeserver/v1/update-authorize.php',
            '/api/homeserver/v1/update-receipts.php',
            '/api/homeserver/v1/replacement-start.php',
            '/api/homeserver/v1/replacement-complete.php',
            '/docs/homeserver/microgifter-cloud-contract-v1-provider.md',
        ] as $path) {
            self::assertFileExists($this->root . $path, $path);
        }
    }

    public function testExtensionlessRoutesMatchHomeServerPhase6AClient(): void
    {
        $rewrite = file_get_contents($this->root . '/api/homeserver/v1/.htaccess');
        self::assertIsString($rewrite);
        foreach ([
            'pairing/exchange',
            'entitlements/refresh',
            'devices/heartbeat',
            'devices/credentials/rotate',
            'updates/authorize',
            'updates/receipts',
            'devices/replacements/start',
            'devices/replacements/complete',
        ] as $route) {
            self::assertStringContainsString($route, $rewrite);
        }
    }

    public function testMigrationIsAdditiveAndContainsRequiredLedgers(): void
    {
        $sql = file_get_contents($this->root . '/database/20260728_homeserver_pairing_entitlement_update_contract_v1.sql');
        self::assertIsString($sql);
        foreach ([
            'CREATE TABLE IF NOT EXISTS homeserver_provider_connections',
            'CREATE TABLE IF NOT EXISTS homeserver_pairing_exchanges_v1',
            'CREATE TABLE IF NOT EXISTS homeserver_device_credentials',
            'CREATE TABLE IF NOT EXISTS homeserver_credential_rotations',
            'CREATE TABLE IF NOT EXISTS homeserver_entitlement_leases_v1',
            'CREATE TABLE IF NOT EXISTS homeserver_update_authorizations_v1',
            'CREATE TABLE IF NOT EXISTS homeserver_update_receipts_v1',
            'CREATE TABLE IF NOT EXISTS homeserver_device_replacements_v1',
            'CREATE TABLE IF NOT EXISTS homeserver_connection_receipts_v1',
        ] as $marker) {
            self::assertStringContainsString($marker, $sql);
        }
        self::assertStringNotContainsString('DROP TABLE', strtoupper($sql));
        self::assertStringNotContainsString('TRUNCATE ', strtoupper($sql));
        self::assertStringContainsString('DROP INDEX uq_homeserver_devices_installation', $sql);
        self::assertStringContainsString('idx_homeserver_devices_installation', $sql);
        $manifest = require $this->root . '/config/migrations.php';
        self::assertContains('20260728_homeserver_pairing_entitlement_update_contract_v1.sql', $manifest['ordered_files']);
    }

    public function testContractRequiresSignedRequestsAndSignedLeases(): void
    {
        $helper = $this->contractSource();
        self::assertStringContainsString('MG_HOMESERVER_ENTITLEMENT_SIGNING_SEED', $helper);
        self::assertStringContainsString('sodium_crypto_sign_detached', $helper);
        self::assertStringContainsString('sodium_crypto_sign_verify_detached', $helper);
        self::assertStringContainsString('sodium_crypto_secretbox', $helper);
        self::assertStringContainsString('X-MG-Provider-Connection-ID', $helper);
        self::assertStringContainsString('X-MG-Contract-Version', $helper);
        self::assertStringContainsString('microgifter_request_replay', $helper);
        self::assertStringContainsString('entitlement_signing_key', $helper);
    }

    public function testCapabilityAndLifecycleRegistriesMatchClientContract(): void
    {
        $helper = $this->contractSource();
        foreach ([
            'pairing.v1',
            'device-registration.v1',
            'device-heartbeat.v1',
            'entitlement-lease.v1',
            'credential-rotation.v1',
            'merchant-assignments.v1',
            'site-assignments.v1',
            'dataset-grants.v1',
            'sync.incremental.v1',
            'operational-data.v1',
            'campaign-actions.v1',
            'signed-updates.v1',
            'update-authorization.v1',
            'update-receipts.v1',
            'device-replacement.v1',
        ] as $capability) {
            self::assertStringContainsString("'{$capability}'", $helper);
        }
        foreach (['active', 'grace', 'suspended', 'canceled', 'revoked', 'replacing', 'error'] as $state) {
            self::assertStringContainsString($state, $helper);
        }
    }

    public function testPhysicalDeviceLimitsAllowMultipleIsolatedConnections(): void
    {
        $identity = file_get_contents($this->root . '/includes/homeserver-device-identity.php');
        $pairing = file_get_contents($this->root . '/api/homeserver/v1/_contract-pairing.php');
        $syncCode = file_get_contents($this->root . '/api/homeserver/pairing-code.php');
        self::assertIsString($identity);
        self::assertIsString($pairing);
        self::assertIsString($syncCode);
        self::assertStringContainsString('COUNT(DISTINCT installation_id)', $identity);
        self::assertStringContainsString('multiple isolated Microgifter connections', $pairing);
        self::assertStringContainsString('new_physical_device_slot_available', $syncCode);
        self::assertStringContainsString('physical HomeServer device allowance', $pairing);
    }

    public function testUpdateReceiptShapeMatchesPhase6AClient(): void
    {
        $helper = $this->contractSource();
        foreach (['authorization_id', 'update_id', 'version', 'result_state', 'failure_code'] as $field) {
            self::assertStringContainsString("'{$field}'", $helper);
        }
    }

    public function testPrivateLocalContentIsNotAcceptedByHeartbeat(): void
    {
        $helper = $this->contractSource();
        foreach (['connection_state', 'homeserver_version', 'update_channel', 'health_category'] as $allowed) {
            self::assertStringContainsString("'{$allowed}'", $helper);
        }
        foreach (['knowledge_vault_contents', 'prompt_contents', 'conversation_contents', 'backup_contents'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $helper);
        }
    }
}
