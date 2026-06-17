<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage11FActionCenterReconciliationTest extends TestCase
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

    public function testReconciliationUsesCanonicalProjectionAuthority(): void
    {
        $source=$this->read('api/account/_action_center_reconciliation.php');
        self::assertStringContainsString('mg_action_center_project_lifecycle(',$source);
        self::assertStringContainsString('mg_action_center_recipient_folder(',$source);
        self::assertStringNotContainsString('INSERT INTO microgift_inbox_items',$source);
        self::assertStringNotContainsString('UPDATE microgift_instances',$source);
    }

    public function testReconciliationDetectsMissingMismatchAndOrphanDrift(): void
    {
        $source=$this->read('api/account/_action_center_reconciliation.php');
        foreach(["'missing'","'mismatch'","'orphan'"] as $type){
            self::assertStringContainsString($type,$source);
        }
        self::assertStringContainsString("'sent'",$source);
        self::assertStringContainsString('mg_action_center_recipient_folder',$source);
    }

    public function testRepairsRunInsidePerInstanceTransactions(): void
    {
        $source=$this->read('api/account/_action_center_reconciliation.php');
        $begin=strpos($source,'$pdo->beginTransaction()');
        $project=strpos($source,'mg_action_center_reconcile_instance(',$begin);
        $commit=strpos($source,'$pdo->commit()',$project);
        self::assertIsInt($begin);
        self::assertIsInt($project);
        self::assertIsInt($commit);
        self::assertLessThan($project,$begin);
        self::assertLessThan($commit,$project);
        self::assertStringContainsString('$pdo->rollBack()',$source);
    }

    public function testAuditAndRepairModesAreBoundedAndRepeatable(): void
    {
        $source=$this->read('api/account/_action_center_reconciliation.php');
        self::assertStringContainsString('max(1,min($limit,500))',$source);
        self::assertStringContainsString("$repair?'repair':'audit'",$source);
        self::assertStringContainsString('next_after_id',$source);
        self::assertStringContainsString('has_more',$source);
    }

    public function testAdminAndCliEntryPointsUseSharedService(): void
    {
        $admin=$this->read('api/admin/action-center-reconcile.php');
        self::assertStringContainsString("mg_require_permission('microgift.lifecycle.manage')",$admin);
        self::assertStringContainsString('mg_require_csrf_for_write(',$admin);
        self::assertStringContainsString('mg_action_center_reconcile_batch(',$admin);
        $cli=$this->read('scripts/reconcile_action_center.php');
        self::assertStringContainsString('mg_action_center_reconcile_batch(',$cli);
        self::assertStringContainsString("'repair'",$cli);
        self::assertStringContainsString("'all'",$cli);
    }
}
