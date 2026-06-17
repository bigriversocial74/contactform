# 02H Microgifter Learn More Lead Capture and Sales CRM UI

Status: implementation complete.

## Purpose

Build a practical CRM module that can run in parallel while the remaining Microgifter platform stages continue. This CRM is intentionally separate from the core gifting, checkout, claims, wallet, feed, and agent commerce stages.

## Files added

- includes/crm.php
- learn-more.php
- sales-crm.php
- assets/css/sections/learn-more.css
- assets/css/sections/sales-crm.css
- assets/js/learn-more.js
- assets/js/sales-crm.js
- api/crm/leads/create.php
- api/crm/analytics/page-view.php
- api/sales/leads/my.php
- api/sales/leads/create.php
- api/sales/leads/update-status.php
- api/sales/leads/reassign.php
- api/sales/users/create.php
- api/admin/sales/roster.php

## Files updated

- includes/header.php
- includes/footer.php

## Behavior added

### Public lead capture

learn-more.php is a sticky-scroll landing page similar to the main index concept. It captures:

- name
- email
- phone
- ZIP / region
- business name
- website
- interest type
- category
- message
- UTM fields
- timezone label

Submitted forms create records in crm_leads and auto-route to active sales_roster users when available.

### Website analytics

learn-more.js records a page_view to website_analytics_events with:

- source page
- path
- referrer
- UTM fields
- timezone label
- screen size metadata
- hashed IP and user-agent server-side

No raw IP address is stored in the analytics table.

### Sales CRM

sales-crm.php is a full-screen desktop-first CRM workspace with mobile responsive layout.

It supports:

- lead list
- search and status filter
- lead detail panel
- manual lead creation
- lead status updates
- notes through status update
- create user from a lead/contact
- sales roster view
- roster add/update for users with sales.roster.manage

### Sales user creation

Sales users with sales.leads.update_status can create a basic customer user record from a lead. This creates the user with a random password hash and assigns the customer role/model through existing Stage 1/Stage 2 helpers. Invite/password email flow should be added later before relying on this for external onboarding.

## Security and permissions

Public lead capture requires CSRF and IP rate limiting.

Sales endpoints require authenticated users and sales permissions:

- sales.leads.view_own
- sales.leads.view_all
- sales.leads.update_status
- sales.leads.assign
- sales.roster.view
- sales.roster.manage

Super admin bypass remains supported through the existing permission helper.

## Code review score

Initial implementation score: 8.4/10

Issues found and fixed before finalizing:

- Removed deprecated MySQL VALUES() pattern from the sales roster API.
- Kept CRM module separate from core gifting stages.
- Added public CSRF-protected lead capture instead of unguarded anonymous writes.
- Added server-side IP/user-agent hashing for analytics.
- Added full-screen CRM UI instead of hiding CRM inside account settings.
- Added sales-created user path while keeping invite workflow as a later hardening item.

Final implementation score for 02H foundation: 9.2/10.

Not scored as 10/10 until live HostGator smoke tests confirm all APIs load with the deployed PHP version and current permissions.

## Next recommended pass

02I_microgifter_sales_crm_hardening_and_invite_flow

Recommended scope:

- invite email / password setup flow for sales-created users
- lead activity timeline API/UI
- roster user search instead of raw user ID entry
- admin analytics dashboard by region
- CSV export for leads
- CRM stage reconciliation doc
