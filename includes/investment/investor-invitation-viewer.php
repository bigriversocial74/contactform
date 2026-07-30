<?php
declare(strict_types=1);

/**
 * Resolve an optional, DB-refreshed viewer for the public Investor invitation
 * landing page. Signed-in users retain the normal email-verification gate,
 * while anonymous recipients can still review the invitation entry screen.
 */
function mg_investment_invitation_optional_viewer(string $returnPath): ?array
{
    $user = mg_authenticated_user(true);
    if ($user === null) {
        return null;
    }

    return mg_require_auth('/signin.php', $returnPath);
}
