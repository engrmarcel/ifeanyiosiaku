<?php

namespace App\Providers;

use App\Domain\Cms\Blocks\BlockRegistry;
use App\Domain\Cms\Events\PageContentChanged;
use App\Domain\Cms\PageCache;
use App\Domain\Identity\Listeners\AuthEventSubscriber;
use App\Domain\Notifications\Listeners\RecordNotificationDelivery;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BlockRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Lazy loading, silently discarded attributes and missing attributes throw outside production.
        Model::shouldBeStrict(! $this->app->isProduction());

        TrustProxies::at(config('app.trusted_proxies'));

        Password::defaults(function () {
            $rule = Password::min(10)->max(128);

            // Checks the Have I Been Pwned range API (k-anonymity: the password never leaves the server).
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        RateLimiter::for('api', function (Request $request) {
            // The website's own server-side lookups (page data, address checks) arrive from the
            // website server without a forwarded visitor address. Throttling them per IP would
            // throttle every visitor at once, so they are not limited here. Visitors' requests
            // through the proxy carry their real address and keep the normal limits.
            if (! $request->headers->has('X-Forwarded-For') && in_array($request->ip(), config('app.trusted_proxies'), true)) {
                return Limit::none();
            }

            return $request->user()
                ? Limit::perMinute(120)->by('user:'.$request->user()->getKey())
                : Limit::perMinute(60)->by('ip:'.$request->ip());
        });

        RateLimiter::for('captcha', fn (Request $request) => Limit::perMinute(20)->by('captcha:'.$request->ip()));

        Event::subscribe(AuthEventSubscriber::class);
        Event::listen(PageContentChanged::class, [PageCache::class, 'forget']);
        Event::listen(NotificationSent::class, [RecordNotificationDelivery::class, 'sent']);
        Event::listen(NotificationFailed::class, [RecordNotificationDelivery::class, 'failed']);
    }
}
