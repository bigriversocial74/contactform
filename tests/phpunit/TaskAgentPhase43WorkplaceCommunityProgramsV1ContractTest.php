<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase43WorkplaceCommunityProgramsV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function source(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path.' must be readable.');
        return $source;
    }

    public function testCanonicalDistributionAuthorityIsReusedWithoutDuplicateProgramTables(): void
    {
        $canonical=$this->source('database/stage_4e_distribution_external_inputs.sql');
        $phase4=$this->source('database/20260720_task_agent_phase4_v1.sql');
        $service=$this->source('includes/task-agent-program-coordination.php');

        foreach([
            'CREATE TABLE IF NOT EXISTS distribution_programs',
            'CREATE TABLE IF NOT EXISTS distribution_program_products',
            'CREATE TABLE IF NOT EXISTS distribution_recipients',
            'CREATE TABLE IF NOT EXISTS distribution_allocations',
            'CREATE TABLE IF NOT EXISTS distribution_issuance_jobs',
        ] as $marker) self::assertStringContainsString($marker,$canonical);

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS multi_agent_distribution_program_links',$phase4);
        foreach([
            'CREATE TABLE IF NOT EXISTS distribution_programs',
            'CREATE TABLE IF NOT EXISTS distribution_program_products',
            'CREATE TABLE IF NOT EXISTS distribution_recipients',
            'CREATE TABLE IF NOT EXISTS distribution_allocations',
            'CREATE TABLE IF NOT EXISTS distribution_issuance_jobs',
        ] as $duplicate) self::assertStringNotContainsString($duplicate,$phase4);

        foreach(['distribution_programs','distribution_recipients','distribution_program_products','distribution_allocations'] as $authority) {
            self::assertStringContainsString($authority,$service);
        }
    }

    public function testWorkplaceAndCommunityTemplatesAreActiveMerchantAgents(): void
    {
        $templates=$this->source('includes/multi-agent-workspace-data.php');
        foreach(['workplace_rewards','community_fundraising'] as $key) {
            $start=strpos($templates,"'{$key}' => [");
            self::assertNotFalse($start);
            $block=substr($templates,$start,900);
            self::assertStringContainsString("'status' => 'active'",$block);
            self::assertStringContainsString("'merchant_required' => true",$block);
        }
        self::assertStringContainsString('existing merchant distribution authorities',$templates);
        self::assertStringContainsString('existing fundraisers, contests, giveaways, prizes, and sponsored community programs',$templates);
    }

    public function testProgramsAreMerchantOwnerAgentAndTypeScoped(): void
    {
        $service=$this->source('includes/task-agent-program-coordination.php');
        $phase4=$this->source('database/20260720_task_agent_phase4_v1.sql');
        foreach([
            "'workplace_rewards' => ['workplace_reward']",
            "'community_fundraising' => ['fundraiser','contest','giveaway','merchant_grant']",
            'dp.merchant_user_id=link.owner_user_id',
            'link.owner_user_id=? AND link.agent_id=?',
            'WHERE dp.merchant_user_id=? AND dp.program_type IN',
            'WHERE merchant_user_id=? AND public_id=? AND program_type IN',
            'agent_id,owner_user_id,distribution_program_id',
            'fk_multi_agent_distribution_link_agent',
            'fk_multi_agent_distribution_link_owner',
            'fk_multi_agent_distribution_link_program',
        ] as $marker) self::assertStringContainsString($marker,$service."\n".$phase4);
    }

    public function testSpecializedAgentOnlyLinksAndUnlinksCanonicalPrograms(): void
    {
        $api=$this->source('api/agents/runtime.php');
        $service=$this->source('includes/task-agent-program-coordination.php');
        foreach(["action==='link_distribution_program'","action==='unlink_distribution_program'"] as $allowed) {
            self::assertStringContainsString($allowed,$api);
        }
        foreach([
            'create_distribution_program','update_distribution_program','save_distribution_program',
            'create_distribution_recipient','update_distribution_recipient','approve_distribution_recipient',
            'allocate_distribution_reward','select_distribution_winner','issue_distribution_reward',
            'process_distribution_issuance','distribution_program_status',
        ] as $forbidden) {
            self::assertStringNotContainsString("action==='{$forbidden}'",$api);
        }
        foreach([
            'INSERT INTO distribution_programs','UPDATE distribution_programs',
            'INSERT INTO distribution_recipients','UPDATE distribution_recipients',
            'INSERT INTO distribution_allocations','UPDATE distribution_allocations',
            'INSERT INTO distribution_issuance_jobs','UPDATE distribution_issuance_jobs',
        ] as $mutation) self::assertStringNotContainsString($mutation,$service);
        self::assertStringContainsString("'program_mutated'=>false",$service);
    }

    public function testCreationAndSensitiveActionsHandoffToCanonicalWorkspace(): void
    {
        $router=$this->source('includes/task-agent-program-coordination-router.php');
        $service=$this->source('includes/task-agent-program-coordination.php');
        $script=$this->source('assets/js/task-agent-program-coordination-runtime.js');
        foreach([
            '/merchant-distribution-program.php',
            'Program creation stays in the canonical merchant distribution workspace',
            'recipient eligibility, product assignment, allocation, issuance, and status mutations remain',
            'Program creation and all commerce-sensitive actions stay in the merchant distribution authority',
        ] as $marker) self::assertStringContainsString($marker,$router."\n".$service."\n".$script);
    }

    public function testProgramRoutingIsDeterministicAndZeroAi(): void
    {
        $router=$this->source('includes/task-agent-program-coordination-router.php');
        $service=$this->source('includes/task-agent-program-coordination.php');
        foreach([
            "'response_source'=>'system_query'",
            "'used_ai'=>false",
            "'ai_tokens_total'=>0",
            "'tool'=>'distribution_programs'",
        ] as $marker) self::assertStringContainsString($marker,$router);
        $combined=$router."\n".$service;
        self::assertDoesNotMatchRegularExpression('/\bmg_anthropic_messages\s*\(/',$combined);
        self::assertDoesNotMatchRegularExpression('/\bmg_ai_credit_consume\s*\(/',$combined);
        self::assertDoesNotMatchRegularExpression('/\bmg_openai[A-Za-z0-9_]*\s*\(/',$combined);
    }

    public function testProgramChatRunsBeforeGeneralAiCapableRuntime(): void
    {
        $api=$this->source('api/agents/runtime.php');
        $start=strpos($api,"if(\$action==='chat')");
        self::assertNotFalse($start);
        $block=substr($api,$start,900);
        $recurring=strpos($block,'mg_task_agent_recurring_chat');
        $group=strpos($block,'mg_task_agent_group_chat');
        $program=strpos($block,'mg_task_agent_program_chat');
        $general=strpos($block,'mg_multi_agent_runtime_chat');
        foreach([$recurring,$group,$program,$general] as $position) self::assertNotFalse($position);
        self::assertTrue($recurring<$group && $group<$program && $program<$general);
    }

    public function testModelProjectionIsAggregateOnlyAndPrivate(): void
    {
        $service=$this->source('includes/task-agent-program-coordination.php');
        $start=strpos($service,'function mg_task_agent_program_for_model');
        $end=strpos($service,'function mg_task_agent_program_card',$start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $model=substr($service,$start,$end-$start);
        self::assertStringContainsString('array_slice($programs,0,12)',$model);
        foreach([
            'name','program_type','status','budget','reserved','issued','remaining_budget',
            'recipient_count','eligible_count','selected_count','allocated_count',
            'product_count','allocation_count','issued_allocation_count','authority',
        ] as $aggregate) self::assertStringContainsString($aggregate,$model);
        foreach([
            'public_id','merchant_user_id','created_by_user_id','user_id','external_recipient_id',
            'display_name','email_hash','phone_hash','metadata_json','rules_json','selection_proof_json',
            'failure_message','idempotency_key','request_json',
        ] as $private) self::assertStringNotContainsString($private,$model);
    }

    public function testCanvasExposesReviewCardsAndOnlyAssociationActions(): void
    {
        $script=$this->source('assets/js/task-agent-program-coordination-runtime.js');
        $page=$this->source('agent.php');
        foreach([
            'distribution_program_link','distribution_program','distribution_program_handoff',
            'link_distribution_program','unlink_distribution_program',
            'No duplicated program data','No recipient mutation','No issuance','Zero AI credits',
        ] as $marker) self::assertStringContainsString($marker,$script);
        self::assertStringNotContainsString('/api/distribution/allocate.php',$script);
        self::assertStringNotContainsString('/api/public/v1/rewards/issue.php',$script);
        self::assertStringContainsString('/assets/js/task-agent-program-coordination-runtime.js?v=1.0.0',$page);
        self::assertStringContainsString('/assets/css/task-agent-program-coordination-v1.css?v=1.0.0',$page);
    }
}
