<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PppmSentResendTimelineContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testHistoricalDeliveryTimelineRemainsImmutableAndIdempotent(): void
    {
        $sql=$this->source('database/stage_18g_pppm_resend_timeline.sql');
        foreach([
            'CREATE TABLE IF NOT EXISTS microgift_delivery_events',
            "event_type ENUM('sent','resent','delivered')",
            'idempotency_key VARCHAR(190) NOT NULL',
            'occurred_at DATETIME NOT NULL',
            'UNIQUE KEY uq_microgift_delivery_idempotency',
            'pppm_item_id BIGINT UNSIGNED NULL',
            'stage_18g_pppm_resend_timeline',
        ] as $needle){
            self::assertStringContainsString($needle,$sql);
        }
        self::assertStringContainsString("'stage_18g_pppm_resend_timeline.sql'",$this->source('config/migrations.php'));
    }

    public function testRegiftRecordsTheActualTransferTimeWithoutChangingIssuer(): void
    {
        $send=$this->source('api/account/action-center-send.php');
        $projection=$this->source('api/microgifts/_action_center_projection.php');
        self::assertStringContainsString("\$pdo,\n        \$instance,\n        'sent'",$send);
        self::assertStringContainsString("'sent_at'=>\$deliveryEvent['occurred_at']",$send);
        self::assertStringContainsString("sent_at=COALESCE(?,sent_at)",$projection);
        self::assertStringContainsString("SET owner_user_id=?,recipient_user_id=?,status='delivered'",$send);
        self::assertStringNotContainsString('SET issuer_user_id=?',$send);
    }

    public function testFollowUpReplacesResendWithoutChangingOwnershipOrDeliveryHistory(): void
    {
        $followUp=$this->source('api/account/action-center-follow-up.php');
        $retired=$this->source('api/account/action-center-resend.php');
        foreach([
            "folder']!=='sent'",
            "action_sender_user_id']!==(int)\$user['id']",
            "owner_user_id']!==\$recipientUserId",
            'mg_message_conversation_key(',
            'mg_message_send_microgift(',
            "'follow_up'",
            'Only the most recent sender can follow up.',
        ] as $needle){
            self::assertStringContainsString($needle,$followUp);
        }
        self::assertStringNotContainsString('mg_pppm_transfer_owner_canonical',$followUp);
        self::assertStringNotContainsString('UPDATE pppm_items',$followUp);
        self::assertStringNotContainsString('UPDATE microgift_instances',$followUp);
        self::assertStringNotContainsString('mg_microgift_delivery_event(',$followUp);
        self::assertStringContainsString('Resend has been retired. Use Follow Up',$retired);
        self::assertStringContainsString(',410)',$retired);
    }

    public function testSentTabShowsFollowUpAvailabilityAndMessageTimestamps(): void
    {
        $center=$this->source('assets/js/gift-action-center.js');
        $actions=$this->source('assets/js/gift-action-center-actions.js');
        $api=$this->source('api/account/_action_center.php');
        foreach([
            'data-gift-action="follow-up"',
            'Last Follow Up:',
            'Follow Ups:',
            'data-action-form="follow-up"',
            'Only the most recent sender can follow up',
        ] as $needle){
            self::assertStringContainsString($needle,$center);
        }
        self::assertStringContainsString("['send','follow-up','claim','message','tip']",$actions);
        self::assertStringContainsString("function endpoint(type){return '/api/account/action-center-'+type+'.php';}",$actions);
        self::assertStringNotContainsString("'resend'",$actions);
        foreach(['follow_up.last_follow_up_at','follow_up.follow_up_count','can_follow_up'] as $needle){
            self::assertStringContainsString($needle,$api);
        }
    }

    public function testClaimTipAndMessageAuthoritiesKeepTheirOwnTimestamps(): void
    {
        $claim=$this->source('database/stage_9c_microgift_lifecycle.sql');
        $message=$this->source('api/messages/_messaging.php');
        $tip=$this->source('api/tips/_tips.php');
        self::assertStringContainsString('verified_at DATETIME NOT NULL',$claim);
        self::assertStringContainsString('redeemed_at DATETIME NOT NULL',$claim);
        self::assertStringContainsString('INSERT INTO messages',$message);
        self::assertStringContainsString("'microgift.follow_up_sent'",$message);
        self::assertStringContainsString('tip_events',$tip);
        self::assertStringContainsString('created_at) VALUES (?,?,?,?,?,?,?,?,NOW())',$tip);
    }
}
