# Microgifter Brand–Creator Campaign System

## Planning status

This document preserves the approved product direction for Microgifter's unified brand–creator campaign system. It is a planning and implementation reference only. It does not activate production functionality.

## 1. Product purpose

The system allows Microgifter merchants, acting as brands, to collaborate with approved Microgifter creators for UGC, product promotion, referral acquisition, campaign signups, product and Microgift sales, claims, redemptions, and measurable local commerce activity.

UGC and affiliate workflows belong in one Brand–Creator Campaign System.

Canonical chain:

```text
Merchant → Campaign → Creator → Agreement → Deliverable or Tracking Source
→ Customer Interaction → CRM Attribution → Creator Earning → Payout
```

## 2. Identity and role model

Use the existing Microgifter identity model.

- `users` remains the login identity.
- `user_models` determines enabled operating modes.
- Roles and permissions determine authorization.
- A single user may operate as Merchant, Creator, and Customer.
- The existing Creator model is the unified campaign participant.
- The legacy `marketing_affiliate` model is not used by the new system.

Creator specialties are profile or campaign labels, not new user models:

- UGC Creator
- Affiliate
- Influencer
- Brand Ambassador
- Referral Partner
- Community Partner
- Local Promoter
- Photographer
- Videographer
- Reviewer
- Music Creator
- Event Creator

Only active, approved Creator accounts may discover campaigns, apply, receive invitations, accept agreements, access tracking tools, submit deliverables, or earn compensation.

Merchants may invite only existing approved Microgifter Creator accounts.

## 3. Primary lifecycle models

### Campaign

```text
draft → scheduled → active → paused → completed → archived
                       ↘ cancelled
```

### Creator participation

```text
invited or applied → under_review → approved → agreement_pending
→ active → completed
```

Exceptions:

```text
declined
removed
suspended
```

### Agreement

```text
draft → offered → accepted → completed
                   ↘ superseded
                   ↘ terminated
```

Accepted agreement versions are immutable.

### Deliverable

```text
assigned → draft → submitted → under_review → approved
                    ↘ revision_requested → resubmitted
approved → published → verified → completed
```

Exceptions:

```text
rejected
withdrawn
waived
cancelled
overdue
```

### Earnings

```text
tracked → pending → qualified → approved → payable → paid
```

Exceptions:

```text
held
rejected
reversed
disputed
cancelled
```

### Budget

```text
unfunded → funded → near_limit → exhausted → closed
```

## 4. Campaign Builder outline

The merchant builder contains ten steps.

### Step 1 — Campaign details

Fields:

- Campaign name
- Internal campaign reference
- Objective
- Description
- Category
- Status
- Access mode
- Start date and time
- End date and time
- Timezone
- Geographic eligibility
- Campaign cover image
- Campaign manager or merchant workspace owner

Objectives:

- Product sales
- Microgift sales
- New-user acquisition
- Campaign signups
- Lead generation
- Content creation
- Product launch
- Event promotion
- Store visits
- Gift claims
- Redemptions
- Loyalty enrollment
- Brand awareness
- Hybrid objective

Access modes:

- Application required
- Invite only
- Approved brand creators
- Selected creators only

### Step 2 — Products and offers

Fields:

- Campaign focus
- Featured products
- Primary product
- Featured offer or reward
- Creator product access
- Product compensation value
- Creator landing destination
- Commissionable products
- Excluded products
- Commission basis

Campaign focus options:

- Merchant profile
- Single product
- Multiple products
- Product collection
- Microgift offer
- Reward
- Event
- Service
- Experience
- General brand campaign

### Step 3 — Creator eligibility

Fields:

- Participation method
- Creator specialties
- Creator categories
- Verification requirements
- Allowed social platforms
- Audience requirements
- Location requirements
- Maximum approved creators
- Application deadline
- Application questions
- Automatic acceptance toggle
- Existing creator relationship preference

Default approval rule: merchant approval is required.

### Step 4 — Deliverables

Each deliverable defines:

- Name
- Type
- Quantity
- Platform
- Content format
- Brief
- Required talking points
- Prohibited claims
- Required product mentions
- Required brand mention
- Required campaign link
- Required referral code
- Required hashtags
- Required account mentions
- Required disclosure
- Draft approval requirement
- Publication requirement
- Publication proof requirement
- Minimum live period
- Deadline
- Revision limit
- Deliverable payment

Supported types:

- Photo
- Short-form video
- Long-form video
- Audio
- Social post
- Story
- Reel or short
- Livestream
- Blog article
- Review
- Testimonial
- Product demonstration
- Event appearance
- QR promotion
- Referral outreach
- Campaign share
- Other

### Step 5 — Compensation

A campaign may combine:

- Fixed campaign payment
- Payment per deliverable
- Percentage commission per sale
- Fixed commission per sale
- Payment per qualified signup
- Payment per qualified lead
- Payment per campaign interaction
- Payment per gift claim
- Payment per redemption
- Performance bonus
- Tiered commission
- Product compensation
- Service or experience compensation
- Custom reviewed metric

Each rule defines:

- Rule name
- Trigger event
- Fixed amount or percentage
- Commission basis
- Qualification conditions
- Attribution requirement
- Merchant approval requirement
- Hold period
- Per-customer limit
- Per-creator limit
- Campaign-wide limit
- Stacking behavior
- Reversal conditions
- Bonus thresholds or tiers

### Step 6 — Attribution

Fields:

- Attribution model
- Attribution window
- Eligible tracking methods
- Referral-code priority
- Logged-in account attribution
- Cross-device policy
- Existing-customer eligibility
- New-customer-only toggle
- Multi-creator conflict rule
- Gift-purchase attribution stage
- Offline attribution toggle

Initial paid attribution should select one creator while retaining all candidate touchpoints.

Self-referrals are prohibited.

### Step 7 — Budget and limits

Fields:

- Campaign budget
- Budget type
- Pending earnings reserve
- Fixed payment reserve
- Creator limit
- Application limit
- Maximum earnings per creator
- Maximum qualifying events per creator
- Maximum qualifying events per customer
- Maximum signups
- Maximum commissions
- Maximum interaction payments
- Warning threshold
- Budget action at limit
- Over-budget handling

Recommended exhausted-budget behavior: continue recording activity but stop creating new payable earnings.

### Step 8 — Content rights

Fields:

- Content ownership
- Usage rights
- Paid advertising rights
- Creator-handle advertising rights
- Editing rights
- Creator approval for edits
- License duration
- License territory
- Exclusivity
- Exclusivity duration
- Creator credit requirement
- Takedown rules
- Rights-expiration behavior

Recommended default: creator retains ownership and grants the brand a defined usage license.

### Step 9 — Terms and disclosures

Fields:

- Campaign terms
- Platform terms requirement
- Campaign-specific agreement
- Advertising disclosure requirement
- Disclosure instructions
- Prohibited content
- Competitor restrictions
- Creator conduct requirements
- Refund and reversal policy
- Cancellation policy
- Dispute window
- Confidentiality
- Minimum age
- Additional acknowledgements

### Step 10 — Review and publish

The review screen shows:

- Campaign summary
- Creator-facing preview
- Financial liability preview
- CRM behavior preview
- Tracking preview
- Validation checklist
- Save, schedule, publish, duplicate, and edit actions

Publishing creates Agreement Version 1.

Material changes create a new version and may require creator reacceptance.

## 5. Merchant workspace outline

Primary navigation:

1. Overview
2. Campaigns
3. Applications
4. Creators
5. Deliverables
6. Content Review
7. Tracking
8. Earnings
9. Payouts
10. Analytics
11. Messages
12. Disputes
13. Settings

### Merchant overview

Key cards:

- Active campaigns
- Approved creators
- Pending applications
- Content awaiting review
- Pending earnings
- Payable earnings
- Attributed revenue
- New CRM contacts
- Budget remaining
- Disputes

Panels:

- Campaign health
- Recent activity
- Active campaigns
- Top campaigns
- Top creators

### Campaign detail

Tabs:

- Summary
- Creators
- Applications
- Agreements
- Deliverables
- Submissions
- Tracking
- Customers
- Earnings
- Payouts
- Analytics
- Messages
- Audit Log

### Applications

Actions:

- View creator
- Review answers and portfolio
- Approve
- Decline
- Request information
- Add internal notes

Approval creates or updates the participant and agreement records.

### Invitations

Search only active approved creators.

Invitation fields:

- Campaign
- Creator
- Invitation message
- Response deadline
- Creator-specific compensation
- Creator-specific deliverables
- Internal note

### Content review

Review layout:

- Submitted media
- Caption
- Brief and talking points
- Disclosure
- Tracking link
- Revision history
- Merchant feedback
- Approve, request revision, or reject

### Tracking

Display:

- Creator link
- Referral code
- QR code
- Product links
- Event timeline
- Attribution status
- Fraud status
- Related earnings

### Earnings and payouts

Merchant can:

- Review evidence
- Approve
- Hold
- Reject
- Reverse
- Mark payable
- Record manual payouts

Payout records preserve included earnings and prevent duplicate payment.

## 6. Creator workspace outline

Primary navigation:

1. Overview
2. Discover Campaigns
3. Invitations
4. Applications
5. Active Campaigns
6. Agreements
7. Deliverables
8. Submissions
9. Tracking Tools
10. Performance
11. Earnings
12. Payouts
13. Messages
14. Profile

### Creator overview

Cards:

- Active campaigns
- Pending applications
- Agreements awaiting acceptance
- Deliverables due
- Submissions under review
- Attributed sales
- Qualified signups
- Pending earnings
- Payable earnings
- Total paid

### Campaign discovery

Filters:

- Category
- Location
- Objective
- Deliverable type
- Compensation type
- Deadline

Cards show:

- Brand
- Campaign
- Products
- Compensation summary
- Deliverables
- Location
- Dates
- Eligibility
- Apply and view actions

### Active campaign workspace

Tabs:

- Summary
- Agreement
- Deliverables
- Submissions
- Tracking Tools
- Performance
- Earnings
- Messages

### Tracking tools

Creators can:

- Copy campaign link
- Copy referral code
- Download QR code
- Open product links
- Use approved assets and suggested copy

Creators cannot alter attribution identifiers or destinations.

## 7. CRM integration rules

Anonymous activity may be retained for attribution without automatically creating a marketing contact.

A Merchant CRM contact may be created or updated when an identified person provides the required information and consent.

Store:

- Campaign
- Referring creator
- First-touch creator
- Last-touch creator
- Selected creator
- Referral code
- Tracking source
- Content source
- Signup date
- Consent status
- First purchase
- Gift purchase
- Claim
- Redemption
- Attributed revenue
- Compensation generated

Customer lifecycle:

```text
tracked_visitor → campaign_lead → registered_user → first_time_customer
→ gift_purchaser or gift_recipient → claimed_customer → redeemed_customer
→ repeat_customer → loyalty_member
```

## 8. Initial release boundary

Include:

- Approved Creator access
- Campaign builder
- Applications
- Existing-account invitations
- Participants
- Versioned agreements
- Products and offers
- Multiple deliverables
- Content submissions and revisions
- Creator links, referral codes, and QR codes
- CRM attribution
- Fixed and performance compensation
- Earnings ledger
- Manual earnings approval
- Manual payout records
- Budget controls
- Merchant and creator dashboards
- Messages
- Basic disputes
- Basic analytics

Defer:

- Automated external payouts
- Agency accounts
- Multi-creator commission splitting
- Social-platform API ingestion
- Automated publication verification
- Anonymous cross-device matching
- AI creator matching
- Advanced fraud scoring
- Legacy Marketing Affiliate integration
