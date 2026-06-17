#!/usr/bin/env bash
set -euo pipefail

composer migrate
php scripts/run_stage3_delivery.php
php scripts/run_stage4_product_assets.php
php scripts/run_stage4b_builder.php
php scripts/run_stage4c_feed_stream.php
php scripts/stage4d.php
php scripts/aggregate_stage4d_engagement.php "$(date -u +%F)"
php scripts/stage4e.php
php scripts/aggregate_stage4e_distribution.php "$(date -u +%F)"
php scripts/stage4f.php
php scripts/aggregate_stage4f_demand.php "$(date -u +%F)" 30
php scripts/build_stage4f_snapshots.php "$(date -u +%F)" 30
php scripts/process_stage4f_exports.php
php scripts/stage5a.php
php scripts/stage5c.php
php scripts/stage5c_smoke.php
php scripts/stage5d.php
php scripts/stage5d_smoke.php
php scripts/stage5e.php
php scripts/stage5e_smoke.php
php scripts/stage5f.php
php scripts/stage5f_smoke.php
php scripts/stage5g.php
php scripts/stage5g_smoke.php
php scripts/stage5h.php
php scripts/stage5h_smoke.php
php scripts/stage5i.php
php scripts/stage5i_smoke.php
php scripts/stage5j.php
php scripts/stage5j_smoke.php
php scripts/stage7b.php
php scripts/stage7b_smoke.php
php scripts/stage8b.php
php scripts/stage8b_smoke.php
php scripts/stage9b.php
php scripts/stage9b_smoke.php
php scripts/stage9c.php
php scripts/stage9c_smoke.php
php scripts/stage9d.php
php scripts/stage9d_smoke.php
php scripts/reconcile_stage9d_legacy_gifts.php
php scripts/aggregate_stage9d_microgifts.php "$(date -u +%F)" 30
php scripts/stage9e3_preflight.php
php scripts/stage9e3_upgrade_manifest.php
php scripts/stage9e3_smoke.php
php scripts/stage10f_apply.php
php scripts/validate_stage10f_upgrade.php
php scripts/stage10f_runtime_smoke.php
php scripts/stage11g.php
php scripts/stage11g_smoke.php
php scripts/stage11h.php
php scripts/stage11h_smoke.php
php scripts/stage12.php
php scripts/stage12_smoke.php
php scripts/stage12a.php
php scripts/stage12a_smoke.php
php scripts/stage12d.php
php scripts/stage12d_smoke.php
php scripts/stage13.php
php scripts/stage13_smoke.php
php scripts/stage14.php
php scripts/stage14_smoke.php
php scripts/stage15.php
php scripts/stage15_smoke.php
php scripts/stage16.php
php scripts/stage16_smoke.php
php scripts/stage17.php
php scripts/stage17_smoke.php
php scripts/stage18.php
php scripts/stage18_smoke.php
