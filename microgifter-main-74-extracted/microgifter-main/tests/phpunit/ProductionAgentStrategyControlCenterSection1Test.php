<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionAgentStrategyControlCenterSection1Test extends TestCase
{
    private string $root;
    protected function setUp():void{$this->root=dirname(__DIR__,2);}
    private function read(string $path):string{$source=file_get_contents($this->root.'/'.$path);self::assertIsString($source,$path);return $source;}

    public function testRealDatabaseStrategyLifecycle():void
    {
        if((string)getenv('MG_RUN_AGENT_STRATEGY_CONTROL_BEHAVIOR')!=='1')self::markTestSkipped('Focused strategy behavior disabled.');
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database environment is required.');
        $output=[];$exit=0;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->root.'/scripts/validate_agent_strategy_control_behavior.php').' 2>&1',$output,$exit);$raw=implode("\n",$output);
        self::assertSame(0,$exit,$raw);$result=json_decode($raw,true);self::assertIsArray($result,$raw);self::assertSame('agent_strategy_control_center_section_1',$result['suite']??null);
        foreach(['draft_create','safe_projection','update_version','stale_update_rejected','activate','duplicate_transition','active_edit_rejected','pause','owner_isolation','retire','retired_reactivation_rejected','archived_agent_activation_rejected','rollback_clean'] as $key)self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
    }

    public function testStrategyServiceEnforcesOwnershipVersionsAndStateMachine():void
    {
        $service=$this->read('api/agents/_execution.php');
        foreach([
            'MG_AGENT_STRATEGY_TRIGGERS','MG_AGENT_STRATEGY_STATUSES','MG_AGENT_ACTIONS',
            'a.user_id=s.owner_user_id','version_no=version_no+1','Strategy changed since it was loaded',
            "['draft','paused']", "'active'=>in_array(\$current,['draft','paused'],true)",
            "'paused'=>\$current==='active'", "'retired'=>\$current!=='retired'",
            'Archived agents cannot run active strategies','mg_agent_strategy_projection',
        ] as $needle)self::assertStringContainsString($needle,$service);
    }

    public function testEndpointUsesSafePaginationRateLimitsAndAudits():void
    {
        $endpoint=$this->read('api/agents/strategies.php');
        foreach([
            "mg_require_permission('agent.strategies.manage')", "mg_rate_limit('agent.strategies.read'",
            "mg_rate_limit('agent.strategies.write'",'mg_agent_strategy_cursor_encode','mg_agent_strategy_cursor_decode',
            "['create','update','activate','pause','retire']",'mg_require_csrf_for_write','mg_audit(','mg_event(','Cache-Control: private, no-store',
        ] as $needle)self::assertStringContainsString($needle,$endpoint);
        foreach(["'owner_user_id'=>","'created_by_user_id'=>"] as $forbidden)self::assertStringNotContainsString($forbidden,$endpoint);
        self::assertStringContainsString("'agent_id'=>\$projected['agent']['id']",$endpoint);
    }

    public function testWorkspaceContainsCompleteStrategyStatesAndSafeClient():void
    {
        $page=$this->read('includes/agent-workspace.php');
        foreach([
            'data-agent-control-tab="strategies"','data-strategy-create','data-strategy-status','data-strategy-agent-filter',
            'data-strategy-loading','data-strategy-empty','data-strategy-error','data-strategy-retry','data-strategy-pagination',
            'data-strategy-editor','data-strategy-form','data-strategy-actions','data-strategy-save',
        ] as $needle)self::assertStringContainsString($needle,$page);
        $client=$this->read('assets/js/agent-strategies.js');
        foreach(['/api/agents/index.php?lifecycle=active','/api/agents/strategies.php','createElement','textContent','strategy.version','trigger_config','requires_approval'] as $needle)self::assertStringContainsString($needle,$client);
        foreach(['.innerHTML =','insertAdjacentHTML(','document.write(','eval('] as $unsafe)self::assertStringNotContainsString($unsafe,$client);
    }

    public function testBrowserAuthenticationFixtureIsTestingOnlyAndLocal():void
    {
        $fixture=$this->read('tests/browser/fixtures/authenticate-agent.php');
        foreach([
            "mg_env('MG_APP_ENV', '')", "MG_TEST_SKIP_AUTHENTICATED", "['127.0.0.1', '::1']",
            "\$environment !== 'testing'", '!$browserAuthEnabled', '!$isLoopback', 'http_response_code(404)',
            "\$_SESSION['mg_user']", "'agent.strategies.manage'", 'session_regenerate_id(true)',
            "Location: /agent.php?view=strategies", 'Cache-Control: private, no-store',
        ] as $needle)self::assertStringContainsString($needle,$fixture);
        self::assertStringNotContainsString("MG_APP_ENV', 'testing'",$fixture);
        self::assertStringNotContainsString('super_admin',$fixture);

        $browser=$this->read('tests/browser/agent-strategy-control-center-section-1.spec.js');
        self::assertStringContainsString('/tests/browser/fixtures/authenticate-agent.php',$browser);
        self::assertStringContainsString('/agent\\.php\\?view=strategies',$browser);
    }

    public function testResponsiveStrategyStylesAreRegistered():void
    {
        $page=$this->read('agent.php');$css=$this->read('assets/css/agent-strategies.css');
        self::assertStringContainsString('/assets/css/agent-strategies.css',$page);
        self::assertStringContainsString('/assets/js/agent-strategies.js',$page);
        foreach(['.mg-strategy-list','.mg-strategy-editor','.mg-strategy-action-grid','@media (max-width: 980px)','@media (max-width: 640px)'] as $needle)self::assertStringContainsString($needle,$css);
    }
}
