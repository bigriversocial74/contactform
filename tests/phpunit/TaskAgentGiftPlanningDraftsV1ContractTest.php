<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentGiftPlanningDraftsV1ContractTest extends TestCase
{
    public function testSystemFirstGiftPlanDraftsUseCanonicalPersonalAgentAuthority(): void
    {
        $root=dirname(__DIR__,2);
        $context=file_get_contents($root.'/includes/task-agent-context.php');
        $api=file_get_contents($root.'/api/agents/runtime.php');
        $actions=file_get_contents($root.'/includes/personal-agent/actions.php');
        self::assertIsString($context);
        self::assertIsString($api);
        self::assertIsString($actions);
        self::assertStringContainsString('mg_task_agent_plan_payload',$context);
        self::assertStringContainsString("'canonical_plan'",$context);
        self::assertStringContainsString("'action'=>'save_draft'",$context);
        self::assertStringContainsString("'system_generated'=>true",$context);
        self::assertStringContainsString("'used_ai'=>false",$context);
        self::assertStringContainsString('approval-first gift-plan draft',$context);
        self::assertStringContainsString('$canonical=is_array($payload[\'canonical_plan\']??null)',$api);
        self::assertStringContainsString('mg_personal_agent_create_plan',$api);
        self::assertStringContainsString("'response_source'=>'system_action'",$api);
        self::assertStringContainsString("'used_ai'=>false",$api);
        self::assertStringContainsString('multi_agent.gift_plan_draft_created',$api);
        self::assertStringContainsString("'draft'",$actions);
        self::assertStringContainsString('approval_required',$actions);
        self::assertStringContainsString('user_gifting_plan.created',$actions);
    }

    public function testDraftCreationDoesNotPurchaseSendOrSchedule(): void
    {
        $context=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-context.php');
        self::assertStringContainsString('no purchase, message, or delivery will occur',$context);
        self::assertStringNotContainsString('mg_anthropic_messages',$context);
        self::assertStringNotContainsString('mg_ai_credit_consume',$context);
    }
}
