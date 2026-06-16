<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OpsQueueEventVisibilityContractTest extends TestCase
{
    public function testDetailEventsExposeStableEventIdentityButNotReplayKeys(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_queue_api.php');
        self::assertIsString($source);

        $eventFunctionStart=strpos($source,'function mg_ops_queue_event_array');
        self::assertNotFalse($eventFunctionStart);
        $listFunctionStart=strpos($source,'function mg_ops_queue_list', (int)$eventFunctionStart);
        self::assertNotFalse($listFunctionStart);
        $eventFunction=substr($source,(int)$eventFunctionStart,(int)$listFunctionStart-(int)$eventFunctionStart);

        foreach([
            "'event_id'=>(string)\$r['public_id']",
            "'actor_user_id'=>\$r['actor_user_id']===null?null:(int)\$r['actor_user_id']",
            "'event_type'=>(string)\$r['event_type']",
            "'before'=>\$r['before_json']?json_decode((string)\$r['before_json'],true):null",
            "'after'=>\$r['after_json']?json_decode((string)\$r['after_json'],true):null",
            "'created_at'=>(string)\$r['created_at']",
        ] as $needle){
            self::assertStringContainsString($needle,$eventFunction);
        }

        foreach([
            "'event_key'",
            "\$r['event_key']",
            "fingerprint",
        ] as $needle){
            self::assertStringNotContainsString($needle,$eventFunction);
        }
    }

    public function testListReturnsAlertSummariesAndDetailReturnsEvents(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/ops/_queue_api.php');
        self::assertIsString($source);

        foreach([
            "function mg_ops_queue_list(PDO \$pdo,array \$input): array",
            "mg_ops_queue_require_read(\$pdo,\$actor)",
            "while(\$row=\$s->fetch(PDO::FETCH_ASSOC))\$items[]=mg_ops_queue_alert_array(\$row);return ['items'=>\$items,'count'=>count(\$items)]",
            "function mg_ops_queue_detail(PDO \$pdo,array \$input): array",
            "SELECT * FROM ops_alerts WHERE public_id=? LIMIT 1",
            "SELECT * FROM ops_alert_events WHERE alert_id=? ORDER BY id ASC",
            "while(\$row=\$e->fetch(PDO::FETCH_ASSOC))\$events[]=mg_ops_queue_event_array(\$row);return ['alert'=>mg_ops_queue_alert_array(\$alert),'events'=>\$events]",
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
            'Ops alert event conflicts with recorded event.',
            'if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;',
            'mg_ops_require($pdo,$actor,\'ops.alerts.assign\')',
            'mg_ops_require($pdo,$actor,\'ops.alerts.resolve\')',
        ] as $needle){
            self::assertStringContainsString($needle,$alerts);
        }

        foreach([
            '\'event_key\'=>mg_ops_queue_request_key($input)',
            'Missing request key.',
            'Request key is too long.',
            'mg_ops_queue_can_read',
            "ops.alerts.assign')||mg_ops_has(\$pdo,\$actorUserId,'ops.alerts.resolve",
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
