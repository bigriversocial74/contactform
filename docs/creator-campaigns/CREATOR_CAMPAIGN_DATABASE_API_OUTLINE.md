# Microgifter Brand–Creator Campaign System
## Database, API, Permissions, and Service Boundaries Outline

## 1. Existing systems to reuse

Reuse existing tables and services for:

- Users and authentication
- User models
- Roles and permissions
- Creator profiles
- Merchant profiles and workspaces
- Products and product versions
- Reward templates and campaign offers
- Merchant CRM contacts and activity
- Orders, payments, refunds, and chargebacks
- Microgifts, claims, and redemptions
- Notifications
- Audit logs and platform events

Do not create duplicate identity, customer, product, or transaction records.

## 2. Proposed domain tables

### Campaign foundation

- `creator_campaigns`
- `creator_campaign_products`
- `creator_campaign_eligibility_rules`
- `creator_campaign_application_questions`
- `creator_campaign_status_events`

### Participation

- `creator_campaign_applications`
- `creator_campaign_invitations`
- `creator_campaign_participants`
- `creator_campaign_agreements`
- `creator_campaign_agreement_versions`

### Deliverables and content

- `creator_campaign_deliverables`
- `creator_campaign_participant_deliverables`
- `creator_campaign_submissions`
- `creator_campaign_submission_revisions`
- `creator_campaign_assets`

### Tracking and attribution

- `creator_campaign_tracking_sources`
- `creator_campaign_tracking_events`
- `creator_campaign_attributions`

### Compensation and budgets

- `creator_campaign_compensation_rules`
- `creator_campaign_compensation_tiers`
- `creator_campaign_earning_events`
- `creator_campaign_budget_ledger`

### Payouts and operations

- `creator_campaign_payouts`
- `creator_campaign_payout_items`
- `creator_campaign_messages`
- `creator_campaign_disputes`
- `creator_campaign_dispute_events`
- `creator_campaign_fraud_flags`

## 3. Core table relationships

```text
users
 ├── creator_profiles
 ├── merchant_profiles
 ├── user_model_assignments
 └── user_roles

merchant_workspaces
 └── creator_campaigns
      ├── products
      ├── eligibility rules
      ├── application questions
      ├── applications
      ├── invitations
      ├── deliverables
      ├── compensation rules
      ├── tracking events
      ├── attributions
      ├── budget ledger
      ├── messages
      ├── disputes
      └── status events

creator_campaigns
 └── participants
      ├── agreements
      │    └── agreement versions
      ├── participant deliverables
      │    └── submissions
      │         ├── revisions
      │         └── assets
      ├── tracking sources
      │    └── tracking events
      ├── attributions
      ├── earning events
      ├── messages
      └── disputes

compensation rules
 ├── tiers
 └── earning events

earning events
 ├── budget ledger entries
 └── payout items
      └── payouts
```

## 4. Data integrity principles

1. A campaign belongs to one merchant workspace.
2. A creator must be active and approved before participation.
3. A merchant can invite only existing approved Creator accounts.
4. One participant record exists per creator and campaign.
5. Agreement acceptance is required before active tracking or earnings.
6. Accepted agreement versions are immutable.
7. Deliverable assignments reference the governing agreement version.
8. Earnings reference a compensation rule and agreement version.
9. A paid earning belongs to one payout.
10. Tracking events are append-only.
11. Attribution overrides preserve prior decisions.
12. Budget changes use an append-only ledger.
13. Financial and contractual records are not silently deleted.
14. Manual changes require actor, reason, timestamp, and audit record.
15. Self-referrals cannot produce valid earnings.
16. Creator suspension stops new earnings but preserves prior valid records.
17. The legacy Marketing Affiliate model is not referenced.

## 5. Agreement snapshot strategy

Each agreement version stores normalized snapshots of:

- Campaign terms
- Products
- Deliverables
- Compensation rules
- Attribution rules
- Budget-related creator limits
- Content rights
- Disclosures
- Cancellation and reversal rules
- Creator-specific terms

Recommended fields:

```text
terms_snapshot_json JSON
terms_hash CHAR(64)
version_number INT
material_change BOOLEAN
```

## 6. Tracking and attribution model

Tracking sources:

- Campaign link
- Product link
- Microgift link
- Referral code
- QR code
- Content link
- Offline code

Tracking events include:

- Campaign view
- Creator link click
- QR scan
- Product view
- Signup start
- Signup completion
- User registration
- CRM contact creation
- Product purchase
- Microgift purchase
- Gift claim
- Redemption
- Content view or completion
- Event attendance

Attribution statuses:

```text
candidate
attributed
conflicted
held
rejected
expired
overridden
```

The initial release retains all touchpoints while selecting one paid creator per qualifying event.

## 7. Earnings and budget model

Every earning record stores:

- Campaign
- Participant
- Creator
- Agreement version
- Compensation rule
- Trigger event
- Attribution
- Submission or deliverable when applicable
- Customer or CRM contact when applicable
- Transaction reference
- Qualifying amount
- Rate
- Earning amount
- Hold date
- Qualification date
- Approval date
- Payable date
- Payment date
- Decision and reversal reasons
- Calculation snapshot
- Idempotency key

Money is stored as integer minor units with ISO currency.

Budget ledger entry types include:

- Budget allocated
- Budget increased
- Budget reduced
- Fixed payment reserved
- Earning reserved
- Earning released
- Earning approved
- Earning reversed
- Payout recorded
- Manual adjustment
- Product compensation recorded

## 8. API route families

### Merchant routes

```text
/api/merchant/creator-campaigns
/api/merchant/creator-campaign-applications
/api/merchant/creator-campaign-invitations
/api/merchant/creator-campaign-participants
/api/merchant/creator-campaign-agreements
/api/merchant/creator-campaign-deliverables
/api/merchant/creator-campaign-submissions
/api/merchant/creator-campaign-attributions
/api/merchant/creator-campaign-earnings
/api/merchant/creator-campaign-payouts
/api/merchant/creator-campaign-disputes
/api/merchant/creator-campaign-analytics
```

Campaign actions:

- Create
- Read
- Update
- Validate
- Publish
- Schedule
- Pause
- Resume
- Complete
- Cancel
- Archive
- Duplicate

### Creator routes

```text
/api/creator/campaigns/discover
/api/creator/campaign-applications
/api/creator/campaign-invitations
/api/creator/campaign-agreements
/api/creator/campaign-deliverables
/api/creator/campaign-submissions
/api/creator/campaign-attributions
/api/creator/campaign-earnings
/api/creator/campaign-payouts
/api/creator/campaign-performance
```

### Public and internal tracking routes

```text
/c/{tracking_key}
/cq/{tracking_key}
/api/public/creator-campaigns/referral-code
/api/public/creator-campaigns/events
/api/internal/creator-campaigns/events/commerce
/api/internal/creator-campaigns/events/user-registration
/api/internal/creator-campaigns/events/crm-contact
/api/internal/creator-campaigns/events/microgift
/api/internal/creator-campaigns/events/content
```

Sensitive commerce events originate from trusted internal systems, not client-provided amounts.

## 9. Permission outline

Extend the existing permission catalog only where equivalent permissions do not already exist.

### Merchant campaigns

- `merchant.creator_campaigns.view`
- `merchant.creator_campaigns.manage`
- `merchant.creator_campaigns.publish`

### Creator participation

- `merchant.creator_directory.view`
- `merchant.creator_applications.view`
- `merchant.creator_applications.manage`
- `merchant.creator_invitations.manage`
- `merchant.creator_participants.view`
- `merchant.creator_participants.manage`

### Agreements and content

- `merchant.creator_agreements.view`
- `merchant.creator_agreements.manage`
- `merchant.creator_deliverables.view`
- `merchant.creator_deliverables.manage`
- `merchant.creator_submissions.view`
- `merchant.creator_submissions.review`
- `creator.agreements.view_own`
- `creator.agreements.respond_own`
- `creator.deliverables.view_own`
- `creator.submissions.manage_own`

### Tracking, finance, and reporting

- `merchant.creator_tracking.view`
- `merchant.creator_tracking.manage`
- `merchant.creator_attribution.view`
- `merchant.creator_attribution.manage`
- `merchant.creator_compensation.view`
- `merchant.creator_compensation.manage`
- `merchant.creator_budget.view`
- `merchant.creator_budget.manage`
- `merchant.creator_earnings.view`
- `merchant.creator_earnings.approve`
- `merchant.creator_payouts.view`
- `merchant.creator_payouts.manage`
- `creator.tracking.view_own`
- `creator.attribution.view_own`
- `creator.earnings.view_own`
- `creator.payouts.view_own`
- `merchant.creator_analytics.view`
- `creator.performance.view_own`

Permission possession is not sufficient by itself. Every request must also validate merchant workspace or creator ownership scope.

## 10. Service boundaries

Recommended services:

- `CreatorCampaignService`
- `CreatorEligibilityService`
- `CreatorApplicationService`
- `CreatorInvitationService`
- `CreatorParticipantService`
- `CreatorAgreementService`
- `CreatorDeliverableService`
- `CreatorSubmissionService`
- `CreatorTrackingService`
- `CreatorAttributionService`
- `CreatorCompensationService`
- `CreatorBudgetService`
- `CreatorEarningsService`
- `CreatorPayoutService`
- `CreatorMessagingService`
- `CreatorDisputeService`
- `CreatorCampaignNotificationService`
- `CreatorCampaignAuditService`

## 11. Transaction boundaries

Atomic operations include:

### Application approval

- Approve application
- Create participant
- Create agreement
- Create first agreement version
- Record status and audit events

### Agreement acceptance

- Accept current agreement version
- Update agreement
- Activate participant
- Assign deliverables
- Generate tracking sources
- Record status and audit events

### Earnings approval

- Approve earning
- Update budget ledger
- Record status and audit events

### Payout completion

- Mark payout paid
- Finalize payout items
- Mark earnings paid
- Update budget ledger
- Record audit events

## 12. Security and validation

Required controls:

- Authenticated context
- Active user model
- Permission and ownership scope
- CSRF on browser writes
- Signed or unguessable tracking keys
- Public-event rate limiting
- File validation
- Idempotency
- Merchant ownership validation
- Self-referral prevention
- Fraud and refund checks
- Data minimization for creator-facing customer information
- Audit trails for overrides

## 13. Recommended implementation sequence

1. Campaign foundation
2. Creator participation
3. Agreements
4. Deliverables and submissions
5. Tracking and CRM attribution
6. Compensation and budgets
7. Earnings and payouts
8. Messaging and disputes
9. Advanced analytics and fraud controls
