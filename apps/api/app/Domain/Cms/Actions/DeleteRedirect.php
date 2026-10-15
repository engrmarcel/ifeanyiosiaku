<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cms\Events\PageContentChanged;
use App\Domain\Cms\Models\Redirect;
use App\Models\User;

class DeleteRedirect
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Redirect $redirect, ?User $actor): void
    {
        $this->audit->record('redirect.deleted', $redirect, [
            'from' => $redirect->from_path, 'to' => $redirect->to_path, 'automatic' => $redirect->is_automatic,
        ], $actor);

        $redirect->delete();

        PageContentChanged::dispatch([$redirect->from_path]);
    }
}
