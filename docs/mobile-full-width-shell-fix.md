# Mobile full-width shell fix

This branch adds a mobile-only shell override loaded through `assets/css/app-header-sidebar.css`.

The fix keeps the Inbox/Sent/Claimed tab rail only on Action Center pages:

- `/inbox.php`
- `/sent.php`
- `/claimed.php`

Other app pages use only the compact mobile topbar offset so they no longer keep the residual tab/header space.

It also normalizes app-page mobile width so common shells and workspaces do not create a right-side gutter.
