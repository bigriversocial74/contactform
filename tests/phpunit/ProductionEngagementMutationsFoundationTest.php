<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionEngagementMutationsFoundationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function read(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testProfileTipBoundaryUsesPublicIdentifierAndCardConfirmationIsServerAuthoritative(): void
    {
        $adapter=$this->read('api/tips/_engagement.php');
        $create=$this->read('api/tips/create.php');
        $confirm=$this->read('api/tips/confirm.php');
        foreach([
            "WHERE pp.public_id=? AND pp.status='active'","pp.visibility IN ('public','unlisted')",
            "\$input['target_reference']=(string)(int)\$profile['user_id']",
            "\$metadata['public_profile_id']=(string)\$profile['public_id']",
        ] as $needle)self::assertStringContainsString($needle,$adapter);
        self::assertStringContainsString('mg_tip_engagement_input(',$create);
        foreach([
            "sender_user_id=? AND t.funding_type='stripe'",'provider_intent_reference',
            'mg_payment_provider_retrieve_intent(',"intent_status",'mg_tip_finalize_stripe(',
            'mg_tip_confirmation_key(','mg_tip_notify_recipient(',
        ] as $needle)self::assertStringContainsString($needle,$adapter.$confirm);
        foreach(['payment_status','provider_status','amount_cents'] as $untrusted){
            self::assertStringNotContainsString("\$input['{$untrusted}']",$confirm);
        }
    }

    public function testProfileUiWiresFollowReactionsCommentsAndCardConfirmation(): void
    {
        $page=$this->read('profile.php');
        $client=$this->read('assets/js/public-profile-engagement.js');
        $css=$this->read('assets/css/public-profile-engagement.css');
        foreach([
            'data-profile-follow','data-profile-follow-status','data-profile-posts-section',
            'data-profile-support-section',
        ] as $needle)self::assertStringContainsString($needle,$page);
        foreach([
            '/api/social/relationship.php','/api/social/engage.php',
            '/api/public/post-engagement.php','/api/tips/create.php','/api/tips/confirm.php',
            'mg:payment:requires-confirmation','mg:payment:confirmed','textContent',
        ] as $needle)self::assertStringContainsString($needle,$client);
        foreach([
            '.mg-profile-post-actions','.mg-profile-reaction-button','.mg-profile-comments',
            '.mg-profile-comment-form','.mg-profile-tip-confirmation','@media(max-width:640px)',
        ] as $needle)self::assertStringContainsString($needle,$css);
        foreach(['eval(','document.write(','insertAdjacentHTML('] as $unsafe)self::assertStringNotContainsString($unsafe,$client);
    }

    public function testFocusedValidationIsRegistered(): void
    {
        $composer=$this->read('composer.json');
        $workflow=$this->read('.github/workflows/engagement-mutations-validation.yml');
        self::assertStringContainsString('test-engagement-mutations-behavior',$composer);
        foreach([
            'MG_RUN_ENGAGEMENT_MUTATIONS_BEHAVIOR','composer test-engagement-mutations-behavior',
        ] as $needle)self::assertStringContainsString($needle,$workflow);
    }
}
