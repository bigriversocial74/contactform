<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HomepageSaasV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testHomepageKeepsUniversalHeaderAndFooter(): void
    {
        $source = file_get_contents($this->root . '/index.php');
        self::assertIsString($source);
        self::assertStringContainsString("require __DIR__ . '/includes/header.php';", $source);
        self::assertStringContainsString("require __DIR__ . '/includes/footer.php';", $source);
        self::assertStringContainsString("'id' => 'homepage-saas'", $source);
        self::assertStringNotContainsString('homepage-parallax-exact-v2', $source);
        self::assertStringNotContainsString('hero-scroll', $source);
    }

    public function testHomepageUsesDesktopAndPhoneProductArtwork(): void
    {
        $source = file_get_contents($this->root . '/index.php');
        self::assertIsString($source);
        self::assertStringContainsString('/assets/images/home/microgifter-home-desktop-dashboard.svg', $source);
        self::assertStringContainsString('/assets/images/home/microgifter-home-phone.svg', $source);
        self::assertFileExists($this->root . '/assets/images/home/microgifter-home-desktop-dashboard.svg');
        self::assertFileExists($this->root . '/assets/images/home/microgifter-home-phone.svg');
    }

    public function testStandaloneCrmShowcaseWasRemoved(): void
    {
        $source = file_get_contents($this->root . '/index.php');
        self::assertIsString($source);
        self::assertStringNotContainsString('Build relationships with Microgifter CRM', $source);
        self::assertStringNotContainsString('mg-core-crm', $source);
        self::assertStringNotContainsString('id="merchant-crm"', $source);
    }

    public function testComingSoonIntegrationsAreExplicit(): void
    {
        $source = file_get_contents($this->root . '/index.php');
        self::assertIsString($source);
        self::assertStringContainsString('Coming soon', $source);
        self::assertStringContainsString('Gusto', $source);
        self::assertStringContainsString('Square', $source);
        self::assertStringContainsString('Toast', $source);
        self::assertStringContainsString('Other POS Systems', $source);
    }

    public function testHomepageStylesAreResponsiveAndSpacious(): void
    {
        foreach ([
            'homepage-saas-v1.css',
            'homepage-saas-core-v1.css',
            'homepage-saas-visuals-v1.css',
            'homepage-saas-sections-v1.css',
            'homepage-saas-responsive-v1.css',
            'homepage-saas-blue-v1.css',
        ] as $file) {
            self::assertFileExists($this->root . '/assets/css/' . $file);
        }

        $core = file_get_contents($this->root . '/assets/css/homepage-saas-core-v1.css');
        $responsive = file_get_contents($this->root . '/assets/css/homepage-saas-responsive-v1.css');
        self::assertIsString($core);
        self::assertIsString($responsive);
        self::assertStringContainsString('padding-block: clamp(104px, 11vw, 168px)', $core);
        self::assertStringContainsString('@media (max-width: 980px)', $responsive);
        self::assertStringContainsString('@media (max-width: 680px)', $responsive);
    }

    public function testHomepageUsesBlueAccentsSharedTypographyAndWhiteSecondSection(): void
    {
        $bundle = file_get_contents($this->root . '/assets/css/homepage-saas-v1.css');
        $theme = file_get_contents($this->root . '/assets/css/homepage-saas-blue-v1.css');
        $desktop = file_get_contents($this->root . '/assets/images/home/microgifter-home-desktop-dashboard.svg');
        $phone = file_get_contents($this->root . '/assets/images/home/microgifter-home-phone.svg');

        self::assertIsString($bundle);
        self::assertIsString($theme);
        self::assertIsString($desktop);
        self::assertIsString($phone);
        self::assertStringContainsString('homepage-saas-blue-v1.css', $bundle);
        self::assertStringContainsString('--mg-home-teal: #2563eb', $theme);
        self::assertStringContainsString('.mg-home-features', $theme);
        self::assertStringContainsString('background: #fff', $theme);
        self::assertStringContainsString('font-family: Inter, system-ui', $theme);
        self::assertStringContainsString('font-weight: 300', $theme);
        self::assertStringContainsString('font-size: clamp(44px, 5.7vw, 76px)', $theme);
        self::assertStringContainsString('letter-spacing: -.055em', $theme);
        self::assertStringContainsString('-webkit-text-fill-color: #fff', $theme);
        self::assertStringNotContainsString('font-weight: 900;', $theme);
        self::assertStringNotContainsString('#0b934a', $desktop);
        self::assertStringNotContainsString('#00847c', $desktop);
        self::assertStringNotContainsString('#f7fbfb', $phone);
        self::assertStringNotContainsString('#e7efed', $phone);
    }
}
