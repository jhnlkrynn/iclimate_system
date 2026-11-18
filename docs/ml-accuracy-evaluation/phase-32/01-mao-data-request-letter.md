# Request for Anonymized Historical Rice Production Records

**Date:** [YYYY-MM-DD]  
**To:** [Municipal Agriculturist / Records Custodian]  
**Office:** Municipal Agriculture Office, Municipality of Lian, Batangas  
**Subject:** Request for anonymized, source-certified rice production records for academic model validation

Dear [Name/Title],

I am [requester name], [program/institution], working under [adviser name and
contact] on the iClimate rice-yield research project. We respectfully request
an anonymized historical extract for rice production in the Municipality of
Lian, Batangas. The requested records will be used only to define and evaluate
an experimental research model, subject to MAO and adviser approval.

Please provide all available records within the approved period, preferably
[start year]–[end year], or state the actual available coverage. We request one
row per approved independent farm-season, barangay-season, or other documented
observation unit, with the unit explicitly identified. Please do not send names,
addresses, telephone numbers, RSBSA numbers, or other direct personal
identifiers.

## Requested fields

Please use the attached [MAO CSV template](../phase-23/mao-yield-evaluation-template.csv),
or provide an equivalent file with a field mapping. Required values are:

- pseudonymous `observation_id` and, where permitted, `farm_group_id`;
- barangay code or approved geographic identifier and the geographic level;
- planting date, harvest start/end date, observation/record date, and MAO-defined
  season code/label;
- crop, variety if recorded, and ecosystem/irrigation classification if used;
- area planted and area harvested in hectares;
- actual production in metric tonnes, with the exact production definition;
- official actual yield in metric tonnes per hectare, or permission to derive it
  as production divided by valid harvested area;
- source record reference, correction/revision status, and notes on estimates,
  allocations, aggregation, or missing values.

Please identify whether each value is a direct observation, an official
aggregate, an estimate, or a derived value. Do not include seeded/demo rows,
model predictions, replicated copies, or records reconstructed from another
prediction. If production or area is unavailable or invalid, leave yield blank
and explain why; do not fill it with an estimate.

## Certification requested

Along with the data, please provide or confirm:

1. source custodian and office;
2. extraction date, original file/document format, and any revision history;
3. source-document references and a SHA-256 checksum for the delivered file;
4. written confirmation that records are anonymized and may be used for this
   academic research purpose;
5. the meaning and units of every field, including season definitions;
6. the geographic scope and whether records are farm-, barangay-, or
   municipality-level;
7. a statement identifying excluded demo, duplicate, allocated, derived, and
   corrected records;
8. the MAO contact/approval reference that may be cited in the evidence
   manifest.

The file may be delivered through [approved secure channel]. It will first be
stored in an isolated intake directory and will not be imported into the active
iClimate database. Evaluation will not begin until provenance, permission,
target definition, feature compatibility, and an independent leakage-safe
protocol are approved by the adviser/research reviewer.

If a requested field is unavailable, please mark it unavailable rather than
substituting a proxy. In particular, Lian records must remain distinguishable
from PSA provincial aggregates and Ambulong/Tanauan climate observations.

Thank you. Please contact [requester/contact] for the field mapping and secure
submission instructions.

Respectfully,

[Requester name]  
[Program / institution]  
[Adviser name and approval/contact]  
[Research approval or ethics reference, if applicable]
