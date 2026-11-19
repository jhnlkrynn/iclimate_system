import pandas as pd

from phase_30_robust_forecasting import seasonal_values


def test_seasonal_naive_uses_only_previous_same_season():
    history = pd.DataFrame({
        "record_date": ["2020-03-01", "2020-06-01", "2021-03-01", "2021-06-01"],
        "quarter": [1, 2, 1, 2],
        "actual_yield_t_ha": [2.0, 3.0, 4.0, 5.0],
    })
    target = pd.DataFrame({"record_date": ["2022-03-01"], "quarter": [1]})
    prediction = seasonal_values(history, target, "Seasonal Naive", {})
    assert prediction.tolist() == [4.0]
