# Data migration

## Principles

1. **Table names preserved.** `person`, `grievance`, `grievance_*`, `organization`, `region`, etc. Models set `protected $table` where Eloquent convention diverges.
2. **Timestamps standardized.** Legacy `date_created`/`last_updated` backfilled once into `created_at`/`updated_at`.
3. **Permissions re-seeded.** Spatie v4 → v6 has schema differences; do not migrate permission rows — run `RolePermissionSeeder`.
4. **Soft deletes added** on `grievance`, `grievance_resolution`, `grievance_feedback` for auditability. Legacy rows get `deleted_at = null`.
5. **Media files** copied from `public/storage/` to S3 in a single pass; URLs rewritten via migration.

## Process

1. Stand up new DB alongside legacy; grant read-only replica access.
2. Run `php artisan migrate:fresh` on new DB.
3. Run `RolePermissionSeeder`.
4. Run `php artisan migrate:legacy` (custom command, one file per source table) — idempotent upserts keyed on legacy PK.
5. Per table: verify `SELECT COUNT(*) FROM legacy.X = SELECT COUNT(*) FROM new.X`. Spot-check 20 random rows by checksum.
6. Dual-run period: new system reads from new DB, writes still go to legacy for 5 business days. Diff reports weekly.
7. Cutover: flip writes to new DB. Keep legacy read-only for 90 days.
8. After 90 days: archive legacy DB dump to cold storage; decommission.

## Known gotchas from the legacy app

- `person` table is the `User` model's table (not `users`).
- `grievance` has three `*_by` foreign keys to `person`: `created_by`, `modified_by`, `checked_out_by`. Preserve all three.
- Geographic hierarchy: `country → region → district → chiefdom → section → locality`. No GIS data attached.
- Media uses optix/media — legacy schema differs from Spatie Media Library. If switching libraries, write an adapter migration.
