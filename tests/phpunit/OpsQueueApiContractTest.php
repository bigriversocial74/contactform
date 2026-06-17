<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OpsQueueApiContractTest extends TestCase
{
    public function testOpsQueueApiDefinesReadAndActionContracts(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_queue_api.php');
        self::assertIsString($source);
        foreach(['mg_ops_queue_list','mg_ops_queue_detail','mg_ops_queue_assign','mg_ops_queue_resolve','mg_ops_queue_route','ops.alerts.assign','ops.alerts.resolve','status','severity','source_type','request_key'] as $needle){self::assertStringContainsString($needle,$source);}    
    }
}
