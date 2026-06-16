<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage4EDistributionExternalInputsTest extends TestCase
{
    public function testSchemaDefinesProgramsSourcesRecipientsAllocationsJobsAndMetrics(): void
    {
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_4e_distribution_external_inputs.sql');
        self::assertIsString($sql);
        foreach(['distribution_programs','distribution_program_products','distribution_source_connections','distribution_source_events','distribution_recipients','distribution_allocations','distribution_issuance_jobs','distribution_webhook_attempts','distribution_daily_metrics'] as $table){
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        }
        self::assertStringContainsString('uq_distribution_source_events_idempotency',$sql);
        self::assertStringContainsString('uq_distribution_issuance_allocation_sequence',$sql);
    }

    public function testProgramsSupportAllRequiredInputClassesAndCapacityControls(): void
    {
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_4e_distribution_external_inputs.sql');
        $helper=file_get_contents(dirname(__DIR__,2).'/api/distribution/_distribution.php');
        self::assertIsString($sql);
        self::assertIsString($helper);
        foreach(['purchase','merchant_grant','contest','giveaway','fundraiser','workplace_reward','gaming','external_api','batch'] as $type){
            self::assertStringContainsString("'{$type}'",$sql);
        }
        self::assertStringContainsString('Distribution item limit reached.',$helper);
        self::assertStringContainsString('Distribution budget is insufficient.',$helper);
    }

    public function testSourceEventsAreCanonicalAndMerchantScopedIdempotent(): void
    {
        $helper=file_get_contents(dirname(__DIR__,2).'/api/distribution/_distribution.php');
        $ingest=file_get_contents(dirname(__DIR__,2).'/api/distribution/ingest.php');
        self::assertIsString($helper);
        self::assertIsString($ingest);
        self::assertStringContainsString("'idempotency_key' => hash('sha256'",$helper);
        self::assertStringContainsString('payload_checksum',$ingest);
        self::assertStringContainsString('Idempotency key conflict: event payload changed.',$ingest);
        self::assertStringContainsString("'duplicate'=>true",$ingest);
    }

    public function testWebhookUsesTimestampedHmacAndReplayWindow(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/distribution/webhook.php');
        self::assertIsString($source);
        self::assertStringContainsString('HTTP_X_MICROGIFTER_SIGNATURE',$source);
        self::assertStringContainsString('HTTP_X_MICROGIFTER_TIMESTAMP',$source);
        self::assertStringContainsString('abs(time()-(int)$timestamp)>300',$source);
        self::assertStringContainsString('MG_DISTRIBUTION_WEBHOOK_SECRET',$source);
        self::assertStringContainsString('hash_hmac(\'sha256\',$timestamp.\'.\'.$raw,$connectionSecret)',$source);
        self::assertStringContainsString('Webhook already processed.',$source);
    }

    public function testAllocationExpandsQuantityIntoOneJobPerPppmEnvelope(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/distribution/allocate.php');
        self::assertIsString($source);
        self::assertStringContainsString('for($i=1;$i<=$quantity;$i++)',$source);
        self::assertStringContainsString('distribution_issuance_jobs',$source);
        self::assertStringContainsString('Recipient allocation limit reached.',$source);
        self::assertStringContainsString('Program product inventory is insufficient.',$source);
        self::assertStringNotContainsString('INSERT INTO pppm_items',$source);
    }

    public function testIssuanceWorkerContractBindsExistingPppmItemsAndDeadLetters(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/distribution/issuance-jobs.php');
        self::assertIsString($source);
        self::assertStringContainsString("action==='claim'",$source);
        self::assertStringContainsString("action==='complete'",$source);
        self::assertStringContainsString("action==='fail'",$source);
        self::assertStringContainsString("'dead_letter'",$source);
        self::assertStringContainsString('SELECT id FROM pppm_items WHERE public_id=?',$source);
        self::assertStringNotContainsString('INSERT INTO pppm_items',$source);
    }

    public function testRecipientContactMatchingUsesKeyedHashes(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/distribution/recipients.php');
        $helper=file_get_contents(dirname(__DIR__,2).'/api/distribution/_distribution.php');
        self::assertIsString($source);
        self::assertIsString($helper);
        self::assertStringContainsString('mg_distribution_hash($email)',$source);
        self::assertStringContainsString('mg_distribution_hash($phone)',$source);
        self::assertStringContainsString("hash_hmac('sha256'",$helper);
        self::assertStringContainsString('MG_DISTRIBUTION_HASH_SECRET',$helper);
    }

    public function testWinnerSelectionStoresAuditableCommitment(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/distribution/select-winners.php');
        self::assertIsString($source);
        self::assertStringContainsString('random_bytes(32)',$source);
        self::assertStringContainsString('seed_commitment',$source);
        self::assertStringContainsString('hmac_sha256_lowest_score',$source);
        self::assertStringContainsString('deterministic_random_selection',$source);
    }

    public function testAnalyticsAggregateSourceAllocationAndIssuanceFacts(): void
    {
        $aggregate=file_get_contents(dirname(__DIR__,2).'/scripts/aggregate_stage4e_distribution.php');
        $api=file_get_contents(dirname(__DIR__,2).'/api/distribution/analytics.php');
        self::assertIsString($aggregate);
        self::assertIsString($api);
        self::assertStringContainsString('INSERT INTO distribution_daily_metrics',$aggregate);
        self::assertStringContainsString('items_issued',$aggregate);
        self::assertStringContainsString('issued_value_cents',$aggregate);
        self::assertStringContainsString('distribution.analytics.view',$api);
    }

    public function testDocumentationPreservesPppmCreationBoundary(): void
    {
        $notes=file_get_contents(dirname(__DIR__,2).'/docs/stage-4e-distribution-external-inputs.md');
        self::assertIsString($notes);
        self::assertStringContainsString('does not bypass PPPM',$notes);
        self::assertStringContainsString('one issuance job per future PPPM envelope',$notes);
        self::assertStringContainsString('call the existing PPPM item-creation service',$notes);
    }
}
