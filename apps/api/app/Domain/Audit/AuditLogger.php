<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $context  Must not contain secrets or passwords.
     */
    public function record(string $action, ?Model $subject = null, array $context = [], ?User $actor = null): AuditLog
    {
        $actor ??= Auth::user();
        $fromConsole = app()->runningInConsole() && ! app()->runningUnitTests();

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'context' => $context ?: null,
            'ip_address' => $fromConsole ? null : request()->ip(),
            'user_agent' => $fromConsole ? 'console' : Str::limit((string) request()->userAgent(), 490, ''),
            'request_id' => Context::get('request_id'),
        ]);
    }
}
