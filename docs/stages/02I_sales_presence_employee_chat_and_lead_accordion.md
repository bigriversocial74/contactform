# 02I Sales Presence, Employee Chat, and Lead Accordion

Status: implementation complete; deployment requires SQL import and file upload.

## Database migration

Import:

```text
database/02I_sales_presence_and_employee_chat.sql
```

This creates:

- sales_presence
- employee_chat_messages

## CRM changes

- Right sales-team column now begins directly beneath the universal header.
- Lead statistic cards are narrower and limited to the center workspace.
- Each lead is now an accordion row.
- Lead rows include status and assigned-sales-person selectors.
- Expanded rows show contact, business, source, priority, region, message, note, save, and create-user controls.
- The create-user-from-lead workflow remains intact.

## Employee chat

- Sales roster displays online, away, and offline presence.
- Presence heartbeat updates while the CRM is open.
- Sales users can open a direct employee chat.
- Messages to offline employees are stored as offline notes.
- Threads poll while open for basic near-real-time behavior.
- Unread counts display on the sales roster.

## Universal account menu

Added assets/css/account-menu.css and loaded it from includes/header.php.

The account dropdown now uses lighter font weights, smaller type, restrained borders, and a tighter technical interface instead of bold pill-heavy styling.

## Files added

- database/02I_sales_presence_and_employee_chat.sql
- api/sales/presence.php
- api/sales/chat/thread.php
- api/sales/chat/send.php
- assets/css/account-menu.css

## Files updated

- api/admin/sales/roster.php
- sales-crm.php
- assets/js/sales-crm.js
- assets/css/sections/sales-crm.css
- includes/header.php
