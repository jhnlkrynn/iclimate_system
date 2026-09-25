<x-app-layout>

    <style>
        .candidate-page {
            max-width: 1320px;
            margin: 0 auto;
            padding: 28px 20px 50px;
        }

        .candidate-header {
            margin-bottom: 22px;
        }

        .candidate-title {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            color: #f8fafc;
        }

        .candidate-subtitle {
            margin-top: 7px;
            color: #d5dee8;
            font-size: 14px;
            line-height: 1.5;
        }

        .candidate-card {
            background: rgba(15, 23, 42, 0.88);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 20px;
        }

        .candidate-notice {
            border-radius: 14px;
            padding: 15px 17px;
            margin-bottom: 20px;
            background: rgba(30, 64, 175, 0.14);
            border: 1px solid rgba(96, 165, 250, 0.25);
            color: #dbeafe;
            font-size: 13px;
            line-height: 1.65;
        }

        .candidate-warning {
            border-radius: 14px;
            padding: 15px 17px;
            margin-bottom: 20px;
            background: rgba(180, 83, 9, 0.13);
            border: 1px solid rgba(251, 191, 36, 0.24);
            color: #fde68a;
            font-size: 13px;
            line-height: 1.6;
        }

        .candidate-error {
            border-radius: 14px;
            padding: 15px 17px;
            margin-bottom: 20px;
            background: rgba(185, 28, 28, 0.15);
            border: 1px solid rgba(248, 113, 113, 0.28);
            color: #fecaca;
            line-height: 1.6;
        }

        .candidate-form-grid {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) minmax(180px, 1fr) auto;
            gap: 14px;
            align-items: end;
        }

        .candidate-label {
            display: block;
            margin-bottom: 7px;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 700;
        }

        .candidate-select {
            width: 100%;
            min-height: 44px;
            border-radius: 10px;
            padding: 10px 12px;
            background: #0f172a;
            border: 1px solid #334155;
            color: #f8fafc;
        }

        .candidate-button {
            border: 0;
            border-radius: 10px;
            padding: 12px 20px;
            min-height: 44px;
            font-weight: 800;
            cursor: pointer;
            background: #15803d;
            color: white;
        }

        .candidate-button:hover {
            opacity: 0.93;
        }

        .section-heading-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .section-title {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: #f8fafc;
        }

        .section-subtitle {
            color: #94a3b8;
            font-size: 12px;
        }

        .result-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .metric-box {
            background: rgba(15, 23, 42, 0.72);
            border: 1px solid #334155;
            border-radius: 13px;
            padding: 15px;
        }

        .metric-label {
            color: #94a3b8;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .metric-value {
            color: #f8fafc;
            font-size: 18px;
            font-weight: 800;
        }

        .prediction-meta {
            margin-top: 18px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 700;
            background: #1e293b;
            color: #e2e8f0;
            border: 1px solid #334155;
        }

        .badge-green {
            background: rgba(22, 163, 74, 0.15);
            border-color: rgba(74, 222, 128, 0.30);
            color: #bbf7d0;
        }

        .badge-yellow {
            background: rgba(202, 138, 4, 0.15);
            border-color: rgba(250, 204, 21, 0.28);
            color: #fef08a;
        }

        .yield-box {
            text-align: center;
            padding: 28px 20px;
        }

        .yield-label {
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 9px;
        }

        .yield-value {
            font-size: 46px;
            font-weight: 900;
            color: #86efac;
        }

        .yield-unit {
            color: #cbd5e1;
            font-size: 16px;
        }

        .table-shell {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #334155;
            border-radius: 14px;
        }

        .candidate-table {
            width: 100%;
            min-width: 1120px;
            border-collapse: collapse;
            background: rgba(15, 23, 42, 0.55);
        }

        .candidate-table th {
            text-align: center;
            padding: 13px 12px;
            font-size: 12px;
            color: #cbd5e1;
            background: rgba(30, 41, 59, 0.95);
            border-bottom: 1px solid #334155;
            white-space: nowrap;
        }

        .candidate-table td {
            padding: 13px 12px;
            border-bottom: 1px solid rgba(51, 65, 85, 0.75);
            color: #e2e8f0;
            font-size: 13px;
            white-space: nowrap;
        }

        .candidate-table tbody tr:hover {
            background: rgba(30, 41, 59, 0.52);
        }

        .candidate-table tbody tr:last-child td {
            border-bottom: none;
        }

        .candidate-table .month-cell {
            font-weight: 800;
            color: #f8fafc;
        }

        .candidate-table .number-cell {
            text-align: right;
        }

        .candidate-table .yield-cell {
            text-align: right;
            font-weight: 800;
            color: #86efac;
        }

        .candidate-table .center-cell {
            text-align: center;
        }

        .empty-report {
            padding: 28px 18px;
            text-align: center;
            color: #94a3b8;
            line-height: 1.6;
        }

        .report-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .summary-box {
            padding: 14px;
            border-radius: 12px;
            border: 1px solid #334155;
            background: rgba(15, 23, 42, 0.70);
        }

        .summary-label {
            color: #94a3b8;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .summary-value {
            color: #f8fafc;
            font-weight: 800;
            font-size: 17px;
        }

        @media (max-width: 850px) {
            .candidate-form-grid {
                grid-template-columns: 1fr;
            }

            .result-grid {
                grid-template-columns: 1fr 1fr;
            }

            .report-summary {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            .candidate-page {
                padding-left: 14px;
                padding-right: 14px;
            }

            .result-grid {
                grid-template-columns: 1fr;
            }

            .yield-value {
                font-size: 36px;
            }
        }
    </style>


    <div class="candidate-page">

        {{-- ============================================================
             HEADER
        ============================================================ --}}

        <div class="candidate-header">

            <h1 class="candidate-title">
                Candidate Rice Yield Model
            </h1>

            <div class="candidate-subtitle">
                Monthly Ambulong Weather Random Forest
                →
                Candidate Rice Yield Random Forest
            </div>

        </div>


        {{-- ============================================================
             IMPORTANT NOTICE
        ============================================================ --}}

        <div class="candidate-notice">

            This page is for
            <strong>candidate model testing and evaluation</strong>.

            Weather values are generated by the
            <strong>Ambulong Monthly Weather Random Forest</strong>
            and passed to the
            <strong>Candidate Rice Yield Random Forest</strong>.

            The displayed future values are model estimates and are not
            observed future weather or actual future rice yield.

        </div>


        @if ($error)

            <div class="candidate-error">
                {{ $error }}
            </div>

        @endif


        {{-- ============================================================
             SINGLE MONTH FORM
        ============================================================ --}}

        <div class="candidate-card">

            <div class="section-heading-row">

                <div>

                    <h2 class="section-title">
                        Monthly Prediction
                    </h2>

                    <div class="section-subtitle">
                        Select a year and month to generate a candidate prediction.
                    </div>

                </div>

                <span class="badge badge-yellow">
                    Testing Only
                </span>

            </div>


            <form
                method="POST"
                action="{{ route('testing.candidate-rice-yield.predict') }}"
            >

                @csrf

                <div class="candidate-form-grid">

                    {{-- YEAR --}}
                    <div>

                        <label
                            class="candidate-label"
                            for="year"
                        >
                            Prediction Year
                        </label>

                        <select
                            id="year"
                            name="year"
                            class="candidate-select"
                            required
                        >

                            @foreach ($availableYears as $availableYear)

                                <option
                                    value="{{ $availableYear }}"
                                    @selected(
                                        (int) $year
                                        ===
                                        (int) $availableYear
                                    )
                                >
                                    {{ $availableYear }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    {{-- MONTH --}}
                    <div>

                        <label
                            class="candidate-label"
                            for="month"
                        >
                            Prediction Month
                        </label>

                        <select
                            id="month"
                            name="month"
                            class="candidate-select"
                            required
                        >

                            @foreach (range(1, 12) as $monthNumber)

                                <option
                                    value="{{ $monthNumber }}"
                                    @selected(
                                        (int) $month
                                        ===
                                        $monthNumber
                                    )
                                >
                                    {{
                                        \Carbon\CarbonImmutable::createFromDate(
                                            2000,
                                            $monthNumber,
                                            1
                                        )->format('F')
                                    }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    {{-- BUTTON --}}
                    <div>

                        <button
                            type="submit"
                            class="candidate-button"
                        >
                            Predict Rice Yield
                        </button>

                    </div>

                </div>

            </form>

        </div>


        {{-- ============================================================
             SINGLE MONTH WEATHER RESULT
        ============================================================ --}}

        @if (
            $weatherPrediction
            &&
            ($weatherPrediction['ready'] ?? false)
        )

            @php
                $wp = $weatherPrediction['predictions'];
            @endphp

            <div class="candidate-card">

                <div class="section-heading-row">

                    <h2 class="section-title">
                        Monthly Weather Prediction —
                        {{
                            \Carbon\CarbonImmutable::createFromDate(
                                $year,
                                $month,
                                1
                            )->format('F Y')
                        }}
                    </h2>

                    <span class="badge badge-green">
                        Model Estimate
                    </span>

                </div>


                <div class="result-grid">

                    <div class="metric-box">
                        <div class="metric-label">
                            Rainfall
                        </div>

                        <div class="metric-value">
                            {{ number_format($wp['rainfall'], 2) }} mm
                        </div>
                    </div>


                    <div class="metric-box">
                        <div class="metric-label">
                            Maximum Temperature
                        </div>

                        <div class="metric-value">
                            {{ number_format($wp['tmax'], 2) }} °C
                        </div>
                    </div>


                    <div class="metric-box">
                        <div class="metric-label">
                            Minimum Temperature
                        </div>

                        <div class="metric-value">
                            {{ number_format($wp['tmin'], 2) }} °C
                        </div>
                    </div>


                    <div class="metric-box">
                        <div class="metric-label">
                            Relative Humidity
                        </div>

                        <div class="metric-value">
                            {{ number_format($wp['humidity'], 2) }} %
                        </div>
                    </div>


                    <div class="metric-box">
                        <div class="metric-label">
                            Wind Speed
                        </div>

                        <div class="metric-value">
                            {{ number_format($wp['wind_speed'], 2) }}
                        </div>
                    </div>


                    <div class="metric-box">
                        <div class="metric-label">
                            Wind Direction
                        </div>

                        <div class="metric-value">
                            {{ number_format($wp['wind_direction'], 2) }}°
                        </div>
                    </div>

                </div>


                <div class="prediction-meta">

                    <span class="badge">
                        Weather Basis: Ambulong
                    </span>

                    <span class="badge">
                        Season:
                        {{ $wp['season'] ?? '—' }}
                    </span>

                    <span class="badge">
                        Training Through:
                        {{
                            $weatherPrediction['training_period']['to']
                            ?? '—'
                        }}
                    </span>

                </div>

            </div>

        @endif


        {{-- ============================================================
             SINGLE MONTH YIELD RESULT
        ============================================================ --}}

        @if (
            $yieldPrediction
            &&
            ($yieldPrediction['success'] ?? false)
        )

            <div class="candidate-card yield-box">

                <div class="yield-label">
                    Predicted Rice Yield —
                    {{ $yieldPrediction['month_name'] }}
                    {{ $yieldPrediction['year'] }}
                </div>


                <div>

                    <span class="yield-value">
                        {{
                            number_format(
                                $yieldPrediction['predicted_yield'],
                                2
                            )
                        }}
                    </span>

                    <span class="yield-unit">
                        {{
                            $yieldPrediction['unit']
                            ?? 't/ha'
                        }}
                    </span>

                </div>


                <div class="prediction-meta">

                    <span class="badge">
                        Candidate Random Forest
                    </span>

                    <span class="badge">
                        Weather Basis: Ambulong
                    </span>

                    <span class="badge badge-green">
                        Model Estimate
                    </span>

                    <span class="badge badge-yellow">
                        {{
                            $yieldPrediction['status']
                            ?? 'Testing Only'
                        }}
                    </span>

                </div>

            </div>

        @endif


        {{-- ============================================================
             FULL YEAR FORECAST
        ============================================================ --}}

        <div class="candidate-card">

            <div class="section-heading-row">

                <div>

                    <h2 class="section-title">
                        {{ $year }} Full-Year Candidate Forecast
                    </h2>

                    <div class="section-subtitle">
                        January–December generated candidate rice-yield predictions.
                    </div>

                </div>

                @if ($reportAvailable)

                    <span class="badge badge-green">
                        Generated Report Available
                    </span>

                @else

                    <span class="badge badge-yellow">
                        No Generated Report
                    </span>

                @endif

            </div>


            @if (
                $reportAvailable
                &&
                count($fullYearPredictions) > 0
            )

                @php

                    $validYields = collect(
                        $fullYearPredictions
                    )
                        ->pluck(
                            'predicted_yield'
                        )
                        ->filter(
                            fn ($value) =>
                                is_numeric($value)
                        )
                        ->map(
                            fn ($value) =>
                                (float) $value
                        );

                    $averageYield =
                        $validYields->isNotEmpty()
                            ? $validYields->avg()
                            : null;

                    $highestRow =
                        collect($fullYearPredictions)
                            ->filter(
                                fn ($row) =>
                                    is_numeric(
                                        $row['predicted_yield']
                                        ?? null
                                    )
                            )
                            ->sortByDesc(
                                'predicted_yield'
                            )
                            ->first();

                    $lowestRow =
                        collect($fullYearPredictions)
                            ->filter(
                                fn ($row) =>
                                    is_numeric(
                                        $row['predicted_yield']
                                        ?? null
                                    )
                            )
                            ->sortBy(
                                'predicted_yield'
                            )
                            ->first();

                @endphp


                {{-- SUMMARY --}}
                <div class="report-summary">

                    <div class="summary-box">

                        <div class="summary-label">
                            Average Predicted Yield
                        </div>

                        <div class="summary-value">

                            @if ($averageYield !== null)

                                {{ number_format($averageYield, 2) }} t/ha

                            @else

                                —

                            @endif

                        </div>

                    </div>


                    <div class="summary-box">

                        <div class="summary-label">
                            Highest Monthly Prediction
                        </div>

                        <div class="summary-value">

                            @if ($highestRow)

                                {{
                                    $highestRow['month_name']
                                }}
                                —
                                {{
                                    number_format(
                                        $highestRow['predicted_yield'],
                                        2
                                    )
                                }}
                                t/ha

                            @else

                                —

                            @endif

                        </div>

                    </div>


                    <div class="summary-box">

                        <div class="summary-label">
                            Lowest Monthly Prediction
                        </div>

                        <div class="summary-value">

                            @if ($lowestRow)

                                {{
                                    $lowestRow['month_name']
                                }}
                                —
                                {{
                                    number_format(
                                        $lowestRow['predicted_yield'],
                                        2
                                    )
                                }}
                                t/ha

                            @else

                                —

                            @endif

                        </div>

                    </div>

                </div>


                {{-- TABLE --}}
                <div class="table-shell">

                    <table class="candidate-table">

                        <thead>

                            <tr>

                                <th>
                                    Month
                                </th>

                                <th>
                                    Season
                                </th>

                                <th>
                                    Rainfall
                                </th>

                                <th>
                                    TMAX
                                </th>

                                <th>
                                    TMIN
                                </th>

                                <th>
                                    Humidity
                                </th>

                                <th>
                                    Wind Speed
                                </th>

                                <th>
                                    Wind Direction
                                </th>

                                <th>
                                    Predicted Yield
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            @foreach ($fullYearPredictions as $prediction)

                                <tr>

                                    <td class="month-cell">
                                        {{
                                            $prediction['month_name']
                                        }}
                                    </td>


                                    <td class="center-cell">
                                        {{
                                            $prediction['season']
                                            ?: '—'
                                        }}
                                    </td>


                                    <td class="number-cell">

                                        @if (
                                            $prediction['rainfall']
                                            !== null
                                        )

                                            {{
                                                number_format(
                                                    $prediction['rainfall'],
                                                    2
                                                )
                                            }}
                                            mm

                                        @else

                                            —

                                        @endif

                                    </td>


                                    <td class="number-cell">

                                        @if (
                                            $prediction['tmax']
                                            !== null
                                        )

                                            {{
                                                number_format(
                                                    $prediction['tmax'],
                                                    2
                                                )
                                            }}
                                            °C

                                        @else

                                            —

                                        @endif

                                    </td>


                                    <td class="number-cell">

                                        @if (
                                            $prediction['tmin']
                                            !== null
                                        )

                                            {{
                                                number_format(
                                                    $prediction['tmin'],
                                                    2
                                                )
                                            }}
                                            °C

                                        @else

                                            —

                                        @endif

                                    </td>


                                    <td class="number-cell">

                                        @if (
                                            $prediction['rh']
                                            !== null
                                        )

                                            {{
                                                number_format(
                                                    $prediction['rh'],
                                                    2
                                                )
                                            }}
                                            %

                                        @else

                                            —

                                        @endif

                                    </td>


                                    <td class="number-cell">

                                        @if (
                                            $prediction['wind_speed']
                                            !== null
                                        )

                                            {{
                                                number_format(
                                                    $prediction['wind_speed'],
                                                    2
                                                )
                                            }}

                                        @else

                                            —

                                        @endif

                                    </td>


                                    <td class="number-cell">

                                        @if (
                                            $prediction['wind_direction']
                                            !== null
                                        )

                                            {{
                                                number_format(
                                                    $prediction['wind_direction'],
                                                    2
                                                )
                                            }}
                                            °

                                        @else

                                            —

                                        @endif

                                    </td>


                                    <td class="yield-cell">

                                        @if (
                                            $prediction['predicted_yield']
                                            !== null
                                        )

                                            {{
                                                number_format(
                                                    $prediction['predicted_yield'],
                                                    4
                                                )
                                            }}
                                            {{
                                                $prediction['unit']
                                                ?? 't/ha'
                                            }}

                                        @else

                                            —

                                        @endif

                                    </td>


                                    <td class="center-cell">

                                        <span class="badge badge-yellow">
                                            {{
                                                $prediction['status']
                                                ?? 'Testing Only'
                                            }}
                                        </span>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>


                <div class="prediction-meta">

                    <span class="badge">
                        Weather Basis: Ambulong
                    </span>

                    <span class="badge">
                        Candidate Random Forest
                    </span>

                    <span class="badge badge-yellow">
                        Testing Only
                    </span>

                </div>

            @else

                <div class="empty-report">

                    No generated full-year candidate report is available
                    for {{ $year }}.

                    <br>

                    Generate a report before displaying full-year values.

                </div>

            @endif

        </div>


        {{-- ============================================================
             VALIDATION WARNING
        ============================================================ --}}

        <div class="candidate-warning">

            <strong>Validation note:</strong>

            The candidate chained model achieved a 2025 holdout
            MAE of approximately
            <strong>0.2650 t/ha</strong>
            and RMSE of
            <strong>0.3283 t/ha</strong>.

            It outperformed the tested overall-mean and same-month
            baselines, but its holdout R² remained slightly negative.

            Therefore, this model remains
            <strong>Testing Only</strong>
            and does not replace the current production model.

        </div>

    </div>

</x-app-layout>