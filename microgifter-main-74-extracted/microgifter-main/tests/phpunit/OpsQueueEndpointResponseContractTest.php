<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OpsQueueEndpointResponseContractTest extends TestCase
{
    public function testEndpointReturnsConsistentOpsQueueEnvelope(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/queue.php');
        self::assertIsString($source);

        foreach([
            "mg_json_response(['ok' => true, 'data' => mg_ops_queue_route(\$pdo, \$action, \$input)])",
            "catch (MgOpsQueueApiException|MgOpsAlertException \$e)",
            "mg_json_response(['ok' => false, 'error' => \$e->getMessage()], \$e->httpStatus)",
            "catch (Throwable)",
            "mg_json_response(['ok' => false, 'error' => 'Ops queue request failed.'], 500)",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testGetWriteActionsUseOpsQueueErrorEnvelope(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/queue.php');
        self::assertIsString($source);

        foreach([
            "if (in_array(\$action, ['assign', 'resolve'], true) && \$method === 'GET')",
            "throw new MgOpsQueueApiException('Method not allowed.', 405)",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString("mg_fail('Method not allowed.', 405)",$source);
    }

    public function testEndpointOwnsActorAndWriteCsrfBoundary(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/queue.php');
        self::assertIsString($source);

        foreach([
            'mg_require_csrf_for_write($input)',
            '$user = mg_require_api_user();',
            '$input[\'actor_user_id\'] = (int) $user[\'id\'];',
            '$_GET',
            '$_POST + $_GET',
            '$body + $_POST + $_GET',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testRouteStatusContractsRemainExplicit(): void
    {
        $queue=file_get_contents(dirname(__DIR__,2).'/api/ops/_queue_api.php');
        self::assertIsString($queue);

        foreach([
            "if(\$id==='')throw new MgOpsQueueApiException('Missing alert id.',422)",
            "if(!\$alert)throw new MgOpsQueueApiException('Ops alert not found.',404)",
            "default=>throw new MgOpsQueueApiException('Unknown action.',404)",
            "if(\$key==='')throw new MgOpsQueueApiException('Missing request key.',422)",
            "if(mb_strlen(\$key)>190)throw new MgOpsQueueApiException('Request key is too long.',422)",
            "if(!mg_ops_queue_can_read(\$pdo,\$actorUserId))throw new MgOpsQueueApiException('Ops queue read denied.',403)",
        ] as $needle){
            self::assertStringContainsString($needle,$queue);
        }
    }

    public function testResponseHelperStillSupportsLegacyFailEnvelopeForNonQueueUsers(): void
    {
        $response=file_get_contents(dirname(__DIR__,2).'/api/response.php');
        self::assertIsString($response);

        foreach([
            "function mg_json(array \$payload, int \$status = 200): never",
            "header('Content-Type: application/json; charset=utf-8')",
            "echo json_encode(\$payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)",
            "function mg_fail(string \$message, int \$status = 400, array \$errors = []): never",
            "mg_json(['ok' => false, 'message' => \$message, 'errors' => \$errors], \$status)",
        ] as $needle){
            self::assertStringContainsString($needle,$response);
        }
    }
}
