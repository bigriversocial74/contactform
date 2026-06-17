# AWS Database Strategy

## Decision

For the current Microgifter codebase, use MySQL-compatible SQL and target Amazon Aurora MySQL-compatible for AWS production.

## Why

The current Stage 1 implementation already uses MySQL-compatible SQL patterns such as:

- `AUTO_INCREMENT`
- `INSERT IGNORE`
- `ON DUPLICATE KEY UPDATE`
- MySQL `JSON` columns
- `NOW()` timestamp defaults

Moving to PostgreSQL now would require a meaningful rewrite of schema files, migrations, endpoint SQL, and helper code before the core product is built.

## Production target

Recommended AWS path:

1. Development/private beta: MySQL 8 compatible local or managed database.
2. Early production: Amazon RDS for MySQL or Aurora MySQL-compatible.
3. Growth stage: Aurora MySQL-compatible with read replicas, Performance Insights, backups, private subnets, and controlled security groups.
4. Later scale: shard or split by account/store/user scope only when measured load requires it.

## PostgreSQL position

PostgreSQL is excellent, especially for advanced indexing, JSONB, analytics-style querying, and complex constraints. However, the current codebase has already moved toward MySQL compatibility. Switching now would slow delivery without improving the immediate product foundation.

## Portability rule

Keep new SQL as portable as reasonably possible:

- avoid engine-specific tricks unless needed
- isolate MySQL-specific upserts in helpers
- document every database-specific feature
- keep IDs and partition keys database-agnostic

## AWS support stack

Use these managed services as the platform grows:

- Aurora MySQL-compatible or RDS MySQL for relational truth
- ElastiCache for Redis or Valkey for hot cache/rate/session acceleration
- SQS or EventBridge for external queues later if database outbox becomes insufficient
- CloudWatch for operational metrics and alerts
- S3 for media/object storage
- CloudFront for static/media delivery

## Carry-forward rule

Do not introduce a second primary database engine until there is a measured need. Keep one source-of-truth relational engine first, then add cache, queue, search, and analytics stores as separate supporting systems.
