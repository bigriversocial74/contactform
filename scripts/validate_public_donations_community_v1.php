<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $content = file_get_contents($root . '/' . $relative);
    if (!is_string($content) || trim($content) === '') {
        throw new RuntimeException('Missing or empty required file: ' . $relative);
    }
    return $content;
};

$admin = $read('admin/users.php');
$management = $read('api/admin/_user_management.php');
$endpoint = $read('api/admin/user-management.php');
$create = $read('api/admin/user-create.php');
$badgeHelper = $read('includes/role-badges.php');
$badgeApi = $read('api/public/profile-role-badges.php');
$badgeJs = $read('assets/js/community-role-badges-v1.js');
$profile = $read('profile.php');
$sql = $read('database/20260724_public_donations_community_v1_single_install.sql');

$mustContain = static function (string $haystack, array $needles, string $label): void {
    foreach ($needles as $needle) {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException($label . ' is missing contract text: ' . $needle);
        }
    }
};

$mustContain($admin, [
    '<option value="community">Community</option>',
    'name="roles[]" value="community"',
    '/assets/js/community-role-badges-v1.js',
], 'Admin User Center');

$mustContain($management, [
    "['customer', 'community', 'merchant']",
    'INSERT IGNORE INTO user_roles',
    'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?',
    "['admin', 'super_admin']",
], 'Admin role service');

$mustContain($endpoint, [
    "mg_require_method('POST')",
    'mg_require_csrf_for_write($input)',
    "mg_rate_limit('admin.user_management.write'",
    "mg_audit('admin_user_' . \$action",
], 'Admin role endpoint');

$mustContain($create, [
    "mg_admin_account_actor_has(\$actor, 'admin.users.manage')",
    'mg_admin_create_user_roles',
], 'Admin create-user endpoint');

$mustContain($badgeHelper, [
    "'rendered_label' => '★ Community'",
    'Role status only',
    'mg_role_badges_for_slugs',
], 'Shared role badge contract');

$mustContain($badgeApi, [
    "pp.status = 'active'",
    "pp.visibility IN ('public','unlisted')",
    'mg_role_badges_for_slugs($roles)',
    "mg_rate_limit('public.profile_role_badges.read'",
], 'Public badge API');

$mustContain($badgeJs, [
    "renderedLabel: '★ Community'",
    'mg:public-profile:data',
    'Community role removal',
    'Future Community campaign relationships may require review or may prevent removal.',
    'window.Microgifter?.publicProfileData',
], 'Badge renderer');
if (str_contains($badgeJs, 'innerHTML')) {
    throw new RuntimeException('Badge renderer must use safe DOM construction instead of innerHTML.');
}

$mustContain($profile, [
    '/assets/css/community-role-badges-v1.css',
    '/assets/js/community-role-badges-v1.js',
], 'Public profile');

$mustContain($sql, [
    "VALUES ('community', 'Community', NOW())",
    "CALL mg_public_donations_append_enum_value('campaigns', 'campaign_type', 'public_donation')",
    "CALL mg_public_donations_append_enum_value('wallet_items', 'source_type', 'public_donation')",
    'SET @mg_public_donations_sql = v_sql',
    'PREPARE mg_public_donations_stmt FROM @mg_public_donations_sql',
    'CREATE TABLE IF NOT EXISTS campaign_community_assignments',
    'CREATE TABLE IF NOT EXISTS campaign_donation_operations',
    'CREATE TABLE IF NOT EXISTS campaign_donation_batches',
    'CREATE TABLE IF NOT EXISTS campaign_donation_rewards',
    'UNIQUE KEY uq_campaign_donation_operations_idempotency',
    'UNIQUE KEY uq_campaign_donation_rewards_wallet_item',
    'public_display_status ENUM',
], 'Community migration');
if (str_contains($sql, 'PREPARE mg_public_donations_stmt FROM v_sql')) {
    throw new RuntimeException('Dynamic MySQL statements must be prepared from a session variable.');
}

foreach (['DROP TABLE ', 'TRUNCATE TABLE ', 'REPLACE INTO users', 'DELETE FROM users'] as $destructive) {
    if (stripos($sql, $destructive) !== false) {
        throw new RuntimeException('Community migration contains a destructive operation: ' . $destructive);
    }
}

foreach ([
    'docs/public-donations-community-v1/README.md',
    'docs/public-donations-community-v1/technical-blueprint.md',
    'docs/public-donations-community-v1/api-contracts.md',
    'docs/public-donations-community-v1/phase-plan.md',
    'docs/public-donations-community-v1/qa-reconciliation.md',
    'docs/public-donations-community-v1/deployment-runbook.md',
] as $document) {
    $read($document);
}

echo "Public Donations Community Phase 1 contract valid.\n";
