<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\CaptchaService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CaptchaImageController extends Controller
{
    public function __invoke(Request $request, CaptchaService $captcha, string $context): Response
    {
        abort_unless(in_array($context, ['login', 'register'], true), 404);

        return response($captcha->svg($request, $context), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
