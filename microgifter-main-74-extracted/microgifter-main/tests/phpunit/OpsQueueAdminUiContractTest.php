<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OpsQueueAdminUiContractTest extends TestCase
{
    public function testOpsQueueAdminUiCallsMergedEndpointAndActions(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/admin/ops-queue.php');
        self::assertIsString($source);
        foreach(['/api/ops/queue.php','authenticated ops session','action','list','detail','assign','resolve','request_key','source_type','severity','status'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('actor_user_id',$source);
    }
}
