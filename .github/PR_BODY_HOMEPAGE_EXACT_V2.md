## Summary

- Replaces the reconstructed logged-out homepage with the uploaded homepage demo in `index.php`.
- Preserves the PHP bootstrap and logged-in redirect to `/inbox.php`.
- Adds the uploaded `foreground.png`, `mountains.png`, and `orb.png` directly under `assets/images/`.
- Adds the exact demo CSS and JavaScript under production asset paths.
- Removes the superseded v1 reconstructed homepage CSS, JavaScript, validator, and workflow.

## Validation

- `php -l index.php`
- `node --check assets/js/homepage-parallax-exact-v2.js`
- Original PNG SHA-256 hashes verified.
- Exact production asset references verified.

No SQL required.
