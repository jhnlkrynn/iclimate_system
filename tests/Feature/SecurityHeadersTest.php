<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_csp_loose_and_strict_policies_do_not_allow_unsafe_eval(): void
    {
        $loose = implode('; ', SecurityHeaders::loosePolicy());
        $strict = implode('; ', SecurityHeaders::strictPolicy('test-nonce'));

        $this->assertStringNotContainsString("'unsafe-eval'", $loose);
        $this->assertStringNotContainsString("'unsafe-eval'", $strict);
    }
}
