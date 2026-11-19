<?php
namespace Tests\Feature;
use App\Models\{User,YieldPredictionRequest};
use App\Services\Prediction\{RiceYieldPredictionService,SeasonYield,YieldModelGate};
use App\Jobs\RunYieldPredictionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class YieldRequestGateMessagingTest extends TestCase
{
    use RefreshDatabase;
    public function test_withheld_result_is_explained_as_unverified_instead_of_a_service_outage(): void
    {
        $u=User::factory()->farmer()->create();
        $r=YieldPredictionRequest::create(['user_id'=>$u->id,'scope'=>'farm','season'=>'Wet','farm_type'=>'Rainfed','area_hectares'=>1,'target_month'=>now()->startOfMonth(),'status'=>'queued']);
        $model=\Mockery::mock(RiceYieldPredictionService::class);
        $model->shouldReceive('predictForUser')->once()->andReturn(['success'=>false,'availability'=>'withheld','predicted_yield'=>null,'unavailable_reason'=>YieldModelGate::REASON]);
        (new RunYieldPredictionRequest($r->id))->handle($model,app(SeasonYield::class));
        $this->assertSame(YieldModelGate::REASON,$r->fresh()->error);
        $this->assertNull($r->fresh()->predicted_yield);
    }
}
