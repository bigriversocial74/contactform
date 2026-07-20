<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase41RecurringProgramsV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    public function testPhaseFourExtendsExistingRecurringAuthorityInsteadOfDuplicatingIt(): void
    {
        $migration=file_get_contents($this->root.'/database/20260720_task_agent_phase4_v1.sql');
        $canonicalSchema=file_get_contents($this->root.'/database/20260714_personal_gifting_workflows_phase3.sql');
        $service=file_get_contents($this->root.'/includes/task-agent-recurring-programs.php');
        foreach([$migration,$canonicalSchema,$service] as $source)self::assertIsString($source);

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS user_recurring_gift_programs',$canonicalSchema);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS user_recurring_gift_runs',$canonicalSchema);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS multi_agent_recurring_program_links',$migration);
        self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS user_recurring_gift_programs',$migration);
        self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS user_recurring_gift_runs',$migration);
        foreach([
            'mg_personal_workflows_create_recurring_program',
            'mg_personal_workflows_update_recurring_program',
            'mg_personal_workflows_generate_recurring_draft',
            'mg_personal_workflows_skip_recurring_run',
        ] as $authority)self::assertStringContainsString($authority,$service);
    }

    public function testProgramsAreScopedToAuthenticatedOwnerAndSelectedAgent(): void
    {
        $service=file_get_contents($this->root.'/includes/task-agent-recurring-programs.php');
        $migration=file_get_contents($this->root.'/database/20260720_task_agent_phase4_v1.sql');
        foreach([$service,$migration] as $source)self::assertIsString($source);
        foreach([
            'WHERE link.owner_user_id=? AND link.agent_id=?',
            'rp.owner_user_id=link.owner_user_id',
            'link.owner_user_id=? AND link.agent_id=? AND rp.public_id=?',
            'agent_id,owner_user_id,program_id',
            'fk_multi_agent_recurring_link_agent',
            'fk_multi_agent_recurring_link_owner',
            'fk_multi_agent_recurring_link_program',
        ] as $marker)self::assertStringContainsString($marker,$service.$migration);
    }

    public function testRecurringCyclesRemainDraftOnlyApprovalFirstAndNonFinancial(): void
    {
        $schema=file_get_contents($this->root.'/database/20260714_personal_gifting_workflows_phase3.sql');
        $service=file_get_contents($this->root.'/includes/task-agent-recurring-programs.php');
        $router=file_get_contents($this->root.'/includes/task-agent-recurring-programs-router.php');
        $skip=file_get_contents($this->root.'/includes/personal-agent/workflows-recurring-hardening.php');
        foreach([$schema,$service,$router,$skip] as $source)self::assertIsString($source);
        foreach([
            "generation_mode ENUM('draft_plan_only')",
            "'generation_mode'=>'draft_plan_only'",
            "'approval_required'=>true",
            "'commerce_executed'=>false",
            "status='skipped'",
        ] as $marker)self::assertStringContainsString($marker,$schema.$service.$router.$skip);
        foreach([
            'commerce_checkout','payment_method','stripe','capture_payment','send_gift','claim_code','redemption_code',
        ] as $forbidden){
            self::assertStringNotContainsString($forbidden,$service);
            self::assertStringNotContainsString($forbidden,$skip);
        }
    }

    public function testGenerateAndSkipUseFreshStateAndConcurrencyProtection(): void
    {
        $service=file_get_contents($this->root.'/includes/task-agent-recurring-programs.php');
        $skip=file_get_contents($this->root.'/includes/personal-agent/workflows-recurring-hardening.php');
        foreach([$service,$skip] as $source)self::assertIsString($source);
        foreach([
            'GET_LOCK','RELEASE_LOCK','expectedNextRunAt','expectedStatus','hash_equals',
            'SELECT * FROM user_recurring_gift_programs WHERE owner_user_id=? AND public_id=? LIMIT 1 FOR UPDATE',
            'idempotency_key','run_sequence','mg_personal_workflows_next_run',
        ] as $marker)self::assertStringContainsString($marker,$service.$skip);
    }

    public function testRecurringChatIsInterceptedBeforeGeneralRuntimeAndUsesZeroAi(): void
    {
        $api=file_get_contents($this->root.'/api/agents/runtime.php');
        $router=file_get_contents($this->root.'/includes/task-agent-recurring-programs-router.php');
        foreach([$api,$router] as $source)self::assertIsString($source);
        $intercept=strpos($api,'mg_task_agent_recurring_chat');
        $general=strpos($api,'mg_multi_agent_runtime_chat');
        self::assertNotFalse($intercept);
        self::assertNotFalse($general);
        self::assertLessThan($general,$intercept);
        foreach([
            "'response_source'=>'system_query'",
            "'used_ai'=>false",
            "'ai_tokens_total'=>0",
            "'tool'=>'recurring_programs'",
        ] as $marker)self::assertStringContainsString($marker,$router);
        foreach(['mg_anthropic_messages','mg_ai_credit_consume','mg_openai','ai_reason'=>'recurring'] as $forbidden){
            self::assertStringNotContainsString($forbidden,$router);
            self::assertStringNotContainsString($forbidden,file_get_contents($this->root.'/includes/task-agent-recurring-programs.php'));
        }
    }

    public function testApiExposesOnlyReviewableRecurringActions(): void
    {
        $api=file_get_contents($this->root.'/api/agents/runtime.php');
        self::assertIsString($api);
        foreach([
            "'recurring_programs'=>\$context['recurring_programs']??[]",
            "'recurring_schema_ready'=>\$context['recurring_schema_ready']??false",
            "action==='create_recurring_program'",
            "action==='update_recurring_program'",
            "action==='generate_recurring_draft'",
            "action==='skip_recurring_run'",
        ] as $marker)self::assertStringContainsString($marker,$api);
        foreach(["action==='checkout'","action==='purchase'","action==='send_gift'","action==='charge'"] as $forbidden){
            self::assertStringNotContainsString($forbidden,$api);
        }
    }

    public function testModelProjectionIsCompactAndExcludesSensitiveProgramContent(): void
    {
        $service=file_get_contents($this->root.'/includes/task-agent-recurring-programs.php');
        self::assertIsString($service);
        $start=strpos($service,'function mg_task_agent_recurring_for_model');
        self::assertNotFalse($start);
        $model=substr($service,$start);
        self::assertStringContainsString('array_slice($programs,0,8)',$model);
        foreach(['title','status','cadence','next_run_at','budget_min','budget_max','due','approval_required','generation_mode'] as $marker){
            self::assertStringContainsString($marker,$model);
        }
        foreach(['notes','program_id','owner_user_id','agent_id','public_id','idempotency_key','address','email','phone','claim_code','redemption_code'] as $forbidden){
            self::assertStringNotContainsString($forbidden,$model);
        }
    }

    public function testCanvasUsesCanonicalRuntimeActionsAndLoadsVersionedAssets(): void
    {
        $script=file_get_contents($this->root.'/assets/js/task-agent-recurring-programs-runtime.js');
        $page=file_get_contents($this->root.'/agent.php');
        foreach([$script,$page] as $source)self::assertIsString($source);
        foreach([
            'recurring_program_builder','recurring_gift_program','create_recurring_program','update_recurring_program',
            'generate_recurring_draft','skip_recurring_run','expected_status','expected_next_run_at',
            'Draft plans only','Zero AI credits','No automatic checkout',
        ] as $marker)self::assertStringContainsString($marker,$script);
        self::assertStringContainsString('/assets/js/task-agent-recurring-programs-runtime.js?v=1.0.0',$page);
        self::assertStringContainsString('/assets/css/task-agent-recurring-programs-v1.css?v=1.0.0',$page);
        foreach(['/api/checkout','/api/payments','claim_code','redemption_code'] as $forbidden)self::assertStringNotContainsString($forbidden,$script);
    }

    public function testPersonalAgentAlsoUsesTheCanonicalSkipAuthority(): void
    {
        $api=file_get_contents($this->root.'/api/user-agent/recurring-programs.php');
        $loader=file_get_contents($this->root.'/includes/personal-agent/workflows.php');
        foreach([$api,$loader] as $source)self::assertIsString($source);
        self::assertStringContainsString("if(\$action==='skip_next')",$api);
        self::assertStringContainsString('mg_personal_workflows_skip_recurring_run',$api);
        self::assertStringContainsString("require_once __DIR__ . '/workflows-recurring-hardening.php'",$loader);
    }
}
