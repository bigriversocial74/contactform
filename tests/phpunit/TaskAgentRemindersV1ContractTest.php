<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentRemindersV1ContractTest extends TestCase
{
    public function testReminderWorkflowsUseCanonicalSystemServices(): void
    {
        $root=dirname(__DIR__,2);
        $context=file_get_contents($root.'/includes/task-agent-context.php');
        $api=file_get_contents($root.'/api/agents/runtime.php');
        $script=file_get_contents($root.'/assets/js/multi-agent-runtime.js');
        $actions=file_get_contents($root.'/includes/personal-agent/actions.php');
        self::assertIsString($context);
        self::assertIsString($api);
        self::assertIsString($script);
        self::assertIsString($actions);

        self::assertStringContainsString('mg_task_agent_reminder_payload',$context);
        self::assertStringContainsString("'action'=>'save_reminder'",$context);
        self::assertStringContainsString('mg_task_agent_reminder_cards',$context);
        self::assertStringContainsString("'action'=>'manage_reminder'",$context);
        self::assertStringContainsString('No gift, message, purchase, or delivery is scheduled.',$context);

        self::assertStringContainsString("$action==='create_reminder'",$api);
        self::assertStringContainsString('mg_personal_agent_create_reminder',$api);
        self::assertStringContainsString("$action==='update_reminder_status'",$api);
        self::assertStringContainsString('mg_personal_agent_update_reminder_status',$api);
        self::assertStringContainsString("'used_ai'=>false",$api);
        self::assertStringContainsString("'response_source'=>'system_action'",$api);

        self::assertStringContainsString('data-save-agent-reminder',$script);
        self::assertStringContainsString('data-reminder-status',$script);
        self::assertStringContainsString("action:'create_reminder'",$script);
        self::assertStringContainsString("action:'update_reminder_status'",$script);

        self::assertStringContainsString('user_gifting_reminders',$actions);
        self::assertStringContainsString("'scheduled'",$actions);
        self::assertStringContainsString("['scheduled','completed','dismissed','cancelled']",$actions);
    }

    public function testReminderPreparationConsumesNoExternalAi(): void
    {
        $root=dirname(__DIR__,2);
        $context=file_get_contents($root.'/includes/task-agent-context.php');
        self::assertStringNotContainsString('mg_anthropic_messages',$context);
        self::assertStringNotContainsString('mg_ai_credit_consume',$context);
    }
}
