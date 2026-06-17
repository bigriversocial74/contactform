<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AgentPersistenceTest extends TestCase
{
    public function testAgentMigrationDefinesLifecycleAndImmutableHistory(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_3_agent_persistence.sql');

        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS agents', $sql);
        self::assertStringContainsString("ENUM('active','archived','deleted')", $sql);
        self::assertStringContainsString("ENUM('paused','running')", $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS agent_history', $sql);
        self::assertStringContainsString('agent_name_snapshot', $sql);
        self::assertStringContainsString('ON DELETE RESTRICT', $sql);
    }

    public function testMigrationRunnerIncludesStageThreeAgentMigration(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/run_migrations.php');

        self::assertIsString($source);
        self::assertStringContainsString("'stage_3_agent_persistence.sql'", $source);
    }

    public function testAgentEndpointsRequireAuthenticationPermissionsAndOwnership(): void
    {
        $index = file_get_contents(dirname(__DIR__, 2) . '/api/agents/index.php');
        $item = file_get_contents(dirname(__DIR__, 2) . '/api/agents/item.php');
        $status = file_get_contents(dirname(__DIR__, 2) . '/api/agents/status.php');
        $archive = file_get_contents(dirname(__DIR__, 2) . '/api/agents/archive.php');
        $restore = file_get_contents(dirname(__DIR__, 2) . '/api/agents/restore.php');
        $helper = file_get_contents(dirname(__DIR__, 2) . '/api/agents/_agent.php');

        foreach ([$index, $item, $status, $archive, $restore, $helper] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString("mg_require_permission('agent.create')", $index);
        self::assertStringContainsString("mg_require_permission('agent.update')", $item);
        self::assertStringContainsString("mg_require_permission('agent.delete')", $item);
        self::assertStringContainsString("mg_require_permission('agent.runtime.manage')", $status);
        self::assertStringContainsString("mg_require_permission('agent.archive')", $archive);
        self::assertStringContainsString("mg_require_permission('agent.archive')", $restore);
        self::assertStringContainsString('WHERE public_id = ? AND user_id = ?', $helper);
    }

    public function testDeleteIsSoftAndPreservesAgentHistory(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/agents/item.php');

        self::assertIsString($source);
        self::assertStringContainsString("lifecycle_status = 'deleted'", $source);
        self::assertStringContainsString("mg_agent_history(\$pdo, \$deleted, 'deleted'", $source);
        self::assertStringNotContainsString('DELETE FROM agents', $source);
        self::assertStringContainsString('soft_delete_preserves_financial_history', $source);
    }

    public function testFrontendNoLongerUsesSavedAgentLifecycleLocalStorageKeys(): void
    {
        $tabs = file_get_contents(dirname(__DIR__, 2) . '/assets/js/agent-tabs.js');
        $controls = file_get_contents(dirname(__DIR__, 2) . '/assets/js/agent-controls.js');
        $archived = file_get_contents(dirname(__DIR__, 2) . '/assets/js/archived-agents.js');

        foreach ([$tabs, $controls, $archived] as $source) {
            self::assertIsString($source);
            self::assertStringNotContainsString('mg_saved_agents_v1', $source);
            self::assertStringNotContainsString('mg_archived_agents_v1', $source);
            self::assertStringNotContainsString('mg_agent_runtime_v1', $source);
            self::assertStringNotContainsString('mg_deleted_agent_history_v1', $source);
        }

        self::assertStringContainsString('/api/agents/index.php', $tabs);
        self::assertStringContainsString('/api/agents/status.php', $controls);
        self::assertStringContainsString('/api/agents/restore.php', $archived);
    }
}
