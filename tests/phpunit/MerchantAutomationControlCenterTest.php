<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAutomationControlCenterTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testAutomationControlHelpersDefineGuardrailsAndSettings(): void
    {
        $helper = $this->source('includes/merchant-automation-controls.php');
        foreach (['monitor_only','recommend_action','create_task','draft_message','execute_with_approval','fully_automated_later'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['agent_can_monitor','agent_can_recommend','agent_can_create_task','agent_requires_approval','max_actions_per_day','crm.automation.settings.updated'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testAutomationApisAreMerchantScopedAndAudited(): void
    {
        $settings = $this->source('api/merchant/automation-settings.php');
        $log = $this->source('api/merchant/automation-log.php');
        $helper = $this->source('includes/merchant-automation-controls.php');
        foreach (["mg_require_permission('merchant.campaigns.view')","mg_require_permission('merchant.campaigns.manage')",'mg_require_csrf_for_write','mg_merchant_ensure_workspace','mg_automation_save_settings'] as $needle) {
            self::assertStringContainsString($needle, $settings);
        }
        foreach (["mg_require_permission('merchant.campaigns.view')",'mg_automation_log','crm.automation.approval.'] as $needle) {
            self::assertStringContainsString($needle, $log);
        }
        foreach (['WHERE merchant_user_id=?', 'ce.merchant_user_id=?', 'INSERT INTO campaign_events', 'crm.automation.approval.granted'] as $needle) {
            self::assertStringContainsString($needle, $helper . $this->source('api/merchant/crm-playbook-runner.php'));
        }
    }

    public function testMerchantAutomationPageAndAssetsAreWired(): void
    {
        $page = $this->source('merchant-automation.php');
        $view = $this->source('includes/merchant-automation-view.php');
        $js = $this->source('assets/js/merchant-automation-control.js');
        $css = $this->source('assets/css/merchant-automation-control.css');
        foreach (['Automation Control Center','merchant-automation-control.css','merchant-automation-control.js','data-merchant-view="automation"'] as $needle) {
            self::assertStringContainsString($needle, $page . $view);
        }
        foreach (['data-merchant-automation-page','data-automation-save','data-automation-settings','data-automation-log','agent_can_monitor','agent_requires_approval'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        foreach (['/api/merchant/automation-settings.php','/api/merchant/automation-log.php','data-auto-field','max_actions_per_day','Automation guardrails saved'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-automation-hero','.mg-automation-kpis','.mg-automation-table','.mg-automation-log-table'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testRetentionAndCustomerRecommendationsLinkToAutomationControls(): void
    {
        $retention = $this->source('assets/js/merchant-crm-retention-playbooks.js');
        $customer = $this->source('assets/js/merchant-customer-retention-recommendations.js');
        foreach (['/merchant-automation.php','Automation Controls','Manage guardrails','approval-gated'] as $needle) {
            self::assertStringContainsString($needle, $retention);
        }
        foreach (['/merchant-automation.php','Automation controls','data-cp-automation-controls'] as $needle) {
            self::assertStringContainsString($needle, $customer);
        }
    }
}
