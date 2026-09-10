# eDOC V2 cutover and rollback runbook

> Cutover บนเครื่องปัจจุบันเสร็จแล้วเมื่อ 2 กันยายน 2026: `DB_DATABASE=e_docv2`,
> `EDOC_V2_ENABLED=true`, `EDOC_V2_DOCUMENT_READS=true` และ
> `EDOC_V2_WRITE_ENABLED=true` ส่วน `edoc_db` ใช้ตรวจย้อนหลังผ่าน
> `mysql_legacy_readonly` เท่านั้น

This runbook changes production state. Run it only in an approved maintenance
window. Replace placeholders after verifying the exact host, database names, and
backup destination. Never delete or rename `edoc_db` during the acceptance period.

## Required state

- Target database server is MySQL 8.0+ on clean storage.
- A full data-directory backup exists from a stopped source server.
- A logical `edoc_db` dump has been restored successfully to a disposable server.
- Application storage was backed up at the same point in time as the database.
- `php artisan edoc:v2-validate` has no `BLOCK` result.
- All application tests and V2 integration tests pass.
- `EDOC_V2_ENABLED=false` and `EDOC_V2_WRITE_ENABLED=false` before the window.

The current local MariaDB data directory has redo-log/tablespace LSN mismatches.
Do not promote a physical copy of that directory. Use a verified logical dump to
move data to the clean target.

## 1. Pre-cutover backup

1. Record the current commit SHA and application configuration checksum.
2. Enter maintenance mode and stop all queue workers and the scheduler.
3. Confirm the `jobs` table is empty or document each intentionally deferred job.
4. Create a fresh logical dump with `--single-transaction --quick --hex-blob`.
5. Back up `storage/app` and calculate SHA-256 manifests for the dump and files.
6. Restore the dump into a disposable database and compare exact row counts.

The pre-V2 local verification dump currently lives under ignored `tmp/`; it is a
development safety copy, not the required off-machine production backup.

## 2. Prepare V2

Create an empty target database and configure only the isolated connection:

```dotenv
V2_DB_DATABASE=e_docv2
EDOC_V2_ENABLED=false
EDOC_V2_WRITE_ENABLED=false
EDOC_V2_CONNECTION=mysql_v2
```

Run the versioned schema and a read-only migration preview:

```bash
php artisan edoc:v2-schema --force
php artisan edoc:v2-migrate
```

Copy the data and validate it:

```bash
php artisan edoc:v2-migrate --commit
php artisan edoc:v2-repair-leave-workflows
php artisan edoc:v2-validate
```

Expected legacy allocation gaps are reported as `WARN`; the corresponding V2
allocations must exist and all V2 checks must report `PASS`.

## 3. Read-only smoke test

Set `EDOC_V2_ENABLED=true` while keeping writes disabled, clear configuration
cache, and sign in as a dedicated super administrator or auditor.

```bash
php artisan config:clear
```

Open `/admin/v2/health` and require `status: ok`. Compare representative users,
documents, confidential access, leave requests, workflow history, numbering,
bookings, files, and audit entries with the legacy UI.

## 4. Controlled write smoke test

Use a non-production clone first. Enable `EDOC_V2_WRITE_ENABLED=true`, then test:

1. Create and route one document.
2. Approve and reject separate workflow samples.
3. Allocate a number and verify duplicate rejection.
4. Create adjacent room bookings and reject an overlapping booking.
5. Submit and complete one leave workflow.
6. Verify signature evidence path and SHA-256.
7. Verify authorization with each privileged role.

Remove smoke-test rows or rebuild the target from the final source copy. Never
reuse a contaminated target for production cutover.

## 5. Final delta and switch

1. Enter maintenance mode again and drain workers.
2. Take the final database and storage backups.
3. Re-run `edoc:v2-migrate --commit`; it is idempotent and updates copied rows.
4. Re-run `edoc:v2-validate` and the application smoke suite.
5. Deploy the V2 application code and point its primary connection to `e_docv2`.
6. Keep the legacy connection available read-only for verification.
7. Restart workers and the scheduler only after the web smoke tests pass.
8. Leave maintenance mode and monitor errors, failed jobs, latency, and audit logs.

## 6. Rollback triggers

Rollback immediately for any of the following:

- authentication or role resolution failure;
- missing document/file/signature evidence;
- incorrect workflow actor or current step;
- duplicate or skipped official number;
- authorization exposure of confidential documents;
- persistent database errors or failed jobs caused by V2.

## 7. Rollback procedure

1. Enter maintenance mode and stop V2 workers.
2. Record the incident time and preserve V2 logs, database, and storage unchanged.
3. Restore the legacy application configuration and code revision.
4. Point the primary connection back to `edoc_db`.
5. Reconcile writes made after cutover before reopening legacy. Do not silently
   discard V2-created official numbers, approvals, audit entries, or uploaded files.
6. Clear caches, start legacy workers, run smoke tests, and leave maintenance mode.
7. Keep V2 read-only for forensic comparison.

Rollback is simple only while V2 writes are disabled. Once writes are enabled,
the reconciliation step is mandatory.

## 8. Acceptance and legacy retention

- Keep `edoc_db` read-only through the agreed acceptance period.
- Compare daily counts and allocation invariants during that period.
- Retain backups according to the organization's records policy.
- Decommission legacy only after written acceptance and a final restore drill.
