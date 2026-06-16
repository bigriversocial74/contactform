<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ControlPlaneContractTest extends TestCase
{
    public function testControlPlaneAuthorityDefinesRequiredContracts(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/admin/_controls.php');
        self::assertIsString($source);
        self::assertStringContainsString('control_action_events',$source);
        self::assertStringContainsString('control_resource_states',$source);
        self::assertStringContainsString('mg_control_has(',$source);
        self::assertStringContainsString('mg_control_apply(',$source);
        self::assertStringContainsString('idempotency_key',$source);
        self::assertStringContainsString('fingerprint',$source);
        self::assertStringContainsString('mg_audit(',$source);
        self::assertStringContainsString('failureHook',$source);
    }
}
