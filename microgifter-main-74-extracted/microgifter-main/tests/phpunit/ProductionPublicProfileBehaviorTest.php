<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionPublicProfileBehaviorTest extends TestCase
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
        if((string)getenv('MG_RUN_PUBLIC_PROFILE_BEHAVIOR')!=='1')self::markTestSkipped('Real-database profile behavior runs only in the focused validation workflow.');
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed public-profile validation requires MG_DB_HOST.');
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->root.'/scripts/validate_public_profile_behavior.php').' 2>&1';
        $output=[];$exitCode=0;exec($command,$output,$exitCode);$raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);self::assertIsArray($result,$raw);
        self::assertSame('canonical_public_profile_read_contract',$result['suite']??null);
        foreach([
            'anonymous_public','anonymous_non_public_hidden','unlisted_direct_only','owner_preview_authenticated',
            'blocked_non_enumerating','inactive_components_excluded','products_filtered','posts_moderated',
            'follower_visibility','subscriber_visibility','recovery_revokes_access','expired_canceled_revoke_access',
            'tip_eligibility','no_private_data','stable_pagination','read_side_effect_free','bounded_queries','canonical_authorities',
        ] as $key)self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        self::assertLessThan(20,(int)($result['queries_per_read']??PHP_INT_MAX),$raw);
    }

    public function testCanonicalEndpointIsGetOnlyNonEnumeratingAndCacheAware(): void
    {
        $endpoint=$this->read('api/public/profile.php');
        foreach([
            "mg_require_method('GET')",'mg_public_profile_session_viewer(','mg_public_profile_read(',
            "mg_fail('Profile not found.',404)",'Cache-Control: public, max-age=60','Cache-Control: private, no-store',
            'Vary: Cookie, Authorization','X-Robots-Tag: noindex, nofollow',"header_remove('Set-Cookie')",
        ] as $needle)self::assertStringContainsString($needle,$endpoint);
        foreach(['mg_audit(','mg_event(','mg_refresh_session_user(','mg_require_api_user(','mg_require_csrf_for_write('] as $needle){
            self::assertStringNotContainsString($needle,$endpoint);
        }
        self::assertFileDoesNotExist($this->root.'/api/profiles/public.php','A parallel public-profile endpoint must not exist.');
    }

    public function testServiceAggregatesCanonicalAuthoritiesWithoutMutation(): void
    {
        $service=$this->read('api/profiles/_public_profile.php');
        foreach([
            'mg_social_view_context(','mg_social_can_view(','mg_storefront_owned(','mg_storefront_revision(',
            'mg_tip_public_profile_capability(','recovery_status=\'clear\'','current_period_end>NOW()',
            "cp.status='published'","cpv.version_status='published'",
            "rp.visibility='visible'","is_active=1",
        ] as $needle)self::assertStringContainsString($needle,$service);
        foreach(['INSERT INTO','UPDATE ','mg_audit(','mg_event(','mg_ledger_post(','mg_tip_create('] as $needle){
            self::assertStringNotContainsString($needle,$service);
        }
    }

    public function testStage14VisibilityIsPreloadedButRemainsCanonical(): void
    {
        $social=$this->read('api/social/_social.php');
        $service=$this->read('api/profiles/_public_profile.php');
        foreach(['function mg_social_view_context(','function mg_social_can_view(','active_subscription_plan_ids',"recovery_status='clear'",'current_period_end>NOW()'] as $needle){
            self::assertStringContainsString($needle,$social);
        }
        self::assertStringContainsString('mg_social_can_view($pdo, $post, $viewerId, $socialContext)',$service);
        self::assertStringNotContainsString("match((string)\$post['visibility'])",$service);
    }

    public function testRecoveryValidationCoversDisputesRefundsAndChargebacks(): void
    {
        $runner=$this->read('scripts/validate_public_profile_behavior.php');
        foreach(["'disputed'","'refunded'","'chargeback'",'recovery did not revoke subscriber access','recovery did not revoke supporter count eligibility'] as $needle){
            self::assertStringContainsString($needle,$runner);
        }
    }

    public function testPublicTipCapabilityWrapsStage12AndReturnsPublicProfileId(): void
    {
        $adapter=$this->read('api/tips/_public_availability.php');
        foreach(['mg_tip_resolve_target(','public_profiles',"pp.status='active'","u.status='active'","'id'=>$profilePublicId"] as $needle){
            self::assertStringContainsString($needle,$adapter);
        }
        foreach(['provider_price_id','provider_customer_id','provider_payment_method_ref','wallet','ledger'] as $needle){
            self::assertStringNotContainsString($needle,$adapter);
        }
    }

    public function testContractContainsOnlySafeNamedFieldsAndCursorPagination(): void
    {
        $service=$this->read('api/profiles/_public_profile.php');
        foreach([
            "'profile' =>","'links' =>","'sections' =>","'storefront' =>","'products' =>","'posts' =>",
            "'subscription_plans' =>","'tip' =>","'social_counts' =>","'next_cursor'","'has_more'",
            'mg_public_profile_cursor_encode(','mg_public_profile_cursor_decode(','ORDER BY rp.is_featured DESC',
            'ORDER BY fp.created_at DESC,fp.public_id DESC','ORDER BY created_at DESC,public_id DESC',
        ] as $needle)self::assertStringContainsString($needle,$service);
        foreach(["'email' =>","'phone' =>","'metadata_json' =>","'provider_price_id' =>","'provider_customer_id' =>","'wallet' =>","'ledger' =>"] as $needle){
            self::assertStringNotContainsString($needle,$service);
        }
    }

    public function testValidationRegistrationAndFocusedWorkflow(): void
    {
        $composer=$this->read('composer.json');
        $workflow=$this->read('.github/workflows/public-profile-validation.yml');
        self::assertStringContainsString('"test-public-profile-behavior": "php scripts/validate_public_profile_behavior.php"',$composer);
        foreach([
            'MG_RUN_PUBLIC_PROFILE_BEHAVIOR','api/public/profile.php','composer test-public-profile-behavior','ProductionPublicProfileBehaviorTest','build_full_upgrade_sql.php',
            'composer test-tip-behavior','validate_subscription_behavior.php','stage14_smoke.php','stage5c_smoke.php',
            'composer test-frontend-contracts','composer test','repository-phpunit.log','frontend-contracts.log',
        ] as $needle)self::assertStringContainsString($needle,$workflow);
    }
}
