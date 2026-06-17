<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage11GActionCenterDurableMessagingTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function read(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testMigrationAddsDurableThreadAndMessageContracts(): void
    {
        $sql=$this->read('database/stage_11g_action_center_durable_messaging.sql');
        foreach(['microgift_instance_id','recipient_user_id','idempotency_key','source_type','source_reference'] as $column){
            self::assertStringContainsString($column,$sql);
        }
        self::assertStringContainsString('uq_message_threads_microgift_instance',$sql);
        self::assertStringContainsString('uq_messages_sender_idempotency',$sql);
    }

    public function testServicePersistsCanonicalMessagesAndDeliveryJobs(): void
    {
        $source=$this->read('api/messages/_messaging.php');
        self::assertStringContainsString('INSERT INTO message_threads',$source);
        self::assertStringContainsString('message_thread_participants',$source);
        self::assertStringContainsString('INSERT INTO messages',$source);
        self::assertStringContainsString('INSERT INTO notifications',$source);
        self::assertStringContainsString('mg_queue_notification_deliveries(',$source);
        self::assertStringContainsString('INSERT INTO microgift_events',$source);
        self::assertStringNotContainsString('INSERT INTO events',$source);
    }

    public function testServiceEnforcesParticipantsAndDurableIdempotency(): void
    {
        $source=$this->read('api/messages/_messaging.php');
        foreach(['issuer_user_id','owner_user_id','recipient_user_id'] as $field){
            self::assertStringContainsString("'{$field}'",$source);
        }
        self::assertStringContainsString('WHERE sender_user_id=? AND idempotency_key=?',$source);
        self::assertStringContainsString('Idempotency key is already bound to a different message request.',$source);
        self::assertStringContainsString('Message recipient is not authorized for this Microgift.',$source);
    }

    public function testActionCenterEndpointUsesMessagingServiceWithoutLifecycleMutation(): void
    {
        $source=$this->read('api/account/action-center-message.php');
        self::assertStringContainsString('messages/_messaging.php',$source);
        self::assertStringContainsString('mg_message_send_microgift(',$source);
        self::assertStringContainsString("'thread_id'=>",$source);
        self::assertStringContainsString("'message_id'=>",$source);
        self::assertStringNotContainsString('INSERT INTO events',$source);
        self::assertStringNotContainsString('UPDATE microgift_instances',$source);
        self::assertStringNotContainsString('mg_action_center_project_lifecycle(',$source);
    }
}
