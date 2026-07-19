<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MultiAgentRuntimeMemoryV1ContractTest extends TestCase
{
    public function testAgentOwnedRuntimeSchemaIsIsolatedFromDefaultPersonalAgentTables(): void
    {
        $root=dirname(__DIR__,2);
        $sql=file_get_contents($root.'/database/20260719_multi_agent_runtime_memory_v1.sql');
        self::assertIsString($sql);
        foreach (['multi_agent_threads','multi_agent_messages','multi_agent_memory','multi_agent_onboarding','multi_agent_drafts'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        }
        self::assertStringContainsString('FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE',$sql);
        self::assertStringContainsString('UNIQUE KEY uq_multi_agent_memory_agent_key (agent_id,memory_key)',$sql);
        self::assertStringContainsString("'agent.specialized.use'",$sql);
    }

    public function testRuntimeRoutesOwnedAgentsThroughSeparateThreadsMemoryAndCredits(): void
    {
        $root=dirname(__DIR__,2);
        $service=file_get_contents($root.'/includes/multi-agent-runtime.php');
        $api=file_get_contents($root.'/api/agents/runtime.php');
        self::assertIsString($service);
        self::assertIsString($api);
        self::assertStringContainsString('mg_agent_require_owned',$api);
        self::assertStringContainsString('mg_require_csrf_for_write',$api);
        self::assertStringContainsString('mg_multi_agent_runtime_chat',$api);
        self::assertStringContainsString('multi_agent_onboarding',$api);
        self::assertStringContainsString('multi_agent_memory',$api);
        self::assertStringContainsString('multi_agent_drafts',$api);
        self::assertStringContainsString('mg_ai_credit_preflight',$service);
        self::assertStringContainsString('mg_ai_credit_consume',$service);
        self::assertStringContainsString("'birthday_occasion'",$service);
        self::assertStringContainsString("'local_shopping'",$service);
        self::assertStringContainsString("'merchant_campaign'",$service);
        self::assertStringContainsString('explicit confirmation',$service);
    }

    public function testSelectedSpecializedAgentHasResponsiveChatOnboardingMemoryAndDraftUi(): void
    {
        $root=dirname(__DIR__,2);
        $workspace=file_get_contents($root.'/includes/personal-agent/multi-agent-workspace.php');
        $script=file_get_contents($root.'/assets/js/multi-agent-runtime.js');
        $css=file_get_contents($root.'/assets/css/multi-agent-runtime.css');
        $page=file_get_contents($root.'/agent.php');
        self::assertIsString($workspace);
        self::assertIsString($script);
        self::assertIsString($css);
        self::assertIsString($page);
        foreach (['data-agent-runtime-messages','data-agent-runtime-composer','data-agent-onboarding-form','data-agent-memory-list','data-agent-thread-list','data-agent-new-thread'] as $marker) {
            self::assertStringContainsString($marker,$workspace);
        }
        self::assertStringContainsString('/api/agents/runtime.php',$script);
        self::assertStringContainsString("action:'chat'",$script);
        self::assertStringContainsString("action:'onboarding'",$script);
        self::assertStringContainsString("action:'save_draft'",$script);
        self::assertStringContainsString('stopImmediatePropagation',$script);
        self::assertStringContainsString('@media(max-width:760px)',$css);
        self::assertStringContainsString('safe-area-inset-bottom',$css);
        self::assertStringContainsString('/assets/js/multi-agent-runtime.js?v=1.0.0',$page);
        self::assertStringContainsString('/assets/css/multi-agent-runtime.css?v=1.0.0',$page);
    }
}
