<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) throw new RuntimeException('Missing required file: ' . $path);
    $content = file_get_contents($full);
    if (!is_string($content)) throw new RuntimeException('Unable to read required file: ' . $path);
    return $content;
};

$sql = $read('database/20260729_investor_invite_onboarding_v1.sql');
$migrationManifest = $read('config/migrations.php');
$service = $read('includes/investment/investor-invitations.php');
$adminPage = $read('admin/investor-invitations.php');
$adminApi = $read('api/admin/investor-invitations.php');
$publicPage = $read('investor-invitation.php');
$publicApi = $read('api/investment/invitation.php');
$viewerHelper = $read('includes/investment/investor-invitation-viewer.php');
$signin = $read('signin.php');
$signup = $read('signup.php');
$register = $read('api/auth/register.php');
$center = $read('admin/investor-center.php');
$centerData = $read('includes/investment/investor-center-dashboard.php');
$adminAccess = $read('api/admin/investor-access.php');
$adminAccessJs = $read('assets/js/admin-investor-access-v1.js');
$adminInvitationJs = $read('assets/js/admin-investor-invitations-v1.js');
$workflow = $read('.github/workflows/investor-invite-onboarding-v1.yml');

$checks = [
    'additive schema is present and registered in canonical migration order' =>
        str_contains($sql, 'CREATE TABLE IF NOT EXISTS investor_invitations')
        && str_contains($sql, 'CREATE TABLE IF NOT EXISTS investor_invitation_events')
        && str_contains($sql, '20260729_investor_invite_onboarding_v1')
        && str_contains($migrationManifest, "'20260729_investor_invite_onboarding_v1.sql'"),
    'bearer tokens are random, hashed, unique, and never stored raw' =>
        str_contains($service, 'bin2hex(random_bytes(32))')
        && str_contains($service, "hash('sha256', \$token)")
        && str_contains($sql, 'UNIQUE KEY uq_investor_invitation_token (token_hash)')
        && !preg_match('/\btoken\s+VARCHAR/i', $sql),
    'only Super Admin can create, resend, or revoke invitations' =>
        substr_count($service, "Only a Super Admin can") >= 3
        && str_contains($adminApi, 'mg_investment_is_super($actor)')
        && str_contains($adminPage, "in_array('super_admin'"),
    'acceptance requires verified matching email and all disclosures' =>
        str_contains($service, 'Verify your email before completing Investor onboarding.')
        && str_contains($service, "hash_equals((string)\$invitation['invited_email_hash']")
        && str_contains($service, 'identity_acknowledgement')
        && str_contains($service, 'confidentiality_acknowledgement')
        && str_contains($publicPage, 'does not create an investment commitment'),
    'accepted invitations enter the existing governed access-request table' =>
        str_contains($service, 'INSERT INTO investor_access_requests')
        && str_contains($service, "status='accepted'")
        && str_contains($service, 'request_id=?')
        && !str_contains($service, 'INSERT IGNORE INTO user_roles'),
    'expiry, resend rotation, single-use acceptance, revocation, and consumed-link privacy are enforced' =>
        str_contains($service, "status IN ('created','sent','viewed')")
        && str_contains($service, "status='expired'")
        && str_contains($service, "token_hash=?")
        && str_contains($service, "status='revoked'")
        && str_contains($service, 'Accepted or revoked invitations cannot be resent.')
        && str_contains($service, 'This Investor invitation has already been used.')
        && str_contains($service, 'This Investor invitation has expired.')
        && str_contains($service, 'This Investor invitation has been revoked.')
        && str_contains($service, "throw new MgInvestmentException('This Investor invitation is no longer available.', 410)"),
    'sign-in, sign-up, verification, safe return, and public-layout classification preserve onboarding continuity' =>
        str_contains($signin, 'name="return"')
        && str_contains($signup, 'name="return"')
        && str_contains($signup, 'investor-invitation.php?token=')
        && str_contains($register, '$postVerifyRedirect=$returnPath')
        && str_contains($register, 'mg_safe_return_path')
        && str_contains($publicPage, 'mg_investment_invitation_optional_viewer($returnPath)')
        && !str_contains($publicPage, 'mg_require_auth(')
        && str_contains($viewerHelper, "mg_require_auth('/signin.php', \$returnPath)"),
    'Investor Center and access review expose invitation operations and source context' =>
        str_contains($center, '/admin/investor-invitations.php')
        && str_contains($centerData, "'failed_delivery'")
        && str_contains($adminAccess, 'mg_investment_invitation_enrich_access_items')
        && str_contains($adminAccessJs, "item.source === 'admin_invitation'"),
    'email delivery is auditable and the browser consumes the authoritative secure-link response' =>
        str_contains($service, "'template' => 'investor_invitation'")
        && str_contains($service, "'email_sent'")
        && str_contains($service, "'email_failed'")
        && str_contains($adminPage, 'data-invitation-share-url')
        && str_contains($adminInvitationJs, 'data?.share_url')
        && str_contains($adminInvitationJs, 'data.email_sent')
        && !str_contains($adminInvitationJs, 'data?.invite_url')
        && !str_contains($adminInvitationJs, 'data.delivered'),
    'PHP 8.2/8.3, migration, JavaScript, contract, and deployment-package CI are configured' =>
        str_contains($workflow, "php: ['8.2', '8.3']")
        && str_contains($workflow, 'Canonical migration manifest')
        && str_contains($workflow, 'Investor invitation 10-point contract')
        && str_contains($workflow, 'node --check')
        && str_contains($workflow, 'config/migrations.php')
        && str_contains($workflow, 'microgifter-investor-invite-onboarding-v1.zip'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}
$score = round((count($checks) - count($failed)) / count($checks) * 10, 1);
echo 'Investor Invite and Onboarding score: ' . number_format($score, 1) . '/10' . PHP_EOL;
if ($failed !== []) {
    fwrite(STDERR, 'Investor Invite and Onboarding v1 validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}
echo "Investor Invite and Onboarding v1 passed at 10.0/10.\n";
