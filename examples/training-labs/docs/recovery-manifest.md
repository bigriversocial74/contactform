# Training Lab Recovery Manifest

## Purpose

This document preserves the Training Lab build history, expected folder structure, recovered assets list, SQL status, and current next steps so other agents can continue without losing context.

## Expected structure

```text
/contactform/
  config-example.php
  config.php                    # private/local only; do not commit
  labs/                         # deployable webroot for labs.microgifter.com
  examples/
    training-labs/              # source/demo/reference archive
      README.md
      config-example.php
      docs/
      template-assets/
      labs/
```

## Branch

```text
training-lab-stage2-stage4-autobuild
```

## Recovered zip source

```text
training-lab-recovered-examples-structure.zip
```

## Latest checkpoint zips involved

```text
training-lab-stage2-public-template-pages.zip
training-lab-stage2-auth-layout-fixed.zip
training-lab-stage3-stage4-readonly-checkpoint.zip
training-lab-stage5-final-pre-sql-checkpoint.zip
training-lab-stage6-import-safe-sql.zip
training-lab-stage7-full-backend-build.zip
training-lab-stage8-ops-hardening-build.zip
training-lab-stage8-root-config-fixed.zip
training-lab-db-diagnostic-fix.zip
```

## Public template assets recovered

```text
training_lab_landing_page_ui_design.png
minimalist_training_lab_landing_page_design.png
training_lab_blog_landing_page_design.png
team_page_for_training_lab_product.png
minimalist_training_platform_homepage_design.png
modern_saas_landing_page_mockup.png
minimalist_saas_landing_page_design.png
clean_sign_in_page_with_green_accents.png
modern_sign_up_page_with_illustration.png
your_cart_summary_page_design.png
modern_ecommerce_checkout_ui_design.png
simple_pricing_for_action_based_rewards.png
task_completion_to_reward_process.png
step_by_step_process_workflow_illustration.png
growth_and_plans_in_minimal_design.png
secure_online_purchase_and_reward.png
e_commerce_checkout_process_illustration.png
collaborative_startup_team_in_green.png
minimalist_blog_layout_with_green_accents.png
secure_and_focused_workspace.png
productivity_workspace_with_growth_icons.png
task_completion_and_rewards_journey.png
relevant_contact.jpg
```

## App template assets recovered

```text
training_lab_user_dashboard_interface.png
rewards_and_progress_tracker_overview.png
minimalist_saas_dashboard_design.png
training_lab_campaign_dashboard_overview.png
5_day_movement_challenge_dashboard.png
training_lab_dashboard_ui_mockup.png
training_lab_5_day_challenge_dashboard.png
training_lab_dashboard_interface_overview.png
training_lab_dashboard_interface_in_green.png
minimalist_training_dashboard_interface.png
microgifter_training_campaign_dashboard.png
microgifter_training_campaign_dashboard_overview.png
microgifter_campaign_dashboard_interface.png
participant_tracking_dashboard_overview.png
training_campaign_progress_dashboard.png
microgifter_training_campaign_dashboard_view.png
5_day_movement_challenge_campaign_dashboard.png
rewards_and_progress_dashboard_ui.png
movement_session_task_overview_dashboard.png
reward_based_training_campaign_dashboard_design.png
microgifter_training_campaign_dashboard_interface.png
```

## Admin template assets recovered

```text
review_queue_admin_dashboard_ui.png
minimalist_admin_dashboard_interface_design.png
admin_dashboard_for_training_campaigns.png
modern_saas_dashboard_with_review_queue.png
campaign_management_dashboard_overview.png
```

## Icon assets recovered

```text
simple_sprout_icon_on_transparent_canvas.png
laboratory_flask_with_sprout_icon.png
green_check_mark_list_icon.png
cloud_upload_icon_with_green_outline.png
minimalist_dark_green_flame_icon.png
minimalist_gift_box_icon.png
simple_calendar_icon_in_mint_badge.png
verified_badge_icon_with_checkmark.png
heart_icon_for_progress_rewards.png
growth_icon_for_training_rewards.png
```

## SQL status

David imported the import-safe SQL successfully.

The import-safe SQL creates:

```text
training_campaigns
training_campaign_tasks
training_participants
training_proof_submissions
training_reviews
training_action_receipts
training_reward_rules
training_reward_events
training_streaks
training_events
training_permission_catalog
```

The import-safe SQL removed CHECK constraints and external foreign keys after phpMyAdmin/MySQL errors.

## Current diagnostic issue

`/labs/api/training/db-status.php` still returned `db_configured: false`, meaning the app did not find/read root `config.php` or could not connect to the DB.

The correct diagnostic file should report:

```text
config.expected_path
config.file_exists
config.loaded
config.error
config.database_name_present
config.username_present
config.host_present
```

## Correct config rule

Only ship `config-example.php`. David renames it to `config.php`. Future builds must not overwrite `config.php`.

The DB loader must read:

```php
dirname(__DIR__, 2) . '/config.php'
```

from:

```text
/contactform/labs/includes/training-lab-db.php
```

## Safety boundaries

```text
No real media upload processing
No payments
No wallet balance changes
No Microgifter reward issuing
No claim/redeem logic
No duplicate auth system
No production deployment changes unless explicitly requested
```
