<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class ProductBundleStagedActivationV13ContractTest extends TestCase{
 public function testContracts():void{
  $r=dirname(__DIR__,2);foreach(['database/20260721_product_bundle_staged_activation_monitoring_v13.sql','api/bundles/_release_readiness.php','scripts/capture_bundle_release_health.php','api/admin/bundle-release-readiness.php','admin/bundle-release-readiness.php'] as $f)self::assertFileExists($r.'/'.$f);
  $a=file_get_contents($r.'/api/bundles/_release_readiness.php');self::assertStringContainsString('emergency_stop',$a);self::assertStringContainsString('traffic_percent',$a);self::assertStringContainsString('mg_bundle_release_assert_runtime_allowed',$a);
  $s=file_get_contents($r.'/database/20260721_product_bundle_staged_activation_monitoring_v13.sql');self::assertStringContainsString('gift_bundle_release_health_snapshots',$s);self::assertStringContainsString('gift_bundle_release_events',$s);
  $m=file_get_contents($r.'/config/migrations.php');self::assertStringContainsString('20260721_product_bundle_staged_activation_monitoring_v13.sql',$m);
 }
}
