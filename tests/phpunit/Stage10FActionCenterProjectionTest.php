<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage10FActionCenterProjectionTest extends TestCase
{
    public function testProjectionRequiresOwningTransactionAndCreatesSentInboxAndClaimedViews(): void
    {
        $path=dirname(__DIR__,2).'/api/microgifts/_action_center_projection.php';
        $service=file_get_contents($path);
        self::assertIsString($service);

        self::assertStringContainsString('function mg_action_center_require_transaction(', $service);
        self::assertStringContainsString('inTransaction()', $service);
        self::assertStringContainsString('function mg_action_center_projection_upsert(', $service);
        self::assertStringContainsString('function mg_action_center_recipient_folder(', $service);
        self::assertStringContainsString('function mg_action_center_project_lifecycle(', $service);
        self::assertStringContainsString("'sent'", $service);
        self::assertStringContainsString("'inbox'", $service);
        self::assertStringContainsString("'claimed'", $service);
        self::assertStringContainsString('recipient_inbox_item_id', $service);
    }

    public function testSelfOwnedGiftUsesRecipientProjectionWithoutCreatingSentProjection(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/api/microgifts/_action_center_projection.php');
        self::assertIsString($service);
        self::assertStringContainsString('$senderUserId===$recipientUserId', $service);
        self::assertStringContainsString("'sent_item_id'=>null", $service);
    }

    public function testProjectionDoesNotMutateCanonicalOwnershipOrRedemption(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/api/microgifts/_action_center_projection.php');
        self::assertIsString($service);
        self::assertStringNotContainsString('UPDATE pppm_items',$service);
        self::assertStringNotContainsString('UPDATE microgift_instances',$service);
        self::assertStringNotContainsString('INSERT INTO microgift_redemptions',$service);
    }
}
