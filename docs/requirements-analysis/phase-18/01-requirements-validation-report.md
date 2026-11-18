# Requirements Validation Report

## 1. Purpose and method

This report validates the completeness and internal consistency of the Phase 17 requirements against the implemented repository. The review used the Phase 17 documents, Laravel routes/controllers/services/models, Python service, migrations, automated tests, and the recorded ML-readiness results. It is a requirements-validation report, not a user-acceptance result: stakeholder approval fields remain open.

Evidence categories are:

- **Implemented:** a corresponding code path exists.
- **Technically verified:** implementation evidence is supported by tests or recorded runtime verification.
- **Implemented—scientifically unverified:** the function runs, but its agricultural or predictive validity is not established.
- **Incomplete:** implementation exists only partially, conflicts internally, or does not meet the stated requirement.
- **Pending stakeholder approval:** technical behavior exists or can be configured, but the acceptance rule, policy, or domain meaning is not approved.
- **Blocked:** an evidence or dependency gate prevents valid completion.

## 2. Completeness finding

Phase 17 is broad enough to describe the implemented product, but requirements are not yet final because several acceptance criteria are implicit. The following must be made explicit by stakeholders before manuscript or operational sign-off:

1. Whether the prediction target is farm-level, barangay-level, municipal, or another aggregation.
2. Whether “yield” means actual harvested production, yield per hectare, or an approved proxy.
3. The authoritative definition, unit, period, and preprocessing of every model feature.
4. The approved crop-weather matching window and season calendar.
5. Independent evaluation design, minimum sample size, grouping rule, baselines, and acceptance thresholds.
6. The source, freshness, weighting, and publication rule for each Heat Map layer.
7. Privacy, retention, backup recovery, availability, accessibility, browser, and support requirements.
8. Which hidden, retired, or provisional functions are in final scope.

## 3. Technically supported requirements

The repository supports the following as implemented behavior: session authentication and account status checks; Farmer, MAO Personnel, and IT Expert role middleware; MAO Head coverage through the `is_mao_head` attribute; Farmer ownership scoping; MAO barangay scoping; IT administration routes; weather and forecast acquisition; advisories; reports; notifications and messaging; queue/scheduler integration; Reverb channel authorization; provenance classes; prediction request validation; explicit prediction-unavailable handling; and unvalidated-estimate labels.

The prediction path is consistently represented as Laravel request validation → `PredictionEngine` / orchestration → `PythonService` or supported local fallback → model inference → stored prediction/result presentation. The Python service exposes `/health`, `/model-info`, and `/predict`. The deployed model remains unchanged.

## 4. Requirements not yet acceptable as validated claims

The rice-yield model is operationally callable but not scientifically validated. The original training dataset and preprocessing scripts are absent; `Area`, `Rainfall_6M`, and `TEMP_RANGE` definitions remain incompatible or unproven; climate history is stale or insufficient for the approved evaluation; and independent verified observations are unavailable. The readiness record therefore correctly retains `ACCURACY NOT VERIFIED`.

The same distinction applies to monthly climate forecasting, composite risk scoring, weekly risk thresholds, and yield-related Heat Map layers. These may be displayed as system outputs or provisional decision-support outputs only with their limitations visible; they must not be described as validated accuracy results.

## 5. ML and Heat Map evidence required

Before a scientific acceptance decision, obtain:

- original model artifact provenance and checksum;
- the complete training dataset, feature dictionary, units, transformations, missing-value rules, target definition, and split methodology;
- training and preprocessing code or an independently reproducible equivalent;
- verified MAO production and farm records with source documents, planting/harvest dates, location scope, and crop/season identifiers;
- historical climate observations with station/source, timestamps, units, coverage, and a documented Lian-versus-Ambulong relationship;
- independent observations not used for fitting, tuning, feature construction, or duplicated replication;
- an approved chronological or season-grouped evaluation protocol with mean and seasonal baselines;
- Heat Map source lineage, freshness rules, risk weights/thresholds, missing-data behavior, spatial aggregation, and MAO approval.

Required reported metrics, only after the evidence gate passes, are MAE, RMSE, R², bias, sample count, group count, baseline results, provenance, and limitations. No metric is supplied by this report.

## 6. Final validation position

The implementation is suitable for stakeholder validation and controlled demonstration of system behavior. It is not suitable for a claim that the deployed rice-yield model is accurate. Requirements concerning scientific validity, Heat Map methodology, data authority, and operational acceptance remain pending or blocked. The questionnaires and matrix in this phase are the instruments for closing those decisions.

## 7. Recommended approval sequence

1. MAO Head approves target scope, authoritative records, seasons, weather source, risk thresholds, and Heat Map publication rules.
2. MAO Personnel validate data provenance, dates, units, duplicates, and barangay/farm associations.
3. Original model developer supplies reproducible training evidence and resolves the 12-feature compatibility questions.
4. IT Expert approves security, access, backup, monitoring, recovery, and activation gates.
5. Researchers approve the independent evaluation protocol and minimum evidence.
6. Only after all gates pass should a separate scientific evaluation be run; deployment behavior and model weights must remain unchanged until an approved correction decision exists.
