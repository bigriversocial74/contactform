<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class ProductBundleProductionHardeningV12ContractTest extends TestCase{
 public function testContracts():void{
  $r=dirname(__DIR__,2);foreach(['database/20260720_product_bundle_production_hardening_v12.sql','api/bundles/_provider_reversal.php','scripts/process_bundle_provider_reversals.php','api/admin/bundle-production-hardening.php','admin/bundle-production-hardening.php'] as $f)self::assertFileExists($r.'/'.$f);
  $w=file_get_contents($r.'/scripts/process_bundle_provider_reversals.php');self::assertStringContainsString("PHP_SAPI !== 'cli'",$w);self::assertStringContainsString("'/reversals'",$w);self::assertStringContainsString('bundle-reversal-',$w);
  $a=file_get_contents($r.'/api/bundles/_provider_reversal.php');self::assertStringContainsString('MG_BUNDLE_REVERSAL_DISPATCH_ENABLED',$a);self::assertStringContainsString('MG_BUNDLE_REVERSAL_LIVE_ENABLED',$a);self::assertStringContainsString('gift_bundle_provider_dead_letters',$a);
  $m=file_get_contents($r.'/config/migrations.php');self::assertStringContainsString('20260720_product_bundle_production_hardening_v12.sql',$m);
 }
}
