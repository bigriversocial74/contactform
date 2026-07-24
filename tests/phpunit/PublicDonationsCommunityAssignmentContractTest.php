<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/public-donations-community-assignments.php';

final class PublicDonationsCommunityAssignmentContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testRoleLabelsKeepPublicRolesAndHideAdministrativeRoles(): void
    {
        self::assertSame(
            ['Customer', 'Creator', 'Merchant'],
            mg_public_donations_assignment_role_labels('community,customer,creator,merchant,admin,super_admin,creator')
        );
    }

    public function testMediaUrlOnlyAllowsInternalOrHttpUrls(): void
    {
        self::assertSame('/uploads/avatar.jpg', mg_public_donations_assignment_safe_media_url('/uploads/avatar.jpg'));
        self::assertSame('https://example.com/avatar.jpg', mg_public_donations_assignment_safe_media_url('https://example.com/avatar.jpg'));
        self::assertNull(mg_public_donations_assignment_safe_media_url('javascript:alert(1)'));
        self::assertNull(mg_public_donations_assignment_safe_media_url('//example.com/avatar.jpg'));
    }

    public function testPrivateProfileIdentityDoesNotExposeProfileMediaOrLocation(): void
    {
        $identity = mg_public_donations_assignment_identity([
            'community_account_id' => 'pp_private',
            'display_name' => 'Community Member',
            'profile_slug' => 'private-member',
            'profile_status' => 'active',
            'profile_visibility' => 'private',
            'avatar_url' => '/private/avatar.jpg',
            'location_label' => 'Exact hidden place',
            'role_slugs' => 'community,customer,admin',
        ]);
        self::assertSame('pp_private', $identity['community_account_id']);
        self::assertTrue($identity['community_badge']);
        self::assertNull($identity['public_profile_url']);
        self::assertNull($identity['avatar_url']);
        self::assertNull($identity['general_location']);
        self::assertSame(['Customer'], $identity['other_roles']);
        self::assertArrayNotHasKey('email', $identity);
        self::assertArrayNotHasKey('phone', $identity);
        self::assertArrayNotHasKey('address', $identity);
    }

    public function testPublicProfileIdentityUsesOnlyApprovedPublicFields(): void
    {
        $identity = mg_public_donations_assignment_identity([
            'community_account_id' => 'pp_public',
            'display_name' => 'Public Community',
            'profile_slug' => 'public-community',
            'profile_status' => 'active',
            'profile_visibility' => 'public',
            'avatar_url' => '/uploads/public.jpg',
            'location_label' => 'Phoenix area',
            'role_slugs' => 'community,creator,merchant,super_admin',
            'assignment_public_id' => '123e4567-e89b-12d3-a456-426614174000',
            'assignment_status' => 'paused',
        ]);
        self::assertSame('/profile.php?slug=public-community', $identity['public_profile_url']);
        self::assertSame('/uploads/public.jpg', $identity['avatar_url']);
        self::assertSame('Phoenix area', $identity['general_location']);
        self::assertSame(['Creator', 'Merchant'], $identity['other_roles']);
        self::assertSame('paused', $identity['assignment']['status']);
    }

    public function testSourceEnforcesCommunityEligibilityAndIdempotentStateTransitions(): void
    {
        $source = (string)file_get_contents($this->root . '/includes/public-donations-community-assignments.php');
        self::assertStringContainsString("community_role.slug='community'", $source);
        self::assertStringContainsString("u.status='active'", $source);
        self::assertStringContainsString('GROUP_CONCAT(DISTINCT role_all.slug', $source);
        self::assertStringContainsString("if ($currentStatus !== 'active')", $source);
        self::assertStringContainsString("SET status='active',reactivated_at=NOW()", $source);
        self::assertStringContainsString("SET status='paused',paused_at=NOW()", $source);
        self::assertStringContainsString("SET status='removed',removed_at=NOW()", $source);
        self::assertStringContainsString('mg_create_notification(', $source);
    }

    public function testSourceNeverMutatesRewardInventory(): void
    {
        $source = (string)file_get_contents($this->root . '/includes/public-donations-community-assignments.php');
        $endpoint = (string)file_get_contents($this->root . '/api/merchant/public-donations-community.php');
        self::assertDoesNotMatchRegularExpression(
            '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:wallet_items|reward_templates|campaign_donation_rewards|campaign_donation_reward_events)\b/i',
            $source . "\n" . $endpoint
        );
    }

    public function testClientUsesDomConstructionInsteadOfHtmlInjection(): void
    {
        $client = (string)file_get_contents($this->root . '/assets/js/public-donations-community-assignments.js');
        self::assertStringNotContainsString('.innerHTML', $client);
        self::assertStringContainsString('replaceChildren', $client);
        self::assertStringContainsString('textContent', $client);
    }
}
