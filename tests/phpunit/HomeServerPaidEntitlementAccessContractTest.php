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

    public function testCapabilityRegistryAndPackageDeviceLimitsAreMachineReadable(): void
    {
        require_once $this->root . '/includes/homeserver-entitlements.php';

        $registry = mg_homeserver_capability_registry();
        foreach ([
            'homeserver.download',
            'homeserver.manage',
            'homeserver.pair',
            'homeserver.cloud_sync',
            'homeserver.operational_data',
            'homeserver.agent_actions',
            'homeserver.feature_updates',
            'homeserver.device_limit',
        ] as $capability) {
            self::assertContains($capability, $registry);
        }

        self::assertSame(1, mg_homeserver_package_policy('starter')['device_limit']);
        self::assertSame(1, mg_homeserver_package_policy('growth')['device_limit']);
        self::assertSame(2, mg_homeserver_package_policy('pro')['device_limit']);
        self::assertNull(mg_homeserver_package_policy('enterprise')['device_limit']);
        self::assertSame([], mg_homeserver_package_policy('free')['capabilities']);
        self::assertContains('homeserver.beta_updates', mg_homeserver_package_policy('pro')['capabilities']);
    }

    public function testPairingReleaseAndDownloadRoutesRequireHomeServerCapabilities(): void
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
        self::assertStringContainsString("'homeserver.download'", $latest);
        self::assertStringContainsString("'homeserver.download'", $download);
        self::assertStringContainsString('mg_homeserver_entitlement_payload', $devices);
    }

    public function testDirectManagementPageAndSubscriptionPanelUseCanonicalEntitlement(): void
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
        self::assertStringContainsString('/account-subscriptions.php?homeserver=upgrade', $management);
        self::assertStringContainsString('homeserver-subscription-card.php', $subscriptions);
        self::assertStringContainsString('HomeServer Management', $card);
        self::assertStringContainsString('Download HomeServer', $card);
        self::assertStringContainsString('Create Sync Code', $card);
        self::assertStringContainsString('id="create-sync-code"', $view);
    }

    public function testStatusIndicatorSupportsEntitlementAwareGrayBlueAmberGreenAndRedStates(): void
    {
        $script = file_get_contents($this->root . '/assets/js/homeserver-status-indicator.js');
        $styles = file_get_contents($this->root . '/assets/css/homeserver-status-indicator.css');

        self::assertIsString($script);
        self::assertIsString($styles);
        self::assertStringContainsString('HomeServer not included', $script);
        self::assertStringContainsString('Ready to install', $script);
        self::assertStringContainsString('Connected · update available', $script);
        self::assertStringContainsString('Subscription attention', $script);
        self::assertStringContainsString('entitlement.can_download', $script);
        self::assertStringContainsString('.is-ready .mg-homeserver-status-dot', $styles);
        self::assertStringContainsString('.is-warning .mg-homeserver-status-dot', $styles);
        self::assertStringContainsString('.is-blocked .mg-homeserver-status-dot', $styles);
        self::assertStringContainsString('.is-online .mg-homeserver-status-dot', $styles);
        self::assertStringContainsString('.is-muted .mg-homeserver-status-dot', $styles);
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
