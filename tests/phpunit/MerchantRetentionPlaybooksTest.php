<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantRetentionPlaybooksTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testPlaybookEngineDefinesAgentReadyRules(): void
    {
        $engine = $this->source('includes/merchant-crm-playbooks.php');
        foreach (['reward_unclaimed_after_3d','claimed_not_redeemed_after_3d','high_value_inactive_after_30d','contest_entrant_reward_invite','tip_thank_you_followup','agentic_ready','automation_level','recommended_next_action'] as $needle) {
            self::assertStringContainsString($needle, $engine);
        }
    }

    public function testPlaybookApisAreMerchantScoped(): void
    {
        $api = $this->source('api/merchant/crm-playbooks.php');
        $runner = $this->source('api/merchant/crm-playbook-runner.php');
        $engine = $this->source('includes/merchant-crm-playbooks.php');
        foreach (['mg_require_permission(\'merchant.campaigns.view\')','mg_merchant_ensure_workspace','mg_crm_playbook_scan'] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
        foreach (['mg_require_permission(\'merchant.campaigns.manage\')','mg_require_csrf_for_write','crm.playbook.triggered','crm.followup.created'] as $needle) {
            self::assertStringContainsString($needle, $runner . $engine);
        }
        foreach (['wi.merchant_user_id=?','cc.merchant_user_id=?','merchant_crm_contacts WHERE public_id=? AND merchant_user_id=?','campaign_events WHERE merchant_user_id=?'] as $needle) {
            self::assertStringContainsString($needle, $engine);
        }
    }

    public function testMerchantCrmLoadsRetentionTabAssets(): void
    {
        $page = $this->source('merchant-crm.php');
        $js = $this->source('assets/js/merchant-crm-retention-playbooks.js');
        $css = $this->source('assets/css/merchant-crm-retention-playbooks.css');
        foreach (['merchant-crm-retention-playbooks.css','merchant-crm-retention-playbooks.js'] as $needle) {
            self::assertStringContainsString($needle, $page);
        }
        foreach (['data-crm-tab-target","retention','Retention Playbooks','/api/merchant/crm-playbooks.php','/api/merchant/crm-playbook-runner.php','Triggered by playbook','Recommended Next Actions'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-retention-kpis','.mg-retention-grid','.mg-retention-playbook-card','.mg-retention-rec-card'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testCustomerProfileLoadsRetentionRecommendations(): void
    {
        $page = $this->source('merchant-customer.php');
        $js = $this->source('assets/js/merchant-customer-retention-recommendations.js');
        $css = $this->source('assets/css/merchant-crm-retention-playbooks.css');
        foreach (['merchant-customer-retention-recommendations.js','merchant-crm-retention-playbooks.css'] as $needle) {
            self::assertStringContainsString($needle, $page);
        }
        foreach (['data-cp-playbook-recommendations','Recommended Next Action','Triggered by playbook','Automation history','/api/merchant/crm-playbooks.php'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-cp-playbook-card','.mg-cp-playbook-list','.mg-cp-playbook-rec'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }
}
