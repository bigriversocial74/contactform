<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OpsQueueTransactionRollbackContractTest extends TestCase
{
    public function testAssignAndResolveOwnTransactionsAndRollbackOnFailure(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_alerts.php');
        self::assertIsString($source);

        foreach([
            '$owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();',
            'if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;',
            'if($owns)$pdo->commit();return $event+[\'status\'=>\'assigned\'];',
            'if($owns)$pdo->commit();return $event+[\'status\'=>\'resolved\'];',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testFailureHooksRemainBeforeCommitInsideNonDuplicateBranch(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_alerts.php');
        self::assertIsString($source);

        $assignHook='if($failureHook)$failureHook(\'after_audit\',[\'alert_id\'=>$alert[\'public_id\'],\'event_id\'=>$event[\'event_id\']]);}if($owns)$pdo->commit();return $event+[\'status\'=>\'assigned\'];';
        $resolveHook='if($failureHook)$failureHook(\'after_audit\',[\'alert_id\'=>$alert[\'public_id\'],\'event_id\'=>$event[\'event_id\']]);}if($owns)$pdo->commit();return $event+[\'status\'=>\'resolved\'];';

        self::assertStringContainsString($assignHook,$source);
        self::assertStringContainsString($resolveHook,$source);
    }

    public function testStateMutationAuditAndFailureHookStayInsideNonDuplicateBranch(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_alerts.php');
        self::assertIsString($source);

        foreach([
            'if(!$event[\'duplicate\']){$pdo->prepare("UPDATE ops_alerts SET status=\'assigned\'',
            'mg_audit(\'ops.alert_assigned\'',
            'if($failureHook)$failureHook(\'after_audit\',[\'alert_id\'=>$alert[\'public_id\'],\'event_id\'=>$event[\'event_id\']]);}',
            'if(!$event[\'duplicate\']){$pdo->prepare("UPDATE ops_alerts SET status=\'resolved\'',
            'mg_audit(\'ops.alert_resolved\'',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testPriorSafetyBoundariesRemainPresent(): void
    {
        $alerts=file_get_contents(dirname(__DIR__,2).'/api/ops/_alerts.php');
        $queue=file_get_contents(dirname(__DIR__,2).'/api/ops/_queue_api.php');
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/ops/queue.php');
        self::assertIsString($alerts);
        self::assertIsString($queue);
        self::assertIsString($endpoint);

        foreach([
            'Ops alert event conflicts with recorded event.',
            'SELECT * FROM ops_alert_events WHERE event_key=? LIMIT 1 FOR UPDATE',
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
            '$input[\'actor_user_id\'] = (int) $user[\'id\']',
            'mg_ops_queue_route',
        ] as $needle){
            self::assertStringContainsString($needle,$endpoint);
        }
    }
}
