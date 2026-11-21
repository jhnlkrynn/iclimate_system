# `rice_productions` Data-Quality Report

**Scope:** Read-only audit of `rice_productions` in `iclimate_staging_20261103_fix3` (124 total rows; 0 rows with `verified_at` set). No records were modified.

## Summary of Findings

1. **Allocated Municipal Data (All 110 `Irrigated` Rows):**
   Every `Irrigated` record (`remarks`: *"Official MAO planting summary 2020-2025; no special remark."*) has identical (or within `0.01 t/ha` rounding) `yield_per_hectare` across all 10 barangay buckets in every `(year, season)`. This indicates municipal-level season totals were allocated proportionally by area across barangays rather than measured separately per barangay.
   Because each barangay shares the municipal yield per hectare, `YieldLocalAdjustment` produces factors between `0.9996` and `1.0042` (`|factor - 1| < 0.02`), so all of them fall back to `factor = 1.0`.
2. **Zero `Rainfed` Records & Missing Barangays:**
   There are **0 `Rainfed` rows** across all 19 Lian barangays, and **0 `Irrigated` rows** for 11 official barangays (`Bagong Pook`, `Barangay 1–5 (Pob.)`, `Cumba`, `Humayingan`, `Lumaniag`, `Putol`, `San Diego`).
3. **Demo / Unnormalized Rows:**
   14 rows (`2025 Wet`, `irrigation_type = Demo mixed`, `remarks = [demo_seed] Aggregated from demo farmer schedules.`) are demo data and are excluded by `SampleData::without()`. They also contain non-canonical barangay strings (`BARANGAY 1 POB`, `LUYAHN`, `—`).
4. **Blank / Dash Buckets:**
   `(blank)` and `—` each contain 5 Dry + 6 Wet `Irrigated` rows with the same municipal yield series.

## Record Counts per `(barangay, farm_type, season)`

| Barangay | Farm Type | Season | Records | Seasons (Years) | `verified_at` | Flag |
| --- | --- | --- | ---: | ---: | ---: | --- |
| `(blank)` | Irrigated | Dry | 5 | 5 (2020–2024) | 0 | Blank barangay; identical municipal yields |
| `(blank)` | Irrigated | Wet | 6 | 6 (2020–2025) | 0 | Blank barangay; identical municipal yields |
| `—` | Irrigated | Dry | 5 | 5 (2020–2024) | 0 | Dash barangay; identical municipal yields |
| `—` | Irrigated | Wet | 6 | 6 (2020–2025) | 0 | Dash barangay; identical municipal yields |
| Balibago | Irrigated | Dry | 5 | 5 (2020–2024) | 0 | **Flagged:** identical/allocated municipal yields |
| Balibago | Irrigated | Wet | 6 | 6 (2020–2025) | 0 | **Flagged:** identical/allocated municipal yields |
| Binubusan | Irrigated | Dry | 5 | 5 (2020–2024) | 0 | **Flagged:** identical/allocated municipal yields |
| Binubusan | Irrigated | Wet | 6 | 6 (2020–2025) | 0 | **Flagged:** identical/allocated municipal yields |
| Bungahan | Irrigated | Dry | 5 | 5 (2020–2024) | 0 | **Flagged:** identical/allocated municipal yields |
| Bungahan | Irrigated | Wet | 6 | 6 (2020–2025) | 0 | **Flagged:** identical/allocated municipal yields |
| Kapito | Irrigated | Dry | 5 | 5 (2020–2024) | 0 | **Flagged:** identical/allocated municipal yields |
| Kapito | Irrigated | Wet | 6 | 6 (2020–2025) | 0 | **Flagged:** identical/allocated municipal yields |
| Luyahan | Irrigated | Dry | 5 | 5 (2020–2024) | 0 | **Flagged:** identical/allocated municipal yields |
| Luyahan | Irrigated | Wet | 6 | 6 (2020–2025) | 0 | **Flagged:** identical/allocated municipal yields |
| Malaruhatan | Irrigated | Dry | 5 | 5 (2020–2024) | 0 | **Flagged:** identical/allocated municipal yields |
| Malaruhatan | Irrigated | Wet | 6 | 6 (2020–2025) | 0 | **Flagged:** identical/allocated municipal yields |
| Matabungkay | Irrigated | Dry | 5 | 5 (2020–2024) | 0 | **Flagged:** identical/allocated municipal yields |
| Matabungkay | Irrigated | Wet | 6 | 6 (2020–2025) | 0 | **Flagged:** identical/allocated municipal yields |
| Prenza | Irrigated | Dry | 5 | 5 (2020–2024) | 0 | **Flagged:** identical/allocated municipal yields |
| Prenza | Irrigated | Wet | 6 | 6 (2020–2025) | 0 | **Flagged:** identical/allocated municipal yields |
| 14 barangay labels | Demo mixed | Wet | 14 (1 each) | 1 (2025) | 0 | Demo seed (`SampleData::without` excludes) |

## Cross-Barangay Yield Identity (`Irrigated`, 10 Barangay Buckets)

| Year & Season | Distinct `yield_per_hectare` Values Across All 10 Buckets |
| --- | --- |
| 2020 Dry | `4.15` |
| 2021 Dry | `4.20` |
| 2022 Dry | `4.35` (9 buckets), `4.34` (Matabungkay) |
| 2023 Dry | `4.12` |
| 2024 Dry | `3.85` (9 buckets), `3.84` (Balibago) |
| 2020 Wet | `3.90` |
| 2021 Wet | `3.85` (9 buckets), `3.84` (Balibago) |
| 2022 Wet | `3.40` |
| 2023 Wet | `3.95` |
| 2024 Wet | `1.90` |
| 2025 Wet | `3.80` (9 buckets), `3.79` (Balibago) |
