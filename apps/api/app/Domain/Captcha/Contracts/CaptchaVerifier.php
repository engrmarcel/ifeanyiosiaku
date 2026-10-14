<?php

namespace App\Domain\Captcha\Contracts;

use App\Domain\Captcha\CaptchaResult;
use Illuminate\Http\Request;

interface CaptchaVerifier
{
    /** Driver identifier, e.g. "first_party". */
    public function name(): string;

    /**
     * Verify a token submitted with a form. Must be called server-side; a browser-supplied
     * "success" flag is never trusted.
     */
    public function verify(?string $token, string $purpose, Request $request): CaptchaResult;

    /**
     * Public, non-secret configuration the browser needs to render the widget.
     *
     * @return array<string, mixed>
     */
    public function publicConfig(): array;
}
