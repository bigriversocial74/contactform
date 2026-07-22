<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CreatorCampaignMessagingV9ContractTest extends TestCase
{
    private string $root;
    protected function setUp():void{$this->root=dirname(__DIR__,2);}
    public function testCanonicalMessagingIsReused():void{$sql=file_get_contents($this->root.'/database/20260722_creator_campaign_messaging_notifications_v9_single_install.sql');self::assertStringContainsString('REFERENCES message_threads',$sql);self::assertStringContainsString('REFERENCES messages',$sql);self::assertDoesNotMatchRegularExpression('/CREATE TABLE IF NOT EXISTS\s+(message_threads|messages|notifications)\b/i',$sql);}
    public function testCampaignSourceSurvivesMessagesCenterReplies():void{$send=file_get_contents($this->root.'/api/messages/send.php');$thread=file_get_contents($this->root.'/api/messages/thread.php');self::assertStringContainsString('creator_campaign:',$send);self::assertStringContainsString("'creator_campaign_message'",$send);self::assertStringContainsString('creator_campaign_message_contexts',$thread);self::assertStringContainsString('creator_campaign_message_links',$send);self::assertStringContainsString("status']!=='open'",$send);}
    public function testAuthorizationAndPrivateNotesAreSeparated():void{$repo=file_get_contents($this->root.'/includes/creator-campaigns/message-repository.php');$api=file_get_contents($this->root.'/api/merchant/creator-campaign-messages.php');$sql=file_get_contents($this->root.'/database/20260722_creator_campaign_messaging_notifications_v9_single_install.sql');self::assertStringContainsString('c.workspace_id=?',$repo);self::assertStringContainsString('p.creator_user_id=?',$repo);self::assertStringContainsString("WHERE r.slug IN ('customer','creator','admin','super_admin')",$sql);self::assertStringContainsString('merchant.creator_notes.manage',$api);$query=file_get_contents($this->root.'/includes/creator-campaigns/message-query.php');self::assertStringContainsString("COALESCE(mc.status,'not_started')",$query);}
    public function testNotificationsUseCanonicalDeliveryPipeline():void{$file=file_get_contents($this->root.'/includes/creator-campaigns/message-notification.php');self::assertStringContainsString('mg_create_notification',$file);self::assertStringContainsString('muted_until',$file);self::assertStringContainsString('/messages.php?thread=',$file);}
}
