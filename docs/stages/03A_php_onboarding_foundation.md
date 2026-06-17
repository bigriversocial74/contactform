# Build 03A — PHP Onboarding Foundation

This build starts the official Microgifter PHP foundation while preserving the current HTML prototype files.

## Source-of-truth direction

- GitHub repository is the root source for code.
- Active app pages move toward PHP.
- Existing HTML files stay available as UI references.
- CSS and JavaScript are consolidated into shared assets.
- Permission-based UI should be enforced server-side and by protected API endpoints.

## Added active PHP pages

- `index.php`
- `build.php`
- `agent.php`
- `signin.php`
- `signup.php`
- `forgot-password.php`
- `reset-password.php`
- `verify-email.php`
- `account.php`

## Added includes

- `includes/app.php`
- `includes/auth.php`
- `includes/csrf.php`
- `includes/permissions.php`
- `includes/header.php`
- `includes/footer.php`
- `includes/layout.php`

## Added shared assets

- `assets/css/microgifter.css`
- `assets/css/sections/builder.css`
- `assets/css/sections/agent.css`
- `assets/css/sections/social.css`
- `assets/css/sections/ecommerce.css`
- `assets/css/sections/pppm.css`
- `assets/js/microgifter.js`
- `assets/js/api-client.js`
- `assets/js/auth.js`
- `assets/js/onboarding.js`
- `assets/js/builder.js`
- `assets/js/agent.js`
- `assets/js/social.js`
- `assets/js/commerce.js`
- `assets/js/programs.js`

## Notes

The existing prototype files remain in root for now:

- `index.html`
- `build.html`
- `agent.html`

The next cleanup pass should continue extracting useful UI details from those prototype files into the shared PHP/CSS/JS structure.
