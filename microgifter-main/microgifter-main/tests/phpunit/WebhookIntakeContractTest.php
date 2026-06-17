<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WebhookIntakeContractTest extends TestCase
{
    public function testWebhookIntakeAuthorityDefinesReplayQuarantineContracts(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/integrations/_webhook_intake.php');
        self::assertIsString($source);
        foreach(['provider_webhook_events','provider_webhook_quarantine','mg_webhook_intake','mg_webhook_verify_signature','mg_webhook_quarantine','payload_hash','conflicting_replay','retryable','quarantined'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
