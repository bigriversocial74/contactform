<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class PersonalGiftingWorkflowsPhase3Test extends TestCase
{
    private string $root;
    protected function setUp():void{$this->root=dirname(__DIR__,2);}
    private function read(string $path):string{$value=file_get_contents($this->root.'/'.$path);self::assertIsString($value);return $value;}
    public function testSchemaAndMigrationOrder():void
    {
        $sql=$this->read('database/20260714_personal_gifting_workflows_phase3.sql');$manifest=$this->read('config/migrations.php');
        foreach(['user_gifting_schedules','user_recurring_gift_programs','user_recurring_gift_runs','user_group_gifts','user_group_gift_participants','user_recipient_data_requests','user_contact_profile_import_fields','user_gift_bundles','user_gift_bundle_items','user_gift_lifecycle_reminders'] as $table)self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        self::assertLessThan(strpos($manifest,"'20260714_personal_gifting_workflows_phase3.sql'"),strpos($manifest,"'20260714_personal_gifting_agent_phase2.sql'"));
        self::assertLessThan(strpos($manifest,"'stage_19_merchant_market_snapshots.sql'"),strpos($manifest,"'20260714_personal_gifting_workflows_phase3.sql'"));
    }
    public function testRecurringAndGroupBoundaries():void
    {
        $actions=$this->read('includes/personal-agent/workflows-actions.php');$group=$this->read('includes/personal-agent/workflows-group.php');$sql=$this->read('database/20260714_personal_gifting_workflows_phase3.sql');
        self::assertStringContainsString("ENUM('draft_plan_only')",$sql);self::assertStringContainsString('idempotency_key',$actions);self::assertStringContainsString('mg_personal_workflows_expand_list_plan',$actions);self::assertStringContainsString('mg_user_contact_list_eligibility_detail',$group);self::assertStringContainsString("'payment_collected'=>false",$group);self::assertStringNotContainsString('payment_method',$group);
    }
    public function testRecipientConsentSupportsSelectiveRevocation():void
    {
        $service=$this->read('includes/personal-agent/workflows-requests.php');
        self::assertStringContainsString('allow_profile_import_requests',$service);self::assertStringContainsString('user_contact_profile_permissions',$service);self::assertStringContainsString('user_contact_profile_import_fields',$service);self::assertStringContainsString('hash_equals',$service);self::assertStringContainsString('mg_personal_workflows_value_hash',$service);
    }
    public function testBundlesAndLifecycleNeverExecuteCommerce():void
    {
        $bundle=$this->read('includes/personal-agent/workflows-bundles.php');$life=$this->read('includes/personal-agent/workflows-lifecycle.php');$all=$bundle.$life;
        self::assertStringContainsString("cp.status='published'",$bundle);self::assertStringContainsString('mg_personal_workflows_notify',$life);self::assertStringContainsString("'gift_state_changed'=>false",$life);self::assertStringNotContainsString('INSERT INTO commerce_orders',$all);self::assertStringNotContainsString('UPDATE gift_claims',$all);self::assertStringNotContainsString('UPDATE gifts SET',$all);
    }
    public function testWorkflowApisAreProtectedAndUiPreservesComposer():void
    {
        foreach(['schedules.php','recurring-programs.php','list-plan-members.php','group-gifts.php','recipient-requests.php','bundles.php','lifecycle-reminders.php'] as $file){$api=$this->read('api/user-agent/'.$file);self::assertStringContainsString('mg_require_permission',$api);self::assertStringContainsString('mg_require_csrf_for_write',$api);}
        $workspace=$this->read('includes/agent-workspace.php');$sidebar=$this->read('includes/personal-agent-sidebar.php');self::assertStringContainsString('data-agent-composer',$workspace);self::assertStringContainsString('data-phase3-schema-ready',$workspace);self::assertStringContainsString("'label' => 'Scheduled Gifts'",$sidebar);self::assertStringContainsString("'label' => 'Claim & Redemption'",$sidebar);
    }
}
