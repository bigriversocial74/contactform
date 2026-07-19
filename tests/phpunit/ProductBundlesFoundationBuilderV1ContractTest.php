<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductBundlesFoundationBuilderV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    public function testCanonicalFilesExist(): void
    {
        foreach([
            'database/20260719_product_bundles_foundation_builder_v1.sql',
            'api/bundles/_bundles.php','api/merchant/bundles.php',
            'merchant-bundles.php','merchant-bundle-invitations.php',
            'includes/merchant-bundles-view.php','includes/merchant-bundle-invitations-view.php',
            'assets/js/merchant-bundles.js','assets/css/merchant-bundles.css',
        ] as $file) $this->assertFileExists($this->root.'/'.$file,$file);
    }

    public function testSchemaDefinesVersionedBundleAuthority(): void
    {
        $sql=file_get_contents($this->root.'/database/20260719_product_bundles_foundation_builder_v1.sql');
        foreach(['gift_bundles','gift_bundle_components','gift_bundle_participants','gift_bundle_audit_log','terms_version','commission_rate_bps','merchant_net_amount_cents'] as $marker) $this->assertStringContainsString($marker,$sql);
    }

    public function testServiceUsesCanonicalCommissionAuthority(): void
    {
        $php=file_get_contents($this->root.'/api/bundles/_bundles.php');
        $this->assertStringContainsString("require_once dirname(__DIR__) . '/payments/_commissions.php'",$php);
        $this->assertStringContainsString('mg_commission_resolve_merchant_rate',$php);
        $this->assertStringContainsString('MG_COMMISSION_RULE_VERSION',$php);
        $this->assertStringNotContainsString('0.15',$php);
    }

    public function testMerchantWorkflowIncludesRequiredStatesAndPublishValidation(): void
    {
        $api=file_get_contents($this->root.'/api/merchant/bundles.php');
        foreach(['accepted','countered','declined','question','mg_bundle_publish_validation','catalog.products.manage','gift_bundle_audit_log'] as $marker) $this->assertStringContainsString($marker,$api);
    }

    public function testBuilderIsARealSevenStepMerchantUi(): void
    {
        $view=file_get_contents($this->root.'/includes/merchant-bundles-view.php');
        foreach(['1 Identity','2 Components','3 Merchants','4 Options','5 Commission','6 Campaign','7 Publish'] as $marker) $this->assertStringContainsString($marker,$view);
        $this->assertStringContainsString('data-product-list',$view);
        $this->assertStringContainsString('data-publish-checks',$view);
    }
}
