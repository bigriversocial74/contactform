<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OpsQueueHttpEndpointContractTest extends TestCase
{
    public function testOpsQueueEndpointDelegatesToCallableAuthority(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/queue.php');
        self::assertIsString($source);
        foreach(['_queue_api.php','mg_require_api_user','actor_user_id','mg_ops_queue_route','mg_json_response','MgOpsQueueApiException','MgOpsAlertException','action','list'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
