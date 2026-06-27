<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantFollowupTaskCenterTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source);
        return $source;
    }

    public function testCustomerProfileFollowupSectionExists(): void
    {
        $view = $this->source('includes/merchant-customer-profile-view.php');
        foreach (['data-profile-tab="followups"','data-profile-section="followups"','data-cp-followups-card','data-cp-followups','data-cp-followups-full','/merchant-followups.php'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
    }

    public function testFollowupActionsAreWiredInCustomerProfileJs(): void
    {
        $js = $this->source('assets/js/merchant-customer-profile.js');
        foreach (['/api/merchant/crm-followup-tasks.php','function renderFollowups','function followupAction','data-cp-followup-action','data-followup-id'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
    }

    public function testFollowupTaskApiIsMerchantScoped(): void
    {
        $api = $this->source('api/merchant/crm-followup-tasks.php');
        foreach (["ce.merchant_user_id=?","ce.event_type='crm.followup.created'",'merchant_crm_contacts WHERE public_id=? AND merchant_user_id=?',"WHERE ce.public_id=? AND ce.merchant_user_id=? AND ce.event_type='crm.followup.created'",'UPDATE campaign_events SET event_context_json=? WHERE public_id=? AND merchant_user_id=?'] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
    }

    public function testMerchantFollowupQueueExists(): void
    {
        $page = $this->source('merchant-followups.php');
        $view = $this->source('includes/merchant-followups-view.php');
        $js = $this->source('assets/js/merchant-followup-tasks.js');
        $css = $this->source('assets/css/merchant-followup-tasks.css');
        foreach (['data-sidebar-contract="mg-app-sidebar"','merchant-followup-tasks.css','merchant-followup-tasks.js','Follow-ups'] as $needle) {
            self::assertStringContainsString($needle, $page);
        }
        foreach (['data-merchant-followups-page','data-followup-filter="today"','data-followup-filter="overdue"','data-followup-filter="upcoming"','data-followup-filter="completed"','data-followup-queue'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        foreach (['/api/merchant/crm-followup-tasks.php','data-followup-action','data-followup-id','function runAction','function renderSummary'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-followups-page','.mg-followup-summary','.mg-followup-filters','.mg-followup-actions','.mg-cp-task-actions'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }
}
