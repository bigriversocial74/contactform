<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionDemandOrchestrationOperationsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function read(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testMonitoringBehaviorAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed orchestration operations validation requires MG_DB_HOST.');
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->root.'/scripts/validate_demand_orchestration_operations_behavior.php').' 2>&1';
        $output=[];$exitCode=0;exec($command,$output,$exitCode);$raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);self::assertIsArray($result,$raw);
        self::assertSame('demand_orchestration_operations_monitoring',$result['suite']??null);
        foreach([
            'stale_claimed_detected','stale_running_detected','stale_approval_detected','stale_review_detected',
            'recent_failure_detected','critical_overdue_fails_readiness','admin_list_filters',
            'admin_detail_is_sanitized','checks_recorded','permission_admin_only','fixtures_clean',
        ] as $key){self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);}
    }

    public function testMigrationIsReadOnlyAndAdministratorScoped(): void
    {
        $sql=$this->read('database/stage_18b_demand_orchestration_operations.sql');
        self::assertStringContainsString('operations.orchestrations.view',$sql);
        self::assertStringContainsString("r.slug IN ('admin','super_admin')",$sql);
        self::assertStringContainsString('stage_18b_demand_orchestration_operations',$sql);
        foreach(['CREATE TABLE','retention_policies','action_type','retry','open_incident'] as $forbidden){
            self::assertStringNotContainsString($forbidden,$sql);
        }
    }

    public function testReadinessCoversStage17BAndOperationalStates(): void
    {
        $service=$this->read('api/operations/_operations.php');
        $admin=$this->read('api/admin/operations-readiness.php');
        $cli=$this->read('scripts/validate_launch_readiness.php');
        foreach(['stale_claimed','stale_running','stale_approval','stale_review','failed_recent','critical_overdue'] as $needle){
            self::assertStringContainsString($needle,$service);
        }
        foreach(['demand_orchestration_queue','demand_orchestration_failures','demand_orchestration_reviews'] as $needle){
            self::assertStringContainsString($needle,$service);
            self::assertStringContainsString('mg_operations_demand_orchestration_health(',$admin);
            self::assertStringContainsString('mg_operations_demand_orchestration_health(',$cli);
        }
        foreach(['stage_17b_demand_signal_agent_orchestration','stage_18b_demand_orchestration_operations','demand_signal_orchestrations','demand_signal_orchestration_events'] as $needle){
            self::assertStringContainsString($needle,$admin);
            self::assertStringContainsString($needle,$cli);
        }
    }

    public function testAdministratorEndpointsAreGetOnlyAndSanitized(): void
    {
        $list=$this->read('api/admin/operations-orchestrations.php');
        $detail=$this->read('api/admin/operations-orchestration.php');
        foreach([$list,$detail] as $source){
            self::assertStringContainsString("mg_require_method('GET')",$source);
            self::assertStringContainsString("mg_require_permission('operations.orchestrations.view')",$source);
            self::assertStringNotContainsString('mg_require_csrf_for_write(',$source);
            self::assertStringNotContainsString('UPDATE demand_signal_orchestrations',$source);
            self::assertStringNotContainsString('DELETE FROM demand_signal_orchestrations',$source);
        }
        $service=$this->read('api/operations/_operations.php');
        foreach(['dispatch_key','input_fingerprint','recommendation_json','payload_json'] as $sensitive){
            self::assertStringNotContainsString("SELECT {$sensitive}",$service);
        }
        self::assertStringContainsString('SELECT public_id,event_key,event_type,created_at',$service);
    }

    public function testStage18RegistrationAndFocusedValidation(): void
    {
        $builder=$this->read('scripts/build_full_upgrade_sql.php');
        $runner=$this->read('scripts/stage18.php');
        $smoke=$this->read('scripts/stage18_smoke.php');
        $composer=$this->read('composer.json');
        $workflow=$this->read('.github/workflows/demand-orchestration-operations-validation.yml');
        $stage18=strpos($builder,"'stage_18_production_hardening_launch_readiness.sql'");
        $stage18b=strpos($builder,"'stage_18b_demand_orchestration_operations.sql'");
        self::assertIsInt($stage18);self::assertIsInt($stage18b);self::assertLessThan($stage18b,$stage18);
        self::assertStringContainsString('stage_18b_demand_orchestration_operations.sql',$runner);
        self::assertStringContainsString('operations.orchestrations.view',$smoke);
        self::assertStringContainsString('"test-demand-orchestration-operations": "php scripts/validate_demand_orchestration_operations_behavior.php"',$composer);
        self::assertStringContainsString('composer test-demand-orchestration-operations',$workflow);
        self::assertStringContainsString('php scripts/build_full_upgrade_sql.php',$workflow);
        self::assertStringContainsString('php scripts/stage17_smoke.php',$workflow);
        self::assertStringContainsString('php scripts/stage18_smoke.php',$workflow);
    }

    public function testSectionTwoControlsAreNotIntroduced(): void
    {
        foreach([
            'api/operations/_operations.php','api/admin/operations-orchestrations.php',
            'api/admin/operations-orchestration.php','database/stage_18b_demand_orchestration_operations.sql',
        ] as $path){
            $source=$this->read($path);
            self::assertStringNotContainsString('retry_orchestration',$source);
            self::assertStringNotContainsString('open_incident',$source);
            self::assertStringNotContainsString('DELETE FROM demand_signal_orchestration_events',$source);
            self::assertStringNotContainsString('INSERT INTO retention_policies',$source);
        }
    }
}
