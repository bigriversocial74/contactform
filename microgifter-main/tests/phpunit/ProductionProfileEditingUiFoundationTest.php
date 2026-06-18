<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionProfileEditingUiFoundationTest extends TestCase
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
        if ((string)getenv('MG_RUN_PROFILE_EDITOR_BEHAVIOR') !== '1') self::markTestSkipped('Real-database profile editor behavior runs in focused validation.');
        if ((string)getenv('MG_DB_HOST') === '') self::markTestSkipped('Database-backed profile editor validation requires MG_DB_HOST.');
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . '/scripts/validate_profile_editor_behavior.php') . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $raw = implode("\n", $output);
        self::assertSame(0, $exitCode, $raw);
        $result = json_decode($raw, true);
        self::assertIsArray($result, $raw);
        self::assertSame('profile_editing_ui_foundation', $result['suite'] ?? null);
        foreach ([
            'profile_created', 'identity_updated', 'readiness_enforced', 'slug_collision_safe',
            'links_sections_scored', 'media_url_safe', 'visibility_transitions', 'rollback_clean',
        ] as $key) {
            self::assertTrue((bool)($result[$key] ?? false), $key . ' failed: ' . $raw);
        }
    }

    public function testAccountRouteLoadsDedicatedEditorOnly(): void
    {
        $account = $this->read('account.php');
        self::assertStringContainsString('/assets/css/profile-editor.css', $account);
        self::assertStringContainsString('/assets/js/profile-editor.js', $account);
        self::assertStringContainsString("if (\$accountView === 'profile')", $account);
        self::assertStringContainsString("else {\n  \$page_scripts[] = '/assets/js/account.js';", $account);
        self::assertStringContainsString('includes/account/profile-editor.php', $account);
    }

    public function testEditorMarkupCoversAllFourSections(): void
    {
        $view = $this->read('includes/account/profile-editor.php');
        foreach ([
            'data-profile-editor', 'data-profile-editor-form', 'data-editor-nav="identity"',
            'data-editor-nav="links"', 'data-editor-nav="sections"', 'data-editor-nav="content"',
            'data-editor-nav="media"', 'data-editor-nav="publish"', 'data-editor-links',
            'data-editor-sections', 'data-editor-summary-grid', 'data-media-input="avatar"',
            'data-media-input="cover"', 'data-readiness-list', 'data-editor-dirty-bar',
            'data-editor-preview-link', 'data-editor-publish', 'data-editor-hide',
        ] as $marker) {
            self::assertStringContainsString($marker, $view);
        }
    }

    public function testControllerUsesCanonicalProfileEndpointsAndSafeDomProjection(): void
    {
        $script = $this->read('assets/js/profile-editor.js');
        foreach ([
            '/api/profiles/me.php', '/api/profiles/update.php', '/api/profiles/links.php',
            '/api/profiles/sections.php', '/api/profiles/editor-summary.php', '/api/profiles/media.php',
            'beforeunload', 'window.confirm(', 'MG.getCsrfToken()', 'FormData', 'textContent',
            'replaceChildren', 'data-sort-action', 'data-media-input',
        ] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
        foreach (['.innerHTML =', 'insertAdjacentHTML(', 'document.write(', 'eval('] as $unsafe) {
            self::assertStringNotContainsString($unsafe, $script);
        }
    }

    public function testIdentityValidationAndPublishReadinessAreExplicit(): void
    {
        $script = $this->read('assets/js/profile-editor.js');
        foreach ([
            'validateIdentity(', 'Display name is required', 'Website must be a valid URL',
            'Complete the required identity fields before publishing', 'Changing the public profile address',
            "saveIdentity('draft'", "saveIdentity('active'", "saveIdentity('hidden'",
        ] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
    }

    public function testLinksAndSectionsAreBoundedOrderedAndOwnerOnly(): void
    {
        $links = $this->read('api/profiles/links.php');
        $sections = $this->read('api/profiles/sections.php');
        foreach ([$links, $sections] as $source) {
            self::assertStringContainsString('mg_require_api_user()', $source);
            self::assertStringContainsString('mg_require_csrf_for_write(', $source);
            self::assertStringContainsString('beginTransaction()', $source);
            self::assertStringContainsString('rollBack()', $source);
            self::assertStringContainsString('sort_order', $source);
            self::assertStringContainsString('completion_score', $source);
        }
        self::assertStringContainsString('MG_PROFILE_MAX_LINKS', $links);
        self::assertStringContainsString('MG_PROFILE_MAX_SECTIONS', $sections);
    }

    public function testSummaryUsesExistingDomainAuthoritiesWithoutMutation(): void
    {
        $summary = $this->read('api/profiles/editor-summary.php');
        foreach ([
            "mg_require_method('GET')", 'mg_require_api_user()', 'mg_storefront_owned(',
            'mg_tip_public_profile_capability(', 'catalog_products', 'feed_posts',
            'subscription_plans', 'social_follows', "recovery_status='clear'",
        ] as $needle) {
            self::assertStringContainsString($needle, $summary);
        }
        foreach (['INSERT INTO', 'UPDATE ', 'DELETE FROM', 'mg_ledger_post('] as $mutation) {
            self::assertStringNotContainsString($mutation, $summary);
        }
    }

    public function testMediaWorkflowUsesOwnerScopedPrivateAssetsAndPublicPublicationChecks(): void
    {
        $media = $this->read('api/profiles/media.php');
        $publicMedia = $this->read('api/public/media.php');
        foreach ([
            'mg_require_api_user()', 'mg_require_csrf_for_write(', 'owner_user_id=?',
            "asset_type='image'", 'is_uploaded_file(', 'getimagesize(', 'move_uploaded_file(',
            "'private_local'", "profile_role' => \$role",
        ] as $needle) {
            self::assertStringContainsString($needle, $media);
        }
        foreach ([
            "pp.status='active'", "pp.visibility IN ('public','unlisted')", "pu.status='active'",
            'pp.avatar_url=?', 'pp.cover_url=?', 'Cache-Control: public, max-age=300',
        ] as $needle) {
            self::assertStringContainsString($needle, $publicMedia);
        }
    }

    public function testProfileHelpersExposeLimitsReadinessAndSafeUrls(): void
    {
        $profiles = $this->read('includes/profiles.php');
        foreach ([
            'MG_PROFILE_MAX_LINKS = 12', 'MG_PROFILE_MAX_SECTIONS = 20',
            'function mg_profile_external_url(', 'function mg_profile_media_url(',
            'function mg_profile_completion_score(', 'function mg_profile_readiness(',
            "'can_publish'", "'required_complete'", "'allowed'", "'limits'",
        ] as $needle) {
            self::assertStringContainsString($needle, $profiles);
        }
    }

    public function testResponsiveEditorStylesCoverDesktopTabletAndMobile(): void
    {
        $style = $this->read('assets/css/profile-editor.css');
        foreach ([
            '.mg-profile-editor-workspace', '.mg-profile-editor-nav', '.mg-profile-editor-preview',
            '.mg-profile-editor-dirty-bar', '.mg-profile-media-grid', '.mg-profile-publish-grid',
            '@media(max-width:1350px)', '@media(max-width:980px)', '@media(max-width:680px)',
            '@media(prefers-reduced-motion:reduce)',
        ] as $needle) {
            self::assertStringContainsString($needle, $style);
        }
    }

    public function testFocusedValidationIsRegistered(): void
    {
        $composer = $this->read('composer.json');
        $workflow = $this->read('.github/workflows/profile-editor-validation.yml');
        self::assertStringContainsString('test-profile-editor-behavior', $composer);
        foreach ([
            'MG_RUN_PROFILE_EDITOR_BEHAVIOR', 'composer test-profile-editor-behavior',
            'ProductionProfileEditingUiFoundationTest', 'profile-editor-foundation.spec.js',
            'build_full_upgrade_sql.php', 'composer test-frontend-contracts', 'composer test',
            'npm run test:browser',
        ] as $needle) {
            self::assertStringContainsString($needle, $workflow);
        }
    }
}
