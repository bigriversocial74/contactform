<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage11DActionCenterLifecycleProjectionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function read(string $path): string
    {
        $content=file_get_contents($this->root.'/'.$path);
        self::assertIsString($content);
        return $content;
    }

    public function testProjectionCoversInboxSentAndClaimedFolders(): void
    {
        $source=$this->read('api/microgifts/_action_center_projection.php');
        self::assertStringContainsString('function mg_action_center_projection_upsert(', $source);
        self::assertStringContainsString('function mg_action_center_recipient_folder(', $source);
        self::assertStringContainsString('function mg_action_center_project_lifecycle(', $source);
        self::assertStringContainsString("'sent'", $source);
        self::assertStringContainsString("'inbox'", $source);
        self::assertStringContainsString("'claimed'", $source);
        self::assertStringContainsString("['claimed','redeemable','redeemed']", $source);
    }

    public function testPreviouslyClaimedTerminalStatesRemainInClaimed(): void
    {
        $source=$this->read('api/microgifts/_action_center_projection.php');
        self::assertStringContainsString('hasBeenClaimed', $source);
        self::assertStringContainsString('claimed_at', $source);
        self::assertStringContainsString('$hasBeenClaimed||in_array(', $source);
    }

    public function testSelfOwnedGiftUsesRecipientProjectionInsteadOfOverwritingItWithSent(): void
    {
        $source=$this->read('api/microgifts/_action_center_projection.php');
        self::assertStringContainsString('$senderUserId===$recipientUserId', $source);
        self::assertStringContainsString("'sent_item_id'=>null", $source);
    }

    public function testProjectionPreservesArchivedPresentationState(): void
    {
        $source=$this->read('api/microgifts/_action_center_projection.php');
        self::assertStringNotContainsString('archived_at=NULL', $source);
        self::assertStringContainsString('WHERE instance_id=? AND user_id=?', $source);
        self::assertStringContainsString('LIMIT 1 FOR UPDATE', $source);
    }

    public function testCanonicalLifecycleEntryPointsProjectBeforeCommit(): void
    {
        $files=[
            'api/microgifts/issue.php',
            'api/microgifts/claim.php',
            'api/microgifts/redeem.php',
            'api/admin/microgift-lifecycle.php',
        ];
        foreach($files as $file){
            $source=$this->read($file);
            self::assertStringContainsString('_action_center_projection.php',$source,$file);
            self::assertStringContainsString('mg_action_center_project_lifecycle(',$source,$file);
            $projectionPosition=strpos($source,'mg_action_center_project_lifecycle(');
            $commitPosition=strpos($source,'$pdo->commit()',$projectionPosition);
            self::assertIsInt($projectionPosition,$file);
            self::assertIsInt($commitPosition,$file);
            self::assertLessThan($commitPosition,$projectionPosition,$file.' must project inside the lifecycle transaction.');
        }
    }

    public function testMerchantClaimDelegatesCompletedProjectionToAtomicAuthority(): void
    {
        $endpoint=$this->read('api/merchant/microgift-claim.php');
        $atomic=$this->read('api/microgifts/_atomic_merchant_redemption.php');

        self::assertStringContainsString('mg_claim_execute_operation(',$endpoint);
        self::assertStringNotContainsString('mg_action_center_project_lifecycle(',$endpoint);
        self::assertStringContainsString('_action_center_projection.php',$atomic);
        self::assertStringContainsString('mg_action_center_refresh_existing_lifecycle(',$atomic);
        self::assertStringContainsString('mg_microgift_upsert_inbox_redeemed(',$atomic);
        self::assertStringContainsString("'can_tip'=>1",$atomic);
    }

    public function testProjectionCarriesLifecycleAndRedemptionMetadata(): void
    {
        $source=$this->read('api/microgifts/_action_center_projection.php');
        foreach(['state','redemption_id','merchant_user_id','location_id','can_tip','claimed_at','redeemed_at'] as $field){
            self::assertStringContainsString($field,$source);
        }
    }
}
