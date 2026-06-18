<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class Stage5DMerchantOperationsTest extends TestCase
{
 public function testOperationalSchemaDefinesCasesAndNotes():void{$sql=file_get_contents(dirname(__DIR__,2).'/database/stage_5d_merchant_pppm_operations.sql');self::assertIsString($sql);self::assertStringContainsString('merchant_pppm_cases',$sql);self::assertStringContainsString('merchant_pppm_notes',$sql);}
 public function testOrderAndItemQueriesAreMerchantScoped():void{foreach(['orders.php','pppm-items.php','pppm-item.php'] as $file){$source=file_get_contents(dirname(__DIR__,2).'/api/merchant/'.$file);self::assertIsString($source);self::assertStringContainsString('merchant_user_id',$source);}}
 public function testItemDetailLoadsLifecycleAndFulfillmentHistory():void{$source=file_get_contents(dirname(__DIR__,2).'/api/merchant/pppm-item.php');self::assertIsString($source);foreach(['pppm_item_events','pppm_assignments','pppm_delivery_schedules','pppm_deliveries','pppm_delivery_attempts','gift_claims'] as $table)self::assertStringContainsString($table,$source);}
 public function testOperationsUseExistingSnapshots():void{$source=file_get_contents(dirname(__DIR__,2).'/api/merchant/pppm-items.php');self::assertIsString($source);self::assertStringContainsString('title_snapshot',$source);self::assertStringContainsString('value_cents_snapshot',$source);self::assertStringContainsString('source_line_reference',$source);}
 public function testNotesRequirePermissionAndCsrf():void{$source=file_get_contents(dirname(__DIR__,2).'/api/merchant/pppm-note.php');self::assertIsString($source);self::assertStringContainsString("mg_require_permission('merchant.pppm.case.manage')",$source);self::assertStringContainsString('mg_require_csrf_for_write',$source);self::assertStringContainsString('merchant_user_id=?',$source);}
 public function testMerchantPagesReuseSharedShell():void{foreach(['merchant-pppm.php','merchant-pppm-item.php'] as $file){$source=file_get_contents(dirname(__DIR__,2).'/'.$file);self::assertIsString($source);self::assertStringContainsString('includes/merchant-workspace.php',$source);}}
}
