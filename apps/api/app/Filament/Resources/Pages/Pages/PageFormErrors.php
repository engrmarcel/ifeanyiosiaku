<?php

namespace App\Filament\Resources\Pages\Pages;

use Closure;
use Illuminate\Validation\ValidationException;

/**
 * Domain actions report errors as "slug" or "blocks.2.data.media"; Filament forms expect the
 * "data." prefix (or "mountedActions.0.data." inside a pop-up form). This maps one to the other so
 * messages appear next to the right field.
 */
class PageFormErrors
{
    /** Error prefix for forms shown in a pop-up (modal) action. */
    public const MODAL = 'mountedActions.0.data.';

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function map(Closure $callback, string $prefix = 'data.'): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            $messages = [];

            foreach ($e->errors() as $key => $list) {
                $messages[str_starts_with($key, $prefix) ? $key : $prefix.$key] = $list;
            }

            throw ValidationException::withMessages($messages);
        }
    }
}
