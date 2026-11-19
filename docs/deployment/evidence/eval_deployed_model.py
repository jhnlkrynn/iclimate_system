"""Indicative evaluation of the DEPLOYED rice-yield model against real MAO barangay-season records.

Observations : historical_rice_productions (40 rows, source "RICE-PRODUCTION (LIAN).xlsx"), read from a CSV export.
Weather      : storage/datasets/Ambulong_Monthly_Data.csv (PAGASA Ambulong monthly, 2015-01 .. 2025-12).
Model        : storage/models/rice_yield_model_final.pkl (sha256 f3a54e12...), loaded directly; NOT retrained.

ASSUMPTIONS (the original training script is missing, so feature definitions are inferred from the tree thresholds):
  - Season window: WS = May..Oct of season_year; DS = Nov of season_year .. Apr of season_year+1.
  - The model is run for every month of the window and the season estimate is the mean of those monthly predictions.
  - RAINFALL = month total; TEMP_AVG = (TMAX+TMIN)/2; TEMP_RANGE = TMAX-TMIN; Area = area_planted_hectares;
    Previous_* = previous month; Rainfall_6M = MEAN of the last 6 months (thresholds 54-311 imply a mean);
    Temp_3M / Temp_6M = mean TEMP_AVG of the last 3 / 6 months; Seasonal_* = mean over the season months so far.
  - Rows whose window needs weather after 2025-12 are excluded (no data), not filled in.
Independence is NOT proven: the deployed model's training rows are unknown.
"""
import csv, math, sys, warnings
from statistics import mean
import joblib, numpy as np
warnings.filterwarnings("ignore")
sys.path.insert(0, sys.argv[1] + "/python_scripts")
import rice_yield_service as rys

root, obs_csv = sys.argv[1], sys.argv[2]
model = joblib.load(root + "/storage/models/rice_yield_model_final.pkl")
wx = {(int(r["YEAR"]), int(r["MONTH"])): {k: float(r[k]) for k in ("RAINFALL", "TMAX", "TMIN")}
      for r in csv.DictReader(open(root + "/storage/datasets/Ambulong_Monthly_Data.csv"))}

def back(y, m, n):
    out = []
    for _ in range(n):
        out.append((y, m)); m -= 1
        if m == 0: y, m = y - 1, 12
    return out

def tavg(k): return (wx[k]["TMAX"] + wx[k]["TMIN"]) / 2

def window(season, year):
    months = [(year, m) for m in range(5, 11)] if season == "WS" else [(year, 11), (year, 12)] + [(year + 1, m) for m in range(1, 5)]
    return months

rows = list(csv.DictReader(open(obs_csv)))
used, preds, actual, skipped = [], [], [], 0
incomplete = 0
for r in rows:
    if not r["area_planted_hectares"].strip() or not r["average_yield_t_per_hectare"].strip():
        incomplete += 1; continue
    months = window(r["season"].strip().upper(), int(r["season_year"]))
    needed = set(months) | {k for mth in months for k in back(*mth, 7)}
    if not needed <= wx.keys():
        skipped += 1; continue
    season = "Wet" if r["season"].strip().upper() == "WS" else "Dry"
    monthly = []
    for i, mth in enumerate(months):
        last6, last3 = back(*mth, 6), back(*mth, 3)
        prev = back(*mth, 2)[1]
        so_far = months[: i + 1]
        f = {"rainfall": wx[mth]["RAINFALL"], "temp_avg": tavg(mth), "temp_range": wx[mth]["TMAX"] - wx[mth]["TMIN"],
             "area": float(r["area_planted_hectares"]), "previous_rainfall": wx[prev]["RAINFALL"], "previous_temp": tavg(prev),
             "rainfall_6m": mean(wx[k]["RAINFALL"] for k in last6), "temp_3m": mean(tavg(k) for k in last3),
             "temp_6m": mean(tavg(k) for k in last6), "seasonal_rainfall": mean(wx[k]["RAINFALL"] for k in so_far),
             "seasonal_temp": mean(tavg(k) for k in so_far), "season": season}
        monthly.append(float(model.predict(rys.rice_features(f))[0]))
    preds.append(mean(monthly)); actual.append(float(r["average_yield_t_per_hectare"])); used.append(r)

p, a = np.array(preds), np.array(actual)
err = p - a
mae, rmse = float(np.mean(np.abs(err))), float(math.sqrt(np.mean(err ** 2)))
r2 = float(1 - np.sum(err ** 2) / np.sum((a - a.mean()) ** 2))
loo = np.array([(a.sum() - a[i]) / (len(a) - 1) for i in range(len(a))])          # fair "predict the average" baseline
b_mae, b_rmse = float(np.mean(np.abs(loo - a))), float(math.sqrt(np.mean((loo - a) ** 2)))
corr = float(np.corrcoef(p, a)[0, 1]) if p.std() > 0 else float("nan")
print(f"rows evaluated: {len(a)} of {len(rows)} (excluded: {incomplete} with no area/yield recorded, {skipped} needing weather after 2025-12)")
print(f"observed yield : mean {a.mean():.2f}, std {a.std():.2f}, range {a.min():.2f}-{a.max():.2f} t/ha")
print(f"model estimate : mean {p.mean():.2f}, std {p.std():.3f}, range {p.min():.2f}-{p.max():.2f} t/ha")
print(f"deployed model : MAE {mae:.3f}  RMSE {rmse:.3f}  R2 {r2:.3f}  Pearson r {corr:.3f}")
print(f"baseline (mean): MAE {b_mae:.3f}  RMSE {b_rmse:.3f}  (leave-one-out average of the observations)")
print(f"model vs baseline MAE: {'better' if mae < b_mae else 'worse'} by {abs(mae - b_mae):.3f} t/ha")
