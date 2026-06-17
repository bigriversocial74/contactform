<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PublicProfileUiFoundationTest extends TestCase
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

    public function testProfilePageUsesCanonicalReadEndpointInsteadOfDirectDatabaseQueries(): void
    {
        $page = $this->read('profile.php');
        $script = $this->read('assets/js/public-profile.js');

        self::assertStringContainsString('/assets/css/public-profile.css', $page);
        self::assertStringContainsString('/assets/js/public-profile.js', $page);
        self::assertStringContainsString('data-public-profile-page', $page);
        self::assertStringContainsString('/api/public/profile.php?slug=', $script);

        foreach (['mg_db(', 'SELECT ', 'FROM public_profiles', 'mg_profile_public_payload('] as $legacyAuthority) {
            self::assertStringNotContainsString($legacyAuthority, $page);
        }
    }

    public function testSectionOneExposesIdentityLinksSectionsAndStateBoundaries(): void
    {
        $page = $this->read('profile.php');
        foreach ([
            'data-profile-loading',
            'data-profile-error',
            'data-profile-content',
            'data-profile-preview-banner',
            'data-profile-cover',
            'data-profile-avatar',
            'data-profile-name',
            'data-profile-headline',
            'data-profile-biography',
            'data-profile-links',
            'data-profile-sections',
            'data-profile-followers',
            'data-profile-supporters',
            'data-profile-products',
        ] as $marker) {
            self::assertStringContainsString($marker, $page);
        }
    }

    public function testControllerUsesSafeDomProjectionAndSafeUrls(): void
    {
        $script = $this->read('assets/js/public-profile.js');
        foreach (['textContent', 'replaceChildren', 'safeUrl(', 'noopener noreferrer', 'encodeURIComponent(slug)'] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
        foreach (['eval(', 'document.write(', '.innerHTML =', 'insertAdjacentHTML('] as $unsafe) {
            self::assertStringNotContainsString($unsafe, $script);
        }
    }

    public function testControllerRequestsMinimalInitialCollectionPayload(): void
    {
        $script = $this->read('assets/js/public-profile.js');
        self::assertStringContainsString('product_limit=1&post_limit=1&plan_limit=1', $script);
        self::assertStringContainsString("preview ? '&preview=1'", $script);
    }

    public function testResponsiveStylesCoverDesktopMobileLoadingAndReducedMotion(): void
    {
        $style = $this->read('assets/css/public-profile.css');
        foreach ([
            '.mg-public-profile-grid',
            '.mg-profile-loading',
            '.mg-profile-preview-banner',
            '@media(max-width:900px)',
            '@media(max-width:640px)',
            '@media(prefers-reduced-motion:reduce)',
        ] as $needle) {
            self::assertStringContainsString($needle, $style);
        }
    }

    public function testAccountProfileLinkTargetsTheHtmlProfileRoute(): void
    {
        $account = $this->read('account.php');
        $link = $this->read('assets/js/account-public-profile-link.js');
        self::assertStringContainsString('/assets/js/account-public-profile-link.js', $account);
        self::assertStringContainsString('/profile.php?slug=', $link);
    }
}
