<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HomeServerPaidEntitlementAccessContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testEntitlementFoundationFilesExist(): void
    {
        foreach ([
            '/includes/homeserver-entitlements.php',
            '/includes/account/homeserver-subscription-card.php',
            '/assets/css/homeserver-subscription-card.css',
        ] as $path) {
            self::assertFileExists($this->root . $path, $path);
        }
    }

    public function testCapabilityRegistryIsProviderSpecificAndVp3OwnsSoftwareAuthority(): void
    {
        require_once $this->root . '/includes/homeserver-entitlements.php';

        self::assertSame('vp3', mg_homeserver_software_authority());
        $registry = mg_homeserver_capability_registry();
        foreach ([
            'homeserver.manage',
            'homeserver.pair',
            'homeserver.cloud_sync',
            'homeserver.operational_data',
            'homeserver.agent_actions',
            'homeserver.device_limit',
        ] as $capability) {
            self::assertContains($capability, $registry);
        }
        foreach ([
            'homeserver.download',
            'homeserver.feature_updates',
            'homeserver.beta_updates',
        ] as $softwareCapability) {
            self::assertNotContains($softwareCapability, $registry);
        }

        self::assertSame(1, mg_homeserver_package_policy('starter')['device_limit']);
        self::assertSame(1, mg_homeserver_package_policy('growth')['device_limit']);
        self::assertSame(2, mg_homeserver_package_policy('pro')['device_limit']);
        self::assertNull(mg_homeserver_package_policy('enterprise')['device_limit']);
        self::assertSame([], mg_homeserver_package_policy('free')['capabilities']);
        self::assertNotContains('homeserver.beta_updates', mg_homeserver_package_policy('pro')['capabilities']);
    }

    public function testMicrogifterPairingRemainsWhileReleaseAndDownloadDelegateToVp3(): void
    {
        $pairing = file_get_contents($this->root . '/api/homeserver/pairing-code.php');
        $latest = file_get_contents($this->root . '/api/homeserver/latest-release.php');
        $download = file_get_contents($this->root . '/api/homeserver/download.php');
        $devices = file_get_contents($this->root . '/api/homeserver/devices.php');

        self::assertIsString($pairing);
        self::assertIsString($latest);
        self::assertIsString($download);
        self::assertIsString($devices);
        self::assertStringContainsString("'homeserver.pair'", $pairing);
        self::assertStringContainsString('mg_homeserver_active_device_count', $pairing);
        self::assertStringContainsString('device allowance', $pairing);
        self::assertStringContainsString("'software_authority' => 'vp3'", $latest);
        self::assertStringContainsString("'installer_authority' => 'vp3'", $latest);
        self::assertStringContainsString('vp3_installer_authority', $download);
        self::assertStringNotContainsString("'homeserver.download'", $latest);
        self::assertStringNotContainsString("'homeserver.download'", $download);
        self::assertStringContainsString('mg_homeserver_entitlement_payload', $devices);
    }

    public function testDirectManagementPageAndSubscriptionPanelExplainSeparatedAuthority(): void
    {
        $management = file_get_contents($this->root . '/account-homeserver.php');
        $subscriptions = file_get_contents($this->root . '/account-subscriptions.php');
        $card = file_get_contents($this->root . '/includes/account/homeserver-subscription-card.php');
        $view = file_get_contents($this->root . '/includes/account/homeserver-view.php');

        self::assertIsString($management);
        self::assertIsString($subscriptions);
        self::assertIsString($card);
        self::assertIsString($view);
        self::assertStringContainsString("'homeserver.manage'", $management);
        self::assertStringContainsString('homeserver-subscription-card.php', $subscriptions);
        self::assertStringContainsString('Microgifter HomeServer Connection', $card);
        self::assertStringContainsString('Software authority', $card);
        self::assertStringContainsString('VP3', $card);
        self::assertStringContainsString('Create Sync Code', $card);
        self::assertStringContainsString('id="create-sync-code"', $view);
        self::assertStringContainsString('HomeServer license</dt><dd>VP3', $view);
        self::assertStringContainsString('Microgifter remains authoritative for Microgifter data and actions.', $view);
    }

    public function testMicrogifterLeaseAndUpdateAuthorizationCannotBecomeSoftwareAuthority(): void
    {
        $entitlement = file_get_contents($this->root . '/api/homeserver/v1/_contract-entitlement.php');
        $authorization = file_get_contents($this->root . '/api/homeserver/v1/update-authorize.php');

        self::assertIsString($entitlement);
        self::assertIsString($authorization);
        self::assertStringContainsString("'software_authority' => 'vp3'", $entitlement);
        self::assertStringContainsString("'update_eligibility' => false", $entitlement);
        self::assertStringContainsString("'allowed_update_channels' => \$channels", $entitlement);
        self::assertStringNotContainsString("'update-authorization.v1',", $entitlement);
        self::assertStringContainsString("\$reason = 'vp3_software_authority'", $authorization);
        self::assertStringContainsString("'decision' => 'not_required'", $authorization);
        self::assertStringNotContainsString('can_feature_updates', $authorization);
        self::assertStringNotContainsString('can_beta_updates', $authorization);
    }

    public function testNoPairingOrDeviceSchemaRewriteIsIntroduced(): void
    {
        $entitlements = file_get_contents($this->root . '/includes/homeserver-entitlements.php');
        self::assertIsString($entitlements);
        self::assertStringNotContainsString('UPDATE homeserver_devices', $entitlements);
        self::assertStringNotContainsString('DELETE FROM homeserver_devices', $entitlements);
        self::assertStringNotContainsString('ALTER TABLE homeserver_devices', $entitlements);
    }
}
