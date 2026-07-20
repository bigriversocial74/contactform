<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase42GroupGiftingV1ContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void{$this->root=dirname(__DIR__,2);}

    public function testCanonicalGroupGiftAuthorityIsReusedWithoutDuplicateTables(): void
    {
        $canonical=file_get_contents($this->root.'/database/20260714_personal_gifting_workflows_phase3.sql');
        $phase4=file_get_contents($this->root.'/database/20260720_task_agent_phase4_v1.sql');
        $service=file_get_contents($this->root.'/includes/task-agent-group-gifts.php');
        foreach([$canonical,$phase4,$service] as $source)self::assertIsString($source);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS user_group_gifts',$canonical);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS user_group_gift_participants',$canonical);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS multi_agent_group_gift_links',$phase4);
        self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS user_group_gifts',$phase4);
        self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS user_group_gift_participants',$phase4);
        foreach(['mg_personal_workflows_group_gifts','mg_personal_workflows_create_group_gift','mg_personal_workflows_update_group_gift'] as $marker)self::assertStringContainsString($marker,$service);
    }

    public function testGroupGiftsAreOrganizerAndAgentScoped(): void
    {
        $service=file_get_contents($this->root.'/includes/task-agent-group-gifts.php');
        $phase4=file_get_contents($this->root.'/database/20260720_task_agent_phase4_v1.sql');
        foreach([$service,$phase4] as $source)self::assertIsString($source);
        foreach(['g.organizer_user_id=link.owner_user_id','link.owner_user_id=? AND link.agent_id=?','WHERE organizer_user_id=? AND public_id=?','agent_id,owner_user_id,group_gift_id','fk_multi_agent_group_link_agent','fk_multi_agent_group_link_owner','fk_multi_agent_group_link_group'] as $marker)self::assertStringContainsString($marker,$service.$phase4);
    }

    public function testContributionWorkflowRemainsPledgeOnlyAndCollectsNoMoney(): void
    {
        $canonical=file_get_contents($this->root.'/includes/personal-agent/workflows-group.php');
        $service=file_get_contents($this->root.'/includes/task-agent-group-gifts.php');
        $api=file_get_contents($this->root.'/api/agents/runtime.php');
        $script=file_get_contents($this->root.'/assets/js/task-agent-group-gifts-runtime.js');
        foreach([$canonical,$service,$api,$script] as $source)self::assertIsString($source);
        self::assertStringContainsString("'pledge_only'",$canonical);
        foreach(["'contribution_mode'=>'pledge_only'","'money_collected'=>false",'no money is collected','No payment collection'] as $marker)self::assertStringContainsString($marker,$service.$script);
        foreach(['record_external_pledge','pledge_amount','payment_method','stripe','capture_payment','checkout_session','charge_contributor'] as $forbidden){
            self::assertStringNotContainsString("action==='".$forbidden."'",$api);
            self::assertStringNotContainsString('/api/payments',$script);
        }
    }

    public function testExistingGroupsAreLinkedWithoutCopyingParticipantOrPledgeData(): void
    {
        $service=file_get_contents($this->root.'/includes/task-agent-group-gifts.php');
        self::assertIsString($service);
        foreach(['mg_task_agent_group_available','mg_task_agent_group_link','Reuse this existing pledge-only Personal Agent group gift','without copying participants, pledges, or plan data','canonical_reuse'] as $marker)self::assertStringContainsString($marker,$service);
        self::assertStringNotContainsString('INSERT INTO user_group_gift_participants',$service);
    }

    public function testStatusChangesRequireFreshStateAndCanonicalTransitions(): void
    {
        $service=file_get_contents($this->root.'/includes/task-agent-group-gifts.php');
        $script=file_get_contents($this->root.'/assets/js/task-agent-group-gifts-runtime.js');
        foreach([$service,$script] as $source)self::assertIsString($source);
        foreach(['expectedStatus','hash_equals','The group gift changed. Refresh it','expected_status','Refresh this group gift before changing its status.'] as $marker)self::assertStringContainsString($marker,$service.$script);
        foreach(["'draft'=>['open','cancel']","'open'=>['lock','fulfill','close','cancel']","'locked'=>['fulfill','close','cancel']","'fulfilled'=>['close']"] as $marker)self::assertStringContainsString($marker,$service);
    }

    public function testGroupChatRunsAfterRecurringButBeforeGeneralAiRuntime(): void
    {
        $api=file_get_contents($this->root.'/api/agents/runtime.php');self::assertIsString($api);
        $start=strpos($api,"if(\$action==='chat')");self::assertNotFalse($start);$block=substr($api,$start,900);
        $recurring=strpos($block,'mg_task_agent_recurring_chat');$group=strpos($block,'mg_task_agent_group_chat');$general=strpos($block,'mg_multi_agent_runtime_chat');
        self::assertNotFalse($recurring);self::assertNotFalse($group);self::assertNotFalse($general);
        self::assertTrue($recurring<$group&&$group<$general);
    }

    public function testGroupRouterIsDeterministicAndZeroAi(): void
    {
        $router=file_get_contents($this->root.'/includes/task-agent-group-gifts-router.php');$service=file_get_contents($this->root.'/includes/task-agent-group-gifts.php');foreach([$router,$service] as $source)self::assertIsString($source);
        foreach(["'response_source'=>'system_query'","'used_ai'=>false","'ai_tokens_total'=>0","'tool'=>'group_gifts'"] as $marker)self::assertStringContainsString($marker,$router);
        $combined=$router."\n".$service;
        self::assertDoesNotMatchRegularExpression('/\bmg_anthropic_messages\s*\(/',$combined);
        self::assertDoesNotMatchRegularExpression('/\bmg_ai_credit_consume\s*\(/',$combined);
        self::assertDoesNotMatchRegularExpression('/\bmg_openai[A-Za-z0-9_]*\s*\(/',$combined);
    }

    public function testModelProjectionIsCompactAndParticipantPrivate(): void
    {
        $service=file_get_contents($this->root.'/includes/task-agent-group-gifts.php');self::assertIsString($service);
        $start=strpos($service,'function mg_task_agent_group_for_model');self::assertNotFalse($start);$model=substr($service,$start);
        self::assertStringContainsString('array_slice($groups,0,8)',$model);
        foreach(['title','status','goal','pledged','currency','deadline_at','participant_count','joined_count','recipient_name','pledge_only','money_collected'] as $marker)self::assertStringContainsString($marker,$model);
        foreach(['participants','display_name','email','phone','pledge_message','invite_message','public_id','organizer_user_id','agent_id'] as $forbidden)self::assertStringNotContainsString($forbidden,$model);
    }

    public function testApiAndCanvasExposeOnlyOrganizerReviewActions(): void
    {
        $api=file_get_contents($this->root.'/api/agents/runtime.php');$script=file_get_contents($this->root.'/assets/js/task-agent-group-gifts-runtime.js');$page=file_get_contents($this->root.'/agent.php');foreach([$api,$script,$page] as $source)self::assertIsString($source);
        foreach(["action==='create_group_gift'","action==='link_group_gift'","action==='update_group_gift'","'group_gifts'=>\$context['group_gifts']??[]","'available_group_gifts'=>\$context['available_group_gifts']??[]"] as $marker)self::assertStringContainsString($marker,$api);
        foreach(['group_gift_builder','group_gift_link','data-group-action','Open and invite','Lock pledges','Mark fulfilled','Pledge commitments only','No payment collection'] as $marker)self::assertStringContainsString($marker,$script);
        self::assertStringContainsString('/assets/js/task-agent-group-gifts-runtime.js?v=1.0.0',$page);
        self::assertStringContainsString('/assets/css/task-agent-group-gifts-v1.css?v=1.0.0',$page);
    }
}
