<?php
namespace Tests\Feature;
use App\Services\PythonService;
use App\Services\Prediction\PredictionEngine;
use Tests\TestCase;
class PredictionTransportProvenanceTest extends TestCase
{
    public function test_prediction_retains_local_transport_fallback_provenance(): void
    {
        $python=\Mockery::mock(PythonService::class);
        $python->shouldReceive('farmingAssistant')->once()->andReturn(['predicted_yield'=>3.41,'source_type'=>'Trained Model','inference_transport'=>'local_subprocess','api_fallback_used'=>true]);
        $this->app->instance(PythonService::class,$python);
        [$result,$error]=app(PredictionEngine::class)->requestYieldFromModel([]);
        $this->assertNull($error);
        $this->assertSame('local_subprocess',$result['inference_transport']??null);
        $this->assertTrue($result['api_fallback_used']??false);
        $this->assertSame('Trained Model',$result['source_type']);
    }
}
