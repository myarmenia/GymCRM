# Multi-gym customers: SEV and SPITAK

Apply the matching application changes and migration in both repositories. SEV owns the existing Redis synchronization workers and its `mysql_second` connection points to SPITAK. SPITAK continues to increment local record versions; existing gym-scoped reconciliation imports those changes.

## Data contract

- `people` is the shared identity. New customer registrations require a valid phone; all accepted Armenian phone formats are stored as `374` followed by eight digits. Lookup and attachment use that canonical phone. Existing identity fields are read-only to gym staff.
- `gym_person` is a record with a stable UUID, its own version, type, block status, blocking actor/time and a deletion tombstone. Its unique `(person_id, gym_id)` pair is restored rather than duplicated on re-registration.
- Backfill copies the existing person's type and block status to every current gym link. The initial link version inherits the old person version, preserving the pre-migration ordering of shared state. Subsequent edits increment only the link version.
- UUIDs for existing links are deterministic from the person UUID and gym UUID, so different local integer IDs do not create different identities.
- The old `people.type` and `people.is_blocked` columns remain for compatibility with the deferred mobile work. The updated admin workflows use the gym link and no longer synchronize these legacy columns. Mobile screens and the email-based mobile login have not been changed in this phase.

## Deployment

1. Run `php artisan people:check-phones` against each application's database. This is read-only and reports customer IDs, not phone values. Correct invalid phones and normalized collisions using verified data. The migration refuses these cases before changing schema or phones and never merges people or invents a replacement number.
2. Use the existing deployment maintenance/worker-stop procedure for both applications while applying this coordinated schema change. Run `php artisan migrate --force` in SPITAK and SEV with their respective database configuration. Run the same release on both sides before resuming synchronization.
3. Restart the existing SEV Redis workers using the normal deployment procedure (`php artisan queue:restart` where applicable). The new `gym_person` aggregate uses `GYM_PERSON_SYNC_QUEUE`, defaulting to the existing `default` queue.
4. Run the existing gym-scoped reconciliation from the dashboard. It now includes gym-person records, including deletion tombstones. Existing direction, target-newer and equal-version payload-mismatch rules still apply. Resolve pre-existing identity conflicts through the normal conflict workflow.

Legacy customers without a phone are retained with null phone values rather than fabricated credentials. New registration and attachment require a valid phone. The database column remains nullable for those legacy records; making it NOT NULL requires resolving every legacy missing value first.

## Authorization and behavior

- A staff member can only change their gym's customer type, block state and card. Unlinking removes that gym's card permissions and retains the shared identity, other gyms and financial history.
- Card assignment and turnstile duplicate detection are scoped to the current gym. Existing visit/guest allowance rules are preserved.
- Profiles, membership filters, documents and visit statistics use the selected/authorized gym. Owner profile and document selectors only list gyms linked to the person.
- Owners retain read-only membership-sales lists. Sale creation/management and contract reissue/retry endpoints are blocked for owners; contract settings and read-only contract downloads retain their existing rules.
- Document list/upload/delete authorization is enforced server-side. The existing SPITAK-only customer/staff file-upload rule in SEV remains. This change does not relocate existing public storage files or change their direct URLs.

## Validation

Regression coverage includes normalized phone attachment, immutable shared fields, separate gym state/card updates, blocking metadata, gym-scoped profiles/documents, owner restrictions, removal/restoration, deterministic migration backfill and collision rejection. SEV additionally tests independent link synchronization, UUID-to-local-ID mapping, reverse/auto directions, tombstones, target-newer conflicts and equal-version divergence.
