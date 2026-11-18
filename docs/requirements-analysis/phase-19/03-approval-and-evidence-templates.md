# Approval, Decision, and Research-Evidence Templates

## 1. Stakeholder response record

| Field | Entry |
|---|---|
| Requirement or decision ID | |
| Stakeholder role | Farmer / MAO Personnel / MAO Head / IT Expert |
| Respondent name and position | |
| Organizational/barangay scope | |
| Response | Accept / Revise / Reject / Not applicable |
| Rationale | |
| Conditions or exceptions | |
| Supporting artifact/reference | |
| Date and version | |
| Signature or approval reference | |
| Matrix status after review | |
| Recorded by and review date | |

## 2. Requirements decision record

```text
Decision ID:
Requirement ID(s):
Decision owner:
Affected roles/modules:
Decision statement:
Accepted definition or rule:
Rejected alternatives:
Evidence reviewed:
Implementation impact (documentation only / future approved change):
Effective date and version:
Approval/signature:
Open follow-up:
```

## 3. Research evidence intake record

| Field | Required value |
|---|---|
| Evidence ID and title | |
| Provider and responsible contact | |
| Original source/issuing office | |
| File format and filename | |
| SHA-256 checksum | |
| Collection/export date | |
| Coverage dates and geography | |
| Variables, units, and definitions | |
| Provenance documents attached | |
| Approval status and approver | |
| Demo/derived/replicated rows present | Yes / No / Unknown |
| Duplicate and leakage checks | Result and count |
| Crop, season, planting/harvest alignment | Result |
| Ambulong versus Lian relationship | Documented / Not documented |
| Accepted use | Training / tuning / independent evaluation / descriptive only / rejected |
| Rejection reason or limitations | |

## 4. Model compatibility record

```text
Model artifact/version:
Artifact checksum:
Provider/developer:
Training dataset ID and checksum:
Training script/preprocessing reference:
Target definition and unit:
Feature 1–12 names, units, transformations, missing rules:
Production feature source and order:
Compatibility result per feature:
Season encoding and window result:
Input-distribution/sensitivity result:
Independent-evaluation eligibility:
Reviewer and approval:
```

## 5. Heat Map methodology record

```text
Layer name:
Decision purpose:
Spatial unit and aggregation:
Source records and provenance IDs:
Weather/climate source and freshness rule:
Yield source and observation definition:
Weights, thresholds, or scoring rules:
Missing/no-data behavior:
Demo/derived/unvalidated data handling:
Minimum evidence requirement:
MAO Head decision:
Research/adviser decision:
Version and effective date:
```

## 6. Independent-evaluation acceptance record

```text
Evaluation package ID:
Approved target and unit:
Approved chronological/season-grouped split:
Minimum independent observations and groups:
Leakage, duplicate, demo, derived, and training-overlap result:
Baseline definitions:
Metrics to report: MAE / RMSE / R² / bias / counts / limitations:
Data and model checksums:
Researchers/adviser approval:
Result: ACCEPTED / REVISE / REJECTED / BLOCKED:
```
