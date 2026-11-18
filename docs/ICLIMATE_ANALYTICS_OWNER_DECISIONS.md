# iClimate Analytics Owner Decisions

These decisions concern historical failed runs only. No decision is recorded automatically.

## Run 2727

- Queue job: 126
- Type: `dataset_processing`
- Sanitized input metadata: `schema` field present
- Status: failed before start; no result
- Expected effect: normal workflow reads climate and rice-production inputs and updates the run result/status; no direct derived-table persistence was identified in the result persister.
- Idempotency/recovery: use the normal workflow only; do not replay the backed-up payload directly.

Owner decision: **PENDING**

- [ ] APPROVE REGENERATION
- [ ] KEEP HISTORICAL FAILURE
- [ ] INVESTIGATE FURTHER

## Run 2728

- Queue job: 127
- Type: `advisory_ai`
- Sanitized input metadata: `source`, `force_weather`, and `force_pagasa` fields present
- Status: failed before start; no result
- Expected effect: normal workflow reads weather inputs and may create planting advisories using generation-key `firstOrCreate`; downstream notification/realtime effects require review.
- Idempotency/recovery: use the normal workflow only; do not replay the backed-up payload directly.

Owner decision: **PENDING**

- [ ] APPROVE REGENERATION
- [ ] KEEP HISTORICAL FAILURE
- [ ] INVESTIGATE FURTHER

No database records, failed-run history, or backups were modified while preparing this document.

## Readiness assessment — 2026-11-10

Both runs remain **PENDING**. No current evidence authorizes regeneration:

- Run 2727 may be considered only through its normal `dataset_processing` workflow after the owner selects **APPROVE REGENERATION**. It updates the run result/status and reads climate and rice-production inputs.
- Run 2728 may be considered only through its normal `advisory_ai` workflow after the owner selects **APPROVE REGENERATION**. It may persist advisories using generation-key idempotency and trigger notifications/realtime effects.

The alternatives are independent: **KEEP HISTORICAL FAILURE** preserves the failed state and avoids new business output; **INVESTIGATE FURTHER** requests more evidence without execution. No retry, payload reinsertion, regeneration, or historical-record modification occurred.
