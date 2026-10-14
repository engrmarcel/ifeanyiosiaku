<?php

namespace Tests;

use App\Domain\Captcha\Drivers\FirstPartyCaptcha;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /** Roles and permissions exist in every environment, so every test starts with them. */
    protected string $seeder = RolesAndPermissionsSeeder::class;

    protected bool $seed = true;

    /**
     * Headers the website sends with every JSON API call.
     *
     * @return array<string, string>
     */
    protected function apiHeaders(): array
    {
        return ['Accept' => 'application/json', 'Origin' => 'http://localhost:3100'];
    }

    /**
     * Laravel test requests do not keep cookies between calls; this carries the session cookie
     * from a response into the following requests, as a browser would.
     */
    protected function keepSessionFrom(TestResponse $response): static
    {
        $name = config('session.cookie');
        $cookie = $response->getCookie($name);

        if ($cookie !== null) {
            $this->withCredentials()->withCookie($name, (string) $cookie->getValue());
        }

        return $this;
    }

    /**
     * Requests and solves a first-party CAPTCHA challenge like the website's Web Worker does,
     * and returns the token to submit with the form.
     */
    protected function solveCaptcha(string $purpose): string
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/captcha/challenge', ['purpose' => $purpose])
            ->assertCreated();

        $this->keepSessionFrom($response);

        $challenge = (string) $response->json('data.challenge');
        $difficulty = (int) $response->json('data.difficulty');

        $nonce = 0;
        while (! FirstPartyCaptcha::meetsDifficulty($challenge, dechex($nonce), $difficulty)) {
            $nonce++;
        }

        // Humans cannot solve instantly; the minimum solve time is enforced server-side.
        $this->travel(2)->seconds();

        return $challenge.':'.dechex($nonce);
    }

    /** Re-enables CSRF verification, which Laravel skips during tests by default. */
    protected function enforceCsrf(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }
}
