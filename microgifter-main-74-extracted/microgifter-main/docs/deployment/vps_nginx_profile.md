# Microgifter Deployment Profile: VPS / Nginx

## Purpose

This profile documents a safer VPS/Nginx deployment model for Microgifter when the app is not hosted on cPanel/Apache.

## Recommended layout

Use a dedicated Linux user and keep the repository outside generic shared folders.

```text
/var/www/microgifter/current
/var/www/microgifter/shared/logs
/var/www/microgifter/shared/uploads
```

The active web root may point at the repo root for Stage 1 only if Nginx deny rules are active. A future production refactor should move public files under `/public` and keep `api/`, `includes/`, `database/`, `docs/`, `tests/`, and `scripts/` outside the web root.

## Required runtime

- PHP-FPM 8.1+, PHP 8.2+ preferred.
- MySQL 8 / MariaDB 10.6+ preferred.
- TLS terminated at Nginx or trusted load balancer.
- `display_errors=Off` in production.
- Centralized Nginx and PHP-FPM logs.

## Environment configuration

Set `MG_` environment values in PHP-FPM pool config, systemd service environment, or a protected local config layer.

```text
MG_APP_ENV=production
MG_DEBUG=false
MG_BASE_URL=https://example.com
MG_DB_HOST=127.0.0.1
MG_DB_NAME=...
MG_DB_USER=...
MG_DB_PASS=...
MG_TRUST_PROXY=false
```

If behind a trusted load balancer, set `MG_TRUST_PROXY=true` only after confirming proxy headers are sanitized.

## Nginx deny rules

Use deny blocks for non-public paths:

```nginx
location ~ /(?:\.env|.*\.sql)$ { deny all; return 404; }
location ^~ /docs/ { deny all; return 404; }
location ^~ /database/ { deny all; return 404; }
location ^~ /tests/ { deny all; return 404; }
location ^~ /includes/ { deny all; return 404; }
location ^~ /scripts/ { deny all; return 404; }
```

## PHP routing sample

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
}
```

## Migration workflow

Run from the project root:

```bash
php scripts/run_migrations.php
```

## Production hardening checklist

- TLS configured and renewed automatically.
- HSTS enabled after TLS is verified.
- Sensitive path deny rules tested.
- PHP-FPM runs as a non-root app user.
- Database user has only required privileges for the app database.
- Logs rotate and are not web-accessible.
- `/api/health.php` is public shallow only.
- `/api/admin/health.php` requires admin permission.
