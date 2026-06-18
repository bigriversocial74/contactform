<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OpsQueueAuditConsistencyContractTest extends TestCase
{
    public function testAssignAndResolveAuditOnlyNonDuplicateEvents(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_alerts.php');
        self::assertIsString($source);

        foreach([
            'if(!$event[\'duplicate\']){$pdo->prepare("UPDATE ops_alerts SET status=\'assigned\'',
            'mg_audit(\'ops.alert_assigned\'',
            '\'event_id\'=>$event[\'event_id\']',
            'if($failureHook)$failureHook(\'after_audit\',[\'alert_id\'=>$alert[\'public_id\'],\'event_id\'=>$event[\'event_id\']])',
            'if(!$event[\'duplicate\']){$pdo->prepare("UPDATE ops_alerts SET status=\'resolved\'',
            'mg_audit(\'ops.alert_resolved\'',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testAuditPayloadsUseStableAlertAndEventIdentity(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_alerts.php');
        self::assertIsString($source);

        foreach([
            '[\'alert_id\'=>$alert[\'public_id\'],\'event_id\'=>$event[\'event_id\'],\'assigned_to_user_id\'=>$assignee]',
            '[\'alert_id\'=>$alert[\'public_id\'],\'event_id\'=>$event[\'event_id\'],\'reason\'=>$reason]',
            'return [\'event_id\'=>$row[\'public_id\'],\'duplicate\'=>true]',
            'return [\'event_id\'=>$public,\'duplicate\'=>false]',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testPriorReplayAndCsrfBoundariesRemainPresent(): void
    {
        $alerts=file_get_contents(dirname(__DIR__,2).'/api/ops/_alerts.php');
        $queue=file_get_contents(dirname(__DIR__,2).'/api/ops/_queue_api.php');
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/ops/queue.php');
        self::assertIsString($alerts);
        self::assertIsString($queue);
        self::assertIsString($endpoint);

        foreach([
            'function mg_ops_event_key',
            'Ops alert event conflicts with recorded event.',
            'SELECT * FROM ops_alert_events WHERE event_key=? LIMIT 1 FOR UPDATE',
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
            '$input[\'actor_user_id\'] = (int) $user[\'id\']',
            'mg_ops_queue_route',
        ] as $needle){
            self::assertStringContainsString($needle,$endpoint);
        }
    }
}
