<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HomeServerPrimaryUpgradeAuthorityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testUpgradeAuthorityFilesExist(): void
    {
        foreach ([
            '/database/20260803_homeserver_upgrade_control_v2.sql',
            '/includes/homeserver-upgrades.php',
            '/api/admin/homeserver-upgrades.php',
            '/api/admin/homeserver-upgrade-signing-payload.php',
            '/api/homeserver/update-manifest-stable.php',
            '/api/homeserver/update-download.php',
            '/admin/homeserver-upgrades.php',
            '/assets/js/admin-homeserver-upgrades.js',
            '/docs/homeserver/microgifter-primary-upgrade-authority-v2.md',
        ] as $path) {
            self::assertFileExists($this->root . $path, $path);
        }
    }

    public function testMigrationIsAdditiveAndRegistered(): void
    {
        $sql = file_get_contents($this->root . '/database/20260803_homeserver_upgrade_control_v2.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS homeserver_release_controls_v2', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS homeserver_release_control_events_v2', $sql);
        self::assertStringContainsString('rollout_percentage', $sql);
        self::assertStringContainsString('manifest_signature', $sql);
        self::assertStringContainsString('manifest_payload_sha256', $sql);
        self::assertStringContainsString('authenticode_thumbprint', $sql);
        self::assertStringContainsString('rollback_release_id', $sql);
        self::assertStringNotContainsString('DROP TABLE', strtoupper($sql));

        $manifest = file_get_contents($this->root . '/config/migrations.php');
        self::assertIsString($manifest);
        self::assertStringContainsString("'20260803_homeserver_upgrade_control_v2.sql'", $manifest);
    }

    public function testManifestMatchesNativeUpdaterContract(): void
    {
        $helper = file_get_contents($this->root . '/includes/homeserver-upgrades.php');
        self::assertIsString($helper);
        foreach ([
            "'schema_version' => MG_HOMESERVER_UPGRADE_MANIFEST_SCHEMA_VERSION",
            "'product' => 'Microgifter HomeServer'",
            "'channel' => 'stable'",
            "'version' => \$version",
            "'minimum_version'",
            "'published_at_utc'",
            "'release_notes'",
            "'file_name' => 'Microgifter-HomeServer-Setup.exe'",
            "'size_bytes'",
            "'sha256'",
            "'authenticode_thumbprint'",
            "'key_id'",
            "'payload'",
            "'signature'",
        ] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        self::assertStringContainsString('JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR', $helper);
        self::assertStringContainsString('sodium_crypto_sign_verify_detached', $helper);
        self::assertStringContainsString('manifest payload changed after signing', $helper);
    }

    public function testPrivateSigningKeyNeverEntersApplicationStorage(): void
    {
        $helper = file_get_contents($this->root . '/includes/homeserver-upgrades.php');
        $migration = file_get_contents($this->root . '/database/20260803_homeserver_upgrade_control_v2.sql');
        $admin = file_get_contents($this->root . '/api/admin/homeserver-upgrades.php');
        self::assertIsString($helper);
        self::assertIsString($migration);
        self::assertIsString($admin);
        self::assertStringContainsString('MG_HOMESERVER_RELEASE_PUBLIC_KEY_BASE64', $helper);
        self::assertStringNotContainsString('PRIVATE_KEY', $helper);
        self::assertStringNotContainsString('private_key', $migration);
        self::assertStringNotContainsString('secret_key', $migration);
        self::assertStringContainsString('mg_homeserver_upgrade_verify_signature', $admin);
        self::assertStringContainsString('The Ed25519 manifest signature does not verify', $admin);
    }

    public function testUpdaterDeliveryFailsClosed(): void
    {
        $manifest = file_get_contents($this->root . '/api/homeserver/update-manifest-stable.php');
        $download = file_get_contents($this->root . '/api/homeserver/update-download.php');
        self::assertIsString($manifest);
        self::assertIsString($download);
        self::assertStringContainsString('mg_homeserver_require_secure_transport()', $manifest);
        self::assertStringContainsString('mg_homeserver_upgrade_manifest_candidate', $manifest);
        self::assertStringContainsString('mg_homeserver_upgrade_manifest(', $manifest);
        self::assertStringContainsString('mg_homeserver_upgrade_manifest(', $download);
        self::assertStringContainsString("hash_file('sha256'", $download);
        self::assertStringContainsString("control_state'] ?? '') !== 'active'", $download);
        self::assertStringContainsString("!empty(\$bundle['revoked_at'])", $download);
        self::assertStringContainsString('Content-Disposition: attachment; filename="Microgifter-HomeServer-Setup.exe"', $download);
    }

    public function testAdminControlCoversRolloutRevocationRollbackAndReceipts(): void
    {
        $api = file_get_contents($this->root . '/api/admin/homeserver-upgrades.php');
        $page = file_get_contents($this->root . '/admin/homeserver-upgrades.php');
        $script = file_get_contents($this->root . '/assets/js/admin-homeserver-upgrades.js');
        self::assertIsString($api);
        self::assertIsString($page);
        self::assertIsString($script);
        self::assertStringContainsString("mg_require_permission('admin.settings.manage')", $api);
        self::assertStringContainsString('mg_require_csrf_for_write', $api);
        self::assertStringContainsString("['pause','resume','set_rollout','revoke']", $api);
        self::assertStringContainsString("\$action === 'activate_rollback'", $api);
        self::assertStringContainsString('homeserver_update_receipts_v1', $api);
        self::assertStringContainsString('Generate signing payload', $script);
        self::assertStringContainsString('HomeServer signed update control', $page);
        self::assertStringContainsString('Existing updater retained', $page);
    }
}
