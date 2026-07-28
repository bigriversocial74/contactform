<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

header('Cache-Control: no-store, private');
header('Pragma: no-cache');

$reference = trim((string) ($_GET['user'] ?? ''));
$referenceIsValid = $reference !== ''
    && strlen($reference) <= 190
    && strpbrk($reference, "\r\n\t") === false;

$member = null;
$publishedSlug = '';

if ($referenceIsValid) {
    try {
        $pdo = mg_db();
        $hasPublicId = false;
        try {
            $columnStmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'public_id'");
            $columnStmt->execute();
            $hasPublicId = (bool) $columnStmt->fetchColumn();
        } catch (Throwable) {
            $hasPublicId = false;
        }

        $publicIdSelect = $hasPublicId ? 'u.public_id' : 'NULL AS public_id';
        $where = $hasPublicId ? '(u.public_id=? OR u.email=?)' : 'u.email=?';
        $params = $hasPublicId ? [$reference, $reference] : [$reference];
        $stmt = $pdo->prepare(
            "SELECT u.id,{$publicIdSelect},u.display_name,u.full_name
             FROM users u
             WHERE {$where} AND u.status='active'
             LIMIT 1"
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $member = $row;
            try {
                $profileStmt = $pdo->prepare(
                    "SELECT slug,status,visibility
                     FROM public_profiles
                     WHERE user_id=?
                     LIMIT 1"
                );
                $profileStmt->execute([(int) $row['id']]);
                $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
                $slug = strtolower(trim((string) ($profile['slug'] ?? '')));
                $slugIsValid = $slug !== ''
                    && strlen($slug) <= 120
                    && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,118}[a-z0-9])?$/', $slug) === 1;
                if (
                    $profile
                    && $slugIsValid
                    && (string) ($profile['status'] ?? '') === 'active'
                    && in_array((string) ($profile['visibility'] ?? ''), ['public', 'unlisted'], true)
                ) {
                    $publishedSlug = $slug;
                }
            } catch (Throwable $error) {
                if (function_exists('mg_security_log')) {
                    mg_security_log('warning', 'profile.member_public_lookup_failed', 'Unable to resolve a member public profile.', [
                        'exception_class' => $error::class,
                    ], (int) $row['id']);
                }
            }
        }
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'profile.member_lookup_failed', 'Unable to resolve a member profile route.', [
                'exception_class' => $error::class,
            ]);
        }
    }
}

if ($publishedSlug !== '') {
    header('Location: /profile.php?slug=' . rawurlencode($publishedSlug), true, 302);
    exit;
}

if (!$member) {
    http_response_code(404);
}

$memberName = trim((string) ($member['display_name'] ?? ''));
if ($memberName === '') {
    $memberName = trim((string) ($member['full_name'] ?? ''));
}
if ($memberName === '' || filter_var($memberName, FILTER_VALIDATE_EMAIL) !== false) {
    $memberName = 'Microgifter member';
}
$memberInitial = strtoupper(substr($memberName, 0, 1));
$memberReference = trim((string) ($member['public_id'] ?? '')) ?: $reference;

$page_title = ($member ? $memberName : 'Member unavailable') . ' | Microgifter';
$page_section = 'profile';
$header_mode = 'public';
$page_body_class = 'mg-public-profile-page mg-profile-light-theme';
$page_styles = [
    '/assets/css/public-profile.css',
    '/assets/css/public-profile-content-first.css?v=1.0.0',
];
$page_meta = [
    'robots' => 'noindex,nofollow',
];

require __DIR__ . '/includes/header.php';
?>
<section class="mg-public-profile-shell mg-invest-profile-shell" aria-labelledby="member-profile-title">
  <div class="mg-invest-shell mg-profile-content-shell">
    <section class="mg-invest-card mg-profile-empty">
      <?php if ($member): ?>
        <span class="mg-invest-overline">Microgifter member</span>
        <div class="mg-profile-hero-identity">
          <div class="mg-invest-avatar" aria-hidden="true"><span><?= mg_e($memberInitial) ?></span></div>
          <div class="mg-invest-identity-copy">
            <h1 id="member-profile-title"><?= mg_e($memberName) ?></h1>
            <p>This member has not published a public profile yet. You can still start a private Microgifter conversation with them.</p>
          </div>
        </div>
        <div class="mg-invest-actions">
          <?php if ($memberReference !== ''): ?>
            <a class="mg-invest-btn is-gold" href="/feed.php?chat=<?= rawurlencode($memberReference) ?>">Message</a>
          <?php endif; ?>
          <a class="mg-invest-btn" href="/inbox.php">Back to Inbox</a>
        </div>
      <?php else: ?>
        <span class="mg-invest-overline">Member unavailable</span>
        <h1 id="member-profile-title">This member could not be found.</h1>
        <p>The account may be inactive or the profile address may no longer be valid.</p>
        <div class="mg-invest-actions"><a class="mg-invest-btn is-gold" href="/inbox.php">Back to Inbox</a></div>
      <?php endif; ?>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
