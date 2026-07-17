#!/usr/bin/env python3
from __future__ import annotations

import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parents[1]

TARGETS = {
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
    "api/commerce/cart-checkout.php": ("commerce.cart_checkout_failed", "Unable to start checkout."),
    "api/me/mfa/confirm.php": ("identity.mfa_confirm_failed", "Unable to confirm multi-factor authentication."),
}

CATCH_START = re.compile(r"catch\s*\(\s*Throwable\s+\$(?P<var>[A-Za-z_][A-Za-z0-9_]*)\s*\)\s*\{")


def replace_exact(path: pathlib.Path, old: str, new: str, label: str) -> None:
    source = path.read_text(encoding="utf-8")
    if old not in source:
        raise RuntimeError(f"Expected {label} was not found in {path.relative_to(ROOT)}")
    path.write_text(source.replace(old, new, 1), encoding="utf-8")


def matching_brace(source: str, opening: int) -> int:
    depth = 0
    quote: str | None = None
    escaped = False
    line_comment = False
    block_comment = False
    i = opening
    while i < len(source):
        char = source[i]
        nxt = source[i + 1] if i + 1 < len(source) else ""
        if line_comment:
            if char == "\n":
                line_comment = False
            i += 1
            continue
        if block_comment:
            if char == "*" and nxt == "/":
                block_comment = False
                i += 2
                continue
            i += 1
            continue
        if quote is not None:
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == quote:
                quote = None
            i += 1
            continue
        if char == "/" and nxt == "/":
            line_comment = True
            i += 2
            continue
        if char == "/" and nxt == "*":
            block_comment = True
            i += 2
            continue
        if char == "#":
            line_comment = True
            i += 1
            continue
        if char in ("'", '"'):
            quote = char
            i += 1
            continue
        if char == "{":
            depth += 1
        elif char == "}":
            depth -= 1
            if depth == 0:
                return i
        i += 1
    raise RuntimeError("Unbalanced catch block")


def harden_final_catch(path: pathlib.Path, event: str, public_message: str) -> None:
    source = path.read_text(encoding="utf-8")
    matches = list(CATCH_START.finditer(source))
    if not matches:
        raise RuntimeError(f"No Throwable catch found in {path.relative_to(ROOT)}")
    match = matches[-1]
    opening = source.find("{", match.start(), match.end())
    closing = matching_brace(source, opening)
    if source[closing + 1 :].strip() != "":
        raise RuntimeError(f"Last Throwable catch is not terminal in {path.relative_to(ROOT)}")
    body = source[opening + 1 : closing]
    if "getMessage" not in body or "mg_fail" not in body:
        raise RuntimeError(f"Expected raw exception response not found in {path.relative_to(ROOT)}")
    variable = match.group("var")
    replacement = (
        f"catch (Throwable ${variable}) {{\n"
        f"    mg_fail_unexpected(${variable}, '{event}', '{public_message}', 500);\n"
        "}\n"
    )
    path.write_text(source[: match.start()] + replacement, encoding="utf-8")


for relative, (event, message) in TARGETS.items():
    harden_final_catch(ROOT / relative, event, message)

replace_exact(
    ROOT / "api/admin/ledger-reversal.php",
    "catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}",
    "catch(DomainException|InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail_unexpected($e,'admin.ledger_reversal_failed','Unable to reverse the ledger group.',500,[],(int)$user['id']);}",
    "ledger reversal catch",
)

replace_exact(
    ROOT / "api/admin/screen-recordings/publish-tutorial.php",
    """} catch (Throwable $error) {
    mg_security_log('warning', 'admin.screen_recordings.publish_tutorial_failed', 'Unable to publish tutorial.', ['recording_id' => $recordingId, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail($error instanceof RuntimeException ? $error->getMessage() : 'Unable to publish tutorial. Export the recording first and try again.', 422);
}
""",
    """} catch (RuntimeException $error) {
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
""",
    "tutorial publishing catch",
)

replace_exact(
    ROOT / "api/catalog/upload.php",
    """} catch (Throwable $e) {
    mg_catalog_upload_cleanup($destination ?? null);
    mg_security_log('error','catalog.asset_upload_failed','Catalog asset upload failed.',[
        'role'=>$role,
        'mime'=>$mime,
        'asset_type'=>$assetType ?? null,
        'byte_size'=>$size ?? null,
        'exception_type'=>get_class($e),
    ],(int)$user['id']);
    if ($e instanceof RuntimeException) {
        mg_fail($e->getMessage(),500);
    }
    mg_fail('Unable to register the uploaded media.',500);
}
""",
    """} catch (Throwable $e) {
    mg_catalog_upload_cleanup($destination ?? null);
    mg_fail_unexpected(
        $e,
        'catalog.asset_upload_failed',
        'Unable to register the uploaded media.',
        500,
        [
            'role'=>$role,
            'mime'=>$mime,
            'asset_type'=>$assetType ?? null,
            'byte_size'=>$size ?? null,
        ],
        (int)$user['id']
    );
}
""",
    "catalog upload catch",
)

replace_exact(
    ROOT / "api/ads/admin-diagnostics.php",
    """            } catch (Throwable $error) {
                $renderMessage = $error->getMessage();
            }
""",
    """            } catch (Throwable $error) {
                mg_security_log('error', 'ads.admin_diagnostics_render_failed', 'Ad placement render diagnostic failed.', [
                    'placement_key' => $key,
                    'exception_class' => $error::class,
                    'exception_message' => mb_substr($error->getMessage(), 0, 1000),
                ], (int)($user['id'] ?? 0));
                $renderMessage = 'Placement render test failed. Review the server security log for details.';
            }
""",
    "diagnostics render catch",
)

print(f"Hardened {len(TARGETS) + 3} API exception boundaries.")
