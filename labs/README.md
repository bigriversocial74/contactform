# Training Lab Stage 1 UI Shell

This folder contains the Stage 1 visual-first PHP shell for Training Lab by Microgifter.

Target host:

```text
https://labs.microgifter.com
```

Stage 1 rules:

```text
static UI shell only
no database writes
no real uploads
no real payments
no real reward issuing
no separate account system
no production DNS changes
```

## Structure

```text
labs/
  index.php
  pricing.php
  signup.php
  signin.php
  app/
    index.php
  admin/
    index.php
  includes/
    labs-layout.php
  assets/
    css/labs.css
    js/labs.js
```

Additional public, app, and admin pages will be added after the shared shell is stable.
