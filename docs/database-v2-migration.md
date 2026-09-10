# eDOC V2 database migration

## Safety boundary

`edoc_db` remains the source of record throughout the migration. V2 is built in
a separate database and no source row is updated or deleted by the migration.
Cutover is allowed only after a logical dump has been restored successfully and
the validation command reports no blocking differences.

The target database is MySQL 8.0+. MariaDB 10.4 may be used for local inspection,
but it is not the production compatibility target.

## Schema mapping

| Current source | V2 target | Migration rule |
| --- | --- | --- |
| `users.department`, `division`, `work_unit` | `organization_units` | Create a hierarchy from distinct non-empty values and link users to the most specific unit. Preserve the original labels in migration metadata until validation completes. |
| `users.position` | `positions` | Create one position per normalized distinct value. |
| `users.signature` | `user_signatures` | Decode to private storage, calculate SHA-256, then insert the path and hash. Never remove the source value during migration. |
| `documents.doc_type` | `document_types` | Map `incoming`, `outgoing`, and `internal` to stable master-data codes. |
| `documents.doc_type_category` | `document_categories` | Map non-empty labels to master data; unknown labels are preserved as newly created categories. |
| `documents.doc_speed` | `document_priorities` | Map Thai labels to `NORMAL`, `URGENT`, `VERY_URGENT`, or `HIGHEST`. |
| `documents.doc_secret` | `document_confidentialities` | Map Thai labels to `NORMAL`, `CONFIDENTIAL`, `SECRET`, or `TOP_SECRET`. |
| document file-path and `external_*` columns | `document_files` | Create one row per available main, attachment, signed, or external file. Preserve URL, original name, MIME type, size, hash, and download status. |
| `documents.assigned_*` | `document_assignments` | Create the current assignment as an assignment event. Resolve department-only assignments through an organization unit, not a free-text user id. |
| `document_routes` | `workflow_instances`, `workflow_steps` | Create one document workflow instance and copy every route as an immutable instance step. |
| document approval signatures | workflow action evidence | Store actor, action, timestamp, signature path/hash, name, position, request id, and comment as a snapshot. |
| `document_access_requests` | V2 access requests | Rename `user_id` to `requested_by`, preserve status and timestamps, and enforce one active request per document/requester in application logic. |
| `leave_requests.leave_type` | `leave_types` | Map known Thai labels to stable codes and create an explicit legacy code for unknown values. |
| leave workflow columns | shared workflow tables | Create one leave workflow instance and a step for each reached stage, including delegate response/reminder/escalation metadata. |
| `document_number_allocations` | `number_sequences`, `number_allocations` | Build one sequence per type/scope/fiscal year. Import allocations only after duplicate and missing-allocation reconciliation. |
| `rooms`, `room_bookings` | V2 room tables | Preserve soft deletion and room-name snapshots. Copy invitees from `room_booking_user`. |
| `audit_logs` | V2 audit logs | Preserve append-only rows and timestamps without updating source audit data. |
| Laravel/Spatie tables | equivalent V2 tables | Copy ids where possible so polymorphic role assignments remain valid. |
| jobs and extraction tasks | equivalent V2 tables | Do not copy active jobs during normal cutover. Drain workers first; copy failed jobs and extraction history separately. |

## Compatibility decisions

- V2 status values are uppercase PHP-backed enums.
- Number uniqueness is scoped by sequence, not globally by rendered text.
- Workflow definitions are templates. Runtime steps store snapshots so later
  template edits cannot rewrite history.
- Approval evidence does not point only to a user's current signature. It stores
  an immutable signature and actor snapshot.
- Audit logs use only `created_at`; the Eloquent model must disable timestamps.
- Room overlap remains a transactional application invariant because a normal
  MySQL unique index cannot enforce interval exclusion.
- V2 migrations live separately from legacy migrations. An empty V2 database is
  migrated with an explicit V2 path; its migration repository must never be
  populated by manually creating an empty `migrations` table.

## Delivery phases

1. Back up the complete MariaDB data directory and create a logical `edoc_db` dump.
2. Restore that dump to a clean disposable server and record source checksums/counts.
3. Create an empty V2 database using versioned Laravel V2 migrations.
4. Run the idempotent copy command from the restored legacy database to V2.
5. Run validation for counts, identifiers, allocations, relationships, and files.
6. Exercise legacy and V2 workflows with automated and smoke tests.
7. Enter maintenance mode, drain queues, take a final dump, and run the delta copy.
8. Switch the application connection; keep legacy read-only until acceptance ends.

## Blocking validation rules

- Every numbered document or leave request has exactly one allocation.
- No allocation has more than one resource and no resource has multiple allocations.
- Every V2 document, leave, workflow step, assignment, and booking resolves its
  required foreign keys.
- Source and target business-row counts match after documented exclusions.
- Every migrated file exists and matches its stored SHA-256.
- No pending legacy queue job remains at final cutover.
- Authorization role mappings have been approved and every privileged endpoint
  passes its policy test.

## Current source issues to reconcile

- Documents 3, 8, and 9 have a running number but no allocation row.
- The local MariaDB data directory reports redo-log/tablespace LSN mismatches and
  stale dictionary entries for removed databases. Migration must use a logical
  dump restored to a clean instance, never a direct copy of this data directory.
