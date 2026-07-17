<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreateCenterPostModalContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    public function testPostTriggerSurvivesManagedCardReplacement(): void
    {
        $source=file_get_contents($this->root.'/assets/js/create-center-post-trigger-fix.js');
        self::assertIsString($source);
        self::assertStringContainsString('[data-create-inline-target="post"],[data-create-menu-option="post"]',$source);
        self::assertStringContainsString("view.dataset.createCenterView === 'post'",$source);
        self::assertStringContainsString("modal.dataset.createPostActive = 'true'",$source);
        self::assertStringContainsString('event.stopImmediatePropagation()',$source);
        self::assertStringContainsString("document.body.classList.remove('mg-post-composer-open')",$source);
        self::assertStringContainsString('MG.openCreateCenterPost = openPostView',$source);
        self::assertStringContainsString('microgifter:openPostComposer',$source);
        self::assertStringContainsString('new MutationObserver',$source);
    }

    public function testCompatibilityRouterUsesEmbeddedPostWorkspace(): void
    {
        $source=file_get_contents($this->root.'/assets/js/create-post-modal-visible-fallback.js');
        self::assertIsString($source);
        self::assertStringContainsString('function embeddedPostNodes()',$source);
        self::assertStringContainsString('function openEmbeddedPost()',$source);
        self::assertStringContainsString('window.Microgifter.openCreateCenterPost',$source);
        self::assertStringContainsString('if (openEmbeddedPost()) return true;',$source);
        self::assertStringContainsString('event.stopImmediatePropagation()',$source);
        self::assertStringContainsString('return forceLegacyComposerVisible();',$source);
    }

    public function testPostWorkspaceUsesProfessionalResponsiveLayout(): void
    {
        $source=file_get_contents($this->root.'/assets/css/create-center-post-professional.css');
        self::assertIsString($source);
        self::assertStringContainsString('.mg-create-center-post .mg-create-inline-post-form',$source);
        self::assertStringContainsString('grid-template-areas:',$source);
        self::assertStringContainsString('"copy media"',$source);
        self::assertStringContainsString('position:sticky',$source);
        self::assertStringContainsString('@media(max-width:1180px)',$source);
        self::assertStringContainsString('@media(max-width:820px)',$source);
    }

    public function testPostRuntimeLoadsCurrentAssets(): void
    {
        $runtime=file_get_contents($this->root.'/includes/header-components/post-composer-modal.php');
        $footer=file_get_contents($this->root.'/includes/footer.php');
        self::assertIsString($runtime);
        self::assertIsString($footer);
        self::assertStringContainsString('/assets/css/create-center-inline.css?v=1.1.0',$runtime);
        self::assertStringContainsString('/assets/css/create-center-post-professional.css?v=1.0.0',$runtime);
        self::assertStringContainsString('/assets/js/create-center-post-inline.js?v=1.1.0',$runtime);
        self::assertStringContainsString('/assets/js/create-center-post-trigger-fix.js?v=1.2.0',$runtime);
        self::assertStringContainsString('/assets/js/create-post-modal-visible-fallback.js?v=1.1.0',$footer);
    }
}
