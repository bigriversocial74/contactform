# 02H Sales CRM Smoke Test

## Upload files

Upload all 02H files from the stage note.

No new SQL import is required after 02G, unless 02G was not already imported.

## 1. Health

Open:

```text
/api/health.php
```

Expected: database connected.

## 2. Learn more page

Open:

```text
/learn-more.php
```

Expected:

- sticky-scroll style sections render
- lead form is visible
- page has no console-breaking errors

## 3. Submit a website lead

Submit the learn-more form.

Expected:

- success message appears
- crm_leads receives a new record
- crm_lead_events receives crm_lead.created
- if sales_roster has active users, lead status becomes assigned
- website_analytics_events receives a page_view

## 4. Sales CRM access

Open:

```text
/sales-crm.php
```

Expected:

- unauthenticated users see sign-in lock
- users without sales permission see access lock
- sales/super_admin users see CRM workspace

## 5. Manual lead

In CRM, open Add lead and submit a manual lead.

Expected:

- lead created
- lead appears in CRM list
- crm_lead_events includes crm_lead.created

## 6. Lead update

Select a lead and update status to contacted or qualified.

Expected:

- lead status updates
- optional note saves
- crm_lead_events includes crm_lead.status_changed

## 7. Create user from lead

Select a lead and click Create user from lead.

Expected:

- Add user tab is populated
- user record is created or existing user returned
- customer role/model baseline applies through existing helpers

## 8. Roster

Open Roster tab.

Expected:

- sales.roster.view users can view roster
- sales.roster.manage users can add/update roster users

## 9. Permission regression

Confirm customer-only users cannot access sales lead APIs.

Expected API response: 403 Sales access required.
