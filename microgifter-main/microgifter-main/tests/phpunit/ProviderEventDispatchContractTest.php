<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ProviderEventDispatchContractTest extends TestCase
{
    public function testProviderDispatchAuthorityDefinesDomainRoutingContracts(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/integrations/_provider_dispatch.php');
        self::assertIsString($source);
        foreach(['provider_dispatch_routes','mg_provider_dispatch','mg_provider_intake_dispatch','payment_dispute','delivery_callback','payout_callback','ops_review','mg_ops_alert_upsert','mg_webhook_intake'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
