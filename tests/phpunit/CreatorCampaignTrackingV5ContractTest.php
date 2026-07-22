<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignTrackingV5ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function read(string $path): string
    {
        $value=file_get_contents($this->root.'/'.$path);
        self::assertIsString($value,$path);
        return $value;
    }

    public function testSchemaAndPhaseBoundary(): void
    {
        $sql=$this->read('database/20260722_creator_campaign_tracking_attribution_v5.sql');
        foreach(['creator_campaign_tracking_sources','creator_campaign_tracking_events','creator_campaign_attributions','creator_campaign_attribution_events'] as $table){
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        }
        self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS creator_campaign_payouts',$sql);
        self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS creator_campaign_earning_events',$sql);
    }

    public function testPrivacyAndReplayControls(): void
    {
        $definitions=$this->read('includes/creator-campaigns/tracking-definitions.php');
        $repository=$this->read('includes/creator-campaigns/tracking-repository.php');
        self::assertStringContainsString('hash_hmac',$definitions);
        self::assertStringContainsString('rapid_replay',$repository);
        self::assertStringContainsString('high_velocity',$repository);
        self::assertStringContainsString('request_replay',$repository);
    }

    public function testAuthorizationAndPublicRestrictions(): void
    {
        $merchant=$this->read('api/merchant/creator-campaign-tracking.php');
        $creator=$this->read('api/creator/campaign-tracking.php');
        $public=$this->read('api/public/creator-campaign-events.php');
        self::assertStringContainsString('mg_require_csrf_for_write',$merchant);
        self::assertStringContainsString('mg_require_csrf_for_write',$creator);
        self::assertStringContainsString('mg_creator_campaign_tracking_browser_event_types',$public);
        self::assertStringNotContainsString("'purchase'",$public);
    }

    public function testAttributionLifecycleAndWorkspaces(): void
    {
        $service=$this->read('includes/creator-campaigns/attribution-service.php');
        $merchantJs=$this->read('assets/js/merchant-creator-campaign-tracking.js');
        $creatorJs=$this->read('assets/js/creator-campaign-tracking.js');
        self::assertStringContainsString('mg_creator_campaign_attribution_decide',$service);
        self::assertStringContainsString('mg_creator_campaign_attribution_override_merchant',$service);
        self::assertStringContainsString('mg_creator_campaign_tracking_invalidate_event_merchant',$service);
        self::assertStringContainsString('touch_event_id',$service);
        self::assertStringContainsString('override_attribution',$merchantJs);
        self::assertStringContainsString('retire_source',$creatorJs);
    }
}
