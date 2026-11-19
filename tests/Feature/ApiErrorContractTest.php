<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiErrorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_login_denials_return_json_even_without_an_accept_header(): void
    {
        $pending=User::factory()->farmer()->create(['status'=>User::STATUS_PENDING]);
        $this->post('/api/mobile/login',['email'=>$pending->email,'password'=>'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email')->assertJsonMissingPath('user');
        $this->post('/api/mobile/login',[])->assertUnprocessable()->assertJsonValidationErrors(['email','password']);
        $this->get('/api/not-a-real-endpoint')->assertNotFound()->assertHeader('Content-Type','application/json');
    }
}
