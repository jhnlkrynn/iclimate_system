"""EXPLORATORY: deployed model on app-built features (eval-features*.json) vs MAO observed barangay-season yields."""
import json, math, sys, warnings
from statistics import mean
warnings.filterwarnings("ignore")
sys.path.insert(0, sys.argv[2]); import joblib, numpy as np, rice_yield_service as rys
rys.install_sklearn_compatibility_shims()
model = joblib.load(sys.argv[3]); d = json.load(open(sys.argv[1])); recs = d['records']
obs, pred = [], []
for r in recs:
    p = [float(model.predict(rys.rice_features(m))[0]) for m in r['months']]
    obs.append(r['observed']); pred.append(mean(p)); r['predicted'] = round(mean(p), 3)
o, p = np.array(obs), np.array(pred); n = len(o)
mae = float(np.mean(abs(o - p))); rmse = float(np.sqrt(np.mean((o - p) ** 2)))
r2 = 1 - float(np.sum((o - p) ** 2) / np.sum((o - o.mean()) ** 2)); r = float(np.corrcoef(o, p)[0, 1])
base = float(np.mean(abs(o - o.mean()))); loo = float(np.mean([abs(o[i] - np.delete(o, i).mean()) for i in range(n)]))
print(json.dumps({'n': n, 'skipped': d['skipped'], 'MAE': round(mae, 3), 'RMSE': round(rmse, 3), 'R2': round(r2, 3), 'pearson_r': round(r, 3),
  'baseline_mean_MAE': round(base, 3), 'baseline_loo_MAE': round(loo, 3), 'pred_range': [round(p.min(), 2), round(p.max(), 2)], 'obs_range': [o.min(), o.max()],
  'by_season': {s: len([x for x in recs if x['season'] == s]) for s in {x['season'] for x in recs}}, 'years': sorted({x['year'] for x in recs})}))
json.dump(recs, open(sys.argv[1].replace('.json', '-scored.json'), 'w'))
