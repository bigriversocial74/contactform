<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ActionCenterRedeemLocationSelectorContractTest extends TestCase
{
    public function testActionCenterClaimEndpointRemainsRecipientClaimOnly(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-claim.php');
        self::assertIsString($source);

        foreach([
            'mg_microgift_claim($pdo,(int)$user[\'id\'],$input)',
            'Microgift claim processed.',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString('mg_microgift_redeem',$source);
        self::assertStringNotContainsString('location_id',$source);
    }

    public function testRedeemLocationEndpointListsOnlyCatalogMerchantLocations(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-redeem-locations.php');
        self::assertIsString($source);

        foreach([
            'mg_require_method(\'GET\')',
            '$actionItemId=trim((string)($_GET[\'action_item_id\']??$_GET[\'id\']??\'\'))',
            'INNER JOIN microgift_template_versions v ON v.id=i.template_version_id',
            'INNER JOIN catalog_products cp ON cp.id=v.product_id',
            'WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL',
            'if((int)$item[\'owner_user_id\']!==(int)$user[\'id\'])mg_fail(\'You do not own this Microgift.\',403)',
            'if(!in_array((string)$item[\'instance_status\'],[\'claimed\',\'redeemable\'],true))mg_fail(\'Microgift is not redeemable.\',409)',
            "WHERE mw.merchant_user_id=? AND ml.status='active'",
            '\'locations\'=>$locations->fetchAll(PDO::FETCH_ASSOC)',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString('claim_code',$source,'Location claim codes must not be exposed to the Action Center browser selector.');
    }

    public function testRedeemEndpointUsesMicrogiftRedemptionAuthority(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-redeem.php');
        self::assertIsString($source);

        foreach([
            'mg_require_method(\'POST\')',
            'mg_require_csrf_for_write($input)',
            '$locationPublicId=strtolower(trim((string)($input[\'location_id\']??\'\')))',
            'INNER JOIN catalog_products cp ON cp.id=v.product_id',
            'INNER JOIN merchant_workspaces mw ON mw.merchant_user_id=cp.merchant_user_id',
            'INNER JOIN merchant_locations ml ON ml.workspace_id=mw.id AND ml.public_id=? AND ml.status=\'active\'',
            'if((int)$instance[\'owner_user_id\']!==(int)$user[\'id\'])throw new RuntimeException(\'You do not own this Microgift.\')',
            'if(!in_array((string)$instance[\'status\'],[\'claimed\',\'redeemable\'],true))throw new RuntimeException(\'Microgift is not redeemable.\')',
            '$result=mg_microgift_redeem($pdo,(int)$user[\'id\'],[',
            '\'merchant_user_id\'=>(int)$instance[\'merchant_user_id\']',
            '\'location_reference\'=>$locationPublicId',
            'mg_action_center_project_lifecycle($pdo,$reloaded,[',
            '\'location_id\'=>(int)$instance[\'location_internal_id\']',
            'action_center.microgift_redeemed',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString('mg_microgift_claim',$source);
    }

    public function testActionCenterActionsKeepRedeemPayloadSeparateFromRegiftAndFollowUp(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center-actions.js');
        self::assertIsString($source);

        foreach([
            "else if(type==='redeem'){request.location_id=data.location_id;}",
            "form.dataset.actionForm==='redeem'",
            "['send','follow-up','claim','message','tip']",
            "function endpoint(type){return '/api/account/action-center-'+type+'.php';}",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString("'resend'",$source);
        self::assertStringContainsString('request.code=data.claim_code',$source,'Existing merchant claim-code payload remains free-standing and unchanged.');
    }
}
