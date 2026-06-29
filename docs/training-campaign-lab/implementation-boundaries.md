# Training Campaign Lab Implementation Boundaries

## Purpose

This document defines the hard boundary between documentation and implementation for the Training Campaign Lab.

It exists so future agents do not accidentally begin coding while the project is still in planning mode.

## Current boundary

```text
The Training Campaign Lab is documentation-only until the user explicitly approves implementation.
```

## Allowed in documentation mode

Agents may create or edit documentation under:

```text
docs/training-campaign-lab/
docs/training-campaign-lab/ui/
docs/training-campaign-lab/ui/mockups/
```

Allowed work:

```text
Product planning
MVP scope definition
Route planning
UI page mapping
UI layout specifications
Component inventories
Responsive behavior notes
Schema design documents
Data lifecycle documents
Security and permissions planning
Workflow documents
QA plans
Implementation ticket planning
Open question tracking
Agent handoff instructions
```

## Not allowed in documentation mode

Agents must not create or edit implementation artifacts.

Not allowed:

```text
PHP files
SQL migration files
SQL seed files
CSS files
JavaScript files
Validation scripts
Runtime scripts
Upload folders
Runtime state files
Config changes
Database connection changes
API calls
Production reward issuing logic
```

Examples of forbidden files until build approval:

```text
examples/local-quest-rewards/training-lab.php
examples/local-quest-rewards/training-campaigns.php
examples/local-quest-rewards/training-campaign-detail.php
examples/local-quest-rewards/training-sequence.php
examples/local-quest-rewards/training-proof-upload.php
examples/local-quest-rewards/training-rewards.php
examples/local-quest-rewards/training-profile-wallet.php
examples/local-quest-rewards/admin-training-review.php
examples/local-quest-rewards/admin-training-receipts.php
examples/local-quest-rewards/training-storage.php
examples/local-quest-rewards/training-receipt-service.php
examples/local-quest-rewards/training-reward-service.php
examples/local-quest-rewards/assets/training-lab.css
examples/local-quest-rewards/assets/training-lab.js
examples/local-quest-rewards/database/training_campaign_lab.sql
examples/local-quest-rewards/database/training_campaign_lab_seed.sql
scripts/validate_training_campaign_lab.php
scripts/qa_training_campaign_lab_local.php
```

## Protected Local Quest files

These existing files must not be changed unless the user explicitly approves a refactor:

```text
examples/local-quest-rewards/index.php
examples/local-quest-rewards/wallet.php
examples/local-quest-rewards/quests.php
examples/local-quest-rewards/admin.php
examples/local-quest-rewards/admin-portal.php
examples/local-quest-rewards/admin-quest-controls.php
examples/local-quest-rewards/quest-controls.php
examples/local-quest-rewards/storage-sql.php
examples/local-quest-rewards/webhook.php
```

## Branch boundary

```text
Use local-quest-workspace for documentation planning.
Do not merge into main.
Do not open a PR unless the user asks.
Do not change main.
```

## Future implementation boundary

When the user explicitly approves building code, implementation should still remain isolated.

Future implementation should create new files only under a Training Lab namespace/prefix:

```text
training-*.php
admin-training-*.php
assets/training-lab.*
database/training_campaign_lab.*
```

Future implementation should not alter existing Local Quest behavior until the Training Lab vertical slice has been tested independently.

## Stop conditions

An agent must stop and ask for confirmation if a task requires:

```text
Changing main
Merging branches
Deleting existing Local Quest files
Changing protected Local Quest files
Adding production secrets
Calling real reward issuing APIs
Changing authentication behavior
Changing payment or wallet behavior
Changing business rules not already decided in docs
```

## Minimum safe response when unsure

If an agent is unsure whether a request permits code, respond with:

```text
I will keep this documentation-only unless you explicitly approve implementation.
```
