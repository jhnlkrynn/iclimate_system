@php
    $rows = collect($evaluation['rows'] ?? []);
    $best = $evaluation['best_algorithm'] ?? 'Random Forest';
    $source = $evaluation['source'] ?? [];
@endphp

<x-app-layout>
    <style>
        .me-hero {
            border-radius: 8px;
            padding: 1.35rem;
            margin-bottom: 1rem;
            color: #1f2a24;
            background: linear-gradient(135deg, #f6f9f7 0%, #eef4f0 60%, #e7f0ea 100%);
            border: 1px solid #e3ece6;
            box-shadow: 0 1rem 2.2rem rgba(31,42,36,.08);
        }
        .me-hero .text-white-50 { color: #4a5c52 !important; }
        .me-panel {
            border: 1px solid #d4edda;
            border-radius: 8px;
            background: rgba(255,255,255,.96);
            box-shadow: 0 .8rem 1.8rem rgba(13,31,24,.07);
            overflow: hidden;
        }
        .me-panel-header {
            padding: 1rem;
            border-bottom: 1px solid #d4edda;
            background: #f7fbf8;
        }
        .me-panel-body { padding: 1rem; }
        .me-eyebrow {
            color: #74c69d;
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .me-stat {
            border: 1px solid #d4edda;
            border-radius: 8px;
            padding: .9rem;
            background: #fff;
            min-height: 110px;
        }
        .me-label {
            color: #5a7a64;
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .me-value {
            color: #0d1f18;
            font-size: 1.65rem;
            font-weight: 900;
            line-height: 1.1;
            margin-top: .35rem;
        }
        .me-table th {
            color: #446553;
            font-size: .72rem;
            letter-spacing: .07em;
            text-transform: uppercase;
        }
        .me-model-row { cursor: pointer; }
        .me-model-row:hover { background: #f7fbf8; }
        .me-detail-row { display: none; }
        .me-detail-row.open { display: table-row; }
        .me-detail-card {
            border: 1px solid #d4edda;
            border-left: 5px solid #52b788;
            border-radius: 8px;
            padding: 1rem;
            background: #f7fbf8;
        }
        .me-detail-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .85rem;
        }
        .me-comparison-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .85rem;
            margin-top: .85rem;
        }
        .me-detail-block {
            border: 1px solid #d4edda;
            border-radius: 8px;
            background: #fff;
            padding: .85rem;
        }
        .me-details-btn {
            border: 1px solid #95d5b2;
            border-radius: 999px;
            background: #fff;
            color: #1f6f4a;
            padding: .28rem .62rem;
            font-size: .76rem;
            font-weight: 900;
        }
        .me-compare-top {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .me-compare-count { width: min(220px, 100%); }
        .me-compare-card {
            border: 1px solid #d4edda;
            border-radius: 8px;
            background: #fff;
            padding: 1rem;
            min-height: 100%;
        }
        .me-model-brand {
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .me-model-logo {
            width: 64px;
            height: 64px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: #1a3a2a;
            background: #f7fbf8;
            border: 1px solid #d4edda;
            box-shadow: 0 .45rem .9rem rgba(13,31,24,.1);
            flex: 0 0 auto;
        }
        .me-model-logo svg {
            width: 48px;
            height: 48px;
        }
        .me-model-logo.linear { background: linear-gradient(180deg, #f8fcff, #e8f4fa); }
        .me-model-logo.forest { background: linear-gradient(180deg, #f4fff7, #e2f3e8); }
        .me-model-logo.boosting { background: linear-gradient(180deg, #fffaf0, #fbebcf); }
        .me-compare-select {
            border: 1px solid #cfe5d6;
            border-radius: 8px;
            padding: 1rem;
            background: #fff;
            min-height: 90px;
        }
        .me-compare-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .5rem;
            margin-top: .8rem;
        }
        .me-compare-metric {
            border-radius: 8px;
            background: #f7fbf8;
            padding: .65rem;
        }
        .me-plain-note {
            border: 1px solid #d4edda;
            border-radius: 8px;
            background: #fff;
            padding: .85rem;
            margin-top: .85rem;
            color: #315543;
        }
        .me-selected {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: .32rem .62rem;
            color: #1f6f4a;
            background: #d8f3dc;
            font-size: .76rem;
            font-weight: 900;
        }
        .me-compared {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: .32rem .62rem;
            color: #6b5b17;
            background: #fff4cf;
            font-size: .76rem;
            font-weight: 900;
        }
        .me-feature {
            display: inline-flex;
            border-radius: 999px;
            padding: .36rem .65rem;
            margin: .16rem;
            background: #edf7f1;
            color: #2d6a4f;
            font-size: .78rem;
            font-weight: 800;
        }
        @media (max-width: 991.98px) {
            .me-detail-grid, .me-comparison-grid { grid-template-columns: 1fr; }
        }
    </style>

    <section class="me-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="me-eyebrow mb-2">Model Evaluation Report</div>
                <h1 class="h2 fw-bold mb-2">Rice Yield Algorithm Comparison</h1>
        <p class="mb-0" style="max-width: 820px; color: #4a5c52;">
                    This page supports the manuscript requirement using the trained model selection results from the iClimate ML notebook.
                </p>
            </div>
            <span class="badge text-bg-light border px-3 py-2">Selected: {{ $best }}</span>
        </div>
    </section>

    @unless($evaluation['ready'] ?? false)
        <div class="alert alert-warning">
            <strong>Evaluation needs more data.</strong>
            {{ $evaluation['message'] ?? 'Add climate and rice production records to compute model metrics.' }}
        </div>
    @endunless

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="me-stat">
                <div class="me-label">Matched Samples</div>
                <div class="me-value">{{ $evaluation['sample_count'] ?? 0 }}</div>
                <div class="small text-muted mt-2">Local app records currently available for verification.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="me-stat">
                <div class="me-label">Metric Source</div>
                <div class="me-value" style="font-size: 1.15rem;">Training Notebook</div>
                <div class="small text-muted mt-2">{{ $source['name'] ?? 'iClimate_ML_Model_Training.ipynb' }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="me-stat">
                <div class="me-label">Deployed Model</div>
                <div class="me-value" style="font-size: 1.15rem;">Random Forest</div>
                <div class="small text-muted mt-2">Saved as <code>rice_yield_model_final.pkl</code>.</div>
            </div>
        </div>
    </div>

    <div class="me-panel mb-4">
        <div class="me-panel-header">
            <h2 class="h5 fw-bold mb-1">Algorithm Metrics</h2>
            <div class="small text-muted">{{ $evaluation['message'] ?? 'Model evaluation summary.' }}</div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 me-table">
                <thead>
                    <tr>
                        <th>Algorithm</th>
                        <th class="text-end">RMSE</th>
                        <th class="text-end">MAE</th>
                        <th class="text-end">R²</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $index => $row)
                        <tr class="me-model-row" data-model-row="{{ $index }}" tabindex="0" aria-expanded="false" aria-controls="modelDetails{{ $index }}">
                            <td class="fw-bold">{{ $row['algorithm'] }}</td>
                            <td class="text-end">{{ is_null($row['rmse']) ? 'N/A' : number_format((float) $row['rmse'], 6) }}</td>
                            <td class="text-end">{{ is_null($row['mae']) ? 'N/A' : number_format((float) $row['mae'], 6) }}</td>
                            <td class="text-end">{{ is_null($row['r2']) ? 'N/A' : number_format((float) $row['r2'], 6) }}</td>
                            <td>
                                <span class="{{ $row['status'] === 'Selected Model' ? 'me-selected' : 'me-compared' }}">
                                    {{ $row['status'] }}
                                </span>
                                <button class="me-details-btn ms-2" type="button" data-details-button="{{ $index }}">Details</button>
                            </td>
                        </tr>
                        <tr id="modelDetails{{ $index }}" class="me-detail-row" data-model-details="{{ $index }}">
                            <td colspan="5">
                                <div class="me-detail-card">
                                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
                                        <div>
                                            <div class="me-label">Model Details</div>
                                            <div class="h5 fw-bold mb-0">{{ $row['algorithm'] }}</div>
                                        </div>
                                        <div class="small text-muted">Metric source: trained model selection notebook</div>
                                    </div>
                                    <div class="me-detail-grid">
                                        <div class="me-detail-block">
                                            <div class="me-label mb-2">What It Is</div>
                                            <div>{{ $row['details']['summary'] ?? 'No model details available.' }}</div>
                                        </div>
                                        <div class="me-detail-block">
                                            <div class="me-label mb-2">Metric Meaning</div>
                                            <div>{{ $row['details']['interpretation'] ?? 'No interpretation available.' }}</div>
                                        </div>
                                        <div class="me-detail-block">
                                            <div class="me-label mb-2">Defense Note</div>
                                            <div>{{ $row['details']['reason'] ?? 'No defense note available.' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="me-panel mb-4">
        <div class="me-panel-header">
            <h2 class="h5 fw-bold mb-1">Side-by-Side Model Comparison</h2>
            <div class="small text-muted">Choose two or three different models. Already selected models are removed from the other dropdowns.</div>
        </div>
        <div class="me-panel-body">
            <div class="me-plain-note mb-3">
                <strong>Simple guide:</strong> Lower RMSE and MAE means fewer prediction mistakes. Higher R² means the model understands more of the rice-yield pattern. The best model is the one with the smallest errors and strongest pattern score.
            </div>
            <div class="me-compare-top">
                <div class="me-compare-count">
                    <label class="me-label mb-2 d-block" for="comparisonCount">Compare</label>
                    <select id="comparisonCount" class="form-select">
                        <option value="2">Two models</option>
                        <option value="3" selected>Three models</option>
                    </select>
                </div>
            </div>
            <div id="comparisonSelectors" class="me-comparison-grid mb-3">
                @for($i = 0; $i < 3; $i++)
                    <div class="model-select-wrap me-compare-select" data-select-wrap="{{ $i }}">
                        <label class="me-label mb-2 d-block" for="modelSelect{{ $i }}">Model {{ $i + 1 }}</label>
                        <select id="modelSelect{{ $i }}" class="form-select" data-model-select="{{ $i }}">
                            @foreach($rows as $row)
                                <option value="{{ $row['algorithm'] }}" @selected($loop->index === $i)>{{ $row['algorithm'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endfor
            </div>
            <div id="comparisonOutput" class="me-comparison-grid"></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="me-panel h-100">
                <div class="me-panel-header">
                    <h2 class="h5 fw-bold mb-1">Evaluation Method</h2>
                    <div class="small text-muted">Defense-ready summary for the manuscript objective.</div>
                </div>
                <div class="me-panel-body">
                    <p class="mb-2">
                        The displayed metrics are from the trained model selection notebook, where Multiple Linear Regression, Random Forest, and Gradient Boosting were compared using MAE, RMSE, and R². Random Forest had the strongest result and is the selected deployed model.
                    </p>
                    <p class="mb-0 text-muted">
                        Lower RMSE and MAE indicate smaller prediction error. Higher R² indicates better explanation of yield variation. The deployed system uses the saved trained model artifact for rice yield prediction.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="me-panel h-100">
                <div class="me-panel-header">
                    <h2 class="h5 fw-bold mb-1">Input Features</h2>
                    <div class="small text-muted">Variables used for the comparison.</div>
                </div>
                <div class="me-panel-body">
                    @foreach(($evaluation['features'] ?? []) as $feature)
                        <span class="me-feature">{{ $feature }}</span>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="me-panel">
                <div class="me-panel-header">
                    <h2 class="h5 fw-bold mb-1">Source Evidence</h2>
                    <div class="small text-muted">Where the displayed model comparison values come from.</div>
                </div>
                <div class="me-panel-body">
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div class="me-label">Notebook</div>
                            <div class="fw-bold">{{ $source['name'] ?? 'iClimate_ML_Model_Training.ipynb' }}</div>
                            <div class="small text-muted">{{ $source['type'] ?? 'Trained model selection notebook' }}</div>
                        </div>
                        <div class="col-lg-4">
                            <div class="me-label">Dataset Sources</div>
                            <div class="small">
                                {{ implode(', ', $source['dataset_files'] ?? []) }}
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="me-label">Saved Model Artifact</div>
                            <div class="small"><code>{{ $source['deployed_model'] ?? '' }}</code></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modelRows = @json($rows->values());
            const closeOthers = (current) => {
                document.querySelectorAll('[data-model-details]').forEach((row) => {
                    if (row.dataset.modelDetails !== current) row.classList.remove('open');
                });
                document.querySelectorAll('[data-model-row]').forEach((row) => {
                    if (row.dataset.modelRow !== current) row.setAttribute('aria-expanded', 'false');
                });
            };

            const toggleDetails = (id) => {
                const detail = document.querySelector(`[data-model-details="${id}"]`);
                const row = document.querySelector(`[data-model-row="${id}"]`);
                if (!detail || !row) return;
                const shouldOpen = !detail.classList.contains('open');
                closeOthers(id);
                detail.classList.toggle('open', shouldOpen);
                row.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            };

            document.querySelectorAll('[data-model-row]').forEach((row) => {
                row.addEventListener('click', () => toggleDetails(row.dataset.modelRow));
                row.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        toggleDetails(row.dataset.modelRow);
                    }
                });
            });

            document.querySelectorAll('[data-details-button]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    toggleDetails(button.dataset.detailsButton);
                });
            });

            const comparisonCount = document.getElementById('comparisonCount');
            const comparisonOutput = document.getElementById('comparisonOutput');
            const comparisonSelectors = document.getElementById('comparisonSelectors');
            const selects = [...document.querySelectorAll('[data-model-select]')];
            const wraps = [...document.querySelectorAll('[data-select-wrap]')];
            const findModel = (name) => modelRows.find((row) => row.algorithm === name) || modelRows[0];
            const formatMetric = (value) => Number(value).toFixed(6);
            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
            const logoClass = (name) => {
                if (name.includes('Linear')) return 'linear';
                if (name.includes('Random Forest')) return 'forest';
                return 'boosting';
            };
            const logoSvg = (name) => {
                if (name.includes('Linear')) {
                    return `<svg viewBox="0 0 64 64" fill="none" aria-hidden="true">
                        <rect x="10" y="8" width="44" height="48" rx="4" fill="#ffffff" stroke="#9cc8dc" stroke-width="2"/>
                        <path d="M18 48h30M18 18v30" stroke="#7ea9ba" stroke-width="2" stroke-linecap="round"/>
                        <path d="M19 42 27 35l7-5 13-13" stroke="#2f6f8f" stroke-width="3" stroke-linecap="round"/>
                        <circle cx="22" cy="41" r="2.4" fill="#6fb8e0"/>
                        <circle cx="30" cy="33" r="2.4" fill="#6fb8e0"/>
                        <circle cx="40" cy="25" r="2.4" fill="#6fb8e0"/>
                        <path d="M21 22h7M21 27h5" stroke="#c0d8e3" stroke-width="2" stroke-linecap="round"/>
                    </svg>`;
                }
                if (name.includes('Random Forest')) {
                    return `<svg viewBox="0 0 64 64" fill="none" aria-hidden="true">
                        <path d="M8 51h48" stroke="#8bb79a" stroke-width="3" stroke-linecap="round"/>
                        <path d="M20 50V31M32 50V24M45 50V34" stroke="#8a5a2b" stroke-width="4" stroke-linecap="round"/>
                        <circle cx="20" cy="27" r="11" fill="#52b788"/>
                        <circle cx="32" cy="20" r="13" fill="#2d6a4f"/>
                        <circle cx="45" cy="30" r="10" fill="#74c69d"/>
                        <path d="M15 28c3-4 7-5 11-3M27 20c5-5 11-5 16 0M40 31c3-3 7-3 10 0" stroke="#d8f3dc" stroke-width="2" stroke-linecap="round" opacity=".75"/>
                    </svg>`;
                }
                return `<svg viewBox="0 0 64 64" fill="none" aria-hidden="true">
                    <rect x="11" y="11" width="42" height="42" rx="4" fill="#fffdf7" stroke="#e3c078" stroke-width="2"/>
                    <path d="M18 47h29M18 20v27" stroke="#b68a36" stroke-width="2" stroke-linecap="round"/>
                    <rect x="22" y="39" width="6" height="8" rx="1.5" fill="#8FAF9A"/>
                    <rect x="31" y="32" width="6" height="15" rx="1.5" fill="#4B7185"/>
                    <rect x="40" y="23" width="6" height="24" rx="1.5" fill="#1F5A46"/>
                    <path d="M21 36c7-1 13-5 21-14" stroke="#123F32" stroke-width="3" stroke-linecap="round"/>
                    <path d="M39 21h7v7" stroke="#123F32" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>`;
            };

            const renderComparison = () => {
                if (!comparisonCount || !comparisonOutput) return;
                const count = Number(comparisonCount.value);
                wraps.forEach((wrap, index) => wrap.style.display = index < count ? '' : 'none');
                const selected = selects.slice(0, count).map((select) => findModel(select.value));

                if (comparisonSelectors) comparisonSelectors.style.gridTemplateColumns = `repeat(${count}, minmax(0, 1fr))`;
                comparisonOutput.style.gridTemplateColumns = `repeat(${count}, minmax(0, 1fr))`;
                comparisonOutput.innerHTML = selected.map((model) => `
                    <div class="me-compare-card">
                        <div class="d-flex justify-content-between gap-2 align-items-start">
                            <div class="me-model-brand">
                                <div class="me-model-logo ${logoClass(model.algorithm)}">${logoSvg(model.algorithm)}</div>
                                <div>
                                    <div class="me-label">Algorithm</div>
                                    <div class="h5 fw-bold mb-0">${escapeHtml(model.algorithm)}</div>
                                </div>
                            </div>
                            <span class="${model.status === 'Selected Model' ? 'me-selected' : 'me-compared'}">${escapeHtml(model.status)}</span>
                        </div>
                        <div class="me-compare-metrics">
                            <div class="me-compare-metric"><div class="me-label">RMSE</div><div class="fw-bold">${formatMetric(model.rmse)}</div><div class="small text-muted">Lower is better</div></div>
                            <div class="me-compare-metric"><div class="me-label">MAE</div><div class="fw-bold">${formatMetric(model.mae)}</div><div class="small text-muted">Lower is better</div></div>
                            <div class="me-compare-metric"><div class="me-label">R²</div><div class="fw-bold">${formatMetric(model.r2)}</div><div class="small text-muted">Higher is better</div></div>
                        </div>
                        <div class="small text-muted mt-3">${escapeHtml(model.details?.interpretation || '')}</div>
                    </div>
                `).join('');
            };

            const syncAvailableOptions = () => {
                const count = Number(comparisonCount.value);
                const selectedValues = selects.slice(0, count).map((select) => select.value);

                selects.forEach((select, index) => {
                    const current = select.value;

                    [...select.options].forEach((option) => {
                        option.hidden = index < count
                            && option.value !== current
                            && selectedValues.includes(option.value);
                    });

                    if (index >= count) {
                        [...select.options].forEach((option) => option.hidden = false);
                        return;
                    }

                    if (selectedValues.indexOf(current) !== index) {
                        const replacement = [...select.options].find((option) => ! selectedValues.includes(option.value));
                        if (replacement) select.value = replacement.value;
                    }
                });
            };

            comparisonCount?.addEventListener('change', renderComparison);
            selects.forEach((select) => select.addEventListener('change', () => {
                syncAvailableOptions();
                renderComparison();
            }));
            comparisonCount?.addEventListener('change', syncAvailableOptions);
            syncAvailableOptions();
            renderComparison();
        });
    </script>
</x-app-layout>
