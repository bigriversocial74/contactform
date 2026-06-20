<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionStageDRegiftBehaviorTest extends TestCase
{
    public function testRegiftChainAndFollowUpAgainstRealDatabase(): void
    {
        if(trim((string)getenv('MG_DB_HOST'))===''||trim((string)getenv('MG_DB_NAME'))===''){
            self::markTestSkipped('Database-backed Stage D validation requires MG_DB_HOST and MG_DB_NAME.');
        }

        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__,2).'/scripts/validate_stage_d_regift_behavior.php').' 2>&1';
        $output=[];$exitCode=0;
        exec($command,$output,$exitCode);
        $text=implode("\n",$output);
        self::assertSame(0,$exitCode,$text);
        $summary=json_decode($text,true,512,JSON_THROW_ON_ERROR);
        self::assertSame('stage_d_regift_follow_up_behavior',$summary['suite']??null,$text);
        foreach([
            'original_issuer_preserved',
            'pppm_owner_recipient_aligned',
            'three_transfers_completed',
            'recipient_notifications_created',
            'latest_sender_follow_up_only',
            'transfer_conversations_isolated',
            'reverse_reply_uses_same_thread',
            'follow_up_replay_safe',
            'action_center_folders_consistent',
            'fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($summary[$key]??false),$key.' failed: '.$text);
        }
    }

    public function testStageDSourceContractsPreserveIssuerAndTransferOwnership(): void
    {
        $root=dirname(__DIR__,2);
        $send=file_get_contents($root.'/api/account/action-center-send.php');
        $ownership=file_get_contents($root.'/api/pppm/_ownership.php');
        $followUp=file_get_contents($root.'/api/account/action-center-follow-up.php');
        $messaging=file_get_contents($root.'/api/messages/_messaging.php');
        $projection=file_get_contents($root.'/api/microgifts/_action_center_projection.php');
        foreach([$send,$ownership,$followUp,$messaging,$projection] as $source)self::assertIsString($source);

        self::assertStringContainsString("'action_center_regift'",$send);
        self::assertStringContainsString("SET owner_user_id=?,recipient_user_id=?,status='delivered'",$send);
        self::assertStringNotContainsString('SET issuer_user_id=?',$send);
        self::assertStringContainsString('UPDATE pppm_items SET owner_user_id=?,recipient_user_id=?',$ownership);
        self::assertStringContainsString('Only the most recent sender can follow up.',$followUp);
        self::assertStringContainsString("'action_center_follow_up'",$messaging);
        self::assertStringContainsString("'microgift.follow_up_sent'",$messaging);
        self::assertStringContainsString('function mg_action_center_refresh_existing_lifecycle',$projection);
        self::assertStringContainsString("folder=CASE WHEN folder='sent' THEN 'sent' ELSE ? END",$projection);
        self::assertStringContainsString('mg_action_center_refresh_existing_lifecycle($pdo,$instance,$context)',$projection);
    }

    public function testTransferConversationMigrationIsRegisteredAndPrivacyScoped(): void
    {
        $root=dirname(__DIR__,2);
        $migration=file_get_contents($root.'/database/stage_v1d_transfer_conversations.sql');
        $recipientSearch=file_get_contents($root.'/api/account/action-center-recipient-search.php');
        $manifest=require $root.'/config/migrations.php';
        self::assertIsString($migration);
        self::assertIsString($recipientSearch);

        self::assertContains('stage_v1d_transfer_conversations.sql',$manifest['ordered_files']);
        self::assertStringContainsString('conversation_key VARCHAR(190)',$migration);
        self::assertStringContainsString('uq_message_threads_microgift_conversation',$migration);
        self::assertStringContainsString('email_hint',$recipientSearch);
        self::assertStringNotContainsString("'email'=>(string)",$recipientSearch);
        self::assertStringContainsString('if(mb_strlen($q)<2)',$recipientSearch);
    }

    public function testResendIsRetiredAndUiUsesRegiftAndFollowUp(): void
    {
        $root=dirname(__DIR__,2);
        $retired=file_get_contents($root.'/api/account/action-center-resend.php');
        $center=file_get_contents($root.'/assets/js/gift-action-center.js');
        $actions=file_get_contents($root.'/assets/js/gift-action-center-actions.js');
        self::assertIsString($retired);
        self::assertIsString($center);
        self::assertIsString($actions);

        self::assertStringContainsString('Resend has been retired. Use Follow Up',$retired);
        self::assertStringContainsString('data-gift-action="send">Regift',$center);
        self::assertStringContainsString('data-gift-action="follow-up">Follow Up',$center);
        self::assertStringContainsString("['send','follow-up','claim','message','tip']",$actions);
        self::assertStringNotContainsString('data-gift-action="resend"',$center);
        self::assertStringNotContainsString("'resend'",$actions);
    }
}
