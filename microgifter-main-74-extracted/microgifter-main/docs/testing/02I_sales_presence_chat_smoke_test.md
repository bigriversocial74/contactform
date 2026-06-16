# 02I Sales Presence and Employee Chat Smoke Test

## 1. Import SQL

Import:

```text
database/02I_sales_presence_and_employee_chat.sql
```

Expected tables:

```text
sales_presence
employee_chat_messages
```

## 2. Upload files

Upload the latest full project zip after importing SQL.

## 3. CRM layout

Open:

```text
/sales-crm.php
```

Expected:

- compact statistics row in the center column
- right employee-chat column begins directly below the header
- lead rows display status and sales-person selectors
- clicking a lead opens the accordion details

## 4. Lead update

Open a lead, change status and assigned salesperson, enter an optional note, and click Save lead.

Expected:

- crm_leads status updates
- assigned_user_id updates when a salesperson is selected
- crm_lead_events receives the related events

## 5. Create user from lead

Open a lead and click Create user from lead.

Expected:

- Add user view opens
- name and email are prefilled
- existing create-user workflow remains functional

## 6. Presence

Open the CRM as a sales user.

Expected:

- sales_presence receives or updates the current user's row
- the current user is considered online for two minutes after heartbeat
- inactive roster users display offline

## 7. Employee chat

Use two sales accounts in separate browsers or private windows.

Expected:

- both sales users appear in the right roster
- online indicators update
- messages send and display in the thread
- messages sent to an offline user are marked as offline notes
- unread count appears until the thread is opened

## 8. Account menu

Open the universal user dropdown.

Expected:

- lighter font weight
- compact technical styling
- no oversized bold menu typography
