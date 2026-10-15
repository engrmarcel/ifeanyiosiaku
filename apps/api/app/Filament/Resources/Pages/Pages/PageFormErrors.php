<?php

namespace App\Filament\Resources\Pages\Pages;

use Closure;
use Illuminate\Validation\ValidationException;

/**
 * Domain actions report errors as "slug" or "blocks.2.data.media"; Filament forms expect the
 * "data." prefix. This maps one to the other so messages appear next to the right field.
 */
class PageFormErrors
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function map(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            $messages = [];

            foreach ($e->errors() as $key => $list) {
                $messages[str_starts_with($key, 'data.') ? $key : "data.{$key}"] = $list;
            }

            throw ValidationException::withMessages($messages);
        }
    }
}
