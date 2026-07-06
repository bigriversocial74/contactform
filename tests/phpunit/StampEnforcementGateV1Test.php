<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampEnforcementGateV1Test extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root() . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testServiceGateUsesConfiguredStampActions(): void
    {
        $stamps = $this->read('api/stamps/_stamps.php');

        foreach ([
            'function mg_stamp_require_service',
            'mg_stamp_service_catalog',
            'mg_stamp_enforcement_report',
            'mg_stamp_action($pdo, $actionKey)',
            'configured_stamp_value',
            'configured_action',
            'stamp_service_gate_v1',
            'stamp_debit_actions',
            'direct_reward_send',
            'regift_send',
            'campaign_feed_send',
            'email_list_send',
            'sms_send',
            'qr_claim_prompt_send',
            'agentic_discovery_send',
            'story_promotion',
            'campaign_ad_placement',
            'product_boost_publish',
            'bulk_crm_action',
        ] as $marker) {
            self::assertStringContainsString($marker, $stamps);
        }
    }

    public function testMerchantSendPathsUseServiceGate(): void
    {
        foreach ([
            'api/merchant/campaign-send.php',
            'api/merchant/crm-send-gift.php',
            'api/public/campaigns/_limits.php',
            'api/store/_canvas_rewards.php',
        ] as $path) {
            $source = $this->read($path);
            self::assertStringContainsString('mg_stamp_require_service', $source, $path);
            self::assertStringContainsString('stamp_service_gate_v1', $source, $path);
        }

        $campaign = $this->read('api/merchant/campaign-send.php');
        foreach (['campaign_feed_send','email_list_send','sms_send','qr_claim_prompt_send','agentic_discovery_send'] as $actionKey) {
            self::assertStringContainsString($actionKey, $campaign);
        }
    }

    public function testAdminEnforcementPageAndApiExist(): void
    {
        $page = $this->read('admin/stamp-enforcement.php');
        $js = $this->read('assets/js/admin-stamp-enforcement.js');
        $api = $this->read('api/stamps/enforcement.php');
        $sidebar = $this->read('includes/admin-sidebar.php');
        $health = $this->read('admin/stamp-health.php');

        foreach ([
            'Stamp enforcement audit',
            'data-stamp-enforcement-page',
            '/assets/js/admin-stamp-enforcement.js',
            'mg_stamp_require_service',
            'Stamp actions',
        ] as $marker) {
            self::assertStringContainsString($marker, $page);
        }

        foreach ([
            '/api/stamps/enforcement.php',
            'data-enforcement-list',
            'configured Stamp actions catalog',
            'marker_results',
            'resolved_status',
        ] as $marker) {
            self::assertStringContainsString($marker, $js);
        }

        foreach (['mg_stamp_enforcement_report', 'admin.commerce.view', 'stamps.enforcement_report_viewed'] as $marker) {
            self::assertStringContainsString($marker, $api);
        }

        self::assertStringContainsString('/admin/stamp-enforcement.php', $sidebar);
        self::assertStringContainsString('Stamp enforcement', $sidebar);
        self::assertStringContainsString('/admin/stamp-enforcement.php', $health);
        self::assertStringContainsString('Enforcement audit', $health);
    }
}
