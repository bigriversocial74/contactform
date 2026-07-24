<?php
declare(strict_types=1);

$root = dirname(__DIR__);
function phase2_quality_patch(string $path, string $needle, string $replacement): void
{
    global $root;
    $full = $root . '/' . $path;
    $text = file_get_contents($full);
    if (!is_string($text)) throw new RuntimeException('Unable to read ' . $path);
    $count = substr_count($text, $needle);
    if ($count !== 1) throw new RuntimeException($path . ' expected one quality-fix match, found ' . $count);
    $text = str_replace($needle, $replacement, $text);
    if (file_put_contents($full, $text) === false) throw new RuntimeException('Unable to write ' . $path);
}

phase2_quality_patch(
    'profile.php',
    "    '/assets/css/community-role-badges-v1.css?v=1.0.0',\n];",
    "    '/assets/css/community-role-badges-v1.css?v=1.0.0',\n    '/assets/css/public-donations-campaign-v1.css?v=1.0.0',\n];"
);

phase2_quality_patch(
    'includes/public-campaign-page.php',
    "        <p class=\"mg-public-donations-info__notice\">No purchase, join, request, checkout, claim, quantity, or contact-submission action is available on this page.</p>\n      </div>\n      <?php return; endif; ?>",
    "        <p class=\"mg-public-donations-info__notice\">No purchase, join, request, checkout, claim, quantity, or contact-submission action is available on this page.</p>\n        <?php if (\$closed): ?><div class=\"mg-public-campaign-result is-visible\" data-campaign-closed-state=\"<?= mg_e((string)(\$state['code'] ?? 'closed')) ?>\"><strong><?= mg_e((string)(\$state['message'] ?? 'This campaign is currently closed.')) ?></strong></div><?php endif; ?>\n      </div>\n      <?php return; endif; ?>"
);

phase2_quality_patch(
    'scripts/validate_public_donations_campaign_foundation_v1.php',
    "\$route = \$read('public-donations.php');\n",
    "\$route = \$read('public-donations.php');\n\$profilePage = \$read('profile.php');\n"
);
phase2_quality_patch(
    'scripts/validate_public_donations_campaign_foundation_v1.php',
    "\$must(\$route, [\"\\\$mgCampaignExpectedType = 'public_donation'\", '/assets/css/public-donations-campaign-v1.css'], 'public route');\n",
    "\$must(\$route, [\"\\\$mgCampaignExpectedType = 'public_donation'\", '/assets/css/public-donations-campaign-v1.css'], 'public route');\n\$must(\$profilePage, ['/assets/css/public-donations-campaign-v1.css'], 'public profile styles');\n\$must(\$publicPage, ['data-campaign-closed-state', \"\\\$state['message']\"], 'informational campaign state');\n"
);

phase2_quality_patch(
    'tests/phpunit/PublicDonationsCampaignFoundationContractTest.php',
    "        self::assertStringContainsString(\"require __DIR__ . '/engage-core.php'\", \$engage);\n    }",
    "        self::assertStringContainsString(\"require __DIR__ . '/engage-core.php'\", \$engage);\n        self::assertStringContainsString('data-campaign-closed-state', \$page);\n        \$profilePage = (string)file_get_contents(\$this->root . '/profile.php');\n        self::assertStringContainsString('/assets/css/public-donations-campaign-v1.css', \$profilePage);\n    }"
);

phase2_quality_patch(
    '.github/workflows/public-donations-campaign-foundation-v1.yml',
    "      - name: Existing campaign registry regression\n        run: |\n          php scripts/validate_campaign_type_registry_v2.php\n          php scripts/validate_public_campaign_landing_foundation.php\n",
    ""
);

@unlink($root . '/scripts/apply_public_donations_phase2_quality_fixes.php');
@unlink($root . '/.github/workflows/apply-public-donations-phase2-quality-fixes.yml');
echo "Phase 2 quality fixes applied.\n";
