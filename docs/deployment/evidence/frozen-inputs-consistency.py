"""Direct model.predict vs FastAPI /predict vs Laravel (run JSON) for every distinct model input of a run."""
import hashlib, json, sys, urllib.request
sys.path.insert(0, sys.argv[2])
import joblib, rice_yield_service as rys
rys.install_sklearn_compatibility_shims()
MODEL = sys.argv[3]
print('model sha256', hashlib.sha256(open(MODEL, 'rb').read()).hexdigest())
model = joblib.load(MODEL)
runs = json.load(open(sys.argv[1]))
seen, rows = set(), []
for r in runs:
    mi = r.get('model_input')
    if not mi or r.get('raw') is None: continue
    k = json.dumps(mi, sort_keys=True)
    if k in seen: continue
    seen.add(k); rows.append((r, mi))
mism = 0
for r, mi in rows:
    direct = float(model.predict(rys.rice_features(mi))[0])
    req = urllib.request.Request('http://127.0.0.1:8010/predict', data=json.dumps({'prediction_type': 'rice_yield', 'features': mi}).encode(), headers={'Content-Type': 'application/json'})
    api = json.load(urllib.request.urlopen(req, timeout=30))['rice_yield_prediction']['predicted_yield']
    lar = float(r['raw'])
    if not (round(direct, 2) == round(float(api), 2) == round(lar, 2)): mism += 1; print('MISMATCH', r['target'], direct, api, lar)
print(f'{len(rows)} distinct inputs, {mism} mismatches; example', rows[0][0]['target'], round(float(model.predict(rys.rice_features(rows[0][1]))[0]), 6))
