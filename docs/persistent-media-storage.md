# Persistent media storage

Microgifter stores new feed images, video, and audio in a protected directory outside the deployed application release. Deploying or extracting a new application archive can replace the code directory without replacing user media.

## Default HostGator / cPanel layout

When the application is deployed in:

```text
/home/CPANEL_USER/public_html
```

the default media directory is:

```text
/home/CPANEL_USER/microgifter-storage
```

The storage directory is a sibling of `public_html`, not a child of it. Files are never linked directly from the public web root. They are delivered through `/api/public/media.php`, which checks the requesting user's access to the related feed post.

## Configuration

Use environment variables:

```text
MG_MEDIA_STORAGE_DRIVER=persistent_local
MG_MEDIA_STORAGE_ROOT=/home/CPANEL_USER/microgifter-storage
MG_MEDIA_PUBLIC_ENDPOINT=/api/public/media.php
MG_REQUIRE_PERSISTENT_MEDIA_STORAGE=true
```

Alternatively, add the `storage` section shown in `api/config.local.example.php` to the server-only `api/config.local.php` file.

`MG_MEDIA_STORAGE_ROOT` must be an absolute path. In production, the application rejects a storage root located inside the release directory.

## First deployment

From the application directory, run:

```bash
php scripts/check_media_storage.php --initialize
```

This command:

1. Creates the configured directory when necessary.
2. Verifies it is outside the application release.
3. Creates the `.microgifter-storage` sentinel.
4. Performs a write, read, and delete probe.
5. Confirms the protected public delivery endpoint is present.

The public `/api/health.php` check remains unavailable until the media volume is initialized. This prevents a deployment from becoming healthy while uploads would still be written to disposable storage.

## Migrating existing feed uploads

After the database migration containing `feed_post_assets` has been applied, inspect the migration:

```bash
php scripts/migrate_feed_media_storage.php --dry-run
```

Copy existing files to persistent storage and update the database:

```bash
php scripts/migrate_feed_media_storage.php
```

The default migration copies and verifies files but leaves the old files in place as a rollback safety copy. After confirming the feed media works, a later migration run can remove legacy source files during migration:

```bash
php scripts/migrate_feed_media_storage.php --delete-source
```

Use `--limit=100` to migrate in smaller batches or `--asset=UUID` to retry one asset.

The migration verifies SHA-256 checksums, updates feed media URLs to the protected endpoint, restores post-to-asset relationships, and changes the storage provider to `persistent_local`.

## Deployment sequence

Use this order for production releases:

```bash
php scripts/run_migrations.php
php scripts/check_media_storage.php --initialize
php scripts/migrate_feed_media_storage.php --dry-run
php scripts/migrate_feed_media_storage.php
```

Then deploy or extract the application release. The media directory must not be included in or deleted by the release extraction process.

## Backups

Back up both:

- The application database
- The configured persistent media directory

A database backup without the media directory preserves metadata but not the uploaded files. A media-directory backup without the database does not preserve ownership, post relationships, or access rules.

## Cleanup

Abandoned, unattached uploads can be reviewed with:

```bash
php scripts/cleanup_feed_media.php --dry-run --hours=24
```

Run without `--dry-run` to archive their database records and remove their files. The cleanup command excludes media attached through `feed_post_assets` and media still referenced in post JSON.

## Rollback

If the persistent volume cannot be mounted or accessed:

1. Do not point production back to a directory inside the release.
2. Restore access or permissions on the configured persistent directory.
3. Run `php scripts/check_media_storage.php`.
4. Confirm `/api/health.php` returns healthy before re-enabling uploads.

The application intentionally returns a storage-unavailable response rather than silently saving user files into a deployment directory that may be erased later.
