<?php

namespace App\Domain\Settings\Http;

use App\Domain\Settings\SiteConfig;
use Illuminate\Http\JsonResponse;

/** Navigation, footer, social links, theme and identity for the website's shared layout. */
class SiteConfigController
{
    public function __invoke(SiteConfig $site): JsonResponse
    {
        return response()->json(['data' => $site->toPublic()])
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
