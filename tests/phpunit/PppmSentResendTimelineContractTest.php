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

    public function testDeliveryTimelineIsImmutableTimestampedAndIdempotent(): void
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

    public function testInitialSendRecordsActualSendTime(): void
    {
        $send=$this->source('api/account/action-center-send.php');
        $projection=$this->source('api/microgifts/_action_center_projection.php');
        self::assertStringContainsString("\$pdo,\$instance,'sent'",str_replace(["\n","\r"," "],'',$send));
        self::assertStringContainsString("'sent_at'=>\$deliveryEvent['occurred_at']",$send);
        self::assertStringContainsString("\$context['sent_at']??\$projectionAt",$projection);
        self::assertStringContainsString('sent_at=COALESCE(sent_at,?)',$projection);
    }

    public function testResendKeepsOwnershipAndSamePppmIdentity(): void
    {
        $resend=$this->source('api/account/action-center-resend.php');
        $compact=str_replace(["\n","\r"," "],'',$resend);
        foreach([
            "folder']!=='sent'",
            "action_sender_user_id']!==(int)\$user['id']",
            "owner_user_id']!==\$recipientUserId",
            "'pppm_item_id'=>(string)(\$instance['pppm_public_id']??'')",
            "SET read_at=NULL,updated_at=?",
            "folder='inbox'",
            "action_center.microgift_resent",
            "'resent_at'=>\$deliveryEvent['occurred_at']",
        ] as $needle){
            self::assertStringContainsString($needle,$resend);
        }
        self::assertStringContainsString("\$pdo,\$instance,'resent'",$compact);
        self::assertStringNotContainsString('mg_pppm_transfer_owner_canonical',$resend);
        self::assertStringNotContainsString('UPDATE pppm_items',$resend);
        self::assertStringNotContainsString('UPDATE microgift_instances',$resend);
    }

    public function testSentTabShowsResendAndItsTimestamps(): void
    {
        $center=$this->source('assets/js/gift-action-center.js');
        $actions=$this->source('assets/js/gift-action-center-actions.js');
        $api=$this->source('api/account/_action_center.php');
        foreach([
            'data-gift-action="resend"',
            'Last resent:',
            'Resends:',
            'data-action-form="resend"',
            'This creates a new resend timestamp',
        ] as $needle){
            self::assertStringContainsString($needle,$center);
        }
        self::assertStringContainsString("['send','resend','claim','message','tip']",$actions);
        self::assertStringContainsString("function endpoint(type){return '/api/account/action-center-'+type+'.php';}",$actions);
        foreach(['delivery.first_sent_at','delivery.last_resent_at','delivery.resend_count','last_delivery_event_at'] as $needle){
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
        self::assertStringContainsString("source_reference,created_at) VALUES (?,?,?,?,?,?,'action_center',?,NOW())",$message);
        self::assertStringContainsString('tip_events',$tip);
        self::assertStringContainsString('created_at) VALUES (?,?,?,?,?,?,?,?,NOW())',$tip);
    }
}
