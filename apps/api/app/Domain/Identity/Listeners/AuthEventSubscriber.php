<?php

namespace App\Domain\Identity\Listeners;

use App\Domain\Audit\AuditLogger;
use App\Domain\Captcha\Http\VerifyCaptcha;
use App\Domain\Identity\Actions\RevokeUserSessions;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthEventSubscriber
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RevokeUserSessions $revokeSessions,
    ) {}

    public function onLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        RateLimiter::clear(VerifyCaptcha::loginFailureKey(request()));
        $this->audit->record('auth.login', $event->user, actor: $event->user);
    }

    public function onFailed(Failed $event): void
    {
        // Counts toward requiring a CAPTCHA on further login attempts from this IP.
        RateLimiter::hit(VerifyCaptcha::loginFailureKey(request()), 15 * 60);

        $email = (string) ($event->credentials['email'] ?? '');

        $this->audit->record('auth.failed', $event->user instanceof User ? $event->user : null, [
            'email_hash' => hash('sha256', Str::lower($email)),
        ], actor: null);
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record('auth.logout', $event->user, actor: $event->user);
        }
    }

    public function onRegistered(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record('auth.registered', $event->user, actor: $event->user);
        }
    }

    public function onVerified(Verified $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record('auth.email_verified', $event->user, actor: $event->user);
        }
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        // A reset may follow a compromise: sign out every existing session.
        $this->revokeSessions->handle($event->user);
        $this->audit->record('auth.password_reset', $event->user, actor: $event->user);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Failed::class => 'onFailed',
            Logout::class => 'onLogout',
            Registered::class => 'onRegistered',
            Verified::class => 'onVerified',
            PasswordReset::class => 'onPasswordReset',
        ];
    }
}
