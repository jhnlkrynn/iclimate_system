import pandas as pd

from phase_28_timeseries_optimization import build_features, seasonal_naive


def sample_frame():
    return pd.DataFrame({
        "observation_id": [f"o{i}" for i in range(8)],
        "record_date": [f"2020-{(i + 1):02d}-01" for i in range(8)],
        "year": [2020] * 4 + [2021] * 4,
        "quarter": [1, 2, 3, 4] * 2,
        "actual_yield_t_ha": [1.0, 2.0, 3.0, 4.0, 2.0, 3.0, 4.0, 5.0],
    })


def test_lagged_features_are_shifted_and_past_only():
    frame = build_features(sample_frame())
    assert pd.isna(frame.loc[0, "lag_1_yield_t_ha"])
    assert pd.isna(frame.loc[3, "lag_4_yield_t_ha"])
    assert frame.loc[4, "lag_1_yield_t_ha"] == 4.0
    assert frame.loc[4, "lag_4_yield_t_ha"] == 1.0
    assert frame.loc[4, "rolling_4_mean_prior"] == 2.5
    assert frame.loc[4, "trend_4_prior"] == 3.0


def test_naive_predictions_use_only_prior_rows():
    frame = sample_frame()
    train = frame.iloc[:4]
    target = frame.iloc[4:]
    predictions = seasonal_naive(train, target)
    assert predictions["Previous-quarter naive"].tolist() == [4.0, 4.0, 4.0, 4.0]
    assert predictions["Previous-year same-quarter naive"].tolist() == [1.0, 2.0, 3.0, 4.0]
