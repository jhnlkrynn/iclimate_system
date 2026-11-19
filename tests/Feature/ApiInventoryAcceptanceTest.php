<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue,Route};
use Tests\TestCase;

/** Route-wide denial/validation smoke matrix; successful domain flows have dedicated tests. */
class ApiInventoryAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_route_inventory_has_no_unhandled_errors_across_supported_and_denied_accounts(): void
    {
        Http::fake(fn()=>Http::response(['message'=>'Intentional isolated upstream outage'],503));Queue::fake();
        config(['services.groq.enabled'=>false,'services.pagasa.enabled'=>false]);
        $routes=[];
        foreach(Route::getRoutes() as $route){
            $action=$route->getActionName();$type='';
            try {if($action!=='Closure'){$parts=explode('@',$action);$type=(string)(new \ReflectionMethod($parts[0],$parts[1]??'__invoke'))->getReturnType();}}catch(\Throwable){}
            if(str_starts_with($route->uri(),'api/')||str_contains($type,'JsonResponse'))$routes[]=$route;
        }
        $accounts=['guest'=>null,'farmer'=>User::factory()->farmer()->create(),
            'mao'=>User::factory()->maoPersonnel()->create(['is_mao_head'=>false]),'mao_head'=>User::factory()->maoHead()->create(),
            'pending'=>User::factory()->farmer()->create(['status'=>User::STATUS_PENDING]),
            'rejected'=>User::factory()->farmer()->create(['status'=>User::STATUS_REJECTED]),
            'retired_it'=>User::factory()->create(['role'=>'IT Expert'])];
        $evidence=[];$errors=[];
        foreach($accounts as $state=>$user){foreach($routes as $route){
            auth()->forgetGuards();if($user)$this->actingAs($user);
            $path='/'.preg_replace('/\{[^}]+\}/','99999999',$route->uri());
            $method=$route->methods()[0];$response=$this->json($method,$path,[]);
            $evidence[]=['state'=>$state,'method'=>$method,'uri'=>$route->uri(),'probe'=>$path,'status'=>$response->status()];
            if(in_array($state,['guest','pending','rejected','retired_it'],true) && in_array('auth',$route->gatherMiddleware(),true) && $response->status()>=200 && $response->status()<300)$errors[]=$state.' accessed protected '.$path;
            if($response->status()>=500 && $response->status()!==503)$errors[]=$state.' '.$method.' '.$path.' => '.$response->status().' '.substr($response->getContent(),0,250);
        }}
        if($path=env('API_ACCEPTANCE_EVIDENCE'))file_put_contents($path,json_encode($evidence,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        $this->assertNotEmpty($routes);$this->assertSame([],$errors,implode("\n",$errors));
    }
}
