<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionProfileDiscoverySearchFoundationTest extends TestCase
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
        if ((string)getenv('MG_RUN_PROFILE_DISCOVERY_BEHAVIOR') !== '1') {
            self::markTestSkipped('Real-database discovery behavior runs in focused validation.');
        }
        if ((string)getenv('MG_DB_HOST') === '') {
            self::markTestSkipped('Database-backed discovery validation requires MG_DB_HOST.');
        }
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . '/scripts/validate_profile_discovery_behavior.php') . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $raw = implode("\n", $output);
        self::assertSame(0, $exitCode, $raw);
        $result = json_decode($raw, true);
        self::assertIsArray($result, $raw);
        self::assertSame('profile_discovery_search_foundation', $result['suite'] ?? null);
        foreach ([
            'public_and_unlisted', 'visibility_exclusions', 'blocked_exclusion',
            'deterministic_ranking', 'stable_cursor', 'wildcard_safety',
            'filters', 'safe_projection', 'curated_separation', 'rollback_clean',
        ] as $key) {
            self::assertTrue((bool)($result[$key] ?? false), $key . ' failed: ' . $raw);
        }
    }

    public function testExplorePageLoadsContentFirstTemplateAssetsAndStates(): void
    {
        $page = $this->read('discover.php');
        foreach ([
            '/assets/css/profile-discovery.css',
            '/assets/css/profile-discovery-content-cards.css?v=1.0.0',
            '/assets/js/profile-discovery.js?v=2.0.0',
            'data-profile-discovery', 'data-discovery-form', 'data-discovery-loading',
            'data-discovery-empty', 'data-discovery-no-results', 'data-discovery-error',
            'data-discovery-retry', 'data-results-grid', 'data-discovery-pagination',
            'data-featured-grid', 'data-recent-grid', 'data-storefront-grid',
            'Discover local businesses worth following.',
        ] as $needle) {
            self::assertStringContainsString($needle, $page);
        }
    }

    public function testCanonicalQueryEnforcesVisibilityAndProjectsContentCardFields(): void
    {
        $helper = $this->read('api/profiles/_discovery.php');
        foreach ([
            "u.status='active'", "pp.status='active'", "pp.visibility IN ('public','unlisted')",
            'NOT EXISTS(SELECT 1 FROM social_blocks', 'MG_PROFILE_DISCOVERY_MAX_LIMIT',
            'mg_profile_discovery_cursor_decode', 'hash_equals(', 'relevance_score DESC',
            'featured_score DESC', 'recent_activity DESC', 'public_id ASC',
            "ESCAPE '!'", "str_replace(['!', '%', '_']", 'catalog_product_versions cpvc',
            'pp.cover_url', 'AS business_name', 'AS storefront_cover_url',
            'AS published_campaign_count', "c.status='active'",
            "'business_name' =>", "'cover_url' =>", "'published_campaigns' =>",
            "'reviews' =>", "'status' => 'module_pending'",
        ] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['email', 'metadata_json', 'moderation_note', 'provider_', 'payment_method'] as $forbiddenProjection) {
            self::assertStringNotContainsString("'{$forbiddenProjection}' =>", $helper);
        }
    }

    public function testPublicEndpointAppliesRateLimitsCachePolicyAndOperationalLogging(): void
    {
        $endpoint = $this->read('api/public/discover.php');
        foreach ([
            "mg_require_method('GET')", "mg_rate_limit('profile.discovery.read'",
            "mg_security_log('warning', 'profile.discovery.invalid_request'",
            "mg_security_log('error', 'profile.discovery.failed'",
            "mg_event('profile.discovery.read'", 'Cache-Control: public, max-age=30',
            'Cache-Control: private, no-store', 'Vary: Cookie, Authorization',
        ] as $needle) {
            self::assertStringContainsString($needle, $endpoint);
        }
    }

    public function testOrganicAndCuratedResultsRemainExplicitlySeparated(): void
    {
        $helper = $this->read('api/profiles/_discovery.php');
        foreach ([
            "'result_kind' => \$resultKind", "mg_profile_discovery_item(\$row, 'curated')",
            "'organic_and_curated_are_separate' => true",
            "'private_behavioral_or_payment_data_used' => false",
            "'featured' => mg_profile_discovery_section", "'recent' => mg_profile_discovery_section",
            "'storefronts' => mg_profile_discovery_section",
        ] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testClientRendersOnlyContentFirstCardInformation(): void
    {
        $client = $this->read('assets/js/profile-discovery.js');
        foreach ([
            'textContent', 'createElement(', 'appendChild(', 'AbortController',
            "credentials: 'same-origin'", 'next_cursor',
            'show(noResults', 'show(empty', 'show(error',
            'mg-discovery-cover', 'mg-discovery-avatar', 'mg-discovery-business-name',
            'mg-discovery-content-summary', 'published_products', 'published_campaigns',
            'mg-discovery-review-metric', 'View profile →',
        ] as $needle) {
            self::assertStringContainsString($needle, $client);
        }
        foreach ([
            '.innerHTML =', 'insertAdjacentHTML(', 'document.write(', 'eval(',
            'Ticker Value', 'Merchant Score', 'marketPanel(', 'statGrid(', 'rankBadge(',
        ] as $unsafeOrRemoved) {
            self::assertStringNotContainsString($unsafeOrRemoved, $client);
        }
    }

    public function testContentCardStylesCoverDesktopTabletAndMobile(): void
    {
        $css = $this->read('assets/css/profile-discovery-content-cards.css');
        foreach ([
            '.mg-discovery-cover', '.mg-discovery-avatar', '.mg-discovery-business-name',
            '.mg-discovery-content-summary', '.mg-discovery-review-metric',
            'grid-template-columns:repeat(2,minmax(0,1fr))!important',
            '@media(max-width:1180px)', '@media(max-width:860px)',
            '@media(max-width:620px)', '@media(max-width:420px)',
            '.mg-discovery-market-panel', '.mg-discovery-stat-grid', 'display:none!important',
        ] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testFocusedValidationIsRegistered(): void
    {
        $composer = $this->read('composer.json');
        $workflow = $this->read('.github/workflows/profile-discovery-validation.yml');
        self::assertStringContainsString('test-profile-discovery-behavior', $composer);
        foreach ([
            'MG_RUN_PROFILE_DISCOVERY_BEHAVIOR', 'composer test-profile-discovery-behavior',
            'ProductionProfileDiscoverySearchFoundationTest', 'profile-discovery-search-foundation.spec.js',
            'build_full_upgrade_sql.php', 'composer test-frontend-contracts',
            'composer test', 'npm run test:browser',
        ] as $needle) {
            self::assertStringContainsString($needle, $workflow);
        }
    }
}
