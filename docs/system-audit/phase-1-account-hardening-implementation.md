# Phase 1 — Account and Access Hardening

Implemented in the isolated test environment only.

## Changes

- `EnsurePasswordChanged` now blocks every non-exempt HTTP method for accounts requiring a password change and returns HTTP 409 JSON for API-style requests.
- The MAO default password fallback was removed. MAO creation/reset generates a per-account random one-time password when no configured password exists.
- Farmer masterlist and demo seeding no longer reuse one password hash for newly created accounts.
- Added `iclimate:flag-default-passwords`, dry-run by default, with optional `--apply` to mark shared/known-default accounts for password change without exposing passwords or hashes.
- Permissions submission now requires the `modules` array and an explicit confirmation before leaving supported roles with no modules. Audit context includes before/after values.
- IT user management can assign the Municipal Agriculturist flag to an MAO account; assigning a new head clears the previous head transactionally.

## Not performed

- No active database command, seeder, migration, service restart, commit, push, or deployment was performed.
- Owner actions remain: review the dry-run output, run `iclimate:flag-default-passwords --apply` on the intended database, rotate any flagged staff credentials, and choose the MAO Head.

## Verification

The Phase 1 feature tests cover all HTTP methods, JSON blocking, permission validation/confirmation, and dry-run/apply password flagging. Existing panel and settings tests were updated only where they asserted the removed shared default-password behavior.
