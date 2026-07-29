<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage11EActionCenterActionWiringTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function read(string $path): string
    {
        $content=file_get_contents($this->root.'/'.$path);
        self::assertIsString($content,$path);
        return $content;
    }

    public function testRegiftUsesCanonicalOwnershipProjectionNotificationAndFreeActionAuthority(): void
    {
        $source=$this->read('api/account/action-center-send.php');
        self::assertStringContainsString('mg_pppm_transfer_owner_canonical(',$source);
        self::assertStringContainsString("'action_center_regift'",$source);
        self::assertStringContainsString('mg_microgift_delivery_event(',$source);
        self::assertStringContainsString('mg_action_center_sent(',$source);
        self::assertStringContainsString('mg_create_notification(',$source);
        self::assertStringContainsString('mg_require_csrf_for_write(',$source);
        self::assertStringContainsString("['issued','delivered']",$source);
        self::assertMatchesRegularExpression("/'sent_at'\\s*=>\\s*\\$deliveryEvent\\['occurred_at'\\]/",$source);
        self::assertStringContainsString("SET owner_user_id=?,recipient_user_id=?,status='delivered'",$source);
        self::assertStringContainsString("'regift_send'",$source);
        self::assertStringContainsString('mg_action_center_merchant_sponsored_regift_stamp(',$source);
        self::assertStringContainsString("'debit_status' => 'free_action'",$source);
        self::assertStringContainsString('stamp_ledger',$source);
        self::assertStringNotContainsString('mg_stamp_debit_send(',$source);
        self::assertStringNotContainsString('SET issuer_user_id=?',$source);
        self::assertStringNotContainsString('INSERT INTO pppm_items',$source);
    }

    public function testFollowUpUsesMessagingWithoutOwnershipDeliveryOrStampMutation(): void
    {
        $source=$this->read('api/account/action-center-follow-up.php');
        self::assertStringContainsString("folder']!=='sent'",$source);
        self::assertStringContainsString("action_sender_user_id']!==(int)\$user['id']",$source);
        self::assertStringContainsString("owner_user_id']!==\$recipientUserId",$source);
        self::assertStringContainsString('mg_message_conversation_key(',$source);
        self::assertStringContainsString("'follow_up'",$source);
        self::assertStringContainsString('mg_message_send_microgift(',$source);
        self::assertStringContainsString("'stamp_ledger_entry_id'=>null",$source);
        self::assertStringContainsString("'stamp_ledger'=>\$stampLedger",$source);
        self::assertStringNotContainsString('mg_stamp_debit_send(',$source);
        self::assertStringNotContainsString('mg_pppm_transfer_owner_canonical(',$source);
        self::assertStringNotContainsString('UPDATE pppm_items',$source);
        self::assertStringNotContainsString('UPDATE microgift_instances',$source);
        self::assertStringNotContainsString('mg_microgift_delivery_event(',$source);
    }

    public function testClaimUsesCanonicalClaimReplayAndLifecycleProjection(): void
    {
        $source=$this->read('api/account/action-center-claim.php');
        $authority=$this->read('api/microgifts/_claim_authority.php');
        self::assertStringContainsString('mg_microgift_claim_canonical(',$source);
        self::assertStringContainsString('mg_microgift_assert_claim_replay(',$authority);
        self::assertStringContainsString('mg_microgift_claim(',$authority);
        self::assertStringContainsString('mg_action_center_project_lifecycle(',$authority);
        self::assertStringContainsString('recipient_user_id',$source);
        self::assertStringContainsString("['issued','delivered','claim_pending']",$source);
        $claimPosition=strpos($source,'mg_microgift_claim_canonical(');
        $commitPosition=strpos($source,'$pdo->commit()',$claimPosition);
        self::assertIsInt($claimPosition);
        self::assertIsInt($commitPosition);
        self::assertLessThan($commitPosition,$claimPosition);
    }

    public function testMessageUsesTransferScopedDurableMessagingWithoutStampMutation(): void
    {
        $source=$this->read('api/account/action-center-message.php');
        self::assertStringContainsString('messages/_messaging.php',$source);
        self::assertStringContainsString('mg_message_conversation_key(',$source);
        self::assertStringContainsString('mg_message_send_microgift(',$source);
        self::assertStringContainsString('action_sender_user_id',$source);
        self::assertStringContainsString('action_recipient_user_id',$source);
        self::assertStringContainsString("'stamp_ledger_entry_id'=>null",$source);
        self::assertStringContainsString("'stamp_ledger'=>\$stampLedger",$source);
        self::assertStringNotContainsString('mg_stamp_debit_send(',$source);
        self::assertStringNotContainsString('INSERT INTO events',$source);
        self::assertStringNotContainsString('UPDATE microgift_instances',$source);
        self::assertStringNotContainsString('mg_action_center_project_lifecycle(',$source);
    }

    public function testFrontendPostsRegiftAndFollowUpToCanonicalEndpoints(): void
    {
        $source=$this->read('assets/js/gift-action-center-actions.js');
        self::assertStringContainsString("'/api/account/action-center-'",$source);
        foreach(['send','follow-up','claim','message'] as $action){
            self::assertStringContainsString("'{$action}'",$source);
        }
        self::assertStringContainsString('Microgifter.post(',$source);
        self::assertStringContainsString('idempotency_key',$source);
        self::assertStringNotContainsString("'resend'",$source);
        $layout=$this->read('includes/gift-action-center.php');
        self::assertStringContainsString('/assets/js/gift-action-center-actions.js',$layout);
    }
}
