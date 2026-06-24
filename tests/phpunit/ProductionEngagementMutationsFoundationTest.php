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

    public function testRealDatabaseBehaviorMatrix(): void
    {
        if((string)getenv('MG_RUN_ENGAGEMENT_MUTATIONS_BEHAVIOR')!=='1'){
            self::markTestSkipped('Real-database engagement behavior runs in focused validation.');
        }
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database validation requires MG_DB_HOST.');
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->root.'/scripts/validate_engagement_mutations_behavior.php').' 2>&1';
        $output=[];$exitCode=0;exec($command,$output,$exitCode);$raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);
        self::assertIsArray($result,$raw);
        self::assertSame('engagement_mutations_foundation',$result['suite']??null);
        foreach([
            'follow_idempotency','follow_count','block_cleanup','block_enforcement',
            'reaction_create_change_remove','reaction_idempotency_conflict','comment_create_replay',
            'comment_permissions','comment_count','hidden_post_exclusion','safe_projection',
            'public_profile_tip_target','card_tip_pending','card_tip_confirmation',
            'card_tip_confirmation_replay','single_tip_ledger','rollback_clean',
        ] as $key)self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
    }

    public function testMigrationCreatesBoundReplayAuthorityAndIsOrdered(): void
    {
        $migration=$this->read('database/stage_18e_engagement_mutations.sql');
        $builder=$this->read('scripts/build_full_upgrade_sql.php');
        foreach([
            'CREATE TABLE IF NOT EXISTS social_mutation_requests',
            'UNIQUE KEY uq_social_mutation_actor_key (actor_user_id,idempotency_key)',
            'request_fingerprint CHAR(64)',
            'response_json JSON',
        ] as $needle)self::assertStringContainsString($needle,$migration);
        self::assertStringContainsString("'stage_18e_engagement_mutations.sql'",$builder);
    }

    public function testRelationshipMutationsUsePublicProfilesBlocksRateLimitsAndReplayProtection(): void
    {
        $endpoint=$this->read('api/social/relationship.php');
        $service=$this->read('api/social/_engagement.php');
        foreach([
            "mg_require_permission('social.engage')","mg_require_csrf_for_write(",
            "mg_rate_limit('social.relationship.write'",'mg_engagement_claim(',
            "'profile_id'=>\$targetReference",'mg_engagement_relationship(',
        ] as $needle)self::assertStringContainsString($needle,$endpoint);
        foreach([
            "pp.status='active'","pp.visibility IN ('public','unlisted')","u.status='active'",
            'mg_social_is_blocked(',"DE"."LETE FROM social_follows WHERE (follower_user_id=?",
            'social_mutation_requests','hash_equals(',
        ] as $needle)self::assertStringContainsString($needle,$service);
        self::assertStringNotContainsString("'target_user_id'=>",$endpoint);
    }

    public function testPostMutationsEnforceVisibilityCountersCommentsAndModerationPermissions(): void
    {
        $endpoint=$this->read('api/social/engage.php');
        $service=$this->read('api/social/_engagement.php');
        foreach([
            "'react','unreact','comment','comment_delete','comment_hide','comment_restore'",
            "mg_rate_limit('social.engagement.write'",'mg_engagement_reaction(',
            'mg_engagement_comment_create(','mg_engagement_comment_moderate(',
        ] as $needle)self::assertStringContainsString($needle,$endpoint);
        foreach([
            'mg_social_can_view(',"reaction_count=GREATEST(reaction_count-1,0)",
            "comment_count=GREATEST(comment_count-1,0)","Replies may only be one level deep.",
            "'can_delete'=>","'can_hide'=>",'MG_ENGAGEMENT_COMMENT_MAX',
        ] as $needle)self::assertStringContainsString($needle,$service);
    }

    public function testPublicEngagementReadIsBlockAwareSafeAndCacheBound(): void
    {
        $endpoint=$this->read('api/public/post-engagement.php');
        foreach([
            'mg_public_profile_session_viewer(',"mg_rate_limit('social.engagement.read'",
            'mg_engagement_post(','mg_engagement_comments(','Cache-Control: public, max-age=15',
            'Cache-Control: private, no-store','Vary: Cookie, Authorization',
        ] as $needle)self::assertStringContainsString($needle,$endpoint);
        foreach(['email','user_id','feed_post_id','post_owner_id','metadata_json'] as $forbidden){
            self::assertStringNotContainsString("'{$forbidden}' =>",$endpoint);
        }
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
            'ProductionEngagementMutationsFoundationTest','engagement-mutations-foundation.spec.js',
            'build_full_upgrade_sql.php','composer test-frontend-contracts','composer test','npm run test:browser',
        ] as $needle)self::assertStringContainsString($needle,$workflow);
    }
}
