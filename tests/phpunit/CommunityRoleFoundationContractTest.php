<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CommunityRoleFoundationContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCommunityIsAdminControlledAndMultiRoleSafe(): void
    {
        $helper = (string) file_get_contents($this->root . '/api/admin/_user_management.php');
        $endpoint = (string) file_get_contents($this->root . '/api/admin/user-management.php');
        $admin = (string) file_get_contents($this->root . '/admin/users.php');

        self::assertStringContainsString("['customer', 'community', 'merchant']", $helper);
        self::assertStringContainsString('INSERT IGNORE INTO user_roles', $helper);
        self::assertStringContainsString('DELETE FROM user_roles WHERE user_id = ? AND role_id = ?', $helper);
        self::assertStringContainsString("['admin', 'super_admin']", $helper);
        self::assertStringContainsString("'add_role', 'remove_role' => 'admin.users.manage'", $helper);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $endpoint);
        self::assertStringContainsString("mg_rate_limit('admin.user_management.write'", $endpoint);
        self::assertStringContainsString('value="community"', $admin);
    }

    public function testCommunityBadgeIsRoleOnlyAndPubliclyEligibilityGated(): void
    {
        $helper = (string) file_get_contents($this->root . '/includes/role-badges.php');
        $api = (string) file_get_contents($this->root . '/api/public/profile-role-badges.php');
        $javascript = (string) file_get_contents($this->root . '/assets/js/community-role-badges-v1.js');

        self::assertStringContainsString("'rendered_label' => '★ Community'", $helper);
        self::assertStringContainsString('Role status only', $helper);
        self::assertStringContainsString("pp.status = 'active'", $api);
        self::assertStringContainsString("pp.visibility IN ('public','unlisted')", $api);
        self::assertStringContainsString("renderedLabel: '★ Community'", $javascript);
        self::assertStringContainsString('not identity, nonprofit, charity, campaign, financial, government', $javascript);
    }

    public function testMasterMigrationProvidesIdempotentFoundation(): void
    {
        $sql = (string) file_get_contents($this->root . '/database/20260724_public_donations_community_v1_single_install.sql');

        self::assertStringContainsString("ON DUPLICATE KEY UPDATE name = VALUES(name)", $sql);
        self::assertStringContainsString('INSERT IGNORE INTO role_permissions', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS campaign_community_assignments', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS campaign_donation_operations', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS campaign_donation_batches', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS campaign_donation_rewards', $sql);
        self::assertStringContainsString('uq_campaign_donation_operations_idempotency', $sql);
        self::assertStringContainsString('public_display_status', $sql);
        self::assertStringNotContainsString('DROP TABLE ', $sql);
        self::assertStringNotContainsString('TRUNCATE TABLE ', $sql);
    }
}
