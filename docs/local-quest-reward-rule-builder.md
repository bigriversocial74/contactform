# Local Quest reward rule builder

## Purpose

Quest admins should not manually edit reward program and template IDs forever. A reusable starter app needs a reward rule builder that can connect quest actions to merchant-approved Microgifter rewards.

## Intended admin flow

1. Load Distribution Programs visible to the app credential.
2. Select a program.
3. Load approved reward templates for that program.
4. Select the reward template.
5. Preview capacity, limits, expiration, and merchant approval status.
6. Save the reward rule to the quest.
7. Validate before publishing.

## Rule fields

Each quest reward rule should include:

```text
program_id
template_id
reward_label
quantity
issue_event_type
max_rewards_per_user
requires_completion
requires_linked_account
```

## Current status

Quest files already support program and template IDs. The admin UI builder is not yet implemented because direct writes to the credential-backed reward rule page were blocked during this pass.

## Remaining work

1. Add admin page for selecting programs/templates.
2. Add API-backed dropdowns.
3. Add preflight validation before saving.
4. Add capacity/limit preview.
5. Add validator for reward-rule configuration.
