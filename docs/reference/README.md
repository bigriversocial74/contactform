# Reference UI Files

The current root HTML prototypes remain in the repository as visual and interaction references while the official app foundation is moved into PHP pages.

Current prototype references:

- `/index.html` — landing/onboarding prototype
- `/build.html` — builder prototype
- `/agent.html` — workspace prototype

Official active PHP foundation:

- `/index.php`
- `/build.php`
- `/agent.php`
- `/signin.php`
- `/signup.php`
- `/forgot-password.php`
- `/reset-password.php`
- `/verify-email.php`
- `/account.php`

Cleanup rule:

- Do not add new long inline CSS or inline JavaScript to root HTML/PHP pages.
- Shared styles belong in `/assets/css/microgifter.css`.
- Section styles belong in `/assets/css/sections/`.
- Shared JavaScript belongs in `/assets/js/`.
- Server-side permission checks belong in `/includes/permissions.php` and protected API endpoints.
