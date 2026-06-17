<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionAdminDashboardFoundationTest extends TestCase
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

    public function testRealDatabaseBehaviorMatrix(): void
    {
        if((string)getenv('MG_RUN_ADMIN_DASHBOARD_BEHAVIOR')!=='1')self::markTestSkipped('Real-database admin dashboard behavior runs in the focused workflow.');
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed admin dashboard validation requires MG_DB_HOST.');
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->root.'/scripts/validate_admin_dashboard_behavior.php').' 2>&1';
        $output=[];$exitCode=0;exec($command,$output,$exitCode);$raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);self::assertIsArray($result,$raw);
        self::assertSame('admin_dashboard_foundation',$result['suite']??null);
        foreach(['super_admin_access','permission_partitioning','platform_aggregation','operations_aggregation','safe_recent_records','window_bounds','no_private_data','read_side_effect_free','stable_reads','bounded_queries'] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
        self::assertLessThanOrEqual(10,(int)($result['queries_per_full_read']??PHP_INT_MAX),$raw);
    }

    public function testEndpointIsAuthenticatedGetOnlyAndReadOnly(): void
    {
        $endpoint=$this->read('api/admin/dashboard.php');
        self::assertStringContainsString("mg_require_method('GET')",$endpoint);
        self::assertStringContainsString('mg_admin_dashboard_require_user()',$endpoint);
        self::assertStringContainsString('mg_admin_dashboard_read(',$endpoint);
        foreach(['mg_require_csrf_for_write(','mg_audit(','mg_event(','INSERT INTO','UPDATE ','DELETE FROM'] as $needle){
            self::assertStringNotContainsString($needle,$endpoint);
        }
    }

    public function testAggregationUsesExistingAuthoritiesAndPermissionPartitioning(): void
    {
        $service=$this->read('api/admin/_dashboard.php');
        foreach(['admin.users.view','admin.audit.view','admin.health.view','security.logs.view','admin.sessions.view','operational.alerts.view','demand.dashboard.view','merchant.payments.view','subscriptions.admin','microgift.operations.view','tips.reverse','super_admin'] as $permission){
            self::assertStringContainsString($permission,$service);
        }
        foreach(['mg_admin_dashboard_platform(','mg_admin_dashboard_commerce(','mg_admin_dashboard_operations(','mg_admin_dashboard_recent_alerts(','mg_admin_dashboard_recent_security(','mg_admin_dashboard_recent_audit(','mg_admin_dashboard_recent_checks(','mg_admin_dashboard_recent_incidents(','mg_admin_dashboard_latest_release('] as $needle){
            self::assertStringContainsString($needle,$service);
        }
        foreach(['INSERT INTO','UPDATE ','DELETE FROM','mg_audit(','mg_event(','mg_ledger_post('] as $needle){
            self::assertStringNotContainsString($needle,$service);
        }
    }

    public function testProjectionExcludesRawMetadataAndProviderState(): void
    {
        $queries=$this->read('api/admin/_dashboard_queries.php');
        foreach(['metadata_json','context_json','details_json','rollback_plan_json','provider_customer_id','provider_payment_method_ref','password_hash','session_hash'] as $needle){
            self::assertStringNotContainsString("'{$needle}' =>",$queries);
        }
        self::assertStringNotContainsString('SELECT *',$queries);
        self::assertStringContainsString('MG_ADMIN_DASHBOARD_RECENT_LIMIT',$queries);
        self::assertStringContainsString('information_schema.TABLES',$queries);
    }

    public function testAccountRouteLoadsDedicatedResponsiveFoundation(): void
    {
        $account=$this->read('account.php');
        $view=$this->read('includes/account/admin-dashboard.php');
        $script=$this->read('assets/js/admin-dashboard.js');
        $style=$this->read('assets/css/admin-dashboard.css');
        foreach(['/assets/css/admin-dashboard.css','/assets/js/admin-dashboard.js','includes/account/admin-dashboard.php'] as $needle)self::assertStringContainsString($needle,$account);
        foreach(['data-admin-dashboard','data-admin-overview','data-admin-platform','data-admin-commerce','data-admin-operations','data-admin-alerts','data-admin-incidents','data-admin-checks','data-admin-security','data-admin-audit','data-admin-shortcuts'] as $needle)self::assertStringContainsString($needle,$view);
        self::assertStringContainsString('/api/admin/dashboard.php?window_days=',$script);
        self::assertStringContainsString('@media(max-width:760px)',$style);
    }

    public function testCanonicalSecurityPermissionIsUsedAcrossNavigationAndApi(): void
    {
        foreach(['account.php','includes/header.php','api/admin/security-logs.php','api/admin/_dashboard.php'] as $path){
            self::assertStringContainsString('security.logs.view',$this->read($path),$path);
        }
    }

    public function testFocusedValidationIsRegistered(): void
    {
        $composer=$this->read('composer.json');
        $workflow=$this->read('.github/workflows/admin-dashboard-validation.yml');
        self::assertStringContainsString('"test-admin-dashboard-behavior": "php scripts/validate_admin_dashboard_behavior.php"',$composer);
        foreach(['MG_RUN_ADMIN_DASHBOARD_BEHAVIOR','composer test-admin-dashboard-behavior','ProductionAdminDashboardFoundationTest','build_full_upgrade_sql.php','composer test-frontend-contracts','composer test','npm run test:browser'] as $needle){
            self::assertStringContainsString($needle,$workflow);
        }
    }
}
