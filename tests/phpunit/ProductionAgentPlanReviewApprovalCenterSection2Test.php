<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionAgentPlanReviewApprovalCenterSection2Test extends TestCase
{
    private string $root;
    protected function setUp():void{$this->root=dirname(__DIR__,2);}
    private function read(string $path):string{$source=file_get_contents($this->root.'/'.$path);self::assertIsString($source,$path);return $source;}

    public function testRealDatabaseApprovalLifecycle():void
    {
        if((string)getenv('MG_RUN_AGENT_APPROVAL_CENTER_BEHAVIOR')!=='1')self::markTestSkipped('Focused approval behavior disabled.');
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database environment is required.');
        $output=[];$exit=0;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->root.'/scripts/validate_agent_approval_center_behavior.php').' 2>&1',$output,$exit);$raw=implode("\n",$output);
        self::assertSame(0,$exit,$raw);$result=json_decode($raw,true);self::assertIsArray($result,$raw);self::assertSame('agent_plan_review_approval_center_section_2',$result['suite']??null);
        foreach(['plan_created','strategy_version_snapshot','safe_plan_projection','safe_approval_projection','high_risk_reason_required','approve','duplicate_approve','conflicting_decision','owner_isolation','reject_with_reason','expiration','partial_reconciliation','events_recorded','rollback_clean'] as $key)self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
    }

    public function testPlanProjectionIsOwnerScopedAndPresentationSafe():void
    {
        $workflow=$this->read('api/agents/_workflow.php');
        foreach([
            'mg_agent_safe_request','mg_agent_safe_run_input','mg_agent_plan_projection','mg_agent_approval_projection',
            'r.public_id=? AND r.owner_user_id=?','strategy_version','approval_required','expiring_soon',
            "['reason','prompt','source','summary','demand_signal_id','distribution_program_id']",
        ] as $needle)self::assertStringContainsString($needle,$workflow);
        foreach(["'owner_user_id'=>","'request_json'=>","'input_json'=>","'idempotency_key'=>"] as $forbidden)self::assertStringNotContainsString($forbidden,$workflow);
    }

    public function testApprovalAuthorityEnforcesExpiryRiskReasonAndIdempotency():void
    {
        $workflow=$this->read('api/agents/_workflow.php');
        foreach([
            'mg_agent_expire_approvals','approval_expired','Approval request expired.',
            "in_array((string)\$approval['risk_level'],['high','critical'],true)",
            'A decision reason is required for high-risk actions.',
            "if((string)\$approval['status']===\$targetStatus)",
            'Approval decision conflicts with the recorded decision.',
            'mg_agent_reconcile_approval_run',
        ] as $needle)self::assertStringContainsString($needle,$workflow);
    }

    public function testApprovalAndPlanEndpointsAreBoundedPrivateAndAudited():void
    {
        $approvals=$this->read('api/agents/approvals.php');
        foreach([
            "mg_require_permission('agent.approvals.decide')",'mg_agent_approval_cursor_encode','mg_agent_approval_cursor_decode',
            "max(1,min((int)(\$_GET['limit']??20),50))",'bulk_approval_enabled','high_risk_reason_required',
            "mg_rate_limit('agent.approvals.read'", "mg_rate_limit('agent.approvals.write'",'mg_require_csrf_for_write',
            'mg_audit(','mg_event(','Cache-Control: private, no-store',
        ] as $needle)self::assertStringContainsString($needle,$approvals);
        $plans=$this->read('api/agents/plans.php');
        foreach(["mg_require_permission('agent.approvals.decide')","mg_rate_limit('agent.plans.read'",'individual_decisions_only','financial_actions_enabled','Cache-Control: private, no-store'] as $needle)self::assertStringContainsString($needle,$plans);
    }

    public function testWorkspaceContainsCompleteApprovalAndPlanStates(): void

    {
        $page=$this->read('merchant-agent-approvals.php');
        $view=$this->read('includes/merchant-agent-approvals-view.php');
        $client=$this->read('assets/js/merchant-agent-approvals.js');
        foreach(['merchant-agent-approvals.css','merchant-agent-approvals.js','data-merchant-view="agent_approvals"'] as $needle) self::assertStringContainsString($needle,$page);
        foreach(['data-merchant-agent-approvals','data-agent-approval-status','data-agent-approval-filter','data-agent-approval-list'] as $needle) self::assertStringContainsString($needle,$view);
        foreach(['/api/merchant/agent-approvals.php','/api/merchant/agent-approval-action.php','data-approval-action','mg:agent-approvals:updated'] as $needle) self::assertStringContainsString($needle,$client);



    }

    public function testPlanSnapshotAndResponsiveAssetsAreRegistered(): void

    {
        $execution=$this->read('api/agents/_execution.php');
        foreach(['strategy_version','strategy_objective','planned_at'] as $needle) self::assertStringContainsString($needle,$execution);
        $page=$this->read('merchant-agent-approvals.php');$css=$this->read('assets/css/merchant-agent-approvals.css');
        self::assertStringContainsString('/assets/css/merchant-agent-approvals.css',$page);
        self::assertStringContainsString('/assets/js/merchant-agent-approvals.js',$page);
        foreach(['.mg-agent-approvals-page','.mg-agent-approvals-grid','@media'] as $needle) self::assertStringContainsString($needle,$css);



    }
}
