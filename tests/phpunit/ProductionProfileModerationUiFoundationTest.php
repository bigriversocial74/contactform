<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionProfileModerationUiFoundationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testRealDatabaseBehaviorMatrix(): void
    {
        if ((string)getenv('MG_RUN_PROFILE_MODERATION_BEHAVIOR') !== '1') self::markTestSkipped('Real-database moderation behavior runs in focused validation.');
        if ((string)getenv('MG_DB_HOST') === '') self::markTestSkipped('Database-backed moderation validation requires MG_DB_HOST.');
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . '/scripts/validate_profile_moderation_behavior.php') . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $raw = implode("\n", $output);
        self::assertSame(0, $exitCode, $raw);
        $result = json_decode($raw, true);
        self::assertIsArray($result, $raw);
        self::assertSame('profile_moderation_ui_foundation', $result['suite'] ?? null);
        foreach ([
            'case_opened', 'duplicate_blocked', 'queue_visible', 'case_detail_visible',
            'suspension_atomic', 'public_access_blocked', 'owner_cannot_escape',
            'appeal_submitted', 'appeal_accepted', 'history_durable', 'audit_durable', 'cleanup_complete',
        ] as $key) {
            self::assertTrue((bool)($result[$key] ?? false), $key . ' failed: ' . $raw);
        }
    }

    public function testMigrationCreatesCanonicalCasesActionsAppealsAndPermissions(): void
    {
        $migration = $this->read('database/stage_18d_profile_moderation.sql');
        foreach ([
            'CREATE TABLE IF NOT EXISTS profile_moderation_cases',
            'CREATE TABLE IF NOT EXISTS profile_moderation_actions',
            'CREATE TABLE IF NOT EXISTS profile_moderation_appeals',
            'UNIQUE KEY uq_profile_moderation_appeals_case (case_id)',
            'admin.profiles.moderation.view',
            'admin.profiles.moderation.manage',
            'fk_profile_moderation_cases_profile',
            'fk_profile_moderation_actions_case',
            'fk_profile_moderation_appeals_case',
        ] as $needle) {
            self::assertStringContainsString($needle, $migration);
        }
        self::assertStringContainsString("'stage_18d_profile_moderation.sql'", $this->read('scripts/build_full_upgrade_sql.php'));
    }

    public function testModerationAccessIsPermissionAwareAndSuperAdminCompatible(): void
    {
        $base = $this->read('api/admin/profile-moderation/_base.php');
        foreach ([
            'function mg_profile_moderation_access(',
            "in_array('super_admin'",
            'admin.profiles.moderation.view',
            'admin.profiles.moderation.manage',
            'mg_profile_moderation_require_view()',
            'mg_profile_moderation_require_manage()',
            'mg_security_log(',
            'mg_audit(',
        ] as $needle) {
            self::assertStringContainsString($needle, $base);
        }
    }

    public function testQueueIsBoundedFilteredAndDoesNotExposeOwnerEmail(): void
    {
        $base = $this->read('api/admin/profile-moderation/_base.php');
        self::assertStringContainsString('MG_PROFILE_MODERATION_DEFAULT_LIMIT = 24', $base);
        self::assertStringContainsString('MG_PROFILE_MODERATION_MAX_LIMIT = 50', $base);

        $queue = $this->read('api/admin/profile-moderation/_queue.php');
        foreach ([
            'assigned_user_id IS NULL',
            "FIELD(c.priority,'urgent','high','normal','low')",
            'LIMIT {$limit} OFFSET {$offset}',
            "ESCAPE '='",
            "str_replace(['=', '%', '_']",
            'An active case already exists',
            'beginTransaction()',
            'rollBack()',
        ] as $needle) {
            self::assertStringContainsString($needle, $queue);
        }
        self::assertStringNotContainsString('u.email', $queue);
        self::assertStringNotContainsString('metadata_json AS', $queue);
    }

    public function testCaseDetailUsesExistingProfileAndContentAuthorities(): void
    {
        $case = $this->read('api/admin/profile-moderation/_case.php');
        foreach ([
            'public_profiles pp',
            'public_profile_links',
            'public_profile_sections',
            'catalog_products',
            'feed_posts',
            'merchant_storefronts',
            'profile_moderation_actions',
            'profile_moderation_appeals',
            "'public_url' => '/profile.php?slug='",
        ] as $needle) {
            self::assertStringContainsString($needle, $case);
        }
        self::assertStringNotContainsString('password_hash', $case);
        self::assertStringNotContainsString('email', $case);
    }

    public function testActionsAreTransactionalAuditedAndCannotBypassProfileReadiness(): void
    {
        $actions = $this->read('api/admin/profile-moderation/_actions.php');
        foreach ([
            'beginTransaction()', 'FOR UPDATE', 'rollBack()',
            'UPDATE public_profiles SET status=?',
            "actionType === 'suspend'",
            "actionType === 'restore'",
            "in_array(\$actionType, ['appeal_accept', 'appeal_deny'], true)",
            "actionType === 'appeal_accept'",
            "appealStatus = 'denied'",
            'mg_profile_readiness(',
            "mg_audit('profile.moderation.'",
            "mg_event('profile.moderation.action'",
        ] as $needle) {
            self::assertStringContainsString($needle, $actions);
        }
        $profiles = $this->read('includes/profiles.php');
        self::assertStringContainsString("\$profile['status'] === 'suspended' ? 'suspended'", $profiles);
    }

    public function testOwnerAppealIsOwnerScopedCsrfProtectedAndOneTime(): void
    {
        $owner = $this->read('api/admin/profile-moderation/_owner.php');
        foreach ([
            'appellant_user_id',
            'profile_id=?',
            "status IN ('actioned','resolved')",
            'An appeal has already been submitted for this case.',
            'beginTransaction()',
            'rollBack()',
            'profile.moderation.appeal_submitted',
        ] as $needle) {
            self::assertStringContainsString($needle, $owner);
        }
        $endpoint = $this->read('api/profiles/moderation-appeal.php');
        self::assertStringContainsString('mg_require_api_user()', $endpoint);
        self::assertStringContainsString('mg_require_csrf_for_write(', $endpoint);
    }

    public function testModeratorAndOwnerInterfacesExposeRequiredStates(): void
    {
        $moderator = $this->read('includes/account/profile-moderation.php');
        foreach ([
            'data-profile-moderation', 'data-moderation-filters', 'data-moderation-case-list',
            'data-moderation-case-detail', 'data-profile-links', 'data-profile-sections',
            'data-moderation-appeals', 'data-moderation-history', 'data-moderation-action-form',
            'data-moderation-open-form',
        ] as $marker) {
            self::assertStringContainsString($marker, $moderator);
        }
        $owner = $this->read('includes/account/profile-moderation-owner.php');
        foreach (['data-profile-moderation-owner', 'data-owner-appeal-open', 'data-owner-appeal-form', 'data-owner-appeal-state'] as $marker) {
            self::assertStringContainsString($marker, $owner);
        }
    }

    public function testNewControllersUseSafeDomProjection(): void
    {
        foreach (['assets/js/profile-moderation.js', 'assets/js/profile-moderation-owner.js'] as $path) {
            $source = $this->read($path);
            self::assertStringContainsString('textContent', $source, $path);
            self::assertStringContainsString('replaceChildren', $source, $path);
            foreach (['.innerHTML =', 'insertAdjacentHTML(', 'document.write(', 'eval('] as $unsafe) {
                self::assertStringNotContainsString($unsafe, $source, $path);
            }
        }
    }

    public function testAccountAndDashboardIntegrationIsPermissionAware(): void
    {
        $account = $this->read('account.php');
        foreach ([
            "'profile_moderation' => 'Profile Moderation | Microgifter'",
            'admin.profiles.moderation.view',
            'admin.profiles.moderation.manage',
            '/account-profile-moderation.php',
            'includes/account/profile-moderation.php',
            'includes/account/profile-moderation-owner.php',
        ] as $needle) {
            self::assertStringContainsString($needle, $account);
        }
        $dashboard = $this->read('api/admin/_dashboard.php');
        foreach (['moderation_manage', '/account-profile-moderation.php', 'profile_moderation_cases', 'moderation_urgent', "mg_admin_dashboard_platform(\$pdo,\$tables,\$cutoff,\$access['moderation'])"] as $needle) {
            self::assertStringContainsString($needle, $dashboard);
        }
        $queries = $this->read('api/admin/_dashboard_queries.php');
        foreach (['profiles_suspended', 'moderation_active', 'moderation_appealed', 'moderation_urgent', 'bool $includeModeration=false'] as $needle) {
            self::assertStringContainsString($needle, $queries);
        }
        $frontend = $this->read('assets/js/admin-dashboard.js');
        self::assertStringContainsString('Profile moderation', $frontend);
    }

    public function testResponsiveStylesCoverDesktopTabletAndMobile(): void
    {
        $moderator = $this->read('assets/css/profile-moderation.css');
        foreach (['.mg-moderation-workspace', '.mg-moderation-action-panel', '@media(max-width:1400px)', '@media(max-width:1100px)', '@media(max-width:720px)', '@media(prefers-reduced-motion:reduce)'] as $needle) {
            self::assertStringContainsString($needle, $moderator);
        }
        $owner = $this->read('assets/css/profile-moderation-owner.css');
        self::assertStringContainsString('.mg-profile-moderation-owner', $owner);
        self::assertStringContainsString('@media(max-width:760px)', $owner);
    }

    public function testFocusedValidationIsRegistered(): void
    {
        $composer = $this->read('composer.json');
        $workflow = $this->read('.github/workflows/profile-moderation-validation.yml');
        self::assertStringContainsString('test-profile-moderation-behavior', $composer);
        foreach ([
            'MG_RUN_PROFILE_MODERATION_BEHAVIOR',
            'composer test-profile-moderation-behavior',
            'ProductionProfileModerationUiFoundationTest',
            'profile-moderation-foundation.spec.js',
            'build_full_upgrade_sql.php',
            'composer test-frontend-contracts',
            'composer test',
            'npm run test:browser',
        ] as $needle) {
            self::assertStringContainsString($needle, $workflow);
        }
    }
}
