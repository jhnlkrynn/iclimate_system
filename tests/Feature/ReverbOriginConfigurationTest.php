<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReverbOriginConfigurationTest extends TestCase
{
    public function test_local_reverb_origins_are_explicitly_allowed(): void
    {
        $origins = config('reverb.apps.apps.0.allowed_origins');

        $this->assertContains('127.0.0.1', $origins);
        $this->assertContains('localhost', $origins);
        $this->assertNotContains('*', $origins);
    }

    public function test_reverb_origin_policy_does_not_allow_an_unlisted_origin(): void
    {
        $origins = config('reverb.apps.apps.0.allowed_origins');

        $this->assertNotContains('example.invalid', $origins);
        $this->assertNotContains('https://example.invalid', $origins);
    }
}
