# Requirements Validation Matrix

**Status meanings:** Implemented = code path exists; Technically verified = supported by tests or recorded runtime evidence; Implemented—scientifically unverified = operational path exists but scientific correctness is not established; Incomplete = partial/conflicting implementation; Pending stakeholder approval = decision or acceptance criterion is missing; Blocked = required evidence or dependency is absent.

| ID | Requirement | Repository evidence | Current status | Stakeholder decision/evidence needed |
|---|---|---|---|---|
| AUTH-01 | Authenticate active users | `routes/auth.php`, account-status middleware | Technically verified | IT confirms lifecycle policy |
| AUTH-02 | Enforce Farmer/MAO/IT access boundaries | `User`, role middleware, redirect middleware, policies | Technically verified | IT/MAO approve role policy |
| AUTH-03 | Scope MAO data by barangay and Head coverage | `MaoScope`, `is_mao_head`, routes/channels | Implemented | MAO Head confirms coverage rules |
| FARM-01 | Farmer manages own farm and planting records | Farmer controllers, ownership queries, tests | Technically verified | Farmers confirm required fields |
| FARM-02 | Record actual harvest and production | planting/production models and routes | Implemented—scientifically unverified | MAO validates source and correction policy |
| WX-01 | Acquire and display climate/weather data | scheduled commands, climate/weather services | Technically verified | MAO approves sources and freshness |
| WX-02 | Match climate to crop season | `RiceSeason`, climate/prediction services | Incomplete | MAO approves season calendar/windows |
| ADV-01 | Generate/review/publish advisories | advisory services, MAO routes, notifications | Implemented | MAO approves thresholds and wording |
| RISK-01 | Display barangay risk levels | risk services and Heat Map | Implemented—scientifically unverified | MAO approves thresholds/weights |
| RISK-02 | Publish yield Heat Map layer | `HeatMapLayerService`, provenance support | Implemented—scientifically unverified | MAO approves target, source, minimum evidence |
| RPT-01 | Generate authorized reports | report routes/controllers/exports | Implemented | MAO/IT confirm report scope |
| COM-01 | Deliver notifications/messages | notifications, channels, Reverb fallback | Technically verified | IT confirms retention and availability |
| ADM-01 | Manage accounts/settings/backups/audit | IT routes/services | Implemented | IT approves operational controls |
| OPS-01 | Run queue/scheduler/realtime integrations | commands, jobs, Reverb channels | Technically verified | IT approves SLO and recovery |
| ML-01 | Validate deployed model input schema | `PredictionEngine`, Python `/predict`, readiness gates | Technically verified | Model developer confirms definitions |
| ML-02 | Reconstruct original 12 training features | production builder exists; original definitions absent | Blocked | Original model developer supplies dictionary/scripts |
| ML-03 | Evaluate deployed model independently | evaluation/readiness tooling exists | Blocked | Researchers approve protocol and data |
| ML-04 | Report scientifically valid accuracy | readiness keeps `ACCURACY NOT VERIFIED` | Blocked | Independent evidence and approval |
| ML-05 | Validate climate freshness/coverage | readiness gate exists | Blocked | MAO/researchers supply approved history |
| DATA-01 | Accept only provenance-validated research data | intake/readiness validators | Implemented | MAO approves source and evidence policy |
| DATA-02 | Reject duplicates/demo/derived evaluation rows | validators and provenance classes | Implemented | Researchers approve exclusion rules |
| DATA-03 | Prevent chronological/season leakage | evaluation gates | Implemented | Researchers approve grouping/minimums |
| NFR-01 | Protect data and enforce least privilege | middleware, policies, private channels, headers | Implemented | IT security review |
| NFR-02 | Support recovery and continuity | backup/health services | Incomplete | IT defines off-server backup, RPO/RTO, restore test |
| NFR-03 | Provide measurable performance/reliability | no accepted load/SLO baseline found | Pending stakeholder approval | IT supplies targets and test plan |
| NFR-04 | Provide usability/accessibility acceptance | UI exists; no stakeholder/usability evidence | Pending stakeholder approval | Farmer/MAO questionnaire and accessibility review |
| NFR-05 | Maintainability and auditability | services, logs, tests, model metadata | Implemented | IT confirms retention and change control |

## Matrix conclusion

Technical implementation is strongest for access control, operational workflows, provenance-aware intake, and prediction safeguards. Scientific requirements ML-02 through ML-05 and Heat Map validation remain blocked or unverified. The matrix must be updated from signed stakeholder responses and source evidence, not from assumptions or demo records.
