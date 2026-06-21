<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SocialFeedComposerCleanupContractTest extends TestCase
{
    public function testComposerKeepsPrimaryPostingFlowSimple(): void
    {
        $root=dirname(__DIR__,2);
        $page=file_get_contents($root.'/feed.php');
        $composer=file_get_contents($root.'/includes/social-feed-composer.php');
        self::assertIsString($page);
        self::assertIsString($composer);
        self::assertStringContainsString("require __DIR__ . '/includes/social-feed-composer.php'", $page);
        foreach([
            'Write the update first, then add media or optional Microgifter links.',
            'What do you want to share?',
            'Who can see this post?',
            'Publish post',
            '<summary><span>Advanced options</span>',
            '<summary>Technical Microgifter linking</summary>',
            '<summary>External media URLs</summary>',
        ] as $needle){
            self::assertStringContainsString($needle,$composer);
        }

        $advancedPosition=strpos($composer,'<summary><span>Advanced options</span>');
        self::assertNotFalse($advancedPosition);
        foreach(['Product public ID','Microgift public ID','Subscription plan public ID','External media URLs'] as $technicalField){
            $fieldPosition=strpos($composer,$technicalField);
            self::assertNotFalse($fieldPosition);
            self::assertGreaterThan($advancedPosition,$fieldPosition);
        }
    }

    public function testAttachmentsSupportDragAndAccessibleReordering(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/social-feed-upload.js');
        self::assertIsString($source);
        foreach([
            'item.draggable = true',
            "uploader.addEventListener('dragstart'",
            "uploader.addEventListener('dragover'",
            "uploader.addEventListener('drop'",
            'data-feed-upload-move',
            'Move attachment earlier',
            'Move attachment later',
            'Lead media',
            'Attachment order updated.',
            "postTypeField.value = 'multimedia_card'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testPublishedMediaUsesResponsiveGalleryAndFullWidthPlayers(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/css/social-feed-upload.css');
        self::assertIsString($source);
        foreach([
            '.mg-feed-media:has(>figure:only-child)',
            '.mg-feed-media:has(>figure:nth-child(2):last-child)',
            '.mg-feed-media:has(>figure:nth-child(3):last-child)',
            '.mg-feed-media figure:has(>video)',
            '.mg-feed-media figure:has(>audio)',
            "content:'Audio attachment'",
            'object-fit:contain',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
