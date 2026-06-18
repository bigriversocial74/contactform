<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OpsQueueAdminAccessContractTest extends TestCase
{
    public function testOpsQueueAdminPageRequiresAuthenticatedPermittedSession(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/admin/ops-queue.php');
        self::assertIsString($source);

        foreach([
            "require_once dirname(__DIR__).'/api/bootstrap.php'",
            'mg_require_api_user',
            'mg_api_user_has_permission',
            'ops.alerts.assign',
            'ops.alerts.resolve',
            'Permission denied.',
            '/api/ops/queue.php',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString('actor_user_id',$source);
    }

    public function testOpsQueueEndpointStillUsesAuthenticatedActorBridge(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/queue.php');
        self::assertIsString($source);

        foreach(['mg_require_api_user', "['actor_user_id']", 'mg_ops_queue_route'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
