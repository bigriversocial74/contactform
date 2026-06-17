<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductBuilderSharedShellTest extends TestCase
{
    public function testAllTemplatesUseOneSharedBuilderSidebarAndPreviewFrame(): void
    {
        $root=dirname(__DIR__,2);
        $page=file_get_contents($root.'/build.php');
        $sidebar=file_get_contents($root.'/includes/product-builder-sidebar.php');
        $css=file_get_contents($root.'/assets/css/builder-stage4b.css');
        $js=file_get_contents($root.'/assets/js/product-builder-shell.js');
        self::assertIsString($page);
        self::assertIsString($sidebar);
        self::assertIsString($css);
        self::assertIsString($js);
        self::assertStringContainsString('includes/product-builder-sidebar.php',$page);
        self::assertSame(1,substr_count($page,'<div class="mg-builder-preview-frame">'));
        foreach(['simple_product','greeting_card','multimedia_greeting_card','simple_collab'] as $template){
            self::assertStringContainsString('data-preview-template="'.$template.'"',$page);
            self::assertStringContainsString('value="'.$template.'"',$sidebar);
        }
        self::assertStringNotContainsString('data-builder-sidebar-toggle',$sidebar);
        self::assertStringNotContainsString('data-save-draft',$sidebar);
        self::assertStringContainsString('.mg-builder-sidebar.is-open',$css);
        self::assertStringContainsString('data-preview-template-label',$js);
    }
}
