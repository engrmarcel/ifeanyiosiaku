<?php

use App\Domain\Announcements\Http\AnnouncementController;
use App\Domain\Captcha\Http\CaptchaController;
use App\Domain\Cms\Http\EmbedController;
use App\Domain\Cms\Http\PageController;
use App\Domain\Cms\Http\SitemapController;
use App\Domain\Commerce\Http\CartController;
use App\Domain\Commerce\Http\OrderController;
use App\Domain\Commerce\Http\StoreController;
use App\Domain\Contact\Http\ContactController;
use App\Domain\Content\Http\ArticleController;
use App\Domain\Content\Http\PublicationController;
use App\Domain\Identity\Http\CurrentUserController;
use App\Domain\Identity\Http\SessionController;
use App\Domain\Newsletter\Http\NewsletterController;
use App\Domain\Portfolio\Http\ProjectController;
use App\Domain\Settings\Http\PublicSettingsController;
use App\Domain\Settings\Http\SiteConfigController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
| Versioned JSON API, registered in bootstrap/app.php with the 'web' middleware group: the website
| calls it same-origin with the session cookie and an X-XSRF-TOKEN header (ADR-003).
| Authentication endpoints (login, register, 2FA...) are registered by Fortify under /api/v1/auth.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Any GET through the web group sets the XSRF-TOKEN cookie; the website calls this before forms.
    Route::get('csrf-cookie', fn () => response()->noContent())->name('csrf-cookie');
    Route::get('health', HealthController::class)->name('health');
    Route::get('settings/public', PublicSettingsController::class)->name('settings.public');
    Route::get('site', SiteConfigController::class)->name('site');
    Route::get('session', [CurrentUserController::class, 'state'])->name('session');
    Route::get('sitemap', SitemapController::class)->name('sitemap');

    Route::get('page-resolve', [PageController::class, 'resolve'])->name('pages.resolve');
    Route::get('projects/{slug}', [ProjectController::class, 'show'])
        ->where('slug', '[a-z0-9\-]{1,120}')
        ->name('projects.show');
    Route::prefix('pages')->name('pages.')->group(function () {
        Route::get('home', [PageController::class, 'home'])->name('home');
        Route::get('{slug}', [PageController::class, 'show'])
            ->where('slug', '[A-Za-z0-9-/]{1,150}')
            ->name('show');
    });
    Route::get('preview/page', [PageController::class, 'preview'])
        ->middleware('throttle:preview')
        ->name('preview.page');
    Route::get('embeds/{token}', [EmbedController::class, 'show'])
        ->where('token', '[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+')
        ->middleware('throttle:embeds')
        ->name('embeds.show');
    Route::get('preview/article', [ArticleController::class, 'preview'])
        ->middleware('throttle:preview')
        ->name('preview.article');

    Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('articles/{slug}', [ArticleController::class, 'show'])
        ->where('slug', '[a-z0-9\-]{1,150}')
        ->name('articles.show');
    // Store (Milestone 3): behind the Commerce add-on switch (ADR-025).
    Route::prefix('store')->name('store.')->middleware('addon:commerce')->group(function () {
        Route::get('products', [StoreController::class, 'index'])->name('products.index');
        Route::get('products/{slug}', [StoreController::class, 'show'])
            ->where('slug', '[a-z0-9\-]{1,150}')
            ->name('products.show');

        // Cart: visitors and members. Prices are recalculated on every response.
        Route::prefix('cart')->name('cart.')->middleware('throttle:cart')->group(function () {
            Route::get('/', [CartController::class, 'show'])->name('show');
            Route::post('items', [CartController::class, 'add'])->name('items.add');
            Route::delete('items/{product}', [CartController::class, 'remove'])->where('product', '[0-9a-z]{26}')->name('items.remove');
            Route::post('coupon', [CartController::class, 'applyCoupon'])->middleware('throttle:coupon')->name('coupon.apply');
            Route::delete('coupon', [CartController::class, 'removeCoupon'])->name('coupon.remove');
            Route::put('currency', [CartController::class, 'currency'])->name('currency');
        });

        // Checkout and order history: signed-in members only.
        Route::middleware('auth')->group(function () {
            Route::post('checkout', [OrderController::class, 'store'])->middleware('throttle:checkout')->name('checkout');
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [OrderController::class, 'show'])->where('order', '[0-9a-z]{26}')->name('orders.show');
            Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->where('order', '[0-9a-z]{26}')->name('orders.cancel');
        });
    });

    Route::get('publications', [PublicationController::class, 'index'])->name('publications.index');
    Route::get('publications/{slug}', [PublicationController::class, 'show'])
        ->where('slug', '[a-z0-9\-]{1,150}')
        ->name('publications.show');

    Route::get('announcements/current', [AnnouncementController::class, 'current'])->name('announcements.current');
    Route::post('announcements/{ulid}/events', [AnnouncementController::class, 'event'])
        ->where('ulid', '[0-9a-z]{26}')
        ->middleware('throttle:announcement-events')
        ->name('announcements.events');

    Route::post('contact', [ContactController::class, 'store'])
        ->middleware(['throttle:contact', 'captcha:contact'])
        ->name('contact.store');

    Route::prefix('newsletter')->name('newsletter.')->group(function () {
        Route::post('subscribe', [NewsletterController::class, 'subscribe'])
            ->middleware(['throttle:newsletter', 'captcha:newsletter'])
            ->name('subscribe');
        Route::post('confirm', [NewsletterController::class, 'confirm'])->middleware('throttle:newsletter-links')->name('confirm');
        Route::post('unsubscribe', [NewsletterController::class, 'unsubscribe'])->middleware('throttle:newsletter-links')->name('unsubscribe');
    });

    Route::prefix('captcha')->name('captcha.')->group(function () {
        Route::get('config', [CaptchaController::class, 'config'])->name('config');
        Route::post('challenge', [CaptchaController::class, 'challenge'])
            ->middleware('throttle:captcha')
            ->name('challenge');
    });

    Route::middleware(['auth'])->group(function () {
        Route::get('me', CurrentUserController::class)->name('me');

        Route::prefix('account/sessions')->name('account.sessions.')->group(function () {
            Route::get('/', [SessionController::class, 'index'])->name('index');
            Route::post('revoke-others', [SessionController::class, 'destroyOthers'])
                ->middleware('throttle:sessions')
                ->name('revoke-others');
            Route::delete('{session}', [SessionController::class, 'destroy'])
                ->where('session', '[a-f0-9]{32}')
                ->name('destroy');
        });
    });
});
