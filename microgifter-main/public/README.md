# Microgifter public web root

Production deployments should point the web server document root to this `public/` directory before public traffic.

Current Stage 1 status:

- Root PHP pages are still active during the hardening transition.
- Internal folders such as `includes/`, `database/`, `docs/`, `tests/`, and `scripts/` must never be directly web-addressable.
- The deployment profiles in `docs/deployment/` describe the Apache/cPanel and VPS/Nginx approaches.

Before launch, move or front-route approved public pages through this directory and serve only approved assets/API entrypoints from the web root.
