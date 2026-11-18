# Evidence Inventory and Validation Status

## 1. Available authentic evidence

| Evidence class | Available repository evidence | Reliability/use |
|---|---|---|
| Application implementation | Laravel/Python source, routes, services, models, migrations | Establishes implemented paths; not stakeholder acceptance |
| Automated verification | Existing Python and Laravel tests plus recorded Phase 15–16 results | Supports selected technical behavior; not scientific accuracy |
| Deployed model artifact/schema | Model metadata, checksum/readiness records, 12-column runtime schema | Supports identity/schema checks; does not establish training semantics |
| Climate file | `database/data/Ambulong Monthly Data.csv` | Station data requiring approved applicability and freshness; not automatically Lian observations |
| MAO/demo files | Seeders and project summary/template files | Demo/derived or unverified unless independently authenticated; excluded from scientific evidence |
| Research intake templates | Phase 11 manifest and snapshot templates; Phase 19 forms | Collection instruments only; not evidence |

## 2. Missing or unaccepted evidence

| Requirement | Exact missing material | Current status | Provider/action |
|---|---|---|---|
| Original model identity | Original artifact provenance, developer, checksum, version history | Blocked | Original model developer |
| Training semantics | Training dataset, preprocessing scripts/notebook, feature dictionary, units, missing rules, split | Blocked | Original model developer/researcher |
| Target definition | Approved farm/barangay/municipal target and yield unit | Blocked | MAO Head + researchers |
| MAO observations | Signed/exported production records with stable IDs and correction history | Blocked | MAO data custodian |
| Planting/harvest alignment | Verified dates, season labels, area, crop, and source documents | Blocked | MAO data custodian |
| Climate evidence | Fresh historical observations, source metadata, coverage, units, Lian applicability | Blocked | MAO/researcher/climate provider |
| Independent evaluation | Approved observations not used in training/tuning/feature construction, with duplicate/leakage review | Blocked | Researchers + adviser |
| Heat Map method | Approved layer definitions, weights, thresholds, aggregation, freshness, no-data behavior | Pending/blocked | MAO Head + adviser |
| Operational acceptance | Signed security, privacy, SLO, backup, RPO/RTO, browser/accessibility decisions | Pending | IT Expert |

## 3. Matrix update decision

The Phase 18/19 Requirements Validation Matrix is not advanced to “Accepted” for any item. Existing technical statuses remain supported; no new external evidence was found. In particular:

- ML-02 through ML-05 remain **BLOCKED**.
- Season and Heat Map requirements remain **PENDING/BLOCKED**.
- Security and operational policy requirements remain **PENDING**.
- **ACCURACY NOT VERIFIED** remains mandatory.

Repository files with names suggesting evidence, such as templates, seeders, demo rows, candidate datasets, or prior reports, were not treated as authentic stakeholder approval or independent research evidence.
