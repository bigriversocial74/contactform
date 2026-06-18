<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SocialFeedPublishedAttachmentCardsContractTest extends TestCase
{
    public function testFeedLoadsPublishedAttachmentCardEnhancer(): void
    {
        $footer=file_get_contents(dirname(__DIR__,2).'/includes/footer.php');
        self::assertIsString($footer);
        self::assertStringContainsString("/assets/js/social-feed-attachments.js",$footer);
        self::assertStringContainsString("/assets/js/social-feed-attachment-cards.js",$footer);
    }

    public function testBatchEndpointRechecksPostVisibilityBeforeReturningCards(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/public/feed-attachments.php');
        self::assertIsString($source);
        foreach([
            "require_once dirname(__DIR__) . '/social/_publishing.php';",
            "require_once dirname(__DIR__) . '/social/_published_attachment_cards.php';",
            "mg_public_profile_session_viewer",
            "count(\$ids)>36",
            "mg_rate_limit('social.feed_attachments.read'",
            "mg_social_view_context",
            "mg_social_can_view",
            "mg_feed_published_attachment_cards",
            "Cache-Control: private, no-store, max-age=0",
            "Vary: Cookie, Authorization",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testSerializerBuildsProductGiftAndMembershipCardsWithAccessRules(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/social/_published_attachment_cards.php');
        self::assertIsString($source);
        foreach([
            "p.status='published'",
            "catalog_product_version_assets",
            "pva2.product_version_id=i.product_version_id",
            "i.owner_user_id",
            "i.issuer_user_id",
            "i.recipient_user_id",
            "Open Microgift",
            "Private Microgift",
            "Sign in to check access",
            "status IN ('trialing','active','cancel_pending')",
            "current_period_end>NOW()",
            "Active membership",
            "Membership available",
            "View product",
            "value_cents",
            "image_url",
            "interval_unit",
            "trial_days",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testClientBatchesPostsAndRendersPolishedAccessAwareCards(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/social-feed-attachment-cards.js');
        self::assertIsString($source);
        foreach([
            "/api/public/feed-attachments.php?post_ids=",
            "new MutationObserver",
            "var cache=new Map()",
            "cache.set(id,items)",
            "mg-feed-linked-card",
            "mg-feed-linked-status",
            "mg-feed-linked-value",
            "mg-feed-linked-access",
            "mg-feed-linked-action",
            "Intl.NumberFormat",
            "button.disabled=true",
            "Attached Microgifter items",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('innerHTML',$source);
    }

    public function testPublishedCardsHaveResponsiveProductGiftAndMembershipPresentation(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/css/social-feed-attachment-cards.css');
        self::assertIsString($source);
        foreach([
            '.mg-feed-linked-grid',
            '.mg-feed-linked-card',
            '.mg-feed-linked-preview.is-microgift',
            '.mg-feed-linked-preview.is-plan',
            '.mg-feed-linked-status.is-expired',
            '.mg-feed-linked-access.is-restricted',
            '.mg-feed-linked-action:disabled',
            '@media(max-width:720px)',
            '@media(max-width:480px)',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
