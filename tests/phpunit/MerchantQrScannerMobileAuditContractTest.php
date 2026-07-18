<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantQrScannerMobileAuditContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testScannerUsesFrontCameraWithSafeFallback(): void
    {
        $js = file_get_contents($this->root . '/assets/js/merchant-scanner-cleanup.js');
        self::assertStringContainsString("facingMode:{ exact:'user' }", $js);
        self::assertStringContainsString("facingMode:{ ideal:'user' }", $js);
        self::assertStringContainsString('track.getSettings()', $js);
        self::assertStringContainsString("formats:['qr_code']", $js);
    }

    public function testScannerLoadsAndDisplaysMerchantSettings(): void
    {
        $js = file_get_contents($this->root . '/assets/js/merchant-scanner-cleanup.js');
        self::assertStringContainsString("Microgifter.get('/api/merchant/scanner-settings.php')", $js);
        self::assertStringContainsString('data-scanner-active-settings', $js);
        self::assertStringContainsString('settings.require_confirmation', $js);
        self::assertStringContainsString('settings.lock_scanner_to_location', $js);
        self::assertStringContainsString('settings.allow_manual_entry', $js);
    }

    public function testCameraScansUseOperationsEndpointAndSourceMetadata(): void
    {
        $js = file_get_contents($this->root . '/assets/js/merchant-scanner-cleanup.js');
        self::assertStringContainsString('/api/merchant/scanner-claim-ops.php', $js);
        self::assertStringContainsString("scan_source: 'camera'", $js);
        self::assertStringContainsString('MicrogifterMerchantScannerRuntime', $js);
    }

    public function testMobileWorkspaceGetsPrimaryScannerShortcut(): void
    {
        $js = file_get_contents($this->root . '/assets/js/merchant-scanner-cleanup.js');
        self::assertStringContainsString('data-scanner-mobile-primary', $js);
        self::assertStringContainsString('Scan QR Code', $js);
        self::assertStringContainsString('ensureMobileShortcut', $js);
    }

    public function testPermissionAndCameraFailuresAreActionable(): void
    {
        $js = file_get_contents($this->root . '/assets/js/merchant-scanner-cleanup.js');
        self::assertStringContainsString('NotAllowedError', $js);
        self::assertStringContainsString('NotFoundError', $js);
        self::assertStringContainsString('OverconstrainedError', $js);
        self::assertStringContainsString('Allow camera access in browser settings', $js);
    }
}
