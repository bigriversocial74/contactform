<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OpsQueueCsrfContractTest extends TestCase
{
    public function testOpsQueueEndpointRequiresCsrfForWriteRequests(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/queue.php');
        self::assertIsString($source);

        foreach([
            'mg_require_csrf_for_write($input)',
            "['assign', 'resolve']",
            "Method not allowed.",
            'mg_require_api_user',
            "['actor_user_id']",
            'mg_ops_queue_route',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testOpsQueueAdminSendsSessionCsrfTokenWithoutActorIdentity(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/admin/ops-queue.php');
        self::assertIsString($source);

        foreach([
            'mg_require_api_user',
            'mg_api_user_has_permission',
            'ops.alerts.assign',
            'ops.alerts.resolve',
            'mg_csrf_token',
            'csrfToken',
            'X-CSRF-TOKEN',
            'csrf_token',
            '/api/ops/queue.php',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString('actor_user_id',$source);
    }
}
