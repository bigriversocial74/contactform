<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SocialFeedAttachmentPickerContractTest extends TestCase
{
    public function testFeedLoadsTheRealAttachmentPickerClient(): void
    {
        $footer=file_get_contents(dirname(__DIR__,2).'/includes/footer.php');
        self::assertIsString($footer);
        self::assertStringContainsString("if ((\$page_section ?? '') === 'feed')",$footer);
        self::assertStringContainsString("\$page_scripts[] = '/assets/js/social-feed-attachments.js';",$footer);
    }

    public function testPickerReplacesPublicIdInputsWithSearchableCards(): void
    {
        $client=file_get_contents(dirname(__DIR__,2).'/assets/js/social-feed-attachments.js');
        self::assertIsString($client);
        foreach([
            "product: 'product_id'",
            "microgift: 'microgift_id'",
            "plan: 'subscription_plan_id'",
            "field.type = 'hidden'",
            'Attach something from Microgifter',
            'Choose product',
            'Choose Microgift',
            'Choose plan',
            '/api/social/attachment-options.php?type=',
            'dataset.attachmentChoose',
            'Choose an active member plan for subscriber-only posts.',
            "['subscribers','premium']",
        ] as $needle){
            self::assertStringContainsString($needle,$client);
        }
        self::assertStringNotContainsString('prompt(',$client);
    }

    public function testPickerEndpointScopesEveryAttachmentToTheCurrentUser(): void
    {
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/social/attachment-options.php');
        self::assertIsString($endpoint);
        foreach([
            "mg_require_permission('social.posts.create')",
            "in_array(\$type, ['product','microgift','plan'], true)",
            'p.merchant_user_id=?',
            '(i.owner_user_id=? OR i.issuer_user_id=?)',
            "sp.owner_user_id=? AND sp.status='active'",
            'mg_rate_limit',
            "AND p.public_id=?",
            "AND i.public_id=?",
            "AND sp.public_id=?",
            'catalog_product_version_assets',
            "a2.status='ready'",
        ] as $needle){
            self::assertStringContainsString($needle,$endpoint);
        }
    }

    public function testPickerHasResponsiveSelectedAndDialogPresentation(): void
    {
        $css=file_get_contents(dirname(__DIR__,2).'/assets/css/social-feed-attachments.css');
        self::assertIsString($css);
        foreach([
            '.mg-feed-item-attachment-grid',
            '.mg-feed-selected-item',
            '.mg-feed-attachment-dialog',
            '.mg-feed-attachment-option',
            '.mg-feed-item-attachment-slot.is-required',
            '@media(max-width:900px)',
        ] as $needle){
            self::assertStringContainsString($needle,$css);
        }
    }
}
