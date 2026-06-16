<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OpsAlertResolutionContractTest extends TestCase
{
    public function testOpsAlertAuthorityDefinesQueueAndResolutionContracts(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_alerts.php');
        self::assertIsString($source);
        foreach(['ops_alerts','ops_alert_events','mg_ops_alert_upsert','mg_ops_assign_alert','mg_ops_resolve_alert','ops.alerts.assign','ops.alerts.resolve','alert_key','event_key','duplicate','fingerprint','mg_audit'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
