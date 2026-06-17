# Authenticated Application Shell Template

## Default rule

Every protected Microgifter application page uses the same branded sidebar-and-header application frame by default.

The shell owns:

- Microgifter logo and fixed left sidebar frame
- top application header
- account, notification, message, and cart controls
- mobile sidebar behavior
- content offsets below the header and beside the sidebar
- base background, borders, spacing, and responsive breakpoints

A page should provide only:

- the sidebar content variant
- active navigation state
- page actions
- main workspace content
- page-specific scripts and styles

## Explicit variants

Only these protected page groups may use an explicit shell variant:

1. `Agent`
2. `Inbox / Sent / Claimed`
3. `Product Builder`

These variants may change the workspace body, but they must keep the shared branded outer frame, header height, sidebar width, mobile behavior, and account controls.

## Inbox / Sent / Claimed layout contract

The top header tabs are the only folder navigation:

- Inbox
- Sent
- Claimed

The inner workspace must not render a second Inbox/Sent/Claimed tab bar.

All three folders share one consolidated row-action layout:

- left/main list of PPPM gift rows
- compact metadata on every row
- folder-specific row CTA buttons
- LOAD opens the right-side PPPM content drawer
- SEND, CLAIM, MESSAGE, and TIP open modal forms
- mobile drawers and forms become app-like full-screen views

Folder CTA contracts:

- Inbox: `Send`, `Claim`, `Load`
- Sent: `Message`, `Load`
- Claimed: `Message`, `Tip`

Row metadata contracts:

- Inbox: from, received date, type, value, status, expiration when present
- Sent: recipient, sent date, type, value, delivery/status
- Claimed: merchant/location, redeemed date, type, value, claim/redemption status

## Prohibited patterns

- Missing Microgifter logo on protected pages
- empty reserved sidebar columns
- floating account-card sidebars used as page shells
- page content starting under or behind the fixed header
- duplicate Inbox/Sent/Claimed navigation inside the content area
- page-specific shells that redefine header/sidebar offsets
- moving functionality into a visual-only replacement instead of reallocating existing functionality
