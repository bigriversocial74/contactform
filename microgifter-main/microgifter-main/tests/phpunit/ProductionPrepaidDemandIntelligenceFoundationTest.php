<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionPrepaidDemandIntelligenceFoundationTest extends TestCase
{
    private string $root;
    protected function setUp():void{$this->root=dirname(__DIR__,2);}
    private function read(string $path):string{$value=file_get_contents($this->root.'/'.$path);self::assertIsString($value,$path);return $value;}

    public function testDatabaseBehavior():void
    {
        if((string)getenv('MG_RUN_PREPAID_DEMAND_BEHAVIOR')!=='1')self::markTestSkipped('Focused database behavior disabled.');
        $output=[];$exit=0;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->root.'/scripts/validate_prepaid_demand_behavior.php').' 2>&1',$output,$exit);$raw=implode("\n",$output);
        self::assertSame(0,$exit,$raw);$result=json_decode($raw,true);self::assertSame('prepaid_demand_intelligence_foundation',$result['suite']??null);
        foreach(['created_from_purchase','buyer_authority','unpaid_excluded','scheduled_window','one_signal_per_gift','sent_transition','claimed_transition','redeemed_transition','refund_transition','expired_transition','customer_projection','batch_replay','rollback_clean'] as $key)self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
    }

    public function testCanonicalLifecycleAuthority():void
    {
        $sql=$this->read('database/stage_15c_prepaid_demand_commitments.sql');
        foreach(['microgift_demand_commitment_links','uq_microgift_demand_link_instance','uq_microgift_demand_link_signal','demand.commitments.view_own'] as $needle)self::assertStringContainsString($needle,$sql);
        $read=$this->read('api/demand/_prepaid_read.php');
        $write=$this->read('api/demand/_prepaid_write.php');
        $service=$read.$write;
        foreach([
            "'committed_demand'",'microgift_instances','microgift_redemptions','microgift_lifecycle_actions',
            'microgift-demand:','mg_demand_event(','scheduled_for','occasion_date','commerce_order_items',
            'commerce_orders','order_buyer_user_id','mg_prepaid_demand_has_purchase_authority',
            "['paid','partially_refunded','refunded','disputed']",'purchaser_user_id',
        ] as $needle)self::assertStringContainsString($needle,$service);
        self::assertStringNotContainsString('users.public_id',$service);
        self::assertStringNotContainsString('manual_intent',$service);
        self::assertStringContainsString("'user_id'=>\$purchaser",$write);
        self::assertStringNotContainsString("'user_id'=>(int)\$instance['issuer_user_id']",$write);
    }

    public function testPrivacyAndDemandDefinitions():void
    {
        $customer=$this->read('api/account/demand-commitments.php');
        foreach([
            'demand.commitments.view_own',"manual_intent_enabled'=>false",
            "commitment_requires_purchase'=>true",'Cache-Control: private, no-store',
            'commerce_order_items','commerce_orders','buyer_user_id',
        ] as $needle)self::assertStringContainsString($needle,$customer);
        $merchant=$this->read('api/merchant/prepaid-demand.php');
        foreach([
            "signal_type='committed_demand'",'minimum_cohort_size','customer_identity_exposed','recommendation_only',
            'awaiting_approval','demand_scope_snapshots','demand_signal_orchestrations','scope_suppressed',
            '$scopeSuppressed','HAVING COUNT(DISTINCT p.user_id)>=?',
        ] as $needle)self::assertStringContainsString($needle,$merchant);
    }

    public function testPagesAndClientsUseSafeCompleteStates():void
    {
        $customer=$this->read('commitments.php');
        foreach(['data-commitment-loading','data-commitment-signin','data-commitment-empty','data-commitment-error','data-commitment-retry','data-commitment-pagination'] as $needle)self::assertStringContainsString($needle,$customer);
        $merchant=$this->read('intelligence.php');
        foreach(['data-demand-kpis','data-demand-chart','data-demand-lifecycle','data-demand-products','data-demand-signals','data-demand-snapshot','data-demand-error'] as $needle)self::assertStringContainsString($needle,$merchant);
        $intelligence=$this->read('assets/js/intelligence.js');
        self::assertStringContainsString('/api/merchant/committed-demand.php',$intelligence);
        self::assertStringContainsString('/api/intelligence/overview.php',$intelligence);
        self::assertStringContainsString('scope_suppressed',$intelligence);
        foreach(['assets/js/demand-commitments.js','assets/js/intelligence.js'] as $path){$client=$this->read($path);self::assertStringContainsString('textContent',$client);self::assertStringContainsString('createElement',$client);self::assertStringNotContainsString('.innerHTML =',$client);self::assertStringNotContainsString('insertAdjacentHTML(',$client);}
    }

    public function testValidationRegistration():void
    {
        self::assertStringContainsString('$orderedMigrations = [',$this->read('scripts/build_full_upgrade_sql.php'));
        self::assertStringContainsString("'stage_15c_prepaid_demand_commitments.sql'",$this->read('scripts/build_full_upgrade_sql.php'));
        self::assertStringContainsString('test-prepaid-demand-behavior',$this->read('composer.json'));
        $workflow=$this->read('.github/workflows/prepaid-demand-intelligence-validation.yml');
        foreach(['MG_RUN_PREPAID_DEMAND_BEHAVIOR','composer test-prepaid-demand-behavior','ProductionPrepaidDemandIntelligenceFoundationTest','prepaid-demand-intelligence-foundation.spec.js','composer test','npm run test:browser'] as $needle)self::assertStringContainsString($needle,$workflow);
    }
}
