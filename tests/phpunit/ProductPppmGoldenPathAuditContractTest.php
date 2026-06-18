<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductPppmGoldenPathAuditContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testAuditCoversMultiLineQuantityAndIdentity(): void
    {
        $source=$this->source('scripts/audit_product_pppm_golden_path.php');
        foreach([
            "'line_quantities'=>[2,3]",
            "'multi_line_quantity_issuance'",
            "'line_unit_sequences'",
            "'one_to_one_identity'",
            "'buyer_inbox_projection'",
            'COUNT(DISTINCT p.public_id)',
            'COUNT(DISTINCT mi.public_id)',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testAuditCoversTransferAndLifecycleAuthority(): void
    {
        $source=$this->source('scripts/audit_product_pppm_golden_path.php');
        foreach([
            "'transfer_actor_authorization'",
            "'current_recipient_projection'",
            "'pppm_delivery_state'",
            "'closed_gift_transfer'",
            "'purchased_gift_claim_bridge'",
            "'direct_merchant_claim'",
            "'location_policy_enforcement'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testAuditCoversMessagingIssuerAndTimestamps(): void
    {
        $source=$this->source('scripts/audit_product_pppm_golden_path.php');
        foreach([
            "'message_timing_policy'",
            "'original_issuer_preservation'",
            "'post_claim_message_recipient'",
            "'issuance_timestamps'",
            "'issuer_authority_mismatch'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testAuditIsRegisteredAsNonGatingRecoveryEvidence(): void
    {
        $composer=$this->source('composer.json');
        $baseline=$this->source('scripts/recovery_baseline.sh');
        self::assertStringContainsString('audit-product-pppm-golden-path',$composer);
        self::assertStringContainsString('audit-product-pppm-golden-path',$baseline);
        self::assertStringContainsString('build/product_pppm_golden_path_audit.json',$composer);
        self::assertStringContainsString("'mode'=>'non_gating_audit'",$this->source('scripts/audit_product_pppm_golden_path.php'));
    }

    public function testWorkflowUploadsAuditReport(): void
    {
        $workflow=$this->source('.github/workflows/recovery-baseline.yml');
        self::assertStringContainsString('Upload product PPPM audit artifact',$workflow);
        self::assertStringContainsString('product-pppm-golden-path-audit',$workflow);
        self::assertStringContainsString('build/product_pppm_golden_path_audit.json',$workflow);
        self::assertStringContainsString('if-no-files-found: error',$workflow);
    }

    public function testProductRouteCollisionIsDocumentedFromCurrentContract(): void
    {
        $schema=$this->source('database/stage_4_product_asset_foundation.sql');
        $page=$this->source('product.php');
        $api=$this->source('api/public/product.php');
        $audit=$this->source('docs/audits/PRODUCT_PPPM_GOLDEN_PATH_AUDIT.md');

        self::assertStringContainsString('UNIQUE KEY uq_catalog_products_merchant_slug (merchant_user_id, slug)',$schema);
        self::assertStringContainsString("\$_GET['p']",$page);
        self::assertStringContainsString('WHERE cp.slug = ?',$api);
        self::assertStringContainsString('Product URL slugs are ambiguous across merchants',$audit);
    }
}
