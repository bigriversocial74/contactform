<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductBundleSettlementReviewV8ContractTest extends TestCase
{
    public function testAdminReviewContract(): void
    {
        $root=dirname(__DIR__,2);
        $api=file_get_contents($root.'/api/admin/bundle-settlement-reviews.php');
        $page=file_get_contents($root.'/admin/bundle-settlement-reviews.php');
        $js=file_get_contents($root.'/assets/js/bundle-settlement-review-v8.js');

        self::assertStringContainsString("mg_admin_permission_user_has",$api);
        self::assertStringContainsString("mg_require_csrf_for_write",$api);
        self::assertStringContainsString("gift_bundle_settlement_events",$api);
        self::assertStringContainsString("transfer_execution_enabled'=>false",$api);
        self::assertStringNotContainsString('Stripe\\Transfer',$api);
        self::assertStringContainsString('data-settlement-review-page',$page);
        self::assertStringContainsString('mark_release_ready',$js);
    }
}
