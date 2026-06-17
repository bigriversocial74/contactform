<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OpsQueuePermissionRegressionContractTest extends TestCase
{
    public function testAssignAndResolveRequireDistinctPermissions(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_alerts.php');
        self::assertIsString($source);

        foreach([
            "function mg_ops_require(PDO \$pdo,int \$userId,string \$permission): void",
            "throw new MgOpsAlertException('Ops action is not permitted.',403)",
            "mg_ops_require(\$pdo,\$actor,'ops.alerts.assign')",
            "mg_ops_require(\$pdo,\$actor,'ops.alerts.resolve')",
            "'ops.alerts.assign','Assign operations alerts'",
            "'ops.alerts.resolve','Resolve operations alerts'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testQueueReadsRequireAssignOrResolvePermission(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_queue_api.php');
        self::assertIsString($source);

        foreach([
            "function mg_ops_queue_can_read(PDO \$pdo,int \$actorUserId): bool",
            "mg_ops_has(\$pdo,\$actorUserId,'ops.alerts.assign')||mg_ops_has(\$pdo,\$actorUserId,'ops.alerts.resolve')",
            "function mg_ops_queue_require_read(PDO \$pdo,int \$actorUserId): void",
            "throw new MgOpsQueueApiException('Ops queue read denied.',403)",
            "mg_ops_queue_require_read(\$pdo,\$actor)",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testEndpointOwnsActorIdentityFromSession(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/queue.php');
        self::assertIsString($source);

        foreach([
            'mg_require_api_user',
            '$input[\'actor_user_id\'] = (int) $user[\'id\']',
            'mg_ops_queue_route($pdo, $action, $input)',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testPriorProductionSafetyContractsRemainPresent(): void
    {
        $alerts=file_get_contents(dirname(__DIR__,2).'/api/ops/_alerts.php');
        $queue=file_get_contents(dirname(__DIR__,2).'/api/ops/_queue_api.php');
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/ops/queue.php');
        self::assertIsString($alerts);
        self::assertIsString($queue);
        self::assertIsString($endpoint);

        foreach([
            'if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;',
            'Ops alert event conflicts with recorded event.',
            '\'event_id\'=>$event[\'event_id\']',
        ] as $needle){
            self::assertStringContainsString($needle,$alerts);
        }

        foreach([
            '\'event_key\'=>mg_ops_queue_request_key($input)',
            'Missing request key.',
            'Request key is too long.',
        ] as $needle){
            self::assertStringContainsString($needle,$queue);
        }

        foreach([
            'mg_require_csrf_for_write($input)',
            "if (in_array(\$action, ['assign', 'resolve'], true) && \$method === 'GET')",
            "throw new MgOpsQueueApiException('Method not allowed.', 405)",
            "mg_json_response(['ok' => false, 'error' => \$e->getMessage()], \$e->httpStatus)",
        ] as $needle){
            self::assertStringContainsString($needle,$endpoint);
        }

        self::assertStringNotContainsString("mg_fail('Method not allowed.', 405)",$endpoint);
    }
}
