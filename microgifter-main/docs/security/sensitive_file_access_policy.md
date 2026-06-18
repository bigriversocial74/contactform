# Sensitive File Access Policy

Microgifter keeps planning documents, database scripts, tests, and internal PHP includes in the repository because they are important for development. They must not be exposed as public web content.

## Publicly routable files

The active public application entry points are root PHP pages such as:

- `index.php`
- `build.php`
- `agent.php`
- `signin.php`
- `signup.php`
- `forgot-password.php`
- `reset-password.php`
- `verify-email.php`
- `account.php`

Static assets under `assets/` are also public.

## Private/internal paths

These should be blocked from direct web access:

- `docs/`
- `database/`
- `tests/`
- `includes/`
- `.env`
- SQL dumps
- dependency manifests and lock files when not needed publicly

## Apache

The root `.htaccess` added in build 03K blocks the sensitive folders and common secret file names on Apache/cPanel-style hosting.

## Nginx equivalent

For Nginx, add equivalent rules in the site config, for example:

```nginx
location ~ ^/(docs|database|tests|includes)/ {
    deny all;
    return 403;
}

location ~ /\.env {
    deny all;
    return 403;
}

location ~* \.(sql)$ {
    deny all;
    return 403;
}
```

## Deployment rule

Before launch, manually visit the private/internal paths in a browser. A secure deployment returns `403` or `404`, never directory listings or file contents.
