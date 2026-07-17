<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ActionCenterMutationContractV1Test extends TestCase
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

    public function testServerEnvelopeReloadsContractV2AndMergedCounts(): void
    {
        $source=$this->read('api/account/_action_center_mutation_contract.php');
        self::assertStringContainsString('MG_ACTION_CENTER_MUTATION_CONTRACT_VERSION = 1',$source);
        self::assertStringContainsString('mg_action_center_detail(',$source);
        self::assertStringContainsString('mg_ac_wallet_load_for_user(',$source);
        self::assertStringContainsString('mg_action_center_contract_items(',$source);
        self::assertStringContainsString('mg_ac_wallet_counts_merge(',$source);
        foreach(['action_item','counts','remove_action_item_ids','synchronized_at'] as $field){
            self::assertStringContainsString("'{$field}'",$source);
        }
    }

    public function testStateEndpointsReturnTheSharedMutationEnvelope(): void
    {
        foreach(['read','unread','archive','restore'] as $action){
            $source=$this->read('api/account/action-center-'.$action.'.php');
            self::assertStringContainsString('/_action_center_mutation_contract.php',$source);
            self::assertStringContainsString('mg_require_csrf_for_write($input)',$source);
            self::assertStringContainsString("mg_action_center_mutation_ok(\$pdo, \$user, '{$action}'",$source);
        }
    }

    public function testFrontendUsesRuntimeSelectionAndCanonicalReconciliation(): void
    {
        $source=$this->read('assets/js/gift-action-center-actions.js');
        self::assertStringContainsString('MicrogifterActionCenterRuntime',$source);
        self::assertStringContainsString('/api/account/action-center-mutation-state.php',$source);
        self::assertStringContainsString('mutation_contract_version',$source);
        self::assertStringContainsString('var inFlight = new Map()',$source);
        self::assertStringContainsString('window.MicrogifterActionCenterMutations',$source);
        self::assertStringNotContainsString('function actionItemFromRow',$source);
        self::assertStringNotContainsString('refresh.click()',$source);
    }

    public function testSpecializedTransactionAuthoritiesRemainSeparate(): void
    {
        $contracts=[
            'api/account/action-center-send.php'=>'mg_pppm_transfer_owner_canonical(',
            'api/account/action-center-claim.php'=>'mg_microgift_claim_canonical(',
            'api/account/action-center-follow-up.php'=>'Only the most recent sender can follow up.',
            'api/account/action-center-message.php'=>'mg_message_send_microgift(',
            'api/account/action-center-tip.php'=>'mg_tip_create(',
            'api/account/action-center-voucher-token.php'=>'mg_claim_voucher_issue_token(',
            'api/account/action-center-voucher-claim.php'=>'action_center_voucher_claim_attempts',
            'api/merchant/scanner-claim.php'=>"mg_require_permission('merchant.gifts.redeem')",
        ];
        foreach($contracts as $path=>$token){
            self::assertStringContainsString($token,$this->read($path),$path);
        }
    }
}
