<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PersonalGiftingAgentPhase2Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    public function testPhaseTwoSchemaIsNormalizedAndMigrationOrdered(): void
    {
        $sql=file_get_contents($this->root.'/database/20260714_personal_gifting_agent_phase2.sql');
        $manifest=file_get_contents($this->root.'/config/migrations.php');
        self::assertIsString($sql);
        self::assertIsString($manifest);
        foreach(['user_agent_settings','user_gifting_plans','user_gifting_plan_members','user_gifting_reminders','user_agent_memory','user_agent_threads','user_agent_messages'] as $table){
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        }
        self::assertLessThan(
            strpos($manifest,"'20260714_personal_gifting_agent_phase2.sql'"),
            strpos($manifest,"'20260714_user_contact_lists_phase1.sql'")
        );
        self::assertLessThan(
            strpos($manifest,"'20260714_personal_gifting_agent_phase2.sql'"),
            strpos($manifest,"'stage_19c_claude_sonnet_merchant_agent_planner.sql'")
        );
        self::assertLessThan(
            strpos($manifest,"'stage_19_merchant_market_snapshots.sql'"),
            strpos($manifest,"'20260714_personal_gifting_agent_phase2.sql'")
        );
    }

    public function testExistingAgentLayoutAndStickyComposerArePreserved(): void
    {
        $page=file_get_contents($this->root.'/agent.php');
        $workspace=file_get_contents($this->root.'/includes/agent-workspace.php');
        $css=file_get_contents($this->root.'/assets/css/personal-gifting-agent.css');
        self::assertIsString($page);
        self::assertIsString($workspace);
        self::assertIsString($css);
        self::assertStringContainsString("require __DIR__ . '/includes/agent-workspace.php';",$page);
        self::assertStringContainsString('data-agent-composer',$workspace);
        self::assertStringContainsString('data-personal-agent-composer',$workspace);
        self::assertStringContainsString('.mg-personal-agent-composer',$css);
        self::assertStringContainsString('position:sticky',$css);
    }

    public function testPersonalAgentNavigationIncludesRequiredCustomerDestinations(): void

    {
        $sidebar=file_get_contents($this->root.'/includes/personal-agent-sidebar.php');
        self::assertIsString($sidebar);
        foreach(['Inbox','My Feed','My Loyalty Cards','My Lists','My Saves','New Chat','Design','Calendar'] as $label) self::assertStringContainsString('<strong>'.$label.'</strong>',$sidebar);
        foreach(['/inbox.php','/feed.php','/loyalty-cards.php','/lists.php','/saves.php','/design-studio.php','/design-calendar.php'] as $route) self::assertStringContainsString($route,$sidebar);



    }

    public function testContextAndRecommendationsRemainPrivateAndApprovalFirst(): void
    {
        $service='';
        foreach(['includes/personal-gifting-agent.php','includes/personal-agent/core.php','includes/personal-agent/data.php','includes/personal-agent/context.php','includes/personal-agent/actions.php','includes/personal-agent/chat.php'] as $path){
            $part=file_get_contents($this->root.'/'.$path);
            self::assertIsString($part);
            $service.=$part;
        }
        self::assertStringContainsString('mg_user_contact_list_eligibility_detail',$service);
        self::assertStringContainsString('owner_user_id=?',$service);
        self::assertStringContainsString("'phone_masked'",$service);
        self::assertStringContainsString('unset($details[$privateDisplayKey])',$service);
        self::assertStringNotContainsString("'phone_ciphertext'",$service);
        self::assertStringNotContainsString("'address_line_1'",$service);
        self::assertStringContainsString('Nothing will be purchased or sent without your review.',$service);
        self::assertStringContainsString("'save_draft_plan'",$service);
        self::assertStringNotContainsString('INSERT INTO commerce_orders',$service);
        self::assertStringNotContainsString('INSERT INTO pppm_items',$service);
    }

    public function testAllWriteApisRequireAuthenticationAndCsrf(): void
    {
        foreach(['chat.php','plans.php','reminders.php','memory.php','dates.php','settings.php'] as $file){
            $content=file_get_contents($this->root.'/api/user-agent/'.$file);
            self::assertIsString($content);
            self::assertStringContainsString('mg_require_api_user',$content,$file);
            self::assertStringContainsString('mg_require_csrf_for_write',$content,$file);
        }
    }

    public function testClaudeIntegrationHasDeterministicFallback(): void
    {
        $service='';
        foreach(['includes/personal-gifting-agent.php','includes/personal-agent/core.php','includes/personal-agent/data.php','includes/personal-agent/context.php','includes/personal-agent/actions.php','includes/personal-agent/chat.php'] as $path){
            $part=file_get_contents($this->root.'/'.$path);
            self::assertIsString($part);
            $service.=$part;
        }
        self::assertStringContainsString('mg_anthropic_messages',$service);
        self::assertStringContainsString('mg_ai_enforce_rate_limits',$service);
        self::assertStringContainsString("\$provider['id']=(int)\$model['provider_id']",$service);
        self::assertStringContainsString('foreach ($rows as $row)',$service);
        self::assertStringContainsString('function mg_personal_agent_fallback',$service);
        self::assertStringContainsString("mg_security_log('warning','user_agent.ai_fallback'",$service);
    }
}
