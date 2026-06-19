<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LifecycleHealthContractTest extends TestCase
{
    public function testHealthScannerAndRepairsUseCanonicalServices(): void
    {
        $root=dirname(__DIR__,2);
        $source=file_get_contents($root.'/api/admin/_golden_path_health.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg_payment_issue_order_pppm',$source);
        self::assertStringContainsString('mg_payment_issue_order_microgifts',$source);
        self::assertStringContainsString('mg_action_center_project_lifecycle',$source);
        self::assertStringNotContainsString('INSERT INTO ledger_entries',$source);
    }

    public function testLifecycleEndpointsAreProtectedAndAudited(): void
    {
        $root=dirname(__DIR__,2);
        $read=file_get_contents($root.'/api/admin/lifecycle-health.php');
        $write=file_get_contents($root.'/api/admin/lifecycle-repair.php');
        self::assertIsString($read);
        self::assertIsString($write);
        self::assertStringContainsString('mg_admin_system_health_require_user',$read.$write);
        self::assertStringContainsString('mg_admin_system_health_require_manager',$write);
        self::assertStringContainsString('mg_require_csrf_for_write',$write);
        self::assertStringContainsString('$pdo->beginTransaction()',$write);
        self::assertStringContainsString('mg_audit',$write);
        self::assertStringContainsString('mg_event',$write);
    }
}
