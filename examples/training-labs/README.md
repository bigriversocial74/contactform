# Microgifter Training Lab Recovered Build

This folder restores the Training Lab build into the structure David expected.

## Branch

```text
training-lab-stage2-stage4-autobuild
```

## Expected root structure

```text
/contactform/
  config-example.php
  config.php              # local/private only; never commit or overwrite
  labs/                   # deployable Labs webroot copy
  examples/
    training-labs/        # preserved source/demo/reference copy
      README.md
      config-example.php
      labs/
      template-assets/
      docs/
```

## Config rule

Only `config-example.php` is included. Rename it locally to `config.php` after deployment/configuration.

Future builds must never overwrite `config.php`.

## Latest known build state

The Training Lab build reached Stage 8 checkpoint in zip artifacts.

Stages built:

```text
Stage 2A — public website template completion
Stage 2B — participant app template completion
Stage 2C — admin/backend UI template completion
Stage 2D — layout cleanup
Stage 3 — backend integration scaffold
Stage 4 — read-only service/API scaffold
Stage 5 — pre-SQL adapter and mapping checkpoint
Stage 6 — consolidated import-safe SQL
Stage 7 — guarded backend action flow
Stage 8 — ops hardening/status pages/demo cycle
```

## SQL status

David imported the import-safe Training Lab SQL successfully.

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

The first SQL failed because `CHECK` constraints and `users(id)` foreign keys caused phpMyAdmin/MySQL import errors. The fixed import-safe SQL removed external FKs and CHECK constraints.

## Immediate issue

The latest diagnostic task is to make `/labs/api/training/db-status.php` report the exact root config path and confirm whether `config.php` is found.

Correct DB loader behavior:

```php
$path = dirname(__DIR__, 2) . '/config.php';
```

From:

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

## Template images

Template images and icons were recovered in checkpoint zip assets. The GitHub connector may not upload binary image files through simple text commits, so this branch includes the asset inventory and docs first. The full recovered zip should still be preserved as the source of binary image assets.
