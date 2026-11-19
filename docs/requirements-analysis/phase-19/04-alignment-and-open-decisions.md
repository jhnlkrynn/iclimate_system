# Cross-Document Alignment and Open Decisions

## Corrections applied

1. The canonical administrative actor is **IT Expert**, matching `User::ROLE_IT_EXPERT`. “Admin” is retained only where it is a route/configuration namespace or a quoted legacy manuscript term.
2. **MAO Head / Municipal Agriculturist** is documented as MAO Personnel with `is_mao_head = true`, not as a fourth stored role.
3. System flows, the access matrix, diagrams, IPO model, and functional requirements now use the canonical IT Expert label in the corrected entries.
4. FR-RPT-06 now states that IT Expert report access is not available because the implementation redirects `reports.*` to the IT dashboard.
5. Automated workers and external services are distinguished from human roles.

## Open decisions

| Topic | Current implementation/documentation position | Decision required |
|---|---|---|
| Seasons | Repository contains differing season conventions | MAO-approved calendar, cross-year grouping, matching window |
| Advisory thresholds | Rule paths and configuration require domain confirmation | MAO Head approval and source/rationale |
| Prediction scope | Farm, barangay, and municipal interpretations are not interchangeable | Approved target geography and yield unit |
| Heat Map methodology | Layers exist, but evidence, weights, freshness, aggregation, and no-data rules require approval | MAO/research methodology record |
| Model features | Runtime schema exists; original semantic definitions are missing | Original developer evidence for all 12 inputs |
| Historical climate | Ambulong station data is not automatically Lian-specific | Researcher/MAO applicability decision |
| Security/privacy | Technical controls exist; retention and policy acceptance are incomplete | IT Expert policy sign-off |
| Operations | Health and recovery tooling exists; SLO/RPO/RTO and browser support are not finalized | IT Expert operational acceptance |
| Final scope | Some features are hidden or retired | Product owner/adviser scope decision |

## Consistency checklist

- [x] Stored roles are Farmer, MAO Personnel, and IT Expert.
- [x] MAO Head is an MAO attribute, not a role enum.
- [x] Actor labels distinguish users from scheduler, queue worker, providers, and Python ML.
- [x] Workflows use Actor → Input → Validation → Process → Database → Output.
- [x] DFDs and use cases represent IT administration without inventing an Admin role.
- [x] IPO outputs distinguish estimates from verified observations.
- [x] Traceability retains implementation and evidence status separately.
- [x] ML accuracy remains **ACCURACY NOT VERIFIED**.
- [ ] Stakeholder approvals recorded.
- [ ] Original model artifacts received and accepted.
- [ ] Independent evaluation evidence accepted.
- [ ] Heat Map methodology approved.

## Thesis-readiness recommendation

The requirements chapter is ready for adviser review as an implementation-grounded draft. It becomes final only after the response records and evidence artifacts in the Phase 19 templates are completed. Scientific claims require a separate approved evaluation record and must not be back-filled from demonstrations, seeded data, or operational estimates.
