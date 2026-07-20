<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase31DiscoveryShortlistV1ContractTest extends TestCase
{
    public function testSchemaIsAgentOwnedAndVersionPinned(): void
    {
        $sql = file_get_contents(dirname(__DIR__,2).'/database/20260720_task_agent_phase3_shortlist_v1.sql');
        self::assertIsString($sql);
        foreach ([
            'CREATE TABLE IF NOT EXISTS multi_agent_shortlist_items',
            'owner_user_id BIGINT UNSIGNED NOT NULL',
            'agent_id BIGINT UNSIGNED NOT NULL',
            'product_id BIGINT UNSIGNED NOT NULL',
            'product_version_id BIGINT UNSIGNED NOT NULL',
            'UNIQUE KEY uq_multi_agent_shortlist_owner_agent_version',
            'FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE',
            'FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE',
            'FOREIGN KEY (product_version_id) REFERENCES catalog_product_versions(id) ON DELETE CASCADE',
        ] as $marker) self::assertStringContainsString($marker,$sql);
    }

    public function testDiscoveryAndShortlistUsePublishedCanonicalProductsWithoutAi(): void
    {
        $root=dirname(__DIR__,2);
        $service=file_get_contents($root.'/includes/task-agent-shortlist.php');
        $router=file_get_contents($root.'/includes/task-agent-shortlist-router.php');
        $runtime=file_get_contents($root.'/includes/multi-agent-runtime.php');
        foreach([$service,$router,$runtime] as $value)self::assertIsString($value);
        foreach([
            'mg_product_discovery_search','mg_public_product_load',"cp.status='published'", "cpv.version_status='published'", "cpvl.availability_status='available'",
            'mg_task_agent_shortlist_add','mg_task_agent_shortlist_remove','owner_user_id=? AND agent_id=?',
            'multi_agent.discovery_completed','multi_agent.shortlist_added','multi_agent.shortlist_removed',
        ] as $marker) self::assertStringContainsString($marker,$service);
        self::assertStringNotContainsString('mg_anthropic_messages',$service);
        self::assertStringNotContainsString('mg_ai_credit_consume',$service);
        self::assertStringContainsString('mg_task_agent_shortlist_route',$runtime);
        self::assertStringContainsString('mg_task_agent_discover_products',$runtime);
        self::assertLessThan(strpos($runtime,'mg_anthropic_messages'),strpos($runtime,'mg_task_agent_shortlist_route'));
    }

    public function testApiAndCanvasExposeOnlyReviewAndShortlistActions(): void
    {
        $root=dirname(__DIR__,2);
        $api=file_get_contents($root.'/api/agents/runtime.php');
        $script=file_get_contents($root.'/assets/js/multi-agent-runtime.js');
        $page=file_get_contents($root.'/agent.php');
        foreach([$api,$script,$page] as $value)self::assertIsString($value);
        foreach(['mg_agent_require_owned','mg_require_csrf_for_write','if($action===\'discover_products\')','if($action===\'add_shortlist\')','if($action===\'remove_shortlist\')',"'used_ai'=>false"] as $marker)self::assertStringContainsString($marker,$api);
        foreach(['data-shortlist-product','data-shortlist-remove',"action:'add_shortlist'","action:'remove_shortlist'",'Review product','Shortlisted'] as $marker)self::assertStringContainsString($marker,$script);
        self::assertStringContainsString('/assets/js/multi-agent-runtime.js?v=1.7.0',$page);
        self::assertStringContainsString('/assets/css/task-agent-shortlist-v1.css?v=1.0.0',$page);
        foreach(['order-checkout-session','action-center-send.php','microgift-claim.php'] as $forbidden) {
            self::assertStringNotContainsString($forbidden,$api);
            self::assertStringNotContainsString($forbidden,$script);
        }
    }

    public function testMigrationIsBackwardCompatibleForReadsAndRequiredForWrites(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-shortlist.php');
        self::assertIsString($service);
        self::assertStringContainsString('mg_task_agent_shortlist_schema_ready',$service);
        self::assertStringContainsString('if (!mg_task_agent_shortlist_schema_ready($pdo)) return [];',$service);
        self::assertStringContainsString('Task Agent Phase 3.1 shortlist migration is required.',$service);
    }

    public function testComparisonReceivesOnlyCompactPublishedShortlistContext(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-shortlist.php');
        $router=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-shortlist-router.php');
        $runtime=file_get_contents(dirname(__DIR__,2).'/includes/multi-agent-runtime.php');
        foreach([$service,$router,$runtime] as $value)self::assertIsString($value);
        $projectionStart=strpos($service,'function mg_task_agent_shortlist_for_model');
        self::assertNotFalse($projectionStart);
        $projection=substr($service,$projectionStart);
        self::assertStringContainsString('array_slice($items,0,8)',$projection);
        self::assertStringContainsString('mg_task_agent_shortlist_model_context',$router);
        self::assertStringContainsString('mg_task_agent_shortlist_model_context($context)',$runtime);
        self::assertStringNotContainsString('recipient_context',$projection,'Model shortlist projection must not include recipient identifiers.');
    }
}
