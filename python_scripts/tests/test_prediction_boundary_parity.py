"""Inference-only boundary regressions: no fitting or model-file writes."""
import sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import pytest
from fastapi.testclient import TestClient
import main
import predict as local_predict

FEATURES=dict(rainfall=180,temp_avg=29,temp_range=8,area=1,previous_rainfall=180,previous_temp=29,previous_humidity=78,previous_wind_speed=8,rainfall_6m=900,temp_3m=29,temp_6m=29,seasonal_rainfall=900,seasonal_temp=29,season='Wet',humidity=78,wind_speed=8,farm_type='Rainfed',month_num=6)

@pytest.mark.parametrize('field,value',[('area',-5),('temp_avg',60),('rainfall',3001),('area',True)])
def test_local_cli_rejects_the_same_invalid_agricultural_inputs(field,value):
    with pytest.raises(ValueError):local_predict.build_features(FEATURES|{field:value})

@pytest.mark.parametrize('field,value',[('area',True),('humidity',140),('wind_speed',-1)])
def test_http_rejects_invalid_optional_or_boolean_numeric_features(field,value):
    response=TestClient(main.app).post('/predict',json={'prediction_type':'rice_yield','features':FEATURES|{field:value}})
    assert response.status_code==422

def test_metrics_do_not_claim_to_evaluate_the_deployed_artifact():
    import evaluation_service
    result=evaluation_service._not_ready(0,'No approved observations')
    assert result['accuracy_status']=='ACCURACY NOT VERIFIED'
    assert result['deployed_model_evaluated'] is False
    assert result['independent_validation'] is False
