# Microgifter MCP Approved Draft Conversion Phase 3B

## Purpose

Phase 3B adds a separate owner-controlled conversion step after Phase 3A approval.

An approved MCP draft remains a review record until its owner prepares a conversion and then confirms creation of an inactive native Microgifter draft. No external MCP tool can perform either step.

## Native destinations

- Gift: private `gifts.status=draft` record preserving source-product and recipient context.
- Campaign: `crm.campaign_builder.draft` evidence in Merchant CRM.
- Reward: `reward_templates.status=draft` with all distribution options disabled.
- Message: `crm.agent.message.draft.created` evidence in the merchant message queue.

## Required migration

```text
database/20260720_mcp_approved_draft_conversion_phase3b_v1.sql
```

Import it after:

```text
database/20260720_mcp_approval_gated_drafts_phase3a_v1.sql
```

## Owner workflow

Open `/account-agent-drafts.php`, approve the source proposal, select **Prepare conversion**, review the destination, select **Create inactive draft**, and then use **Open native draft**.

Prepare, create, cancel, and open actions are CSRF protected. Native opening also rechecks ownership and allows only a same-origin relative destination.

## Revalidation

Native creation rechecks source ownership and approval, current permission, current workspace access, the exact workspace package, applicable package limits, and source-product availability.

## Boundary

Phase 3B creates inactive native records only. It does not make a campaign live, deliver customer communication, start a commerce operation, issue or transfer a gift, activate a reward, or add work to either execution queue. A later first-party Microgifter action is still required.
