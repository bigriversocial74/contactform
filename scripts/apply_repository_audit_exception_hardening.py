#!/usr/bin/env python3
from __future__ import annotations

import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[1]

TARGETS = {
    "api/admin/ledger-reversal.php": ("admin.ledger_reversal_failed", "Unable to reverse the ledger group."),
    "api/admin/marketplace-index.php": ("admin.marketplace_index_failed", "Unable to load the marketplace index."),
    "api/ads/admin-diagnostics.php": ("ads.admin_diagnostics_failed", "Unable to load Campaign Ads diagnostics."),
    "api/ads/admin-placement-control.php": ("ads.admin_placement_control_failed", "Unable to update ad placement controls."),
    "api/ads/create-demo.php": ("ads.create_demo_failed", "Unable to create demo ads."),
    "api/ads/create.php": ("ads.create_failed", "Unable to create the ad campaign."),
    "api/ads/list.php": ("ads.list_failed", "Unable to load ad campaigns."),
    "api/ads/performance.php": ("ads.performance_failed", "Unable to load ad performance."),
    "api/ads/placements.php": ("ads.placements_failed", "Unable to load ad placements."),
    "api/ads/review.php": ("ads.review_failed", "Unable to review the ad campaign."),
    "api/ads/submit.php": ("ads.submit_failed", "Unable to submit the ad campaign."),
    "api/ads/track.php": ("ads.track_failed", "Unable to record the ad event."),
    "api/ads/update.php": ("ads.update_failed", "Unable to update the ad campaign."),
    "api/ads/upload-creative.php": ("ads.upload_creative_failed", "Unable to upload the ad creative."),
    "api/auth/email/verify.php": ("auth.email_verify_failed", "Unable to verify the email address."),
    "api/catalog/builder-draft.php": ("catalog.builder_draft_failed", "Unable to save the product draft."),
    "api/catalog/upload.php": ("catalog.upload_failed", "Unable to upload the catalog asset."),
    "api/commerce/cart-checkout.php": ("commerce.cart_checkout_failed", "Unable to start checkout."),
    "api/me/mfa/confirm.php": ("identity.mfa_confirm_failed", "Unable to confirm multi-factor authentication."),
}

FINAL_CATCH = re.compile(
    r"(?P<prefix>\}\s*)catch\s*\(\s*Throwable\s+\$(?P<var>[A-Za-z_][A-Za-z0-9_]*)\s*\)\s*\{(?P<body>.*)\}\s*$",
    re.DOTALL,
)


def harden_final_catch(path: pathlib.Path, event: str, public_message: str) -> None:
    source = path.read_text(encoding="utf-8")
    match = FINAL_CATCH.search(source)
    if not match:
        raise RuntimeError(f"No final Throwable catch found in {path.relative_to(ROOT)}")
    variable = match.group("var")
    body = match.group("body")
    if "getMessage" not in body or "mg_fail" not in body:
        raise RuntimeError(f"Expected raw exception response not found in {path.relative_to(ROOT)}")
    replacement = (
        match.group("prefix")
        + f"catch (Throwable ${variable}) {{\n"
        + f"    mg_fail_unexpected(${variable}, '{event}', '{public_message}', 500);\n"
        + "}\n"
    )
    path.write_text(source[: match.start()] + replacement, encoding="utf-8")


for relative, (event, message) in TARGETS.items():
    harden_final_catch(ROOT / relative, event, message)

# Preserve intentionally safe RuntimeException validation messages for tutorial publishing.
publish = ROOT / "api/admin/screen-recordings/publish-tutorial.php"
source = publish.read_text(encoding="utf-8")
old = """} catch (Throwable $error) {
    mg_security_log('warning', 'admin.screen_recordings.publish_tutorial_failed', 'Unable to publish tutorial.', ['recording_id' => $recordingId, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail($error instanceof RuntimeException ? $error->getMessage() : 'Unable to publish tutorial. Export the recording first and try again.', 422);
}
"""
new = """} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_fail_unexpected(
        $error,
        'admin.screen_recordings.publish_tutorial_failed',
        'Unable to publish tutorial. Export the recording first and try again.',
        500,
        ['recording_id' => $recordingId],
        (int)$user['id']
    );
}
"""
if old not in source:
    raise RuntimeError("Expected tutorial publishing catch was not found")
publish.write_text(source.replace(old, new, 1), encoding="utf-8")

# Do not surface renderer exception text inside the admin diagnostics payload.
diag = ROOT / "api/ads/admin-diagnostics.php"
source = diag.read_text(encoding="utf-8")
old = """            } catch (Throwable $error) {
                $renderMessage = $error->getMessage();
            }
"""
new = """            } catch (Throwable $error) {
                mg_security_log('error', 'ads.admin_diagnostics_render_failed', 'Ad placement render diagnostic failed.', [
                    'placement_key' => $key,
                    'exception_class' => $error::class,
                    'exception_message' => mb_substr($error->getMessage(), 0, 1000),
                ], (int)($user['id'] ?? 0));
                $renderMessage = 'Placement render test failed. Review the server security log for details.';
            }
"""
if old not in source:
    raise RuntimeError("Expected diagnostics render catch was not found")
diag.write_text(source.replace(old, new, 1), encoding="utf-8")

print(f"Hardened {len(TARGETS) + 1} API exception boundaries.")
