<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OpsQueueRequestIntegrityContractTest extends TestCase
{
    public function testOpsQueueMutationsRequireBoundedRequestKeys(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_queue_api.php');
        self::assertIsString($source);

        foreach([
            'function mg_ops_queue_request_key',
            'Missing request key.',
            'Request key is too long.',
            '\'event_key\'=>mg_ops_queue_request_key($input)',
            'mg_ops_assign_alert',
            'mg_ops_resolve_alert',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString('\'event_key\'=>(string)($input[\'request_key\']??\'\')',$source);
    }

    public function testOpsAlertEventReplayUsesFingerprintConflictBoundary(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_alerts.php');
        self::assertIsString($source);

        foreach([
            'function mg_ops_event_key',
            'Missing request key.',
            'Request key is too long.',
            'mg_ops_event_fingerprint',
            'SELECT * FROM ops_alert_events WHERE event_key=? LIMIT 1 FOR UPDATE',
            'hash_equals',
            'Ops alert event conflicts with recorded event.',
            '\'duplicate\'=>true',
            '\'duplicate\'=>false',
            'INSERT INTO ops_alert_events',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testOpsQueueEndpointStillUsesSessionActorAndCsrf(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/queue.php');
        self::assertIsString($source);

        foreach([
            'mg_require_csrf_for_write($input)',
            'mg_require_api_user',
            '$input[\'actor_user_id\'] = (int) $user[\'id\']',
            'mg_ops_queue_route',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
