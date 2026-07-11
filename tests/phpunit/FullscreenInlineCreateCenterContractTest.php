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
        }

        self::assertStringContainsString('data-create-menu-close aria-label="Close create center">×</button>',$source);
        self::assertStringContainsString('data-create-inline-target="product"',$source);
        self::assertStringContainsString('data-create-inline-target="location"',$source);
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

    public function testCreateAndPostModalsUseFullscreenLayoutsAndTopRightCloseButtons(): void
    {
        $createCss=file_get_contents($this->root.'/assets/css/create-center-inline.css');
        $postCss=file_get_contents($this->root.'/assets/css/post-composer-modal.css');
        $postModal=file_get_contents($this->root.'/includes/header-components/post-composer-modal.php');
        self::assertIsString($createCss);
        self::assertIsString($postCss);
        self::assertIsString($postModal);

        self::assertStringContainsString('width:100vw!important',$createCss);
        self::assertStringContainsString('height:100dvh!important',$createCss);
        self::assertStringContainsString('width:100vw',$postCss);
        self::assertStringContainsString('height:100dvh',$postCss);
        self::assertStringContainsString('class="mg-post-composer-x"',$postModal);
        self::assertStringContainsString('/assets/js/create-center-inline.js',$postModal);
        self::assertStringContainsString('/assets/js/create-center-storefront-preserve.js',$postModal);
    }

    public function testPostComposerRetainsDirectSubmitAndVisibleSuccessFeedback(): void
    {
        $controller=file_get_contents($this->root.'/assets/js/global-post-composer.js');
        $success=file_get_contents($this->root.'/assets/js/create-center-post-success.js');
        self::assertIsString($controller);
        self::assertIsString($success);

        self::assertStringContainsString("MG.post('/api/social/posts.php'",$controller);
        self::assertStringContainsString('Post published.',$controller);
        self::assertStringContainsString('Post saved as a draft.',$controller);
        self::assertStringContainsString('mg-create-post-success-toast',$success);
    }
}
