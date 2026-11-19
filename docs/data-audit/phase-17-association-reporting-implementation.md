# Phase 17 — Farmers Association Reporting Implementation

## Scope and safety

This implementation adds an additive, MAO-controlled reporting workflow. It does not alter existing farmer records, stored predictions, ML artifacts, risk calculations, or historical analytics. The photographed association directory remains explicitly provisional.

## Implemented

- Idempotent provisional directory seeder for the 15 photographed associations. It adds no contact details and creates no accounts; existing non-null organization details are preserved.
- Additive association-chairman role and assignment model. Invitations use a random one-time token, expiry, password setup, and explicit activation; photographed chairmen are not automatically activated.
- Reporting periods, one report per association/period, draft versus formal submission status, server-side date/unit validation, calculated average yield, optional private supporting-document path, review history, and separate `research_provenance=unverified` fields.
- MAO monitoring and review routes, revision notes, acceptance, and in-app notification creation through the existing notification/Reverb event path.
- Scheduled idempotent reminder command with configurable pre-deadline and overdue intervals. Missing-submission reminders stop for formal submissions; revision notifications remain separate.
- `ModuleAccess` registration and scheduler entry.

## Verification performed

- PHP syntax checks passed for all new controllers and the reminder command.
- Laravel route registration passed; association chairman, invitation, MAO monitoring, review, reminder, and period routes are present.
- SQLite migration dry-run passed for the new tables. The reporting foreign key explicitly targets `association_reporting_periods`.
- The configured MariaDB connection was unavailable to the sandbox, so no active migration or database seeding was run.

## Known limits requiring follow-up

- No external email/SMS is sent. The invitation link is shown only to the authorized MAO operator for controlled sharing after contact verification.
- A dedicated MAO invitation UI and full message-composer UI remain follow-up work; the underlying invitation and notification endpoints are present.
- The existing `farmer_organizations` schema historically required `farm_type`; the additive migration allows provisional directory entries to remain unknown. No farm type is inferred.
- Association report acceptance is administrative only and never promotes data into an ML training or evaluation dataset.

## Approval gates

MAO must confirm names, barangays, chairmen, and contact details before invitations. The researcher/adviser must separately approve any use of reports for ML research, including provenance, season definitions, observation independence, and leakage-free validation.
