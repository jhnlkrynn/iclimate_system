import sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import pytest
from fastapi.testclient import TestClient
import main,evaluation_service

@pytest.mark.parametrize('task,payload',[('weather_prediction',{}),('rice_yield_prediction',{}),('advisory_ai',{'weather_records':None}),('report_analytics',{}),('heatmap_risk',{'climate_records':None})])
def test_invalid_analytics_requests_are_validation_errors(task,payload):
    response=TestClient(main.app).post('/analytics/'+task,json=payload)
    assert response.status_code==422

def test_default_dataset_processing_uses_an_allowed_temporary_csv_without_fitting(monkeypatch,tmp_path):
    monkeypatch.setattr(evaluation_service,'DEFAULT_CSV',str(tmp_path/'private/ml/training.csv'))
    response=TestClient(main.app).post('/analytics/dataset_processing',json={})
    assert response.status_code==200,response.text
    assert response.json()['evaluation']['ready'] is False
    assert list((tmp_path/'private/ml').iterdir())==[]

def test_internal_model_load_errors_do_not_leak_filesystem_paths(monkeypatch):
    def unavailable(*args,**kwargs):raise FileNotFoundError('/private/verification-secret/model.pkl')
    monkeypatch.setattr(main.rice_yield_service,'load_models',unavailable)
    client=TestClient(main.app)
    for method,path,data in [('get','/health',None),('get','/model-info',None),('post','/predict',{'prediction_type':'rice_yield','features':{}})]:
        response=getattr(client,method)(path,**({'json':data} if data else {}))
        assert '/private/verification-secret' not in response.text


def test_valid_advisory_request_does_not_require_an_unused_target_date():
    response=TestClient(main.app).post('/analytics/advisory_ai',json={'weather_records':[]})
    assert response.status_code==200
    assert response.json()['advisories_created']==0
