<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MicrogiftLifecycleValidationContractTest extends TestCase
{
    public function testBehaviorValidatorCoversCompleteLifecycleAndRollback(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/scripts/validate_microgift_behavior.php');
        self::assertIsString($source);

        foreach([
            'mg_microgift_issue',
            'mg_pppm_transfer_owner_canonical',
            'mg_message_send_microgift',
            'mg_microgift_claim',
            'mg_microgift_atomic_merchant_redeem',
            'message_replay',
            'message_authorization',
            'action_center_consistent',
            'audit_trail_consistent',
            'ledger_neutral_for_merchant_funded',
            'invalid_transition_rolled_back',
            'fixtures_clean',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testBehaviorValidatorVerifiesOwnershipMessagingAuditAndOutbox(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/scripts/validate_microgift_behavior.php');
        self::assertIsString($source);

        foreach([
            'PPPM owner did not follow send.',
            'PPPM owner did not remain with claimant.',
            'PPPM owner changed during redemption.',
            'Message delivery jobs were not queued.',
            'Unauthorized Microgift message did not fail.',
            'Lifecycle audit event is missing or duplicated:',
            'Redemption outbox event is missing.',
            'Merchant-funded Microgift lifecycle created an unexpected ledger transaction group.',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testActionCenterSendAllowsOnlyUnclaimedDeliveredInventory(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-send.php');
        self::assertIsString($source);

        self::assertStringContainsString("['issued','delivered']",$source);
        self::assertStringNotContainsString("['issued','delivered','claim_pending']",$source);
        self::assertStringNotContainsString("['issued','delivered','claim_pending','claimed','redeemable']",$source);
    }

    public function testComposerAndCiRunMicrogiftBehaviorValidation(): void
    {
        $composer=file_get_contents(dirname(__DIR__,2).'/composer.json');
        $workflow=file_get_contents(dirname(__DIR__,2).'/.github/workflows/pr-validation.yml');
        self::assertIsString($composer);
        self::assertIsString($workflow);

        self::assertStringContainsString('"test-microgift-behavior": "php scripts/validate_microgift_behavior.php"',$composer);
        self::assertStringContainsString('Validate Microgift lifecycle behavior',$workflow);
        self::assertStringContainsString('composer test-microgift-behavior',$workflow);
    }
}
