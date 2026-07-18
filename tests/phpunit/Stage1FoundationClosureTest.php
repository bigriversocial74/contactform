<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage1FoundationClosureTest extends TestCase
{
    public function testStageOneClosureMigrationIsRegistered(): void
    {
        $root = dirname(__DIR__, 2);
        $runner = file_get_contents($root . '/scripts/run_migrations.php');
        self::assertIsString($runner);
        self::assertStringContainsString("'stage_1_foundation_closure.sql'", $runner);
        self::assertLessThan(
            strpos($runner, "'stage_3_agent_persistence.sql'"),
            strpos($runner, "'stage_1_foundation_closure.sql'")
        );
    }

    public function testStageOneClosureAddsDurableLoginMetadata(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root . '/database/stage_1_foundation_closure.sql');
        self::assertIsString($migration);
        self::assertStringContainsString("COLUMN_NAME = 'last_login_at'", $migration);
        self::assertStringContainsString('ADD COLUMN last_login_at DATETIME NULL', $migration);
        self::assertStringContainsString('idx_users_last_login_at', $migration);
    }

    public function testSuccessfulLoginAuditUpdatesLastLoginMetadata(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root . '/database/stage_1_foundation_closure.sql');
        $login = file_get_contents($root . '/api/auth/login.php');
        self::assertIsString($migration);
        self::assertIsString($login);
        self::assertStringContainsString("mg_audit('auth.login'", $login);
        self::assertStringContainsString('CREATE TRIGGER trg_audit_auth_login_last_seen', $migration);
        self::assertStringContainsString("NEW.action = 'auth.login'", $migration);
        self::assertStringContainsString('SET last_login_at = NEW.created_at', $migration);
        self::assertStringNotContainsString('PREPARE stage1_login_trigger_stmt', $migration);
    }

    public function testStageOneSecurityFoundationsRemainFailClosed(): void

    {
        $root = dirname(__DIR__, 2);
        $security = file_get_contents($root . '/api/security.php');
        $bootstrap = file_get_contents($root . '/api/bootstrap.php');
        self::assertIsString($security); self::assertIsString($bootstrap);
        foreach(['rate_limit.failed_closed','session.validate_failed_closed','function mg_session_is_active','function mg_record_user_session'] as $needle) self::assertStringContainsString($needle,$security);
        self::assertStringContainsString('mg_require_csrf_for_write',$bootstrap);



    }
}
