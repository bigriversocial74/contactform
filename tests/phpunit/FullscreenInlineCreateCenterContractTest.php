<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FullscreenInlineCreateCenterContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    public function testCreateCenterContainsDirectInlineFormsAndSuccessStates(): void
    {
        $source=file_get_contents($this->root.'/includes/header-templates/create-menu.php');
        self::assertIsString($source);

        foreach(['product','campaign','reward','storefront','location'] as $type){
            self::assertStringContainsString('data-create-inline-form="'.$type.'"',$source);
            self::assertStringContainsString('data-create-inline-success="'.$type.'"',$source);
            self::assertStringContainsString('data-create-inline-reset="'.$type.'"',$source);
            self::assertStringContainsString("'key' => '".$type."'",$source);
        }

        self::assertStringContainsString("'key' => 'post'",$source);
        self::assertStringContainsString('id="mg-create-center-post" data-create-center-view="post"',$source);
        self::assertStringContainsString('data-create-post-success',$source);
        self::assertStringContainsString('data-create-menu-close aria-label="Close create center">×</button>',$source);
        self::assertStringContainsString('data-create-inline-target="<?= mg_e($target) ?>"',$source);
    }

    public function testInlineControllerUsesExistingProductionEndpoints(): void
    {
        $source=file_get_contents($this->root.'/assets/js/create-center-inline.js');
        self::assertIsString($source);

        foreach([
            "/api/catalog/builder-draft.php",
            "/api/catalog/upload.php",
            "/api/merchant/campaigns.php",
            "/api/merchant/reward-templates.php",
            "/api/merchant/storefront.php",
            "/api/merchant/locations.php",
        ] as $endpoint){
            self::assertStringContainsString($endpoint,$source);
        }

        self::assertStringContainsString('event.stopImmediatePropagation()',$source);
        self::assertStringContainsString('showSuccess(', $source);
    }

    public function testStorefrontInlineSavePreservesExistingBrandMedia(): void
    {
        $source=file_get_contents($this->root.'/assets/js/create-center-storefront-preserve.js');
        self::assertIsString($source);

        self::assertStringContainsString('currentRevision.logo_asset_public_id',$source);
        self::assertStringContainsString('currentRevision.cover_asset_public_id',$source);
        self::assertStringContainsString("MG.get('/api/merchant/storefront.php')",$source);
        self::assertStringContainsString("MG.post('/api/merchant/storefront.php'",$source);
        self::assertStringContainsString("form.addEventListener('submit', saveStorefront, true)",$source);
    }

    public function testPostComposerLivesInsideCreateCenterAndSubmitsDirectly(): void
    {
        $menu=file_get_contents($this->root.'/includes/header-templates/create-menu.php');
        $composer=file_get_contents($this->root.'/includes/social-feed-composer.php');
        $runtime=file_get_contents($this->root.'/includes/header-components/post-composer-modal.php');
        $controller=file_get_contents($this->root.'/assets/js/create-center-post-inline.js');
        self::assertIsString($menu);
        self::assertIsString($composer);
        self::assertIsString($runtime);
        self::assertIsString($controller);

        self::assertStringContainsString('data-create-center-view="post"',$menu);
        self::assertStringContainsString('mg-create-inline-post-composer',$composer);
        self::assertStringNotContainsString('data-global-post-composer',$runtime);
        self::assertStringContainsString('/assets/js/create-center-post-inline.js',$runtime);
        self::assertStringContainsString("MG.post('/api/social/posts.php'",$controller);
        self::assertStringContainsString('data-create-post-success',$controller);
    }

    public function testMobileCreateCenterHidesToolRailAndCancelButtons(): void
    {
        $createCss=file_get_contents($this->root.'/assets/css/create-center-inline.css');
        $mobileCss=file_get_contents($this->root.'/assets/css/create-center-mobile-post-unified.css');
        $menuJs=file_get_contents($this->root.'/assets/js/create-menu.js');
        self::assertIsString($createCss);
        self::assertIsString($mobileCss);
        self::assertIsString($menuJs);

        self::assertStringContainsString('width:100vw!important',$createCss);
        self::assertStringContainsString('height:100dvh!important',$createCss);
        self::assertStringContainsString('@media(max-width:820px)',$mobileCss);
        self::assertStringContainsString('.mg-create-center-rail',$mobileCss);
        self::assertStringContainsString('.mg-create-inline-actions>.mg-create-secondary[data-create-center-home]',$mobileCss);
        self::assertStringContainsString('input:not([disabled]),select:not([disabled]),textarea:not([disabled])',$menuJs);
    }
}