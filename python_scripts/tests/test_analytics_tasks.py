from analytics_tasks import advisory_ai, heatmap_risk, report_analytics, weather_prediction


def test_weather_prediction_keeps_live_api_snapshot_and_ml_guidance_separate():
    result = weather_prediction({
        "target_date": "2026-11-15",
        "location": "Lian, Batangas",
        "live_weather": {
            "source": "Open-Meteo",
            "current_temperature_c": 29.4,
            "current_condition": "Light Rain",
        },
        "climate_records": [
            {
                "record_date": f"2026-{month:02d}-15",
                "rainfall": 80 + month * 8,
                "temperature": 27 + month * 0.2,
                "humidity": 70 + month,
                "wind_speed": 8 + month * 0.3,
            }
            for month in range(1, 7)
        ],
    })

    assert result["ready"] is True
    assert result["processor"] == "python"
    assert result["live_weather"]["source"] == "Open-Meteo"
    assert result["predicted_rainfall"] == result["predictions"]["rainfall"]
    assert result["season"] == result["predictions"]["season"]
    assert result["reliability"] == result["confidence"]
    assert result["risk_guidance"]
    assert result["farming_recommendation"]


def test_climate_report_filters_real_rows_and_sorts_newest_first():
    result = report_analytics({
        "report_type": "climate_records",
        "filters": {"season": "Wet", "date_from": "2026-06-01"},
        "source_rows": [
            {"record_date": "2026-07-01", "rainfall": 120, "temperature": 29, "humidity": 81, "wind_speed": 8, "season": "Wet", "source": "PAGASA"},
            {"record_date": "2026-08-01", "rainfall": 150, "temperature": 28, "humidity": 84, "wind_speed": 9, "season": "Wet", "source": "PAGASA"},
            {"record_date": "2026-05-01", "rainfall": 70, "temperature": 31, "humidity": 68, "wind_speed": 7, "season": "Dry", "source": "MAO"},
        ],
    })

    assert result["processor"] == "python"
    assert result["row_count"] == 2
    assert [row["record_date"] for row in result["records"]] == ["2026-08-01", "2026-07-01"]
    assert list(result["columns"]) == [
        "record_type", "record_date", "rainfall", "temperature", "humidity", "wind_speed", "season", "source"
    ]


def test_climate_report_keeps_historical_months_and_entered_records_apart():
    result = report_analytics({
        "report_type": "climate_records",
        "filters": {"date_from": "2024-01-15", "date_to": "2024-02-10"},
        "source_rows": [
            {"record_type": "Historical Weather", "period": "month", "record_date": "2024-01", "rainfall": 40.5, "season": "Dry", "source": "PAGASA Ambulong Station (Tanauan, Batangas)"},
            {"record_type": "Historical Weather", "period": "month", "record_date": "2024-02", "rainfall": None, "season": "Dry", "source": "PAGASA Ambulong Station (Tanauan, Batangas)"},
            {"record_type": "Historical Weather", "period": "month", "record_date": "2024-03", "rainfall": 12.0, "season": "Dry", "source": "PAGASA Ambulong Station (Tanauan, Batangas)"},
            {"record_type": "Entered Record", "period": "day", "record_date": "2024-01-10", "rainfall": 3.0, "season": "Dry", "source": "MAO"},
            {"record_type": "Entered Record", "period": "day", "record_date": "2024-02-01", "rainfall": 5.0, "season": "Dry", "source": "MAO"},
        ],
    })

    # January and February overlap the range (March does not); the entered row of Jan 10 is before it.
    assert [(row["record_type"], row["record_date"]) for row in result["records"]] == [
        ("Entered Record", "2024-02-01"), ("Historical Weather", "2024-02"), ("Historical Weather", "2024-01"),
    ]
    # A missing value stays missing.
    assert result["records"][1]["rainfall"] is None


def test_empty_report_returns_no_rows():
    result = report_analytics({
        "report_type": "climate_records",
        "filters": {"season": "Dry"},
        "source_rows": [{"record_date": "2026-08-01", "season": "Wet"}],
    })

    assert result["row_count"] == 0
    assert result["records"] == []


def test_heatmap_and_advisory_scoring_are_python_results():
    heatmap = heatmap_risk({
        "areas": [{"barangay": "Balibago", "latitude": 13.98, "longitude": 120.65}],
        "climate_records": [{"record_date": "2026-09-01", "rainfall": 390, "temperature": 31, "wind_speed": 12}],
        "rice_productions": [],
    })
    advisories = advisory_ai({
        "weather_records": [{"forecast_date": "2026-09-14", "rainfall_probability": 90, "wind_speed": 20, "temperature": 30}],
    })

    assert heatmap["processor"] == "python"
    assert heatmap["areas"][0]["risk_type"] == "Flood"
    assert advisories["processor"] == "python"
    assert advisories["advisories_created"] == 1
