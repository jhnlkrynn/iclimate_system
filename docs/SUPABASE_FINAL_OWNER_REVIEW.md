# iClimate Final Owner Review

Prepared: November 10, 2026
Purpose: plain-language review of the four remaining post-migration decisions.
Status: **PENDING — no new owner decisions recorded**

Notification ID 96 is already approved separately as **ACCEPT LOCAL ENGLISH
VERSION — APPROVED**. It is not reopened by this document.

## Summary

| Item | Evidence status | Owner decision |
|---|---|---|
| 3,227 additional yield predictions | **Technically supported; business acceptance pending** | **PENDING** |
| 7 additional weather records | **Likely post-migration; source identity/validity review incomplete** | **PENDING** |
| 6 additional planting advisories | **Likely post-migration; business and source review incomplete** | **PENDING** |
| User 10 security flags | **Technically verified equivalent** | **PENDING** |

No decision below authorizes deletion, regeneration, synchronization, or
database modification.

## 1. Additional yield predictions

### What changed?

Local MySQL contains 3,227 more `yield_predictions` rows than the original
Supabase snapshot: local IDs 3,272–6,498. The original 3,271 source rows remain
in local IDs 1–3,271.

### Why does the evidence support the change?

The additional rows use the new `2026-11-01` season, the same model version,
and were generated locally on November 10, 2026 within approximately 35
seconds. No duplicate `(subject_key, season_start, period)` identities were
found. Full-field comparison of the original 3,271 rows found no differences.

The local command `iclimate:warm-farmer-yields`, scheduled every three hours,
stores farm and barangay estimates through `YieldPredictionStore`. This is the
identified local generation path; it was not run as part of this review.

### Application effect and risk

These records are used by farmer dashboards and the heat map as stored derived
results. Retaining them keeps the new-season local forecasts available. The
remaining uncertainty is business acceptance of the new season, not evidence
that original source rows were overwritten or lost.

### Owner decision

Choose one: **ACCEPT — Retain the 3,227 additional predictions**;
**REJECT — Investigate remediation**; or **NEEDS FURTHER INVESTIGATION**.

## 2. Additional external weather records

### What changed?

Local MySQL contains 7 additional `external_weather_data` rows compared with
the prior source snapshot.

### Evidence and application effect

The local application stores external weather data from its weather-fetch
process. The scheduler and `WeatherApiService`/Open-Meteo path are present, and
weather data feeds forecasts, climate monitoring, and advisory calculations.
The count difference is consistent with post-migration collection, but the
current owner packet does not establish each extra row’s source/date identity
against the live source. Original source keys were not reported missing in the
live comparison.

### Risk and owner decision

Retaining the rows could preserve current weather context; accepting invalid or
duplicate observations could influence derived forecasts. Choose **ACCEPT —
Retain the 7 additional weather records**, **REJECT — Investigate remediation**,
or **NEEDS FURTHER INVESTIGATION**.

## 3. Additional planting advisories

### What changed?

Local MySQL contains 6 additional `planting_advisories` rows compared with the
prior source snapshot.

### Evidence and application effect

The local scheduler and Python advisory pipeline generate advisory output from
weather and application data. Planting advisories are visible to users through
the planting-advisories views and are used in current recommendations. The
count difference is consistent with post-migration derived/business activity,
but the six rows’ live source identity, authorization, and precise generation
event remain incomplete. Original source keys were not reported missing in the
live comparison.

### Risk and owner decision

Retaining them may preserve current recommendations; accepting an incorrect or
duplicate advisory could affect planting guidance. Choose **ACCEPT — Retain the
6 additional advisories**, **REJECT — Investigate remediation**, or **NEEDS
FURTHER INVESTIGATION**.

## 4. User ID 10 security confirmation

### Technical evidence

User 10 is an active Farmer in both databases. The security flags are
semantically equal: PostgreSQL `false` corresponds to MySQL `0` for both
`is_mao_head` and `must_change_password`. `last_active_at` and `last_login_at`
match. The application authorization rules use the local `users` record and no
unexpected elevated role was identified in the technical review.

No password, hash, token, email, or other personal account detail is included
here. No security-field correction is recommended.

### Owner decision

Choose **CONFIRM — Accept the existing local security settings**;
**REJECT — Request a separate security review**; or **NEEDS FURTHER
INVESTIGATION**.

## Evidence boundaries

- Live comparison: zero source rows missing locally; original critical rows
  and primary-key coverage were preserved in the verified checks.
- Isolated PostgreSQL backup recovery passed.
- Supabase Auth/Storage/Realtime database metadata checks found no application
  users, identities, sessions, MFA factors, buckets, objects, or published
  application tables. Dashboard-only services and external consumers remain
  unverified.
- No database, backup, cloud resource, or active environment setting was
  modified for this review.
- Read-only local automation checks found fresh weather and PAGASA advisory
  data and passed duplicate-protection checks for weather, advisories, and
  monthly forecasts. The data audit reported two separate dashboard recount
  mismatches (monitored barangays and valid yield prediction runs); those were
  not changed or attributed to the four owner decisions.

## Owner response

Authorized owner: ____________________________________
Role: _______________________________________________
Date: _______________________________________________

- Additional yield predictions: **PENDING**
- Additional weather records: **PENDING**
- Additional planting advisories: **PENDING**
- User 10 security flags: **PENDING**
- Notification ID 96: **ACCEPT LOCAL ENGLISH VERSION — APPROVED**

Comments:

________________________________________________________________________
________________________________________________________________________

