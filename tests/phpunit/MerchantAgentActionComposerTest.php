<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentActionComposerTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testComposerHelperDefinesEventsAndWorkflowOutputs(): void
    {
        $helper = $this->source('includes/merchant-agent-action-composer.php');
        foreach (['mg_agent_composer_queue','mg_agent_composer_find_item','mg_agent_composer_perform','mg_agent_composer_approval_items','mg_agent_composer_record','mg_agent_composer_message_item'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['crm.agent.composer.draft_created','crm.agent.composer.submitted_for_review','crm.agent.composer.message_seeded','crm.agent.composer.followup_seeded','crm.agent.message.draft.created'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['review_queue_action','message_draft','followup_task','campaign_repeat','customer_reactivation','expected_claims','expected_revenue_cents','expected_psr_impact_cents'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testComposerApisAreScoped(): void
    {
        $readApi = $this->source('api/merchant/agent-action-composer.php');
        foreach (['mg_require_method(\'GET\')', "mg_require_permission('merchant.campaigns.view')", 'mg_merchant_ensure_workspace', 'mg_agent_composer_queue'] as $needle) {
            self::assertStringContainsString($needle, $readApi);
        }
        $writeApi = $this->source('api/merchant/agent-action-compose.php');
        foreach (['mg_require_method(\'POST\')', "mg_require_permission('merchant.campaigns.manage')", 'mg_require_csrf_for_write', 'mg_agent_composer_perform'] as $needle) {
            self::assertStringContainsString($needle, $writeApi);
        }
    }

    public function testComposerPageAndAssetsAreWired(): void
    {
        $page = $this->source('merchant-agent-action-composer.php');
        $view = $this->source('includes/merchant-agent-action-composer-view.php');
        $js = $this->source('assets/js/merchant-agent-action-composer.js');
        $css = $this->source('assets/css/merchant-agent-action-composer.css');
        foreach (['Agent Action Composer','merchant-agent-action-composer.css','merchant-agent-action-composer.js','data-merchant-view="agent_composer"'] as $needle) {
            self::assertStringContainsString($needle, $page . $view);
        }
        foreach (['data-merchant-agent-composer','data-compose-action-type','data-compose-target','data-compose-claims','data-compose-message','data-compose-run'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        foreach (['/api/merchant/agent-action-composer.php','/api/merchant/agent-action-compose.php','create_draft','submit_for_review','seed_message','seed_followup'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-agent-composer-hero','.mg-agent-composer-kpis','.mg-agent-composer-card','.mg-agent-composer-form'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testGrowthPlannerAndApprovalQueueAreLinked(): void
    {
        $growthView = $this->source('includes/merchant-agent-growth-plan-view.php');
        foreach (['/merchant-agent-action-composer.php','Action Composer','Open action composer'] as $needle) {
            self::assertStringContainsString($needle, $growthView);
        }
        $approvals = $this->source('includes/merchant-agent-approvals.php');
        foreach (['merchant-agent-action-composer.php','mg_agent_composer_approval_items','source_type\' => \'composer'] as $needle) {
            self::assertStringContainsString($needle, $approvals);
        }
    }
}
