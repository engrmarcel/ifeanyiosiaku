<?php

namespace App\Domain\Settings\Http;

use App\Domain\Settings\Addons;
use App\Domain\Settings\Enums\Addon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for add-on modules: ->middleware('addon:commerce'). A switched-off module behaves as
 * if it does not exist (404), so its pages and API endpoints cannot be reached.
 */
class EnsureAddonEnabled
{
    public function __construct(private readonly Addons $addons) {}

    public function handle(Request $request, Closure $next, string $addon): Response
    {
        $module = Addon::from($addon);

        if (! $this->addons->enabled($module)) {
            return response()->json([
                'error' => [
                    'code' => 'addon_disabled',
                    'message' => 'This feature is not available.',
                ],
            ], 404);
        }

        return $next($request);
    }
}
